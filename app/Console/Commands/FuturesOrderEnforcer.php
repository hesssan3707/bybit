<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Trade;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
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
            
            // Get all positions from exchange (fetch all symbols to allow cross-symbol checks)
            $positionsResult = $exchangeService->getPositions(null);
            $exchangePositions = $positionsResult['list'] ?? [];

            // 1. Check all pending orders
            $this->checkPendingOrders($exchangeService, $userExchange, $symbol, $exchangeOpenOrders);

            // 2. Check all filled orders (active positions)
            $this->checkFilledOrders($exchangeService, $userExchange, $symbol, $exchangePositions);

            // 3. Check for foreign orders on exchange
            $this->checkForeignOrders($exchangeService, $userExchange, $symbol, $exchangeOpenOrders);

            // 4. Optionally purge positions in other symbols if env key allows
            $this->purgeOtherSymbolsPositions($exchangeService, $userExchange, $symbol, $exchangePositions);

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
            ->where('is_locked', false)
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
                    DB::transaction(function () use ($exchangeService, $dbOrder, $symbol) {
                        $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                        $dbOrder->delete();
                    });
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

            // ابتدا بررسی سود/زیان: اگر بزرگ‌تر از ±10% باشد، موقعیت بسته شود
            $pnlRatio = $this->getPositionPnlRatio($matchingPosition);
            if ($pnlRatio !== null && abs($pnlRatio) >= 0.10) {
                try {
                    DB::transaction(function () use ($exchangeService, $dbTrade, $matchingPosition) {
                        $closeSide = (strtolower($dbTrade->side) === 'buy') ? 'Buy' : 'Sell';
                        $exchangeSizeForClose = (float)($matchingPosition['size'] ?? 0);
                        $exchangeService->closePosition($dbTrade->symbol, $closeSide, $exchangeSizeForClose);
                        $dbTrade->closed_at = now();
                        $dbTrade->save();
                    });
                    $this->info("    بستن موقعیت به دلیل سود/زیان بزرگ: {$dbTrade->symbol} (PnL=" . round($pnlRatio*100,2) . "%)");
                    continue;
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت به دلیل PnL {$dbTrade->symbol}: " . $e->getMessage());
                }
            }

            // Check if size or entry price doesn't match (نسبت به سفارش اصلی)
            $exchangeSize  = (float)($matchingPosition['size'] ?? 0);
            $exchangePrice = (float)($matchingPosition['avgPrice'] ?? 0);

            // از سفارش مرتبط به عنوان مبنا استفاده شود؛ در نبود سفارش، از رکورد معامله استفاده شود
            $relatedOrder = $dbTrade->order;
            $orderBaselineSize  = (float)($relatedOrder->amount ?? $dbTrade->qty ?? 0);
            $orderBaselinePrice = (float)($relatedOrder->entry_price ?? $dbTrade->avg_entry_price ?? 0);

            // درصد اختلاف‌ها نسبت به سفارش اصلی
            $sizeBase  = max(abs($orderBaselineSize), 1e-9);
            $priceBase = max(abs($orderBaselinePrice), 1e-9);
            $sizeDiffPct  = ($sizeBase > 0) ? (abs($exchangeSize - $orderBaselineSize) / $sizeBase) : 0.0;
            $priceDiffPct = ($priceBase > 0) ? (abs($exchangePrice - $orderBaselinePrice) / $priceBase) : 0.0;

            $tolerance = 0.002; // 0.2%

            if ($sizeDiffPct > $tolerance || $priceDiffPct > $tolerance) {
                // اختلاف قابل توجه نسبت به سفارش اصلی: بستن موقعیت و حذف از سیستم
                try {
                    DB::transaction(function () use ($exchangeService, $dbTrade, $exchangeSize) {
                        $closeSide = (strtolower($dbTrade->side) === 'buy') ? 'Buy' : 'Sell';
                        $exchangeService->closePosition($dbTrade->symbol, $closeSide, (float)$exchangeSize);

                        $dbTrade->closed_at = now();
                        $dbTrade->save();
                    });

                    $this->info("    بستن موقعیت تغییر یافته: {$dbTrade->symbol} (عدم تطابق >0.2% نسبت به سفارش ثبت‌شده)");
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت {$dbTrade->symbol}: " . $e->getMessage());
                }
            } else if ($sizeDiffPct > 0 || $priceDiffPct > 0) {
                // اختلاف جزئی نسبت به سفارش اصلی: فقط مقدار/قیمت ورودی معامله بروزرسانی شود
                $dbTrade->qty = $exchangeSize;
                $dbTrade->avg_entry_price = $exchangePrice;
                $dbTrade->save();
                $this->info("    به‌روزرسانی موقعیت با اختلاف جزئی: {$dbTrade->symbol} (همسان‌سازی با صرافی؛ مبنا سفارش اصلی)");
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

        // Read env flag: if true, purge foreign orders in other symbols too
        $purgeOtherSymbolsRaw = env('FUTURES_STRICT_PURGE_OTHER_SYMBOLS', false);
        $purgeOtherSymbols = ($purgeOtherSymbolsRaw === true || $purgeOtherSymbolsRaw === 'true' || $purgeOtherSymbolsRaw === 1 || $purgeOtherSymbolsRaw === '1');

        foreach ($exchangeOpenOrders as $exchangeOrder) {
            $orderId = $exchangeOrder['orderId'];
            $orderSymbol = $exchangeOrder['symbol'] ?? $symbol;

            // اگر نماد سفارش متفاوت از نماد انتخاب‌شده است و پرچم محیطی فعال باشد، سفارش خارجی حذف شود
            if ($orderSymbol !== $symbol) {
                if ($purgeOtherSymbols && !in_array($orderId, $ourOrderIds)) {
                    try {
                        $exchangeService->cancelOrderWithSymbol($orderId, $orderSymbol);
                        $this->info("    حذف سفارش خارجی نماد دیگر: {$orderId} ({$orderSymbol})");
                    } catch (Exception $e) {
                        $this->warn("    خطا در حذف سفارش خارجی {$orderId} ({$orderSymbol}): " . $e->getMessage());
                    }
                }
                // در غیر این صورت، این سفارشات لمس نشوند
                continue;
            }

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
     * Purge positions in symbols other than the user's selected market when env key is true
     */
    private function purgeOtherSymbolsPositions($exchangeService, UserExchange $userExchange, string $selectedSymbol, array $exchangePositions)
    {
        // Read env flag: if false, do nothing
        $purgeOtherSymbolsRaw = env('FUTURES_STRICT_PURGE_OTHER_SYMBOLS', false);
        $purgeOtherSymbols = ($purgeOtherSymbolsRaw === true || $purgeOtherSymbolsRaw === 'true' || $purgeOtherSymbolsRaw === 1 || $purgeOtherSymbolsRaw === '1');
        if (!$purgeOtherSymbols) { return; }

        $this->info("  پاکسازی موقعیت‌های نمادهای دیگر در حالت سخت‌گیرانه...");

        // Close any active positions for symbols other than selectedSymbol
        foreach ($exchangePositions as $position) {
            $posSymbol = $position['symbol'] ?? null;
            $posSize = (float)($position['size'] ?? 0);
            $rawSide = strtolower($position['side'] ?? $position['positionSide'] ?? '');
            $closeSide = ($rawSide === 'buy' || $rawSide === 'long') ? 'Buy' : 'Sell';

            if (!$posSymbol || $posSymbol === $selectedSymbol) { continue; }
            if ($posSize <= 0) { continue; }

            try {
                DB::transaction(function () use ($exchangeService, $userExchange, $posSymbol, $closeSide, $posSize) {
                    $exchangeService->closePosition($posSymbol, $closeSide, $posSize);

                    // Mark any related trades in our DB as closed
                    $relatedTrades = Trade::where('user_exchange_id', $userExchange->id)
                        ->where('is_demo', false)
                        ->whereNull('closed_at')
                        ->where('symbol', $posSymbol)
                        ->get();
                    foreach ($relatedTrades as $t) {
                        $t->closed_at = now();
                        $t->save();
                    }
                });

                $this->info("    بستن موقعیت نماد دیگر: {$posSymbol} (اندازه={$posSize})");
            } catch (Exception $e) {
                $this->warn("    خطا در بستن موقعیت نماد دیگر {$posSymbol}: " . $e->getMessage());
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

    /**
     * محاسبه نسبت سود/زیان موقعیت (ROI) به صورت نسبت (0.10 = 10%)
     */
    private function getPositionPnlRatio(array $position): ?float
    {
        // اولویت: فیلدهای صریح ROI
        $uplRatio = $position['uplRatio'] ?? $position['upl'] ?? null; // Bybit
        if ($uplRatio !== null) {
            $val = (float)$uplRatio;
            return $val; // Bybit returns ratio (e.g., 0.12)
        }
        $roe = $position['roe'] ?? null; // BingX may provide percent
        if ($roe !== null) {
            $val = (float)$roe;
            if (abs($val) > 2) { // if looks like percent, convert to ratio
                $val = $val / 100.0;
            }
            return $val;
        }

        // محاسبه بر اساس قیمت‌ها
        $avg = (float)($position['avgPrice'] ?? $position['entryPrice'] ?? $position['avgCostPrice'] ?? 0);
        $mark = (float)($position['markPrice'] ?? $position['marketPrice'] ?? 0);
        $side = strtolower($position['side'] ?? $position['positionSide'] ?? '');
        if ($avg > 0 && $mark > 0 && ($side === 'buy' || $side === 'sell' || $side === 'long' || $side === 'short')) {
            $isLong = ($side === 'buy' || $side === 'long');
            $ratio = $isLong ? (($mark - $avg) / $avg) : (($avg - $mark) / $avg);
            return $ratio;
        }
        return null;
    }

    private function enforceCancelExpireOnlyForUser(User $user)
    {
        $this->info("پردازش کاربر برای بررسی قیمت لغو/انقضا: {$user->email}");

        // دریافت همه صرافی‌های فعال کاربر
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
            $this->enforceCancelExpireOnlyForUserExchange($user, $userExchange);
        }
    }

    private function enforceForStrictAccount()
    {
        $this->info("اجرای کامل قوانین برای کاربران با حالت سخت‌گیرانه...");
        $strictUsers = User::where('future_strict_mode', true)->get();
        foreach ($strictUsers as $user) {
            $this->enforceForUser($user);
        }
    }

    private function enforceCancelExpireOnlyForUserExchange(User $user, UserExchange $userExchange)
    {

        try {
            $this->info("پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email} - فقط بررسی قیمت لغو/انقضا");

            // ایجاد سرویس صرافی
            $exchangeService = ExchangeFactory::create(
                $userExchange->exchange_name,
                $userExchange->api_key,
                $userExchange->api_secret,
                false
            );

            $symbol = $user->selected_market;

            // دریافت همه سفارشات باز (همه نمادها)
            $openOrdersResult = $exchangeService->getOpenOrders(null);
            $exchangeOpenOrders = $openOrdersResult['list'] ?? [];

            // اجرای بررسی فقط قیمت لغو و تاریخ انقضا
            $this->checkPendingOrdersCancelExpireOnly($exchangeService, $userExchange, $symbol, $exchangeOpenOrders);
        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email} در بررسی لغو/انقضا: " . $e->getMessage());
        }
    }

    private function checkPendingOrdersCancelExpireOnly($exchangeService, UserExchange $userExchange, string $symbol, array $exchangeOpenOrders)
    {
        $this->info("  بررسی سفارشات در انتظار - فقط قیمت لغو و تاریخ انقضا...");

        $pendingOrders = Order::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', false)
            ->where('status', 'pending')
            ->where('is_locked', false)
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
                        DB::transaction(function () use ($exchangeService, $dbOrder, $symbol) {
                            $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                            $dbOrder->status = 'expired';
                            $dbOrder->closed_at = now();
                            $dbOrder->save();
                        });
                        $this->info("    لغو سفارش منقضی شده: {$dbOrder->order_id}");
                    } catch (Exception $e) {
                        $this->warn("    خطا در لغو سفارش منقضی {$dbOrder->order_id}: " . $e->getMessage());
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
                        try {
                            DB::transaction(function () use ($exchangeService, $dbOrder, $symbol) {
                                $exchangeService->cancelOrderWithSymbol($dbOrder->order_id, $symbol);
                                $dbOrder->status = 'canceled';
                                $dbOrder->closed_at = now();
                                $dbOrder->save();
                            });
                            $this->info("    لغو سفارش به دلیل رسیدن به قیمت بسته شدن: {$dbOrder->order_id}");
                        } catch (Exception $e) {
                            $this->warn("    خطا در لغو سفارش (قیمت بسته شدن) {$dbOrder->order_id}: " . $e->getMessage());
                        }
                    }
                } catch (Exception $e) {
                    $this->warn("    خطا در بررسی قیمت بسته شدن برای سفارش {$dbOrder->order_id}: " . $e->getMessage());
                }
            }
        }
    }
}