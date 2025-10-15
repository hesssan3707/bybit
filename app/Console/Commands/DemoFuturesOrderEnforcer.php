<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Trade;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
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
        $this->info("  بررسی معاملات باز (موقعیت‌های فعال) (دمو)...");

        // Get all not-closed trades from database (demo mode) for this user exchange and symbol
        $openTrades = Trade::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
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
                    $closeSide = (strtolower($dbTrade->side) === 'buy') ? 'Buy' : 'Sell';
                    $exchangeSizeForClose = (float)($matchingPosition['size'] ?? 0);
                    $exchangeService->closePosition($dbTrade->symbol, $closeSide, $exchangeSizeForClose);
                    $dbTrade->closed_at = now();
                    $dbTrade->save();
                    $this->info("    بستن موقعیت به دلیل سود/زیان بزرگ (دمو): {$dbTrade->symbol} (PnL=" . round($pnlRatio*100,2) . "%)");
                    continue; // از بررسی‌های بعدی صرف‌نظر شود
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت به دلیل PnL (دمو) {$dbTrade->symbol}: " . $e->getMessage());
                }
            }

            // Check if size or entry price doesn't match (نسبت به سفارش اصلی)
            $exchangeSize  = (float)($matchingPosition['size'] ?? 0);
            $exchangePrice = (float)($matchingPosition['avgPrice'] ?? 0);
            // از سفارش مرتبط به عنوان مبنا استفاده شود؛ در نبود سفارش، از رکورد معامله استفاده شود
            $relatedOrder = $dbTrade->order;
            $orderBaselineSize  = (float)($relatedOrder->amount ?? $dbTrade->qty ?? 0);
            $orderBaselinePrice = (float)($relatedOrder->entry_price ?? $dbTrade->avg_entry_price ?? 0);
            // درصد اختلاف‌ها نسبت به سفارش اصلی و آستانه 0.2% اعمال شود
            $sizeBase  = max(abs($orderBaselineSize), 1e-9);
            $priceBase = max(abs($orderBaselinePrice), 1e-9);
            $sizeDiffPct  = ($sizeBase > 0) ? (abs($exchangeSize - $orderBaselineSize) / $sizeBase) : 0.0;
            $priceDiffPct = ($priceBase > 0) ? (abs($exchangePrice - $orderBaselinePrice) / $priceBase) : 0.0;
            $tolerance = 0.002; // 0.2%

            if ($sizeDiffPct > $tolerance || $priceDiffPct > $tolerance) {
                try {
                    $closeSide = (strtolower($dbTrade->side) === 'buy') ? 'Buy' : 'Sell';
                    $exchangeService->closePosition($dbTrade->symbol, $closeSide, (float)$exchangeSize);
                    $dbTrade->closed_at = now();
                    $dbTrade->save();
                    $this->info("    بستن موقعیت تغییر یافته (دمو): {$dbTrade->symbol} (عدم تطابق >0.2% نسبت به سفارش ثبت‌شده)");
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت (دمو) {$dbTrade->symbol}: " . $e->getMessage());
                }
            } else if ($sizeDiffPct > 0 || $priceDiffPct > 0) {
                // اختلاف جزئی نسبت به سفارش اصلی: همسان‌سازی رکورد معامله
                $dbTrade->qty = $exchangeSize;
                $dbTrade->avg_entry_price = $exchangePrice;
                $dbTrade->save();
                $this->info("    به‌روزرسانی موقعیت با اختلاف جزئی (دمو): {$dbTrade->symbol} (همسان‌سازی با صرافی؛ مبنا سفارش اصلی)");
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

        // Get all not-closed trades (demo) for validation of TP/SL
        $openTrades = Trade::where('user_exchange_id', $userExchange->id)
            ->where('is_demo', true)
            ->whereNull('closed_at')
            ->with('order')
            ->where('symbol', $symbol)
            ->get();

        foreach ($exchangeOpenOrders as $exchangeOrder) {
            $orderId = $exchangeOrder['orderId'];
            $orderSymbol = $exchangeOrder['symbol'] ?? $symbol;

            // Only consider orders for the selected symbol
            if ($orderSymbol !== $symbol) { continue; }
            
            if (in_array($orderId, $ourOrderIds)) {
                continue;
            }

            // Preserve valid TP/SL orders strictly matching our trades
            if ($this->isValidTpSlOrder($exchangeOrder, $openTrades)) {
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