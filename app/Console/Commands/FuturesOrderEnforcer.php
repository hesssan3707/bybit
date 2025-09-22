<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class FuturesOrderEnforcer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'futures:enforce {--user= : شناسه کاربر خاص برای اعمال قوانین}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'بررسی و اعمال قوانین سفارشات اضافی برای کاربران در حالت سخت‌گیرانه';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('شروع بررسی و اعمال قوانین سفارشات اضافی...');

        $userOption = $this->option('user');

        if ($userOption) {
            $user = User::find($userOption);
            if (!$user) {
                $this->error("کاربر با شناسه {$userOption} یافت نشد.");
                return 1;
            }
            $this->enforceForUser($user);
        } else {
            $this->enforceForAllUsers();
        }

        $this->info('بررسی و اعمال قوانین سفارشات اضافی با موفقیت تکمیل شد.');
        return 0;
    }

    /**
     * Enforce orders for all users in strict mode
     */
    private function enforceForAllUsers()
    {
        // Find all users who have strict mode enabled
        $users = User::where('future_strict_mode', true)->get();
        
        $this->info("پردازش {$users->count()} کاربر در حالت سخت‌گیرانه...");

        foreach ($users as $user) {
            $this->enforceForUser($user);
        }
    }

    /**
     * Enforce orders for a specific user
     */
    private function enforceForUser(User $user)
    {
        $this->info("پردازش کاربر: {$user->email}");

        // Check if user has strict mode enabled
        if (!$user->future_strict_mode) {
            $this->info("کاربر {$user->email} در حالت سخت‌گیرانه نیست. رد شد...");
            return;
        }

        // Get all user exchanges with both API key and secret set (real mode)
        $userExchanges = UserExchange::where('user_id', $user->id)
            ->where('futures_access', true)
            ->whereNotNull('api_key')
            ->whereNotNull('api_secret')
            ->get();

        if ($userExchanges->isEmpty()) {
            $this->info("هیچ صرافی فعالی برای کاربر {$user->email} یافت نشد");
            return;
        }

        foreach ($userExchanges as $userExchange) {
            $this->enforceForUserExchange($user, $userExchange);
        }
    }

    /**
     * Enforce orders for a specific user exchange
     */
    private function enforceForUserExchange(User $user, UserExchange $userExchange)
    {
        try {
            $this->info("پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email}");

            // Create exchange service (real mode)
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->api_key,
                $userExchange->api_secret,
                $userExchange->api_passphrase,
                false // Real mode
            );

            // Get user's selected market, default to ETHUSDT if not set
            $symbol = $user->selected_market ?: 'ETHUSDT';

            // Get all open orders from exchange
            $openOrdersResult = $exchangeService->getOpenOrders($symbol);
            $exchangeOpenOrders = $openOrdersResult['list'] ?? [];
            
            // Get all positions from exchange
            $positionsResult = $exchangeService->getPositions($symbol);
            $exchangePositions = $positionsResult['list'] ?? [];

            // 1. Check all pending orders
            $this->checkPendingOrders($exchangeService, $userExchange, $symbol, $exchangeOpenOrders);

            // 2. Check all filled orders (active positions)
            $this->checkFilledOrders($exchangeService, $userExchange, $symbol, $exchangePositions);

            // 3. Check for foreign orders on exchange
            $this->checkForeignOrders($exchangeService, $userExchange, $symbol, $exchangeOpenOrders);

        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email}: " . $e->getMessage());
            Log::error('FuturesOrderEnforcer error', [
                'user_id' => $user->id,
                'exchange' => $userExchange->exchange_name,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check all pending orders
     */
    private function checkPendingOrders($exchangeService, UserExchange $userExchange, string $symbol, array $exchangeOpenOrders)
    {
        $this->info("  بررسی سفارشات در انتظار...");

        // Get all pending orders from database
        $pendingOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->where('status', 'pending')
            ->get();

        // Create map of exchange orders for quick lookup
        $exchangeOrdersMap = [];
        foreach ($exchangeOpenOrders as $order) {
            $exchangeOrdersMap[$order['orderId']] = $order;
        }

        foreach ($pendingOrders as $dbOrder) {
            $exchangeOrder = $exchangeOrdersMap[$dbOrder->order_id] ?? null;

            // If order is not on exchange, skip (let lifecycle handle it)
            if (!$exchangeOrder) {
                continue;
            }

            // Check if order size or entry price doesn't match
            $exchangePrice = (float)($exchangeOrder['price'] ?? 0);
            $dbPrice = (float)$dbOrder->entry_price;
            $exchangeQty = (float)($exchangeOrder['qty'] ?? 0);
            $dbQty = (float)$dbOrder->amount;

            if (abs($exchangePrice - $dbPrice) > 0.0001 || abs($exchangeQty - $dbQty) > 0.000001) {
                try {
                    $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                    $dbOrder->delete();
                    $this->info("    حذف سفارش تغییر یافته: {$dbOrder->order_id} (عدم تطابق قیمت/مقدار)");
                } catch (Exception $e) {
                    $this->warn("    خطا در حذف سفارش {$dbOrder->order_id}: " . $e->getMessage());
                }
                continue;
            }

            // Check if expiry date has passed
            if ($dbOrder->expire_minutes !== null) {
                $expireAt = $dbOrder->created_at->timestamp + ($dbOrder->expire_minutes * 60);
                if (time() >= $expireAt) {
                    try {
                        $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                        $dbOrder->status = 'expired';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("    لغو سفارش منقضی شده: {$dbOrder->order_id}");
                    } catch (Exception $e) {
                        $this->warn("    خطا در لغو سفارش منقضی {$dbOrder->order_id}: " . $e->getMessage());
                    }
                    continue;
                }
            }

            // Check if closing price has been reached
            if ($dbOrder->cancel_price) {
                try {
                    $klines = $exchangeService->getKlines($symbol, 1, 2);
                    
                    $shouldCancel = ($dbOrder->side === 'buy' && max($klines['list'][0][2], $klines['list'][1][2]) >= $dbOrder->cancel_price) ||
                                   ($dbOrder->side === 'sell' && min($klines['list'][0][3], $klines['list'][1][3]) <= $dbOrder->cancel_price);

                    if ($shouldCancel) {
                        $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                        $dbOrder->status = 'canceled';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("    لغو سفارش به دلیل رسیدن به قیمت بسته شدن: {$dbOrder->order_id}");
                    }
                } catch (Exception $e) {
                    $this->warn("    خطا در بررسی قیمت بسته شدن برای سفارش {$dbOrder->order_id}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Check all filled orders (active positions)
     */
    private function checkFilledOrders($exchangeService, UserExchange $userExchange, string $symbol, array $exchangePositions)
    {
        $this->info("  بررسی سفارشات تکمیل شده (موقعیت‌های فعال)...");

        // Get all filled orders from database
        $filledOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->where('status', 'filled')
            ->get();

        foreach ($filledOrders as $dbOrder) {
            // Find corresponding position on exchange
            $matchingPosition = null;
            foreach ($exchangePositions as $position) {
                if ($position['symbol'] === $dbOrder->symbol && 
                    strtolower($position['side']) === strtolower($dbOrder->side) &&
                    ($position['size'] ?? 0) > 0) {
                    $matchingPosition = $position;
                    break;
                }
            }

            if (!$matchingPosition) {
                // Position not found on exchange, skip
                continue;
            }

            // Check if size or entry price doesn't match
            $exchangeSize = (float)($matchingPosition['size'] ?? 0);
            $dbSize = (float)$dbOrder->amount;
            $exchangePrice = (float)($matchingPosition['avgPrice'] ?? 0);
            $dbPrice = (float)$dbOrder->entry_price;

            if (abs($exchangeSize - $dbSize) > 0.000001 || abs($exchangePrice - $dbPrice) > 0.0001) {
                try {
                    // Send market close order for this position
                    $closeSide = ($dbOrder->side === 'buy') ? 'sell' : 'buy';
                    
                    $marketCloseParams = [
                        'category' => 'linear',
                        'symbol' => $dbOrder->symbol,
                        'side' => $closeSide,
                        'orderType' => 'Market',
                        'qty' => (string)$exchangeSize,
                        'reduceOnly' => true,
                    ];
                    
                    $exchangeService->createOrder($marketCloseParams);
                    
                    // Mark order as closed in database
                    $dbOrder->status = 'closed_by_enforcer';
                    $dbOrder->closed_at = now();
                    $dbOrder->save();
                    
                    $this->info("    بستن موقعیت تغییر یافته: {$dbOrder->symbol} (عدم تطابق اندازه/قیمت)");
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت {$dbOrder->symbol}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Check for foreign orders on exchange that are not in our system
     */
    private function checkForeignOrders($exchangeService, UserExchange $userExchange, string $symbol, array $exchangeOpenOrders)
    {
        $this->info("  بررسی سفارشات خارجی...");

        // Get all our tracked order IDs
        $ourOrderIds = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->whereIn('status', ['pending', 'filled'])
            ->pluck('order_id')
            ->filter()
            ->toArray();

        // Get our registered orders with TP/SL values for validation
        $ourRegisteredOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->whereIn('status', ['pending', 'filled'])
            ->get();

        foreach ($exchangeOpenOrders as $exchangeOrder) {
            $orderId = $exchangeOrder['orderId'];
            
            // Skip if this is our tracked order
            if (in_array($orderId, $ourOrderIds)) {
                continue;
            }

            // Check if this is a TP/SL order that should be preserved
            if ($this->isValidTpSlOrder($exchangeOrder, $ourRegisteredOrders)) {
                $this->info("    حفظ سفارش TP/SL معتبر: {$orderId}");
                continue;
            }

            // This is a foreign order, delete it
            try {
                $exchangeService->cancelOrderWithSymbol($orderId, $symbol);
                $this->info("    حذف سفارش خارجی: {$orderId}");
            } catch (Exception $e) {
                $this->warn("    خطا در حذف سفارش خارجی {$orderId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if an exchange order is a valid TP/SL order that should be preserved
     */
    private function isValidTpSlOrder(array $exchangeOrder, $ourRegisteredOrders): bool
    {
        $isReduceOnly = ($exchangeOrder['reduceOnly'] ?? false) === true;
        $orderPrice = (float)($exchangeOrder['price'] ?? $exchangeOrder['triggerPrice'] ?? 0);
        $orderSide = strtolower($exchangeOrder['side'] ?? '');
        $orderQty = (float)($exchangeOrder['qty'] ?? 0);

        // Only check reduce-only orders (TP/SL orders)
        if (!$isReduceOnly) {
            return false;
        }

        // Check against all our registered orders
        foreach ($ourRegisteredOrders as $registeredOrder) {
            $registeredSl = (float)$registeredOrder->sl;
            $registeredTp = (float)$registeredOrder->tp;
            $registeredSide = strtolower($registeredOrder->side);
            $registeredQty = (float)$registeredOrder->amount;

            // Expected opposite side for TP/SL
            $expectedOppositeSide = ($registeredSide === 'buy') ? 'sell' : 'buy';

            // Check if this matches our SL
            if ($registeredSl > 0 && 
                abs($orderPrice - $registeredSl) < 0.01 && 
                $orderSide === $expectedOppositeSide &&
                abs($orderQty - $registeredQty) < 0.000001) {
                return true;
            }

            // Check if this matches our TP
            if ($registeredTp > 0 && 
                abs($orderPrice - $registeredTp) < 0.01 && 
                $orderSide === $expectedOppositeSide &&
                abs($orderQty - $registeredQty) < 0.000001) {
                return true;
            }
        }

        return false;
    }
}