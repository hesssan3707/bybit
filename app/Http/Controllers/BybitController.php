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

        $orders = BybitOrders::where(function ($query) use ($threeDaysAgo) {
            $query->whereIn('status', ['pending', 'filled'])
                  ->orWhere('updated_at', '>=', $threeDaysAgo);
        })
        ->latest('updated_at')
        ->paginate(50);

        return view('orders_list', ['orders' => $orders]);
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
        // Prevent deleting orders that were closed less than 24 hours ago
        if ($bybitOrder->status === 'closed' && $bybitOrder->closed_at && now()->diffInHours($bybitOrder->closed_at) < 24) {
            return redirect()->route('orders.index')->withErrors(['msg' => 'Cannot delete an order that was closed less than 24 hours ago.']);
        }

        try {
            // Only try to cancel on the exchange if the order is in a state that might be active
            if ($bybitOrder->status === 'pending' && $bybitOrder->order_id) {
                $this->bybitApiService->cancelOrder($bybitOrder->order_id, $bybitOrder->symbol);
            }
        } catch (\Exception $e) {
            // If cancellation fails (e.g., order already filled or canceled), log it but proceed to delete from our DB.
            \Illuminate\Support\Facades\Log::warning("Could not cancel order {$bybitOrder->order_id} on Bybit during deletion. It might have been already filled/canceled. Error: " . $e->getMessage());
        }

        $bybitOrder->delete();

        return redirect()->route('orders.index')->with('success', 'سفارش با موفقیت حذف شد.');
    }
}
