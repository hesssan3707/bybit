<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Trade;
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
            // Run cancel/expire-only flow for specified user
            $this->enforceCancelExpireOnlyForUser($user);
            // If user is strict, also run full strict enforcement
            if ($user->future_strict_mode) {
                $this->enforceForUser($user);
            }
        } else {
            // Run cancel/expire-only across all users with pending orders
            $this->enforceForAllUsers();
            // Then run full enforcement for strict-mode users
            $this->enforceForStrictAccount();
        }

        $this->info('بررسی و اعمال قوانین سفارشات اضافی با موفقیت تکمیل شد.');
        return 0;
    }

    /**
     * Enforce orders for all users in strict mode
     */
    private function enforceForAllUsers()
    {
        // Find users who have at least one pending order
        $pendingUserExchangeIds = Order::where('is_demo', false)
            ->where('status', 'pending')
            ->pluck('user_exchange_id')
            ->filter()
            ->unique();

        $userIds = UserExchange::whereIn('id', $pendingUserExchangeIds)
            ->pluck('user_id')
            ->unique();

        $users = User::whereIn('id', $userIds)->get();
        $this->info("پردازش {$users->count()} کاربر دارای سفارش در انتظار (فقط بررسی قیمت لغو و تاریخ انقضا)...");

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
        // Skip live connectivity in local environment
        if (app()->environment('local')) {
            $this->info("اجرای قوانین در محیط لوکال غیرفعال است؛ اتصال به صرافی‌ها انجام نمی‌شود.");
            return;
        }

        try {
            $this->info("پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email}");

            // Create exchange service (real mode)
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->api_key,
                $userExchange->api_secret,
                false // Real mode
            );

            // Get user's selected market
            $symbol = $user->selected_market;

            // Get all open orders from exchange (all symbols)
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
        }
    }

    /**
     * Check all filled orders (active positions)
     */
    private function checkFilledOrders($exchangeService, UserExchange $userExchange, string $symbol, array $exchangePositions)
    {
        $this->info("  بررسی معاملات باز (موقعیت‌های فعال)...");

        // Get all not-closed trades from database (real mode) for this user exchange and symbol
        $openTrades = Trade::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->whereNull('closed_at')
            ->where('symbol', $symbol)
            ->get();

        foreach ($openTrades as $dbTrade) {
            // Find corresponding position on exchange by symbol and side
            $matchingPosition = null;
            foreach ($exchangePositions as $position) {
                if (($position['symbol'] ?? null) === $dbTrade->symbol &&
                    strtolower($position['side'] ?? '') === strtolower($dbTrade->side) &&
                    (float)($position['size'] ?? 0) > 0) {
                    $matchingPosition = $position;
                    break;
                }
            }

            if (!$matchingPosition) {
                // No active position found for this trade; skip closing and continue
                continue;
            }

            // Check if size or entry price doesn't match
            $exchangeSize = (float)($matchingPosition['size'] ?? 0);
            $dbSize = (float)$dbTrade->qty;
            $exchangePrice = (float)($matchingPosition['avgPrice'] ?? 0);
            $dbPrice = (float)$dbTrade->avg_entry_price;

            if (abs($exchangeSize - $dbSize) > 0.000001 || abs($exchangePrice - $dbPrice) > 0.0001) {
                try {
                    // Unified market close using closePosition across exchanges
                    $closeSide = (strtolower($dbTrade->side) === 'buy') ? 'Buy' : 'Sell';
                    $exchangeService->closePosition($dbTrade->symbol, $closeSide, (float)$exchangeSize);

                    // Mark trade as closed in database (closed_by_enforcer)
                    $dbTrade->closed_at = now();
                    $dbTrade->save();

                    $this->info("    بستن موقعیت تغییر یافته: {$dbTrade->symbol} (عدم تطابق اندازه/قیمت)");
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت {$dbTrade->symbol}: " . $e->getMessage());
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

        // ourOrderIds fetched below

        // دریافت همه شناسه‌های سفارشات ما (واقعی)
        $ourOrderIds = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->whereIn('status', ['pending', 'filled'])
            ->pluck('order_id')
            ->filter()
            ->toArray();

        // دریافت همه معاملات باز (واقعی) برای اعتبارسنجی TP/SL
        $openTrades = Trade::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->whereNull('closed_at')
            ->with('order')
            ->where('symbol', $symbol)
            ->get();

        foreach ($exchangeOpenOrders as $exchangeOrder) {
            $orderId = $exchangeOrder['orderId'];
            $orderSymbol = $exchangeOrder['symbol'] ?? $symbol;

            // فقط سفارشات مربوط به نماد انتخاب‌شده بررسی شوند
            if ($orderSymbol !== $symbol) { continue; }

            if (in_array($orderId, $ourOrderIds)) {
                continue;
            }

            // حفظ سفارشات TP/SL معتبر که دقیقاً با معاملات ما منطبق هستند
            if ($this->isValidTpSlOrder($exchangeOrder, $openTrades)) {
                $this->info("    حفظ سفارش TP/SL معتبر: {$orderId}");
                continue;
            }

            try {
                $exchangeService->cancelOrderWithSymbol($orderId, $orderSymbol);
                $this->info("    حذف سفارش خارجی: {$orderId}");
            } catch (Exception $e) {
                $this->warn("    خطا در حذف سفارش خارجی {$orderId}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check if an exchange order is a valid TP/SL order that should be preserved
     */
    private function isValidTpSlOrder(array $exchangeOrder, $openTrades): bool
    {
        // Normalize fields across exchanges (Bybit/Binance/BingX)
        $reduceRaw = $exchangeOrder['reduceOnly'] ?? false;
        $isReduceOnly = ($reduceRaw === true || $reduceRaw === 'true' || $reduceRaw === 1 || $reduceRaw === '1');
        // Prefer the first non-zero among price, triggerPrice, stopPrice, stopPx
        $orderPrice = 0.0;
        $candidate = (float)($exchangeOrder['price'] ?? 0);
        if ($candidate > 0) { $orderPrice = $candidate; }
        else {
            $candidate = (float)($exchangeOrder['triggerPrice'] ?? 0);
            if ($candidate > 0) { $orderPrice = $candidate; }
            else {
                $candidate = (float)($exchangeOrder['stopPrice'] ?? 0);
                if ($candidate > 0) { $orderPrice = $candidate; }
                else {
                    $candidate = (float)($exchangeOrder['stopPx'] ?? 0);
                    if ($candidate > 0) { $orderPrice = $candidate; }
                }
            }
        }
        $orderSide  = strtolower($exchangeOrder['side'] ?? '');
        $orderQty   = (float)($exchangeOrder['qty'] ?? $exchangeOrder['quantity'] ?? $exchangeOrder['origQty'] ?? 0);
        $orderSymbol = $exchangeOrder['symbol'] ?? null;

        if (!$isReduceOnly) { return false; }
        if ($orderQty <= 0) { return false; }
        if ($orderPrice <= 0) { return false; }

        $qtyEps = 1e-6;        // strict qty match with float epsilon
        $priceTol = 0.01;      // acceptable price tolerance

        foreach ($openTrades as $trade) {
            $order = $trade->order; // related order holds tp/sl
            if (!$order) { continue; }

            $registeredSl = (float)($order->sl ?? 0);
            $registeredTp = (float)($order->tp ?? 0);
            $registeredSide = strtolower($trade->side);
            $registeredQty = (float)$trade->qty;
            $registeredSymbol = $trade->symbol;
            $expectedOppositeSide = ($registeredSide === 'buy') ? 'sell' : 'buy';

            if ($orderSymbol !== null && $registeredSymbol !== $orderSymbol) { continue; }
            if ($orderSide !== $expectedOppositeSide) { continue; }

            // No partial TP/SL: qty must match exactly (within epsilon)
            if (abs($orderQty - $registeredQty) > $qtyEps) { continue; }

            $matchesSl = ($registeredSl > 0) && (abs($orderPrice - $registeredSl) <= $priceTol);
            $matchesTp = ($registeredTp > 0) && (abs($orderPrice - $registeredTp) <= $priceTol);

            if ($matchesSl || $matchesTp) {
                return true;
            }
        }

        return false;
    }
}