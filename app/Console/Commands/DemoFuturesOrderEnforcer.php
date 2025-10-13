<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class DemoFuturesOrderEnforcer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:futures:enforce {--user= : شناسه کاربر خاص برای اعمال قوانین}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'بررسی و اعمال قوانین سفارشات اضافی برای کاربران در حالت سخت‌گیرانه (دمو)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('شروع بررسی و اعمال قوانین سفارشات اضافی (دمو)...');

        $userOption = $this->option('user');

        if ($userOption) {
            $user = User::find($userOption);
            if (!$user) {
                $this->error("کاربر با شناسه {$userOption} یافت نشد.");
                return 1;
            }
            // اجرای جریان بررسی فقط قیمت لغو و تاریخ انقضا برای کاربر مشخص
            $this->enforceCancelExpireOnlyForUser($user);
            // اگر کاربر در حالت سخت‌گیرانه است، اجرای کامل قوانین نیز انجام شود
            if ($user->future_strict_mode) {
                $this->enforceForUser($user);
            }
        } else {
            // اجرای جریان بررسی فقط قیمت لغو و تاریخ انقضا برای همه کاربران دارای سفارش در انتظار
            $this->enforceForAllUsers();
            // سپس اجرای کامل قوانین برای کاربران در حالت سخت‌گیرانه
            $this->enforceForStrictAccount();
        }

        $this->info('بررسی و اعمال قوانین سفارشات اضافی (دمو) با موفقیت تکمیل شد.');
        return 0;
    }

    /**
     * Enforce orders for all users in strict mode
     */
    private function enforceForAllUsers()
    {
        // یافتن کاربران دارای حداقل یک سفارش در انتظار (دمو)
        $pendingUserExchangeIds = Order::where('is_demo', true)
            ->where('status', 'pending')
            ->pluck('user_exchange_id')
            ->filter()
            ->unique();

        $userIds = UserExchange::whereIn('id', $pendingUserExchangeIds)
            ->pluck('user_id')
            ->unique();

        $users = User::whereIn('id', $userIds)->get();
        $this->info("پردازش {$users->count()} کاربر دارای سفارش در انتظار (دمو) (فقط بررسی قیمت لغو و تاریخ انقضا)...");

        foreach ($users as $user) {
            $this->enforceCancelExpireOnlyForUser($user);
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

        // Get all user exchanges with demo API keys
        $userExchanges = UserExchange::where('user_id', $user->id)
            ->where('futures_access', true)
            ->whereNotNull('demo_api_key')
            ->whereNotNull('demo_api_secret')
            ->get();

        if ($userExchanges->isEmpty()) {
            $this->info("هیچ صرافی دمو فعالی برای کاربر {$user->email} یافت نشد");
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
        // در محیط لوکال از اتصال زنده به صرافی‌ها صرف‌نظر شود
        if (app()->environment('local')) {
            $this->info("اجرای قوانین در محیط لوکال غیرفعال است؛ اتصال به صرافی‌ها انجام نمی‌شود (دمو).");
            return;
        }
        try {
            $this->info("پردازش صرافی {$userExchange->exchange_name} (دمو) برای کاربر {$user->email}");

            // Create exchange service (demo mode)
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->demo_api_key,
                $userExchange->demo_api_secret,
                true // Demo mode
            );

            // Get user's selected market
            $symbol = $user->selected_market;

            // Get all open orders from exchange (fetch for all symbols)
            $openOrdersResult = $exchangeService->getOpenOrders(null);
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
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} (دمو) برای کاربر {$user->email}: " . $e->getMessage());
            Log::error("Demo order enforcement failed", [
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
        $this->info("  بررسی سفارشات در انتظار (دمو)...");

        // دریافت همه سفارشات در انتظار از دیتابیس (دمو)
        $pendingOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->where('status', 'pending')
            ->get();

        // ساخت نقشه سفارشات صرافی برای جستجوی سریع
        $exchangeOrdersMap = [];
        foreach ($exchangeOpenOrders as $order) {
            $exchangeOrdersMap[$order['orderId']] = $order;
        }

        foreach ($pendingOrders as $dbOrder) {
            $exchangeOrder = $exchangeOrdersMap[$dbOrder->order_id] ?? null;

            // اگر سفارش روی صرافی وجود ندارد، صرف‌نظر (واگذاری به چرخه عمر)
            if (!$exchangeOrder) {
                continue;
            }

            // بررسی عدم تطابق مقدار/قیمت
            $exchangePrice = (float)($exchangeOrder['price'] ?? 0);
            $dbPrice = (float)$dbOrder->entry_price;
            $exchangeQty = (float)($exchangeOrder['qty'] ?? 0);
            $dbQty = (float)$dbOrder->amount;

            if (abs($exchangePrice - $dbPrice) > 0.0001 || abs($exchangeQty - $dbQty) > 0.000001) {
                try {
                    $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                    $dbOrder->delete();
                    $this->info("    حذف سفارش تغییر یافته (دمو): {$dbOrder->order_id} (عدم تطابق قیمت/مقدار)");
                } catch (Exception $e) {
                    $this->warn("    خطا در حذف سفارش (دمو) {$dbOrder->order_id}: " . $e->getMessage());
                }
                continue;
            }
        }
    }

    /**
     * Check all filled orders (active positions)
     */
    private function checkFilledOrders($exchangeService, UserExchange $userExchange, string $symbol, array $exchangePositions)
    {
        $this->info("  بررسی سفارشات تکمیل شده (موقعیت‌های فعال) (دمو)...");

        // Get all filled orders from database (demo mode)
        $filledOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
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
                    // Unified market close using closePosition across exchanges
                    $closeSideFull = ($dbOrder->side === 'buy') ? 'Sell' : 'Buy';
                    $exchangeService->closePosition($dbOrder->symbol, $closeSideFull, (string)$exchangeSize);
                    
                    // Mark order as closed in database
                    $dbOrder->status = 'closed_by_enforcer';
                    $dbOrder->closed_at = now();
                    $dbOrder->save();
                    
                    $this->info("    بستن موقعیت تغییر یافته (دمو): {$dbOrder->symbol} (عدم تطابق اندازه/قیمت)");
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت (دمو) {$dbOrder->symbol}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Check for foreign orders on exchange that are not in our system
     */
    private function checkForeignOrders($exchangeService, UserExchange $userExchange, string $symbol, array $exchangeOpenOrders)
    {
        $this->info("  بررسی سفارشات خارجی (دمو)...");

        // Get all our tracked order IDs (demo mode)
        $ourOrderIds = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->whereIn('status', ['pending', 'filled'])
            ->pluck('order_id')
            ->filter()
            ->toArray();

        // Get our registered orders with TP/SL values for validation (demo mode)
        $ourRegisteredOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->whereIn('status', ['pending', 'filled'])
            ->get();

        foreach ($exchangeOpenOrders as $exchangeOrder) {
            $orderId = $exchangeOrder['orderId'];
            $orderSymbol = $exchangeOrder['symbol'] ?? $symbol;
            
            if (in_array($orderId, $ourOrderIds)) {
                continue;
            }

            if ($this->isValidTpSlOrder($exchangeOrder, $ourRegisteredOrders)) {
                $this->info("    حفظ سفارش TP/SL معتبر (دمو): {$orderId}");
                continue;
            }

            try {
                $exchangeService->cancelOrderWithSymbol($orderId, $orderSymbol);
                $this->info("    حذف سفارش خارجی (دمو): {$orderId}");
            } catch (Exception $e) {
                $this->warn("    خطا در حذف سفارش خارجی (دمو) {$orderId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if an exchange order is a valid TP/SL order that should be preserved
     */
    private function isValidTpSlOrder(array $exchangeOrder, $ourRegisteredOrders): bool
    {
        $reduceRaw = $exchangeOrder['reduceOnly'] ?? false;
        $isReduceOnly = ($reduceRaw === true || $reduceRaw === 'true' || $reduceRaw === 1 || $reduceRaw === '1');
        $orderPrice = (float)($exchangeOrder['price'] ?? $exchangeOrder['triggerPrice'] ?? $exchangeOrder['stopPrice'] ?? $exchangeOrder['stopPx'] ?? 0);
        $orderSide = strtolower($exchangeOrder['side'] ?? '');
        $orderQty = (float)($exchangeOrder['qty'] ?? $exchangeOrder['quantity'] ?? $exchangeOrder['origQty'] ?? 0);

        if (!$isReduceOnly) {
            return false;
        }

        foreach ($ourRegisteredOrders as $registeredOrder) {
            $registeredSl = (float)$registeredOrder->sl;
            $registeredTp = (float)$registeredOrder->tp;
            $registeredSide = strtolower($registeredOrder->side);
            $registeredQty = (float)$registeredOrder->amount;

            $expectedOppositeSide = ($registeredSide === 'buy') ? 'sell' : 'buy';

            if ($registeredSl > 0 && 
                abs($orderPrice - $registeredSl) < 0.01 && 
                $orderSide === $expectedOppositeSide &&
                abs($orderQty - $registeredQty) < 0.000001) {
                return true;
            }

            if ($registeredTp > 0 && 
                abs($orderPrice - $registeredTp) < 0.01 && 
                $orderSide === $expectedOppositeSide &&
                abs($orderQty - $registeredQty) < 0.000001) {
                return true;
            }
        }

        return false;
    }

    private function enforceCancelExpireOnlyForUser(User $user)
    {
        $this->info("پردازش کاربر برای بررسی قیمت لغو/انقضا (دمو): {$user->email}");

        // دریافت همه صرافی‌های دمو فعال کاربر
        $userExchanges = UserExchange::where('user_id', $user->id)
            ->where('futures_access', true)
            ->whereNotNull('demo_api_key')
            ->whereNotNull('demo_api_secret')
            ->get();

        if ($userExchanges->isEmpty()) {
            $this->info("هیچ صرافی دمو فعالی برای کاربر {$user->email} یافت نشد");
            return;
        }

        foreach ($userExchanges as $userExchange) {
            $this->enforceCancelExpireOnlyForUserExchange($user, $userExchange);
        }
    }

    private function enforceCancelExpireOnlyForUserExchange(User $user, UserExchange $userExchange)
    {
        // در محیط لوکال از اتصال زنده به صرافی‌ها صرف‌نظر شود
        if (app()->environment('local')) {
            $this->info("اجرای قوانین در محیط لوکال غیرفعال است؛ اتصال به صرافی‌ها انجام نمی‌شود (دمو).");
            return;
        }

        try {
            $this->info("پردازش صرافی {$userExchange->exchange_name} (دمو) برای کاربر {$user->email} - فقط بررسی قیمت لغو/انقضا");

            // ایجاد سرویس صرافی (دمو)
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->demo_api_key,
                $userExchange->demo_api_secret,
                true
            );

            $symbol = $user->selected_market;

            // دریافت همه سفارشات باز (همه نمادها)
            $openOrdersResult = $exchangeService->getOpenOrders(null);
            $exchangeOpenOrders = $openOrdersResult['list'] ?? [];

            // اجرای بررسی فقط قیمت لغو و تاریخ انقضا
            $this->checkPendingOrdersCancelExpireOnly($exchangeService, $userExchange, $symbol, $exchangeOpenOrders);
        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} (دمو) برای کاربر {$user->email} در بررسی لغو/انقضا: " . $e->getMessage());
        }
    }

    private function checkPendingOrdersCancelExpireOnly($exchangeService, UserExchange $userExchange, string $symbol, array $exchangeOpenOrders)
    {
        $this->info("  بررسی سفارشات در انتظار (دمو) - فقط قیمت لغو و تاریخ انقضا...");

        $pendingOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->where('status', 'pending')
            ->get();

        $exchangeOrdersMap = [];
        foreach ($exchangeOpenOrders as $order) {
            $exchangeOrdersMap[$order['orderId']] = $order;
        }

        foreach ($pendingOrders as $dbOrder) {
            $exchangeOrder = $exchangeOrdersMap[$dbOrder->order_id] ?? null;
            if (!$exchangeOrder) { continue; }

            // تاریخ انقضا
            if ($dbOrder->expire_minutes !== null) {
                $expireAt = $dbOrder->created_at->timestamp + ($dbOrder->expire_minutes * 60);
                if (time() >= $expireAt) {
                    try {
                        $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                        $dbOrder->status = 'expired';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("    لغو سفارش منقضی شده (دمو): {$dbOrder->order_id}");
                    } catch (Exception $e) {
                        $this->warn("    خطا در لغو سفارش منقضی (دمو) {$dbOrder->order_id}: " . $e->getMessage());
                    }
                    continue;
                }
            }

            // قیمت لغو
            if ($dbOrder->cancel_price) {
                try {
                    $klinesRaw = $exchangeService->getKlines($symbol, '1m', 2);
                    $list = $klinesRaw['list'] ?? $klinesRaw['data'] ?? $klinesRaw['result']['list'] ?? $klinesRaw;
                    if (!is_array($list)) { $list = []; }
                    $candles = array_slice($list, -2);
                    $extractHL = function($candle) {
                        if (is_array($candle)) {
                            if (array_keys($candle) === range(0, count($candle)-1)) {
                                $high = isset($candle[2]) ? (float)$candle[2] : (isset($candle[1]) ? (float)$candle[1] : 0.0);
                                $low = isset($candle[3]) ? (float)$candle[3] : (isset($candle[2]) ? (float)$candle[2] : 0.0);
                                return [$high, $low];
                            }
                            $high = (float)($candle['high'] ?? $candle['highPrice'] ?? $candle['h'] ?? 0);
                            $low  = (float)($candle['low']  ?? $candle['lowPrice']  ?? $candle['l'] ?? 0);
                            return [$high, $low];
                        }
                        return [0.0, 0.0];
                    };
                    [$h1,$l1] = isset($candles[0]) ? $extractHL($candles[0]) : [0.0,0.0];
                    [$h2,$l2] = isset($candles[1]) ? $extractHL($candles[1]) : [0.0,0.0];
                    
                    $shouldCancel = ($dbOrder->side === 'buy' && max($h1, $h2) >= (float)$dbOrder->cancel_price) ||
                                   ($dbOrder->side === 'sell' && min($l1, $l2) <= (float)$dbOrder->cancel_price);

                    if ($shouldCancel) {
                        $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                        $dbOrder->status = 'canceled';
                        $dbOrder->closed_at = now();
                        $dbOrder->save();
                        $this->info("    لغو سفارش به دلیل رسیدن به قیمت بسته شدن (دمو): {$dbOrder->order_id}");
                    }
                } catch (Exception $e) {
                    $this->warn("    خطا در بررسی قیمت بسته شدن برای سفارش (دمو) {$dbOrder->order_id}: " . $e->getMessage());
                }
            }
        }
    }
    private function enforceForStrictAccount()
    {
        $this->info("اجرای کامل قوانین برای کاربران با حالت سخت‌گیرانه (دمو)...");
        $strictUsers = User::where('future_strict_mode', true)->get();
        foreach ($strictUsers as $user) {
            $this->enforceForUser($user);
        }
    }
}