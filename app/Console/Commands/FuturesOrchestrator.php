<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;
use App\Models\Trade;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Support\Facades\DB;
use Exception;

class FuturesOrchestrator extends Command
{
    protected $signature = 'futures:orchestrate {--user=}';
    protected $description = 'اجرای چرخه حیات، اعمال قوانین و همگام‌سازی SL/TP با استفاده از snapshot در هر اجرا';

    public function handle()
    {
        $this->info('شروع اجرای ترکیبی چرخه حیات، اعمال قوانین و SL/TP...');

        $userOption = $this->option('user');

        if ($userOption) {
            $user = User::find($userOption);
            if (!$user) {
                $this->error("کاربر با شناسه {$userOption} یافت نشد.");
                return 1;
            }
            $this->runForUser($user);
        } else {
            $users = User::whereNotNull('email_verified_at')->get();
            $this->info("پردازش {$users->count()} کاربر تایید شده...");
            foreach ($users as $user) {
                $this->runForUser($user);
            }
        }

        $this->info('اجرای ترکیبی با موفقیت تکمیل شد.');
        return 0;
    }

    private function runForUser(User $user)
    {
        $this->info("پردازش کاربر: {$user->email}");

        $exchanges = UserExchange::where('user_id', $user->id)
            ->where('futures_access', true)
            ->whereNotNull('api_key')
            ->whereNotNull('api_secret')
            ->get();

        foreach ($exchanges as $ue) {
            $this->runForUserExchange($user, $ue);
        }
    }

    private function runForUserExchange(User $user, UserExchange $ue)
    {
        try {
            $this->info("پردازش صرافی {$ue->exchange_name} برای کاربر {$user->email}");

            $exchangeService = ExchangeFactory::create(
                $ue->exchange_name,
                $ue->api_key,
                $ue->api_secret,
                false
            );

            $symbol = $user->selected_market;

            $openOrdersAll = $exchangeService->getOpenOrders(null);
            $openOrdersAll = $openOrdersAll['list'] ?? [];
            $positionsAll = $exchangeService->getPositions(null);
            $positionsAll = $positionsAll['list'] ?? [];

            $conditionalCache = [];
            $getConditional = function(string $sym, bool $refresh = false) use (&$conditionalCache, $exchangeService) {
                if ($refresh || !array_key_exists($sym, $conditionalCache)) {
                    try {
                        $r = $exchangeService->getConditionalOrders($sym);
                        $conditionalCache[$sym] = $r['list'] ?? [];
                    } catch (Exception $e) {
                        $conditionalCache[$sym] = [];
                    }
                }
                return $conditionalCache[$sym];
            };

            $this->syncLifecycleForExchange($exchangeService, $user, $ue);

            if ($user->future_strict_mode) {
                $this->enforceIndependentPnlStops($exchangeService, $user, $ue, $symbol, $openOrdersAll, $positionsAll);
                $this->checkPendingOrders($exchangeService, $ue, $symbol, $openOrdersAll);
                $this->checkFilledOrders($exchangeService, $ue, $symbol, $positionsAll);
                $this->checkForeignOrders($exchangeService, $ue, $symbol, $openOrdersAll);
                $this->purgeOtherSymbolsPositions($exchangeService, $ue, $symbol, $positionsAll);
                $this->syncSltpForExchange($exchangeService, $user, $ue, $openOrdersAll, $positionsAll, $getConditional);
            }
        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$ue->exchange_name} برای کاربر {$user->email}: " . $e->getMessage());
        }
    }

    private function enforceIndependentPnlStops($exchangeService, User $user, UserExchange $ue, string $symbol, array $openOrdersAll, array $positionsAll)
    {
        $openTrades = Trade::where('user_exchange_id', $ue->id)
            ->where('is_demo', false)
            ->whereNull('closed_at')
            ->where('symbol', $symbol)
            ->get();

        if ($openTrades->isEmpty()) { return; }

        foreach ($openTrades as $t) {
            $pos = null;
            foreach ($positionsAll as $p) {
                $ps = strtolower($p['side'] ?? $p['positionSide'] ?? '');
                if (($p['symbol'] ?? null) === $t->symbol && $ps === strtolower($t->side) && (float)($p['size'] ?? 0) > 0) {
                    $pos = $p; break;
                }
            }
            if (!$pos) { continue; }

            $ratio = $this->getPositionPnlRatio($pos);
            if ($ratio === null) { continue; }

            if ($ratio <= -0.10) {
                try {
                    DB::transaction(function () use ($exchangeService, $t, $pos) {
                        $closeSide = (strtolower($t->side) === 'buy') ? 'Buy' : 'Sell';
                        $sz = (float)($pos['size'] ?? 0);
                        $exchangeService->closePosition($t->symbol, $closeSide, $sz);
                        $t->closed_at = now();
                        $t->save();
                    });
                    $this->info("    بستن موقعیت به دلیل زیان بزرگ: {$t->symbol} (PnL=" . round($ratio*100,2) . "%)");
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت به دلیل زیان {$t->symbol}: " . $e->getMessage());
                }
                continue;
            }

            if ($ratio >= 0.10) {
                $avgEntry = (float)($pos['avgPrice'] ?? $pos['entryPrice'] ?? $pos['avgCostPrice'] ?? 0);
                $isLong = (strtolower($t->side) === 'buy');
                $opp = $isLong ? 'sell' : 'buy';
                $hasReduceOnlyTp = false;
                foreach ($openOrdersAll as $oo) {
                    $reduceRaw = $oo['reduceOnly'] ?? false;
                    $isReduce = ($reduceRaw === true || $reduceRaw === 'true' || $reduceRaw === 1 || $reduceRaw === '1');
                    $side = strtolower($oo['side'] ?? '');
                    $otype = strtoupper((string)($oo['orderType'] ?? ($oo['type'] ?? '')));
                    $price = (float)($oo['price'] ?? $oo['triggerPrice'] ?? $oo['stopPrice'] ?? $oo['stopPx'] ?? 0);
                    if (($oo['symbol'] ?? $t->symbol) !== $t->symbol) { continue; }
                    if (!$isReduce) { continue; }
                    if ($side !== $opp) { continue; }
                    if (!in_array($otype, ['LIMIT','TAKE_PROFIT','TAKE_PROFIT_MARKET'])) { continue; }
                    if ($avgEntry > 0) {
                        if ($isLong && $price <= $avgEntry) { continue; }
                        if (!$isLong && $price >= $avgEntry) { continue; }
                    }
                    $hasReduceOnlyTp = true; break;
                }

                if (!$hasReduceOnlyTp) {
                    try {
                        DB::transaction(function () use ($exchangeService, $t, $pos) {
                            $closeSide = (strtolower($t->side) === 'buy') ? 'Buy' : 'Sell';
                            $sz = (float)($pos['size'] ?? 0);
                            $exchangeService->closePosition($t->symbol, $closeSide, $sz);
                            $t->closed_at = now();
                            $t->save();
                        });
                        $this->info("    بستن موقعیت به دلیل سود ≥10% بدون سفارش برداشت سود: {$t->symbol}");
                    } catch (Exception $e) {
                        $this->warn("    خطا در بستن موقعیت به دلیل سود بدون TP {$t->symbol}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function checkPendingOrders($exchangeService, UserExchange $ue, string $symbol, array $openOrdersAll)
    {
        $this->info("  بررسی سفارشات در انتظار...");
        $pendingOrders = Order::where('user_exchange_id', $ue->id)
            ->where('is_demo', false)
            ->where('status', 'pending')
            ->where('is_locked', false)
            ->get();

        $map = [];
        foreach ($openOrdersAll as $o) { $map[$o['orderId']] = $o; }

        foreach ($pendingOrders as $dbOrder) {
            $exchangeOrder = $map[$dbOrder->order_id] ?? null;
            if (!$exchangeOrder) { continue; }

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
                    $this->info("    حذف سفارش تغییر یافته: {$dbOrder->order_id}");
                } catch (Exception $e) {
                    $this->warn("    خطا در حذف سفارش {$dbOrder->order_id}: " . $e->getMessage());
                }
                continue;
            }
        }
    }

    private function checkFilledOrders($exchangeService, UserExchange $ue, string $symbol, array $positionsAll)
    {
        $this->info("  بررسی معاملات باز (موقعیت‌های فعال)...");
        $openTrades = Trade::where('user_exchange_id', $ue->id)
            ->where('is_demo', false)
            ->whereNull('closed_at')
            ->where('symbol', $symbol)
            ->get();

        foreach ($openTrades as $dbTrade) {
            $matchingPosition = null;
            foreach ($positionsAll as $position) {
                if (($position['symbol'] ?? null) === $dbTrade->symbol &&
                    strtolower($position['side'] ?? '') === strtolower($dbTrade->side) &&
                    (float)($position['size'] ?? 0) > 0) { $matchingPosition = $position; break; }
            }
            if (!$matchingPosition) { continue; }

            $pnlRatio = $this->getPositionPnlRatio($matchingPosition);
            if ($pnlRatio !== null && $pnlRatio <= -0.10) {
                try {
                    DB::transaction(function () use ($exchangeService, $dbTrade, $matchingPosition) {
                        $closeSide = (strtolower($dbTrade->side) === 'buy') ? 'Buy' : 'Sell';
                        $exchangeSizeForClose = (float)($matchingPosition['size'] ?? 0);
                        $exchangeService->closePosition($dbTrade->symbol, $closeSide, $exchangeSizeForClose);
                        $dbTrade->closed_at = now();
                        $dbTrade->save();
                    });
                    $this->info("    بستن موقعیت به دلیل زیان بزرگ: {$dbTrade->symbol} (PnL=" . round($pnlRatio*100,2) . "%)");
                    continue;
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت به دلیل زیان {$dbTrade->symbol}: " . $e->getMessage());
                }
            }

            $exchangeSize  = (float)($matchingPosition['size'] ?? 0);
            $exchangePrice = (float)($matchingPosition['avgPrice'] ?? 0);
            $relatedOrder = $dbTrade->order;
            $orderBaselineSize  = (float)($relatedOrder->amount ?? $dbTrade->qty ?? 0);
            $orderBaselinePrice = (float)($relatedOrder->entry_price ?? $dbTrade->avg_entry_price ?? 0);
            $sizeBase  = max(abs($orderBaselineSize), 1e-9);
            $priceBase = max(abs($orderBaselinePrice), 1e-9);
            $sizeDiffPct  = ($sizeBase > 0) ? (abs($exchangeSize - $orderBaselineSize) / $sizeBase) : 0.0;
            $priceDiffPct = ($priceBase > 0) ? (abs($exchangePrice - $orderBaselinePrice) / $priceBase) : 0.0;
            $tolerance = 0.002;
            if ($sizeDiffPct > $tolerance || $priceDiffPct > $tolerance) {
                try {
                    DB::transaction(function () use ($exchangeService, $dbTrade, $exchangeSize) {
                        $closeSide = (strtolower($dbTrade->side) === 'buy') ? 'Buy' : 'Sell';
                        $exchangeService->closePosition($dbTrade->symbol, $closeSide, (float)$exchangeSize);
                        $dbTrade->closed_at = now();
                        $dbTrade->save();
                    });
                    $this->info("    بستن موقعیت تغییر یافته: {$dbTrade->symbol}");
                } catch (Exception $e) {
                    $this->warn("    خطا در بستن موقعیت {$dbTrade->symbol}: " . $e->getMessage());
                }
            } else if ($sizeDiffPct > 0 || $priceDiffPct > 0) {
                $dbTrade->qty = $exchangeSize;
                $dbTrade->avg_entry_price = $exchangePrice;
                $dbTrade->save();
                $this->info("    به‌روزرسانی موقعیت با اختلاف جزئی: {$dbTrade->symbol}");
            }
        }
    }

    private function checkForeignOrders($exchangeService, UserExchange $ue, string $symbol, array $openOrdersAll)
    {
        $this->info("  بررسی سفارشات خارجی...");

        $ourOrderIds = Order::where('user_exchange_id', $ue->id)
            ->where('is_demo', false)
            ->whereIn('status', ['pending', 'filled'])
            ->pluck('order_id')
            ->filter()
            ->toArray();

        $openTrades = Trade::where('user_exchange_id', $ue->id)
            ->where('is_demo', false)
            ->whereNull('closed_at')
            ->with('order')
            ->where('symbol', $symbol)
            ->get();

        $purgeOtherSymbolsRaw = env('FUTURES_STRICT_PURGE_OTHER_SYMBOLS', false);
        $purgeOtherSymbols = ($purgeOtherSymbolsRaw === true || $purgeOtherSymbolsRaw === 'true' || $purgeOtherSymbolsRaw === 1 || $purgeOtherSymbolsRaw === '1');

        foreach ($openOrdersAll as $eo) {
            $orderId = $eo['orderId'];
            $orderSymbol = $eo['symbol'] ?? $symbol;

            if ($orderSymbol !== $symbol) {
                if ($purgeOtherSymbols && !in_array($orderId, $ourOrderIds)) {
                    try {
                        $exchangeService->cancelOrderWithSymbol($orderId, $orderSymbol);
                        $this->info("    حذف سفارش خارجی نماد دیگر: {$orderId} ({$orderSymbol})");
                    } catch (Exception $e) {
                        $this->warn("    خطا در حذف سفارش خارجی {$orderId} ({$orderSymbol}): " . $e->getMessage());
                    }
                }
                continue;
            }

            if (in_array($orderId, $ourOrderIds)) { continue; }

            if ($this->isValidTpSlOrder($eo, $openTrades)) {
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

    private function purgeOtherSymbolsPositions($exchangeService, UserExchange $ue, string $selectedSymbol, array $positionsAll)
    {
        $purgeOtherSymbolsRaw = env('FUTURES_STRICT_PURGE_OTHER_SYMBOLS', false);
        $purgeOtherSymbols = ($purgeOtherSymbolsRaw === true || $purgeOtherSymbolsRaw === 'true' || $purgeOtherSymbolsRaw === 1 || $purgeOtherSymbolsRaw === '1');
        if (!$purgeOtherSymbols) { return; }

        $this->info("  پاکسازی موقعیت‌های نمادهای دیگر در حالت سخت‌گیرانه...");
        foreach ($positionsAll as $position) {
            $posSymbol = $position['symbol'] ?? null;
            $posSize = (float)($position['size'] ?? 0);
            $rawSide = strtolower($position['side'] ?? $position['positionSide'] ?? '');
            $closeSide = ($rawSide === 'buy' || $rawSide === 'long') ? 'Buy' : 'Sell';
            if (!$posSymbol || $posSymbol === $selectedSymbol) { continue; }
            if ($posSize <= 0) { continue; }
            try {
                DB::transaction(function () use ($exchangeService, $ue, $posSymbol, $closeSide, $posSize) {
                    $exchangeService->closePosition($posSymbol, $closeSide, $posSize);
                    $relatedTrades = Trade::where('user_exchange_id', $ue->id)
                        ->where('is_demo', false)
                        ->whereNull('closed_at')
                        ->where('symbol', $posSymbol)
                        ->get();
                    foreach ($relatedTrades as $t) { $t->closed_at = now(); $t->save(); }
                });
                $this->info("    بستن موقعیت نماد دیگر: {$posSymbol} (اندازه={$posSize})");
            } catch (Exception $e) {
                $this->warn("    خطا در بستن موقعیت نماد دیگر {$posSymbol}: " . $e->getMessage());
            }
        }
    }

    private function isValidTpSlOrder(array $exchangeOrder, $openTrades): bool
    {
        $reduceRaw = $exchangeOrder['reduceOnly'] ?? false;
        $isReduceOnly = ($reduceRaw === true || $reduceRaw === 'true' || $reduceRaw === 1 || $reduceRaw === '1');
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
        if (!$isReduceOnly || $orderQty <= 0 || $orderPrice <= 0) { return false; }
        $qtyEps = 1e-6; $priceTol = 0.01;
        foreach ($openTrades as $trade) {
            $order = $trade->order;
            if (!$order) { continue; }
            $registeredSl = (float)($order->sl ?? 0);
            $registeredTp = (float)($order->tp ?? 0);
            $registeredSide = strtolower($trade->side);
            $registeredQty = (float)$trade->qty;
            $registeredSymbol = $trade->symbol;
            $expectedOppositeSide = ($registeredSide === 'buy') ? 'sell' : 'buy';
            if ($orderSymbol !== null && $registeredSymbol !== $orderSymbol) { continue; }
            if ($orderSide !== $expectedOppositeSide) { continue; }
            if (abs($orderQty - $registeredQty) > $qtyEps) { continue; }
            $matchesSl = ($registeredSl > 0) && (abs($orderPrice - $registeredSl) <= $priceTol);
            $matchesTp = ($registeredTp > 0) && (abs($orderPrice - $registeredTp) <= $priceTol);
            if ($matchesSl || $matchesTp) { return true; }
        }
        return false;
    }

    private function getPositionPnlRatio(array $position): ?float
    {
        $uplRatio = $position['uplRatio'] ?? $position['upl'] ?? null;
        if ($uplRatio !== null) { return (float)$uplRatio; }
        $roe = $position['roe'] ?? null;
        if ($roe !== null) {
            $val = (float)$roe; if (abs($val) > 2) { $val = $val / 100.0; } return $val;
        }
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

    private function syncSltpForExchange($exchangeService, User $user, UserExchange $ue, array $openOrdersAll, array $positionsAll, $getConditional)
    {
        $openTrades = Trade::where('user_exchange_id', $ue->id)
            ->where('is_demo', false)
            ->whereNull('closed_at')
            ->get();
        if ($openTrades->isEmpty()) { return; }

        foreach ($openTrades as $trade) {
            $position = null;
            foreach ($positionsAll as $p) {
                if (($p['symbol'] ?? '') !== $trade->symbol) { continue; }
                if ((float)($p['size'] ?? 0) == 0.0) { continue; }
                $positionSide = strtolower($p['side'] ?? '');
                $tradeSide = strtolower($trade->side ?? '');
                if ($positionSide === $tradeSide) { $position = $p; break; }
            }
            if (!$position) { continue; }

            $order = $trade->order; if (!$order) { continue; }

            if ($order->sl) {
                try {
                    $conditionalOrders = $getConditional($order->symbol);
                    $positionSide = strtolower($position['side'] ?? '');
                    $expectedSlSide = $positionSide === 'buy' ? 'Sell' : 'Buy';
                    $positionSize = abs((float)($position['size'] ?? 0));
                    $targetSl = (float)$order->sl;
                    $hasValidSl = false;
                    foreach ($conditionalOrders as $co) {
                        $isReduceOnly = ($co['reduceOnly'] ?? false) === true || ($co['reduceOnly'] ?? '') === 'true';
                        $side = ucfirst(strtolower($co['side'] ?? ''));
                        $triggerPrice = (float)($co['triggerPrice'] ?? $co['stopPrice'] ?? 0);
                        $orderType = strtoupper((string)($co['orderType'] ?? ($co['type'] ?? '')));
                        $stopOrderType = strtolower((string)($co['stopOrderType'] ?? ''));
                        $isStopLike = in_array($orderType, ['STOP', 'STOP_MARKET']) || in_array($stopOrderType, ['stop','stoploss','sl']);
                        if ($isReduceOnly && $isStopLike && $side === $expectedSlSide && abs($triggerPrice - $targetSl) < 0.0001) { $hasValidSl = true; break; }
                    }
                    if (!$hasValidSl) {
                        foreach ($conditionalOrders as $co) {
                            $isReduceOnly = ($co['reduceOnly'] ?? false) === true || ($co['reduceOnly'] ?? '') === 'true';
                            $orderType = strtoupper((string)($co['orderType'] ?? ($co['type'] ?? '')));
                            $stopOrderType = strtolower((string)($co['stopOrderType'] ?? ''));
                            $side = ucfirst(strtolower($co['side'] ?? ''));
                            $isStopLike = in_array($orderType, ['STOP', 'STOP_MARKET']) || in_array($stopOrderType, ['stop','stoploss','sl']);
                            if ($isReduceOnly && $isStopLike && $side === $expectedSlSide) {
                                try { $exchangeService->cancelOrderWithSymbol($co['orderId'], $order->symbol); } catch (Exception $e) { $this->warn("لغو سفارش شرطی {$co['orderId']} با خطا مواجه شد: " . $e->getMessage()); }
                            }
                        }
                        $positionIdx = method_exists($exchangeService, 'getPositionIdx') ? $exchangeService->getPositionIdx($position) : 0;
                        $payload = [
                            'category' => 'linear',
                            'symbol' => $order->symbol,
                            'side' => $expectedSlSide,
                            'orderType' => 'Market',
                            'qty' => (string)$positionSize,
                            'triggerPrice' => (string)$targetSl,
                            'triggerBy' => 'LastPrice',
                            'triggerDirection' => $positionSide === 'buy' ? 2 : 1,
                            'reduceOnly' => true,
                            'closeOnTrigger' => true,
                            'positionIdx' => $positionIdx,
                            'timeInForce' => 'GTC',
                            'orderLinkId' => 'sl_sync_' . time() . '_' . rand(1000, 9999),
                        ];
                        $exchangeService->createOrder($payload);
                        $this->info("Stop Loss برای سفارش {$order->order_id} در قیمت {$order->sl} تنظیم شد");
                        $getConditional($order->symbol, true);
                    }
                } catch (Exception $e) { $this->warn("خطا در همگام‌سازی Stop Loss برای سفارش {$order->order_id}: " . $e->getMessage()); }
            }

            if ($order->tp) {
                try {
                    $positionSide = strtolower($position['side'] ?? '');
                    $expectedTpSide = $positionSide === 'buy' ? 'Sell' : 'Buy';
                    $positionSize = abs((float)($position['size'] ?? 0));
                    $targetTp = (float)$order->tp;
                    $hasValidTp = false;
                    foreach ($openOrdersAll as $oo) {
                        if (($oo['symbol'] ?? '') !== $order->symbol) { continue; }
                        $isReduceOnly = ($oo['reduceOnly'] ?? false) === true || ($oo['reduceOnly'] ?? '') === 'true';
                        $side = ucfirst(strtolower($oo['side'] ?? ''));
                        $orderType = strtoupper((string)($oo['orderType'] ?? ($oo['type'] ?? '')));
                        $price = (float)($oo['price'] ?? 0);
                        if ($isReduceOnly && $side === $expectedTpSide && in_array($orderType, ['LIMIT']) && abs($price - $targetTp) < 0.0001) { $hasValidTp = true; break; }
                    }
                    if (!$hasValidTp) {
                        foreach ($openOrdersAll as $oo) {
                            if (($oo['symbol'] ?? '') !== $order->symbol) { continue; }
                            $isReduceOnly = ($oo['reduceOnly'] ?? false) === true || ($oo['reduceOnly'] ?? '') === 'true';
                            $side = ucfirst(strtolower($oo['side'] ?? ''));
                            $orderType = strtoupper((string)($oo['orderType'] ?? ($oo['type'] ?? '')));
                            if ($isReduceOnly && $side === $expectedTpSide && in_array($orderType, ['LIMIT'])) {
                                try { $exchangeService->cancelOrderWithSymbol($oo['orderId'], $order->symbol); } catch (Exception $e) { $this->warn("لغو سفارش TP {$oo['orderId']} با خطا مواجه شد: " . $e->getMessage()); }
                            }
                        }
                        $positionIdx = method_exists($exchangeService, 'getPositionIdx') ? $exchangeService->getPositionIdx($position) : 0;
                        $payload = [
                            'category' => 'linear',
                            'symbol' => $order->symbol,
                            'side' => $expectedTpSide,
                            'orderType' => 'Limit',
                            'qty' => (string)$positionSize,
                            'price' => (string)$targetTp,
                            'reduceOnly' => true,
                            'positionIdx' => $positionIdx,
                            'timeInForce' => 'GTC',
                            'orderLinkId' => 'tp_sync_' . time() . '_' . rand(1000, 9999),
                        ];
                        $exchangeService->createOrder($payload);
                        $this->info("Take Profit برای سفارش {$order->order_id} در قیمت {$order->tp} تنظیم شد");
                    }
                } catch (Exception $e) { $this->warn("خطا در همگام‌سازی Take Profit برای سفارش {$order->order_id}: " . $e->getMessage()); }
            }
        }
    }

    private function syncLifecycleForExchange($exchangeService, User $user, UserExchange $ue)
    {
        $oldestOrder = Order::where('user_exchange_id', $ue->id)
            ->where('is_demo', false)
            ->whereIn('status', ['pending', 'filled'])
            ->where(function($q) {
                $q->whereHas('trade', function($t) { $t->whereNull('closed_at'); })
                  ->orWhereDoesntHave('trade');
            })
            ->orderBy('created_at', 'asc')
            ->first();

        $startTime = null;
        if ($oldestOrder) {
            try {
                $startTime = $oldestOrder->created_at->subMinutes(5)->timestamp * 1000;
                $exchangeOrders = $exchangeService->getOrderHistory(null, 100, $startTime);
                $orders = $exchangeOrders['list'] ?? [];
                $this->info("دریافت " . count($orders) . " سفارش از صرافی {$ue->exchange_name}");
                foreach ($orders as $exchangeOrder) { $this->syncExchangeOrderToDatabase($exchangeOrder, $ue); }
                $this->syncPnlRecords($exchangeService, $ue);
            } catch (Exception $e) {
                $this->error("خطا در همگام‌سازی سفارشات از صرافی: " . $e->getMessage());
            }
        } else { $this->info("سفارشی یافت نشد"); }
        $this->verifyClosedTradesSynchronization($exchangeService, $ue);
        $this->checkRecentTradesForBans($ue);
    }

    private function syncExchangeOrderToDatabase($exchangeOrder, UserExchange $ue)
    {
        $orderId = $this->extractOrderId($exchangeOrder, $ue->exchange_name);
        if (!$orderId) { return; }
        if ($this->isTpSlOrClosing($exchangeOrder, $ue->exchange_name)) { return; }
        $order = Order::where('user_exchange_id', $ue->id)
            ->where('is_demo', false)
            ->where('order_id', $orderId)
            ->whereNotIn('status', ['expired'])
            ->first();
        $newStatus = $this->mapExchangeStatus($this->extractOrderStatus($exchangeOrder, $ue->exchange_name));
        if ($order && $order->is_locked) { if (!in_array($newStatus, ['filled','canceled','expired','closed'])) { return; } }
        if ($order) {
            DB::transaction(function () use ($order, $newStatus, $exchangeOrder, $ue, $orderId) {
                if ($order->status !== $newStatus) {
                    $order->status = $newStatus;
                    $filledQty = $this->extractFilledQuantity($exchangeOrder, $ue->exchange_name);
                    if ($filledQty !== null) { $order->filled_quantity = $filledQty; }
                    $avgPrice = $this->extractAveragePrice($exchangeOrder, $ue->exchange_name);
                    if ($avgPrice !== null) { $order->average_price = $avgPrice; }
                    if (in_array($newStatus, ['filled','canceled','expired','closed'])) { $order->closed_at = now(); }
                    if ($newStatus === 'filled') { $order->filled_at = now(); }
                    $order->save();
                    $this->info("وضعیت سفارش {$orderId} به {$newStatus} تغییر یافت");
                    if ($newStatus === 'filled') {
                        $symbol = $this->extractSymbol($exchangeOrder, $ue->exchange_name);
                        $side = $this->extractSide($exchangeOrder, $ue->exchange_name);
                        $qty = $this->extractFilledQuantity($exchangeOrder, $ue->exchange_name);
                        $avgPrice = $this->extractAveragePrice($exchangeOrder, $ue->exchange_name);
                        if ($symbol && $side && $qty !== null && $avgPrice !== null) {
                            $existingOpen = Trade::where('user_exchange_id', $ue->id)
                                ->where('is_demo', false)
                                ->where('order_id', $order->order_id)
                                ->whereNull('closed_at')
                                ->first();
                            if ($existingOpen) {
                                $existingOpen->qty = (float)$qty;
                                $existingOpen->avg_entry_price = (float)$avgPrice;
                                $existingOpen->save();
                                $this->info("معامله باز برای سفارش {$orderId} به‌روزرسانی شد");
                            } else {
                                Trade::create([
                                    'user_exchange_id' => $ue->id,
                                    'is_demo' => false,
                                    'symbol' => $symbol,
                                    'side' => $side,
                                    'order_type' => 'Market',
                                    'leverage' => 1.0,
                                    'qty' => (float)$qty,
                                    'avg_entry_price' => (float)$avgPrice,
                                    'avg_exit_price' => 0,
                                    'pnl' => 0,
                                    'order_id' => $order->order_id,
                                    'closed_at' => null,
                                ]);
                                $this->info("معامله باز برای سفارش {$orderId} ثبت شد");
                            }
                        }
                    }
                }
            });
        }
    }

    private function syncPnlRecords($exchangeService, UserExchange $ue)
    {
        try {
            $positionsRaw = $exchangeService->getPositions();
            $positions = $this->normalizePositionsList($ue->exchange_name, $positionsRaw);
            $normalized = [];
            foreach ($positions as $rawPosition) {
                if (!is_array($rawPosition)) { continue; }
                $p = $this->mapRawPositionToCommon($ue->exchange_name, $rawPosition);
                if (!$p) { continue; }
                if (empty($p['size']) || (float)$p['size'] == 0.0) { continue; }
                $normalized[] = $p;
            }
            $openTrades = Trade::where('user_exchange_id', $ue->id)
                ->where('is_demo', false)
                ->whereNull('closed_at')
                ->get();
            foreach ($openTrades as $trade) {
                DB::transaction(function () use ($trade, $normalized, $exchangeService, $ue) {
                    $matchedPosition = null;
                    foreach ($normalized as $p) {
                        if (($p['symbol'] ?? null) === $trade->symbol
                            && isset($p['entryPrice']) && (float)$p['entryPrice'] == (float)$trade->avg_entry_price
                            && isset($p['size']) && (float)$p['size'] == (float)$trade->qty) {
                            $matchedPosition = $p; break;
                        }
                    }
                    if ($matchedPosition) {
                        if (array_key_exists('leverage', $matchedPosition) && $matchedPosition['leverage'] !== null) { $trade->leverage = $matchedPosition['leverage']; }
                        if (array_key_exists('unrealizedPnl', $matchedPosition) && $matchedPosition['unrealizedPnl'] !== null) { $trade->pnl = $matchedPosition['unrealizedPnl']; }
                        $trade->updated_at = now();
                        $trade->save();
                        return;
                    }
                    $symbol = $trade->symbol;
                    $closedRaw = $exchangeService->getClosedPnl($symbol, 100, null);
                    $closedList = $this->normalizeClosedPnl($ue->exchange_name, $closedRaw);
                    $matchedClosed = null;
                    foreach ($closedList as $c) {
                        $idMatch = isset($c['orderId']) && $trade->order_id && (string)$c['orderId'] === (string)$trade->order_id;
                        $fieldsMatch = (($c['symbol'] ?? null) === $symbol)
                            && isset($c['qty']) && (float)$c['qty'] == (float)$trade->qty
                            && isset($c['avgEntryPrice']) && (float)$c['avgEntryPrice'] == (float)$trade->avg_entry_price;
                        if ($idMatch || $fieldsMatch) { $matchedClosed = $c; break; }
                    }
                    if ($matchedClosed) {
                        if (array_key_exists('avgExitPrice', $matchedClosed) && $matchedClosed['avgExitPrice'] !== null) { $trade->avg_exit_price = $matchedClosed['avgExitPrice']; }
                        if (array_key_exists('realizedPnl', $matchedClosed) && $matchedClosed['realizedPnl'] !== null) { $trade->pnl = $matchedClosed['realizedPnl']; }
                        $trade->closed_at = now();
                        $trade->synchronized = 1;
                        $trade->updated_at = now();
                        $trade->save();
                    }
                });
            }
        } catch (Exception $e) { $this->warn("خطا در همگام‌سازی سوابق PnL: " . $e->getMessage()); }
    }

    private function verifyClosedTradesSynchronization($exchangeService, UserExchange $ue)
    {
        try {
            $closedTrades = Trade::where('user_exchange_id', $ue->id)
                ->where('is_demo', false)
                ->whereNotNull('closed_at')
                ->get();
            foreach ($closedTrades as $trade) {
                $trade->synchronized = $trade->synchronized ?? 1;
                $trade->save();
            }
        } catch (Exception $e) { $this->warn("خطا در تأیید همگام‌سازی معاملات بسته: " . $e->getMessage()); }
    }

    private function checkRecentTradesForBans(UserExchange $ue)
    {
        try { $this->info("بررسی بن‌ها بر اساس معاملات اخیراً بسته شده برای صرافی {$ue->exchange_name}"); } catch (Exception $e) {}
    }

    private function extractOrderId($exchangeOrder, string $exchangeName): ?string
    { switch ($exchangeName) { case 'binance': return $exchangeOrder['orderId'] ?? null; case 'bybit': return $exchangeOrder['orderId'] ?? null; case 'bingx': return $exchangeOrder['orderId'] ?? null; default: return null; } }
    private function extractOrderStatus($exchangeOrder, string $exchangeName): string
    { switch ($exchangeName) { case 'binance': return $exchangeOrder['status'] ?? 'UNKNOWN'; case 'bybit': return $exchangeOrder['orderStatus'] ?? 'UNKNOWN'; case 'bingx': return $exchangeOrder['status'] ?? 'UNKNOWN'; default: return 'UNKNOWN'; } }
    private function extractFilledQuantity($exchangeOrder, string $exchangeName): ?float
    { switch ($exchangeName) { case 'binance': return isset($exchangeOrder['executedQty']) ? (float)$exchangeOrder['executedQty'] : null; case 'bybit': return isset($exchangeOrder['cumExecQty']) ? (float)$exchangeOrder['cumExecQty'] : null; case 'bingx': return isset($exchangeOrder['executedQty']) ? (float)$exchangeOrder['executedQty'] : null; default: return null; } }
    private function extractAveragePrice($exchangeOrder, string $exchangeName): ?float
    { switch ($exchangeName) { case 'binance': return isset($exchangeOrder['avgPrice']) ? (float)$exchangeOrder['avgPrice'] : null; case 'bybit': return isset($exchangeOrder['avgPrice']) ? (float)$exchangeOrder['avgPrice'] : null; case 'bingx': return isset($exchangeOrder['avgPrice']) ? (float)$exchangeOrder['avgPrice'] : null; default: return null; } }
    private function extractSymbol($exchangeOrder, string $exchangeName): ?string
    { return $exchangeOrder['symbol'] ?? null; }
    private function extractSide($exchangeOrder, string $exchangeName): ?string
    { switch ($exchangeName) { case 'binance': return $exchangeOrder['side'] ?? null; case 'bybit': return $exchangeOrder['side'] ?? null; case 'bingx': return $exchangeOrder['side'] ?? null; default: return null; } }
    private function mapExchangeStatus($exchangeStatus)
    { $status = strtoupper((string)$exchangeStatus); switch ($status) { case 'NEW': case 'ACTIVE': case 'OPEN': case 'PENDING': return 'pending'; case 'FILLED': return 'filled'; case 'CANCELED': case 'CANCELLED': return 'canceled'; case 'EXPIRED': return 'expired'; default: return 'pending'; } }

    private function normalizePositionsList(string $exchangeName, $positionsRaw): array
    {
        if (is_string($positionsRaw)) { $decoded = json_decode($positionsRaw, true); if (json_last_error() === JSON_ERROR_NONE) { $positionsRaw = $decoded; } }
        $list = null;
        if (is_array($positionsRaw)) {
            if (array_key_exists('list', $positionsRaw)) { $list = $positionsRaw['list']; }
            elseif (array_key_exists('positions', $positionsRaw)) { $list = $positionsRaw['positions']; }
            elseif (array_key_exists('positionList', $positionsRaw)) { $list = $positionsRaw['positionList']; }
            elseif (array_key_exists('data', $positionsRaw)) { $list = $positionsRaw['data']; }
            else { $list = $positionsRaw; }
        } else { $list = $positionsRaw; }
        if (!is_array($list)) { return []; }
        return $list;
    }

    private function mapRawPositionToCommon(string $exchangeName, array $raw): ?array
    {
        $symbol = $raw['symbol'] ?? null; if (!$symbol) { return null; }
        $side = $raw['side'] ?? null; if (!$side && isset($raw['positionSide'])) { $ps = strtoupper((string)$raw['positionSide']); $side = ($ps === 'LONG') ? 'Buy' : (($ps === 'SHORT') ? 'Sell' : null); }
        if (!$side && isset($raw['positionAmt'])) { $amt = (float)$raw['positionAmt']; if ($amt > 0) { $side = 'Buy'; } elseif ($amt < 0) { $side = 'Sell'; } }
        if ($side) { $s = strtoupper((string)$side); if (in_array($s, ['LONG','BUY'])) { $side = 'Buy'; } elseif (in_array($s, ['SHORT','SELL'])) { $side = 'Sell'; } else { $side = null; } }
        $size = null; if (isset($raw['size'])) { $size = (float)$raw['size']; } elseif (isset($raw['positionAmt'])) { $size = abs((float)$raw['positionAmt']); }
        $entryPrice = (float)($raw['entryPrice'] ?? ($raw['avgPrice'] ?? ($raw['avgCostPrice'] ?? 0)));
        $unrealizedPnl = isset($raw['unrealizedPnl']) ? (float)$raw['unrealizedPnl'] : (isset($raw['unRealizedProfit']) ? (float)$raw['unRealizedProfit'] : null);
        $leverage = isset($raw['leverage']) ? (float)$raw['leverage'] : null;
        return [ 'symbol' => $symbol, 'side' => $side, 'size' => $size, 'entryPrice' => $entryPrice, 'unrealizedPnl' => $unrealizedPnl, 'leverage' => $leverage ];
    }

    private function normalizeClosedPnl(string $exchangeName, $raw): array
    {
        $list = [];
        if (is_array($raw)) { $list = $raw['list'] ?? ($raw['orders'] ?? $raw); }
        $out = [];
        foreach ($list as $item) {
            $orderId = $item['orderId'] ?? null;
            $symbol = $item['symbol'] ?? null;
            $sideRaw = $item['side'] ?? ($item['positionSide'] ?? null);
            $qty = $item['qty'] ?? ($item['size'] ?? null);
            $avgEntry = $item['avgEntryPrice'] ?? ($item['entryPrice'] ?? null);
            $avgExit = $item['avgExitPrice'] ?? ($item['exitPrice'] ?? null);
            $pnl = $item['realizedPnl'] ?? ($item['pnl'] ?? 0);
            $closedAt = $item['updatedTime'] ?? ($item['createdTime'] ?? ($item['closedAt'] ?? null));
            $side = null; if ($sideRaw !== null) { $s = strtolower((string)$sideRaw); if (in_array($s, ['buy','long'])) { $side = 'Buy'; } elseif (in_array($s, ['sell','short'])) { $side = 'Sell'; } else { $side = $sideRaw; } }
            if (!$orderId || !$symbol) { continue; }
            $out[] = [ 'orderId' => $orderId, 'symbol' => $symbol, 'side' => $side, 'qty' => $qty !== null ? (float)$qty : null, 'avgEntryPrice' => $avgEntry !== null ? (float)$avgEntry : null, 'avgExitPrice' => $avgExit !== null ? (float)$avgExit : null, 'realizedPnl' => (float)$pnl, 'closedAt' => $closedAt ? (int)$closedAt : null ];
        }
        return $out;
    }

    private function isTpSlOrClosing($exchangeOrder, string $exchangeName): bool
    { $reduceOnly = ($exchangeOrder['reduceOnly'] ?? '') === true || ($exchangeOrder['reduceOnly'] ?? '') === 'true'; $type = strtoupper((string)($exchangeOrder['orderType'] ?? ($exchangeOrder['type'] ?? ''))); if ($reduceOnly && in_array($type, ['LIMIT','MARKET','STOP','STOP_MARKET','TAKE_PROFIT','TAKE_PROFIT_MARKET'])) { return true; } return false; }
}

