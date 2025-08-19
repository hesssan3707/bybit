<?php

namespace App\Http\Controllers;

use App\Models\BybitOrders;
use App\Services\BybitApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BybitController extends Controller
{
    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        $this->bybitApiService = $bybitApiService;
    }

    public function index()
    {
        $threeDaysAgo = now()->subDays(3);
        $totalEquity = 'N/A';

        try {
            $balanceInfo = $this->bybitApiService->getWalletBalance('UNIFIED', 'USDT');
            $usdtBalanceData = $balanceInfo['list'][0] ?? null;
            if ($usdtBalanceData && isset($usdtBalanceData['totalEquity'])) {
                $totalEquity = number_format((float)$usdtBalanceData['totalEquity'], 2);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Could not fetch Bybit wallet balance: " . $e->getMessage());
            // Equity will remain 'N/A'
        }

        $orders = BybitOrders::where(function ($query) use ($threeDaysAgo) {
            $query->whereIn('status', ['pending', 'filled'])
                  ->orWhere('updated_at', '>=', $threeDaysAgo);
        })
        ->latest('updated_at')
        ->paginate(50);

        return view('orders_list', [
            'orders' => $orders,
            'totalEquity' => $totalEquity,
        ]);
    }

    public function create()
    {
        $symbol = 'ETHUSDT';
        $marketPrice = '0'; // Default value in case of an error
        try {
            $tickerInfo = $this->bybitApiService->getTickerInfo($symbol);
            $price = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);
            $marketPrice = (string)round($price);
        } catch (\Exception $e) {
            // Log the error or handle it as needed, but don't block the page from loading.
            // For now, we'll just use the default price.
            \Illuminate\Support\Facades\Log::error("Could not fetch Bybit market price: " . $e->getMessage());
        }
        return view('set_order', ['marketPrice' => $marketPrice]);
    }

    public function store(Request $request)
    {
        // Add password to validation rules
        $validated = $request->validate([
            'entry1' => 'required|numeric',
            'entry2' => 'required|numeric',
            'tp'     => 'required|numeric',
            'sl'     => 'required|numeric',
            'steps'  => 'required|integer|min:1',
            'expire' => 'required|integer|min:1',
            'risk_percentage' => 'required|numeric|min:0.1',
            'access_password' => 'required|string',
        ]);

        // Check the access password
        if ($validated['access_password'] !== env('FORM_ACCESS_PASSWORD')) {
            return back()->withErrors(['access_password' => 'رمز عبور دسترسی نامعتبر است.'])->withInput();
        }

        try {
            // Check for existing active orders
            $activeOrder = BybitOrders::whereIn('status', ['pending', 'filled'])->first();
            if ($activeOrder) {
                return back()->withErrors(['msg' => 'An order is already active. Cannot create a new one.'])->withInput();
            }

            // Check for recent loss
            $lastLoss = BybitOrders::where('status', 'closed')
                ->where('pnl', '<', 0)
                ->latest('closed_at')
                ->first();

            if ($lastLoss && now()->diffInMinutes($lastLoss->closed_at) < 60) {
                $remainingTime = 60 - now()->diffInMinutes($lastLoss->closed_at);
                return back()->withErrors(['msg' => "You cannot create a new order for {$remainingTime} minutes after a loss."])->withInput();
            }
            // Business Logic
            $symbol = 'ETHUSDT';
            $entry1 = (float) $validated['entry1'];
            $entry2 = (float) $validated['entry2'];
            if ($entry2 < $entry1) { [$entry1, $entry2] = [$entry2, $entry1]; }

            // Feature 3: If entry prices are the same, force steps to 1.
            $steps = ($entry1 === $entry2) ? 1 : (int)$validated['steps'];

            $avgEntry = ($entry1 + $entry2) / 2.0;
            $side = ($validated['sl'] > $avgEntry) ? 'Sell' : 'Buy';

            // Feature 2: Validate entry price against market price
            $tickerInfo = $this->bybitApiService->getTickerInfo($symbol);
            $marketPrice = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);

            if ($marketPrice > 0) {
                if ($side === 'Buy' && $avgEntry > $marketPrice) {
                    return back()->withErrors(['msg' => "برای معامله خرید، قیمت ورود ({$avgEntry}) نمی‌تواند بالاتر از قیمت بازار ({$marketPrice}) باشد."])->withInput();
                }
                if ($side === 'Sell' && $avgEntry < $marketPrice) {
                    return back()->withErrors(['msg' => "برای معامله فروش، قیمت ورود ({$avgEntry}) نمی‌تواند پایین‌تر از قیمت بازار ({$marketPrice}) باشد."])->withInput();
                }
            }

            // Fetch live wallet balance instead of using a static .env variable
            $balanceInfo = $this->bybitApiService->getWalletBalance('UNIFIED', 'USDT');
            $usdtBalanceData = $balanceInfo['list'][0] ?? null;
            if (!$usdtBalanceData || ! $usdtBalanceData['totalEquity']) {
                throw new \Exception('Could not retrieve USDT wallet balance from Bybit.');
            }
            $capitalUSD = min((float) $usdtBalanceData['totalWalletBalance'] , (float) $usdtBalanceData['totalEquity']);

            // Use the risk percentage from the form, capped at 10%
            $riskPercentage = min((float)$validated['risk_percentage'], 10.0);
            $maxLossUSD = $capitalUSD * ($riskPercentage / 100.0);

            $slDistance = abs($avgEntry - (float) $validated['sl']);

            if ($slDistance <= 0) {
                return back()->withErrors(['sl' => 'SL must be different from the entry price.'])->withInput();
            }
            $amount = $maxLossUSD / $slDistance;

            // Get Market Precision via Service
            $instrumentInfo = $this->bybitApiService->getInstrumentsInfo($symbol);
            $instrumentData = $instrumentInfo['list'][0];
            $amountPrec = strlen(substr(strrchr($instrumentData['lotSizeFilter']['qtyStep'], "."), 1));
            $pricePrec = (int) $instrumentData['priceScale'];
            $amount = round($amount, $amountPrec);

            // Create Orders via Service
            $amountPerStep = round($amount / $steps, $amountPrec);
            $stepSize = ($steps > 1) ? (($entry2 - $entry1) / ($steps - 1)) : 0;

            foreach (range(0, $steps - 1) as $i) {
                $price = round($entry1 + ($stepSize * $i), $pricePrec);

                $orderLinkId = (string) Str::uuid();

                $orderParams = [
                    'category' => 'linear',
                    'symbol' => $symbol,
                    'side' => $side,
                    'orderType' => 'Limit',
                    'qty' => (string)$amountPerStep,
                    'price' => (string)$price,
                    'timeInForce' => 'GTC',
                    'stopLoss'  => (string)$validated['sl'],
                    'orderLinkId' => $orderLinkId,
                ];

                $responseData = $this->bybitApiService->createOrder($orderParams);

                BybitOrders::create([
                    'order_id'       => $responseData['orderId'] ?? null,
                    'order_link_id'  => $orderLinkId,
                    'symbol'         => $symbol,
                    'entry_price'    => $price,
                    'tp'             => (float)$validated['tp'],
                    'sl'             => (float)$validated['sl'],
                    'steps'          => $steps,
                    'expire_minutes' => (int)$validated['expire'],
                    'status'         => 'pending',
                    'side'           => strtolower($side),
                    'amount'         => $amountPerStep,
                    'entry_low'      => $entry1,
                    'entry_high'     => $entry2,
                ]);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => 'An API error occurred: ' . $e->getMessage()])->withInput();
        }

        return back()->with('success', "{$steps} order(s) successfully created using V5 API.");
    }

    public function destroy(BybitOrders $bybitOrder)
    {
        $status = $bybitOrder->status;

        // Logic for 'pending' orders (Revoke)
        if ($status === 'pending') {
            try {
                if ($bybitOrder->order_id) {
                    $this->bybitApiService->cancelOrder($bybitOrder->order_id, $bybitOrder->symbol);
                }
            } catch (\Exception $e) {
                // If cancellation fails (e.g., order already filled or canceled), log it but proceed to delete from our DB.
                \Illuminate\Support\Facades\Log::warning("Could not cancel order {$bybitOrder->order_id} on Bybit during deletion. It might have been already filled/canceled. Error: " . $e->getMessage());
            }
        }
        // For 'expired' orders, we just delete them from the DB.
        // For 'pending' orders, we also delete them after trying to cancel.

        if ($status === 'pending' || $status === 'expired') {
            $bybitOrder->delete();
            return redirect()->route('orders.index')->with('success', "The {$status} order has been removed.");
        }

        // For any other status, do nothing.
        return redirect()->route('orders.index')->withErrors(['msg' => 'This order cannot be removed.']);
    }

    public function close(Request $request, BybitOrders $bybitOrder)
    {
        $validated = $request->validate([
            'price_distance' => 'required|numeric|min:0',
        ]);

        if ($bybitOrder->status !== 'filled') {
            return redirect()->route('orders.index')->withErrors(['msg' => 'Only filled orders can be closed.']);
        }

        try {
            $symbol = $bybitOrder->symbol;
            $priceDistance = (float)$validated['price_distance'];

            // 1. Cancel the existing TP order, if it exists
            if ($bybitOrder->closing_order_id) {
                try {
                    $this->bybitApiService->cancelOrder($bybitOrder->closing_order_id, $symbol);
                    \Illuminate\Support\Facades\Log::info("Canceled existing TP order {$bybitOrder->closing_order_id} to set a new manual close.");
                } catch (\Exception $e) {
                    // It might have already been filled or canceled, which is okay.
                    \Illuminate\Support\Facades\Log::warning("Could not cancel existing TP order {$bybitOrder->closing_order_id}: " . $e->getMessage());
                }
            }

            // 2. Get current market price
            $tickerInfo = $this->bybitApiService->getTickerInfo($symbol);
            $marketPrice = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);
            if ($marketPrice === 0) {
                throw new \Exception('Could not fetch market price to set closing order.');
            }

            // 3. Calculate the new closing price
            $instrumentInfo = $this->bybitApiService->getInstrumentsInfo($symbol);
            $pricePrec = (int) $instrumentInfo['list'][0]['priceScale'];

            $closePrice = ($bybitOrder->side === 'buy')
                ? $marketPrice + $priceDistance
                : $marketPrice - $priceDistance;
            $closePrice = round($closePrice, $pricePrec);

            // 4. Create the new closing order
            $closeSide = ($bybitOrder->side === 'buy') ? 'Sell' : 'Buy';
            $newTpOrderParams = [
                'category' => 'linear',
                'symbol' => $symbol,
                'side' => $closeSide,
                'orderType' => 'Limit',
                'qty' => (string)$bybitOrder->amount,
                'price' => (string)$closePrice,
                'reduceOnly' => true,
                'timeInForce' => 'GTC',
            ];

            $tpOrderResult = $this->bybitApiService->createOrder($newTpOrderParams);
            $newClosingOrderId = $tpOrderResult['orderId'] ?? null;

            // 5. Update our database record with the new closing order ID
            $bybitOrder->closing_order_id = $newClosingOrderId;
            $bybitOrder->save();

            return redirect()->route('orders.index')->with('success', "New closing order has been set at {$closePrice}.");

        } catch (\Exception $e) {
            return redirect()->route('orders.index')->withErrors(['msg' => 'Failed to set new closing order: ' . $e->getMessage()]);
        }
    }
}
