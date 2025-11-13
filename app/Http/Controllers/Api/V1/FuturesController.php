<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Trade;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FuturesController extends Controller
{
    private function resolveCapitalUSD(ExchangeApiServiceInterface $exchangeService): float
    {
        // Skip live exchange calls in local environment
        if (app()->environment('local')) {
            return 1000.0;
        }

        try {
            $exchangeName = strtolower(method_exists($exchangeService, 'getExchangeName') ? $exchangeService->getExchangeName() : '');

            if ($exchangeName === 'binance') {
                $balanceData = $exchangeService->getWalletBalance('FUTURES', 'USDT');
                $row = $balanceData['list'][0] ?? null;
                if ($row) {
                    $walletBase = (float)($row['crossWalletBalance'] ?? ($row['balance'] ?? ($row['availableBalance'] ?? ($row['maxWithdrawAmount'] ?? 0))));
                    $equity = isset($row['marginBalance']) ? (float)$row['marginBalance'] : ($walletBase + (float)($row['crossUnPnl'] ?? 0));
                    return max(0.0, min($equity, $walletBase));
                }
            } elseif ($exchangeName === 'bingx') {
                $balanceData = $exchangeService->getWalletBalance('FUTURES');
                $obj = $balanceData['list'][0] ?? null;
                if ($obj) {
                    $equity = (float)($obj['equity'] ?? (((float)($obj['totalWalletBalance'] ?? ($obj['walletBalance'] ?? 0))) + (float)($obj['unrealizedPnl'] ?? 0)));
                    $wallet = (float)($obj['totalWalletBalance'] ?? ($obj['walletBalance'] ?? ($obj['availableBalance'] ?? 0)));
                    return max(0.0, min($equity, $wallet));
                }
            } else { // bybit or default
                $balanceInfo = $exchangeService->getWalletBalance('UNIFIED', 'USDT');
                $usdt = $balanceInfo['list'][0] ?? null;
                if ($usdt && isset($usdt['totalEquity'])) {
                    $equity = (float)$usdt['totalEquity'];
                    $wallet = (float)($usdt['totalWalletBalance'] ?? $equity);
                    return max(0.0, min($equity, $wallet));
                }
                // Fallback to account-level if coin-specific missing
                $accountInfo = $exchangeService->getWalletBalance('UNIFIED');
                $account = $accountInfo['list'][0] ?? null;
                if ($account && isset($account['totalEquity'])) {
                    $equity = (float)$account['totalEquity'];
                    $wallet = (float)($account['totalWalletBalance'] ?? $equity);
                    return max(0.0, min($equity, $wallet));
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to resolve capitalUSD: ' . $e->getMessage());
        }

        return 1000.0;
    }

    private function getExchangeService(): ExchangeApiServiceInterface
    {
        if (!auth()->check()) {
            throw new \Exception('User not authenticated');
        }

        try {
            return ExchangeFactory::createForUser(auth()->id());
        } catch (\Exception $e) {
            throw new \Exception('Please activate your desired exchange on your profile page first.');
        }
    }

    public function index()
    {
        $ordersQuery = Order::forUser(auth()->id());

        // Filter by current account type (demo/real)
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        if ($currentExchange) {
            $ordersQuery->accountType($currentExchange->is_demo_active);
        }

        $orders = $ordersQuery->latest('updated_at')->paginate(20);

        return response()->json(['success' => true, 'data' => $orders]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'symbol' => 'required|string|in:BTCUSDT,ETHUSDT,ADAUSDT,DOTUSDT,BNBUSDT,XRPUSDT,SOLUSDT,TRXUSDT,DOGEUSDT,LTCUSDT',
            'entry1' => 'required|numeric',
            'entry2' => 'nullable|numeric',
            'tp'     => 'required|numeric',
            'sl'     => 'required|numeric',
            'steps'  => 'required|integer|min:1',
            'expire' => 'nullable|integer|min:1|max:999',
            'risk_percentage' => 'required|numeric|min:0.1|max:100',
            'cancel_price' => 'nullable|numeric',
        ]);

        // If entry2 is not provided (steps=1 case), set it equal to entry1
        if (!isset($validated['entry2'])) {
            $validated['entry2'] = $validated['entry1'];
        }

        try {
            $exchangeService = $this->getExchangeService();
            $user = auth()->user();

            $symbol = $validated['symbol'];
            $entry1 = (float) $validated['entry1'];
            $entry2 = (float) $validated['entry2'];
            if ($entry2 < $entry1) { [$entry1, $entry2] = [$entry2, $entry1]; }
            $steps = ($entry1 === $entry2) ? 1 : (int)$validated['steps'];
            $avgEntry = ($entry1 + $entry2) / 2.0;
            $side = ($validated['sl'] > $avgEntry) ? 'Sell' : 'Buy';
            $capitalUSD = $this->resolveCapitalUSD($exchangeService);
            $maxLossUSD = $capitalUSD * ((float)$validated['risk_percentage'] / 100.0);
            $slDistance = abs($avgEntry - (float) $validated['sl']);

            if ($slDistance <= 0) {
                return response()->json(['success' => false, 'message' => 'Stop loss must be different from the entry price.'], 422);
            }
            $amount = $maxLossUSD / $slDistance;

            $instrumentInfo = $exchangeService->getInstrumentsInfo($symbol);
            $instrumentData = collect($instrumentInfo['list'])->firstWhere('symbol', $symbol);

            if (!$instrumentData) {
                throw new \Exception("Symbol {$symbol} not found in exchange instrument list.");
            }

            $qtyStep = (float) $instrumentData['lotSizeFilter']['qtyStep'];
            $minQty = (float) $instrumentData['lotSizeFilter']['minOrderQty'];
            $pricePrec = (int) $instrumentData['priceScale'];
            $qtyStepStr = (string) $qtyStep;
            $amountPrec = (strpos($qtyStepStr, '.') !== false) ? strlen(substr($qtyStepStr, strpos($qtyStepStr, '.') + 1)) : 0;

            $amountPerStep = $amount / $steps;
            $stepSize = ($steps > 1) ? (($entry2 - $entry1) / ($steps - 1)) : 0;

            $orders = [];
            foreach (range(0, $steps - 1) as $i) {
                $price = $entry1 + ($stepSize * $i);
                $orderLinkId = (string) Str::uuid();
                $finalQty = round($amountPerStep / $qtyStep) * $qtyStep;
                $finalQty = round($finalQty, $amountPrec);

                if ($finalQty < $minQty) {
                    throw new \Exception("Calculated quantity ({$finalQty}) is less than the minimum allowed ({$minQty}). Please increase risk percentage.");
                }

                $finalPrice = round($price, $pricePrec);
                $finalSL = round((float)$validated['sl'], $pricePrec);

                $orderParams = [
                    'category' => 'linear',
                    'symbol' => $symbol,
                    'side' => $side,
                    'orderType' => 'Limit',
                    'qty' => (string)$finalQty,
                    'price' => (string)$finalPrice,
                    'timeInForce' => 'GTC',
                    'stopLoss'  => (string)$finalSL,
                    'orderLinkId' => $orderLinkId,
                ];

                $responseData = $exchangeService->createOrder($orderParams);
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;

                $order = Order::create([
                    'user_exchange_id' => $currentExchange->id,
                    'is_demo'          => $currentExchange->is_demo_active,
                    'order_id'         => $responseData['orderId'] ?? null,
                    'order_link_id'    => $orderLinkId,
                    'symbol'           => $symbol,
                    'entry_price'      => $finalPrice,
                    'tp'               => (float)$validated['tp'],
                    'sl'               => (float)$validated['sl'],
                    'steps'            => $steps,
                    'expire_minutes'   => isset($validated['expire']) ? (int)$validated['expire'] : null,
                    'status'           => 'pending',
                    'side'             => strtolower($side),
                    'amount'           => $finalQty,
                    'balance_at_creation' => $capitalUSD,
                    'initial_risk_percent' => round((float)$validated['risk_percentage'], 2),
                    'entry_low'        => $entry1,
                    'entry_high'       => $entry2,
                    'cancel_price'     => isset($validated['cancel_price']) ? (float)$validated['cancel_price'] : null,
                ]);
                $orders[] = $order;
            }

            return response()->json(['success' => true, 'message' => 'Order created successfully.', 'data' => $orders]);

        } catch (\Exception $e) {
            Log::error('Futures order creation failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Order $order)
    {
        if ($order->user_exchange->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($order->status === 'pending' || $order->status === 'expired') {
            try {
                if ($order->order_id && $order->status === 'pending') {
                    $exchangeService = $this->getExchangeService();
                    $exchangeService->cancelOrderWithSymbol($order->order_id, $order->symbol);
                }
            } catch (\Exception $e) {
                Log::warning("Could not cancel order {$order->order_id} on exchange during deletion. Error: " . $e->getMessage());
            }

            $order->delete();
            return response()->json(['success' => true, 'message' => 'Order deleted successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'This order cannot be deleted.'], 422);
    }

    public function close(Request $request, Order $order)
    {
        if ($order->user_exchange->user_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'price_distance' => 'required|numeric|min:0',
        ]);

        if ($order->status !== 'filled') {
            return response()->json(['success' => false, 'message' => 'Only filled orders can be closed.'], 422);
        }

        try {
            $exchangeService = $this->getExchangeService();
            $symbol = $order->symbol;
            $priceDistance = (float)$validated['price_distance'];

            $tickerInfo = $exchangeService->getTickerInfo($symbol);
            $marketPrice = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);
            if ($marketPrice === 0) {
                throw new \Exception('Could not get the current market price to place the closing order.');
            }

            $instrumentInfo = $exchangeService->getInstrumentsInfo($symbol);
            $pricePrec = (int) $instrumentInfo['list'][0]['priceScale'];

            $closePrice = ($order->side === 'buy')
                ? $marketPrice + $priceDistance
                : $marketPrice - $priceDistance;
            $closePrice = round($closePrice, $pricePrec);

            $closeSide = ($order->side === 'buy') ? 'Sell' : 'Buy';
            $closeQty = $order->amount;

            $newTpOrderParams = [
                'category' => 'linear',
                'symbol' => $symbol,
                'side' => $closeSide,
                'orderType' => 'Limit',
                'qty' => (string)$closeQty,
                'price' => (string)$closePrice,
                'reduceOnly' => true,
                'timeInForce' => 'GTC',
            ];

            $exchangeService->createOrder($newTpOrderParams);

            return response()->json(['success' => true, 'message' => "Manual closing order placed at price {$closePrice}."]);

        } catch (\Exception $e) {
            Log::error('Futures order close failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get P&L history for the authenticated user
     */
    public function pnlHistory()
    {
        $tradesQuery = Trade::forUser(auth()->id());

        // Filter by current account type (demo/real)
        $user = auth()->user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        if ($currentExchange) {
            $tradesQuery->accountType($currentExchange->is_demo_active);
        }

        $trades = $tradesQuery->latest('closed_at')->paginate(20);

        return response()->json(['success' => true, 'data' => $trades]);
    }
}
