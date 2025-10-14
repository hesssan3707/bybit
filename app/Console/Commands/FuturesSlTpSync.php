<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserExchange;
use App\Models\Order;
use App\Models\Trade;
use App\Services\Exchanges\ExchangeFactory;
use Exception;

class FuturesSlTpSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'futures:sync-sltp {--user=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'همگام‌سازی سطوح Stop Loss و Take Profit برای کاربران در حالت سخت‌گیرانه';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('شروع همگام‌سازی Stop Loss و Take Profit...');

        $userOption = $this->option('user');

        if ($userOption) {
            $user = User::find($userOption);
            if (!$user) {
                $this->error("کاربر با شناسه {$userOption} یافت نشد.");
                return 1;
            }
            $this->syncForUser($user);
        } else {
            $this->syncForAllUsers();
        }

        $this->info('همگام‌سازی Stop Loss و Take Profit با موفقیت تکمیل شد.');
        return 0;
    }

    /**
     * Sync SL/TP for all users in strict mode
     */
    private function syncForAllUsers()
    {
        // Find all users who are in strict mode
        $users = User::where('future_strict_mode', true)->get();
        
        $this->info("پردازش {$users->count()} کاربر در حالت سخت‌گیرانه...");

        foreach ($users as $user) {
            $this->syncForUser($user);
        }
    }

    /**
     * Sync SL/TP for a specific user
     */
    private function syncForUser(User $user)
    {
        $this->info("پردازش کاربر: {$user->email}");

        // Get all user exchanges for this user
        $userExchanges = UserExchange::where('user_id', $user->id)
            ->where('futures_access', true)
            ->get();

        foreach ($userExchanges as $userExchange) {
            $this->syncForUserExchange($user, $userExchange);
        }
    }

    /**
     * Sync SL/TP for a specific user exchange
     */
    private function syncForUserExchange(User $user, UserExchange $userExchange)
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

            // Get all NOT-CLOSED trades for this user exchange (real mode)
            $openTrades = Trade::where('user_exchange_id', $userExchange->id)
                ->where('is_demo', false)
                ->whereNull('closed_at')
                ->get();

            if ($openTrades->isEmpty()) {
                $this->info("هیچ معامله بازی برای کاربر {$user->email} در صرافی {$userExchange->exchange_name} یافت نشد");
                return;
            }

            // Get current positions from exchange and normalize
            $positionsRaw = $exchangeService->getPositions();
            $positions = $this->normalizePositions($positionsRaw, $userExchange->exchange_name);

            foreach ($openTrades as $trade) {
                // Find corresponding open position for this trade
                $position = $this->findPositionForTrade($positions, $trade);
                
                if (!$position) {
                    continue; // No open position for this trade
                }

                // Get the originating order for SL/TP definitions
                $order = $trade->order;
                if (!$order) {
                    // Without the original order record, we cannot read SL/TP targets
                    continue;
                }

                // Check and sync Stop Loss
                $this->syncStopLoss($exchangeService, $order, $position);

                // Check and sync Take Profit
                $this->syncTakeProfit($exchangeService, $order, $position);
            }

        } catch (Exception $e) {
            $this->error("خطا در پردازش صرافی {$userExchange->exchange_name} برای کاربر {$user->email}: " . $e->getMessage());
        }
    }

    /**
     * Find position that corresponds to the given trade
     */
    private function findPositionForTrade(array $positions, Trade $trade)
    {
        foreach ($positions as $position) {
            // Check if symbol matches
            if (($position['symbol'] ?? '') !== $trade->symbol) {
                continue;
            }

            // Check if position has size (is open)
            if ((float)($position['size'] ?? 0) == 0.0) {
                continue;
            }

            // Check if side matches
            $positionSide = strtolower($position['side'] ?? '');
            $tradeSide = strtolower($trade->side ?? '');
            
            if ($positionSide === $tradeSide) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Normalize positions across exchanges to a common structure
     * Each normalized position will include: symbol, side (buy/sell), size, and optional positionSide/positionIdx
     */
    private function normalizePositions($positionsResponse, string $exchangeName): array
    {
        $normalized = [];

        switch (strtolower($exchangeName)) {
            case 'bybit':
                $list = $positionsResponse['list'] ?? ($positionsResponse['result']['list'] ?? []);
                foreach ($list as $p) {
                    $size = isset($p['size']) ? (float)$p['size'] : 0.0;
                    $side = strtolower($p['side'] ?? '');
                    if ($size == 0.0 || !in_array($side, ['buy', 'sell'])) {
                        continue;
                    }
                    $normalized[] = [
                        'symbol' => $p['symbol'] ?? '',
                        'side' => $side,
                        'size' => abs($size),
                        'positionIdx' => isset($p['positionIdx']) ? (int)$p['positionIdx'] : null,
                    ];
                }
                break;

            case 'binance':
                $list = $positionsResponse['list'] ?? [];
                foreach ($list as $p) {
                    $amt = isset($p['positionAmt']) ? (float)$p['positionAmt'] : 0.0;
                    $posSide = strtoupper($p['positionSide'] ?? 'BOTH');
                    $side = null;
                    if ($posSide === 'LONG') {
                        $side = 'buy';
                    } elseif ($posSide === 'SHORT') {
                        $side = 'sell';
                    } else {
                        // One-way mode: infer from sign of amount
                        if ($amt > 0) $side = 'buy';
                        elseif ($amt < 0) $side = 'sell';
                    }
                    if (!$side || abs($amt) == 0.0) {
                        continue;
                    }
                    $normalized[] = [
                        'symbol' => $p['symbol'] ?? '',
                        'side' => $side,
                        'size' => abs($amt),
                        'positionSide' => $posSide,
                    ];
                }
                break;

            case 'bingx':
                $list = $positionsResponse['list'] ?? [];
                foreach ($list as $p) {
                    // BingX positions often include positionSide (LONG/SHORT/BOTH) and positionAmt
                    $amt = isset($p['positionAmt']) ? (float)$p['positionAmt'] : (isset($p['size']) ? (float)$p['size'] : 0.0);
                    $posSide = strtoupper($p['positionSide'] ?? 'BOTH');
                    $side = null;
                    if ($posSide === 'LONG') {
                        $side = 'buy';
                    } elseif ($posSide === 'SHORT') {
                        $side = 'sell';
                    } else {
                        // One-way mode: infer from sign or size
                        if ($amt > 0) $side = 'buy';
                        elseif ($amt < 0) $side = 'sell';
                    }
                    if (!$side || abs($amt) == 0.0) {
                        continue;
                    }
                    $normalized[] = [
                        'symbol' => $p['symbol'] ?? '',
                        'side' => $side,
                        'size' => abs($amt),
                        'positionSide' => $posSide,
                    ];
                }
                break;

            default:
                // Fallback: try common keys
                $list = $positionsResponse['list'] ?? ($positionsResponse['data'] ?? []);
                foreach ($list as $p) {
                    $size = (float)($p['size'] ?? $p['positionAmt'] ?? 0.0);
                    $sideRaw = strtolower($p['side'] ?? '');
                    $posSide = strtoupper($p['positionSide'] ?? '');
                    $side = in_array($sideRaw, ['buy', 'sell']) ? $sideRaw : null;
                    if (!$side) {
                        if ($posSide === 'LONG') $side = 'buy';
                        elseif ($posSide === 'SHORT') $side = 'sell';
                        elseif ($size > 0) $side = 'buy';
                        elseif ($size < 0) $side = 'sell';
                    }
                    if (!$side || abs($size) == 0.0) continue;
                    $normalized[] = [
                        'symbol' => $p['symbol'] ?? '',
                        'side' => $side,
                        'size' => abs($size),
                        'positionSide' => $posSide ?: null,
                    ];
                }
        }

        return $normalized;
    }

    /**
     * Check and sync Stop Loss
     */
    private function syncStopLoss($exchangeService, Order $order, $position)
    {
        // Check if SL is defined in database
        if (!$order->sl) {
            return; // No SL defined in database
        }

        try {
            // Get current conditional (stop) orders from exchange for this symbol
            $conditionalResult = $exchangeService->getConditionalOrders($order->symbol);
            $conditionalOrders = $conditionalResult['list'] ?? [];

            $positionSide = strtolower($position['side'] ?? '');
            $expectedSlSide = $positionSide === 'buy' ? 'Sell' : 'Buy';
            $positionSize = abs((float)($position['size'] ?? 0));
            $targetSl = (float)$order->sl;

            // Detect if a matching SL conditional order already exists
            $hasValidSl = false;
            foreach ($conditionalOrders as $co) {
                $isReduceOnly = ($co['reduceOnly'] ?? false) === true || ($co['reduceOnly'] ?? '') === 'true';
                $side = ucfirst(strtolower($co['side'] ?? ''));
                $triggerPrice = (float)($co['triggerPrice'] ?? $co['stopPrice'] ?? 0);
                $orderType = strtoupper((string)($co['orderType'] ?? ($co['type'] ?? '')));
                $stopOrderType = strtolower((string)($co['stopOrderType'] ?? ''));

                $isStopLike = in_array($orderType, ['STOP', 'STOP_MARKET']) || in_array($stopOrderType, ['stop', 'stoploss', 'sl']);

                if ($isReduceOnly && $isStopLike && $side === $expectedSlSide && abs($triggerPrice - $targetSl) < 0.0001) {
                    $hasValidSl = true;
                    break;
                }
            }

            if (!$hasValidSl) {
                // Cancel existing SL-style conditional orders for this symbol to avoid duplicates/mismatches
                foreach ($conditionalOrders as $co) {
                    $isReduceOnly = ($co['reduceOnly'] ?? false) === true || ($co['reduceOnly'] ?? '') === 'true';
                    $orderType = strtoupper((string)($co['orderType'] ?? ($co['type'] ?? '')));
                    $stopOrderType = strtolower((string)($co['stopOrderType'] ?? ''));
                    $side = ucfirst(strtolower($co['side'] ?? ''));

                    $isStopLike = in_array($orderType, ['STOP', 'STOP_MARKET']) || in_array($stopOrderType, ['stop', 'stoploss', 'sl']);
                    if ($isReduceOnly && $isStopLike && $side === $expectedSlSide) {
                        try {
                            $exchangeService->cancelOrderWithSymbol($co['orderId'], $order->symbol);
                        } catch (\Exception $e) {
                            $this->warn("لغو سفارش شرطی {$co['orderId']} با خطا مواجه شد: " . $e->getMessage());
                        }
                    }
                }

                // Create new SL conditional order
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
            }

        } catch (Exception $e) {
            $this->warn("خطا در همگام‌سازی Stop Loss برای سفارش {$order->order_id}: " . $e->getMessage());
        }
    }

    /**
     * Check and sync Take Profit
     */
    private function syncTakeProfit($exchangeService, Order $order, $position)
    {
        // Check if TP is defined in database
        if (!$order->tp) {
            return; // No TP defined in database
        }

        try {
            // Get open orders and look for reduce-only TP limit order
            $openOrdersResult = $exchangeService->getOpenOrders($order->symbol);
            $openOrders = $openOrdersResult['list'] ?? [];

            $positionSide = strtolower($position['side'] ?? '');
            $expectedTpSide = $positionSide === 'buy' ? 'Sell' : 'Buy';
            $positionSize = abs((float)($position['size'] ?? 0));
            $targetTp = (float)$order->tp;

            $hasValidTp = false;
            foreach ($openOrders as $oo) {
                $isReduceOnly = ($oo['reduceOnly'] ?? false) === true || ($oo['reduceOnly'] ?? '') === 'true';
                $side = ucfirst(strtolower($oo['side'] ?? ''));
                $orderType = strtoupper((string)($oo['orderType'] ?? ($oo['type'] ?? '')));
                $price = (float)($oo['price'] ?? 0);

                if ($isReduceOnly && $side === $expectedTpSide && in_array($orderType, ['LIMIT']) && abs($price - $targetTp) < 0.0001) {
                    $hasValidTp = true;
                    break;
                }
            }

            if (!$hasValidTp) {
                // Cancel mismatching reduce-only TP orders
                foreach ($openOrders as $oo) {
                    $isReduceOnly = ($oo['reduceOnly'] ?? false) === true || ($oo['reduceOnly'] ?? '') === 'true';
                    $side = ucfirst(strtolower($oo['side'] ?? ''));
                    $orderType = strtoupper((string)($oo['orderType'] ?? ($oo['type'] ?? '')));
                    if ($isReduceOnly && $side === $expectedTpSide && in_array($orderType, ['LIMIT'])) {
                        try {
                            $exchangeService->cancelOrderWithSymbol($oo['orderId'], $order->symbol);
                        } catch (\Exception $e) {
                            $this->warn("لغو سفارش TP {$oo['orderId']} با خطا مواجه شد: " . $e->getMessage());
                        }
                    }
                }

                // Create TP as reduce-only limit order
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

        } catch (Exception $e) {
            $this->warn("خطا در همگام‌سازی Take Profit برای سفارش {$order->order_id}: " . $e->getMessage());
        }
    }
}