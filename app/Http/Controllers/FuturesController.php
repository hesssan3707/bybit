<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Trade;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use App\Traits\HandlesExchangeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FuturesController extends Controller
{
    use HandlesExchangeAccess;
    /**
     * Get the exchange service for the authenticated user
     */
    private function getExchangeService(): ExchangeApiServiceInterface
    {
        if (!auth()->check()) {
            throw new \Exception('User not authenticated');
        }

        try {
            return ExchangeFactory::createForUser(auth()->id());
        } catch (\Exception $e) {
            throw new \Exception('لطفاً ابتدا در صفحه پروفایل، صرافی مورد نظر خود را فعال کنید.');
        }
    }

    /**
     * Check if user has an active exchange and return status
     */
    private function checkActiveExchange()
    {
        try {
            $this->getExchangeService();
            return ['hasActiveExchange' => true, 'message' => null];
        } catch (\Exception $e) {
            return [
                'hasActiveExchange' => false, 
                'message' => 'برای استفاده از این قسمت، لطفاً ابتدا صرافی خود را فعال کنید.'
            ];
        }
    }

    /**
     * Parse Bybit API error message and return user-friendly message
     * 
     * @param string $errorMessage
     * @return string
     */
    private function parseBybitError($errorMessage)
    {
        // Extract error code if present
        $errorCode = null;
        if (preg_match('/Code: (\d+)/', $errorMessage, $matches)) {
            $errorCode = $matches[1];
        }
        
        // Extract symbol if present
        $symbol = null;
        if (preg_match('/"symbol":"([^"]+)"/', $errorMessage, $matches)) {
            $symbol = $matches[1];
        }
        
        // Extract side if present  
        $side = null;
        if (preg_match('/"side":"([^"]+)"/', $errorMessage, $matches)) {
            $side = $matches[1] === 'Buy' ? 'خرید' : 'فروش';
        }
        
        // Extract quantity if present
        $qty = null;
        if (preg_match('/"qty":"([^"]+)"/', $errorMessage, $matches)) {
            $qty = $matches[1];
        }
        
        // Map specific error codes to user-friendly messages
        switch ($errorCode) {
            case '170131': // Insufficient balance
                return "موجودی USDT شما برای این معامله آتی کافی نیست.\n" .
                       "برای سفارش {$side} {$symbol}، ابتدا موجودی USDT خود را شارژ کنید.";
                
            case '10001': // Parameter error
                if (str_contains($errorMessage, 'minimum limit')) {
                    return "مقدار سفارش ({$qty}) کمتر از حداقل مجاز است.\n" .
                           "لطفاً درصد ریسک را افزایش دهید یا تعداد مراحل را کاهش دهید.";
                } elseif (str_contains($errorMessage, 'qty')) {
                    return "مقدار سفارش نامعتبر است ({$qty}).\n" .
                           "احتمالاً مقدار محاسبه شده خیلی کوچک است. لطفاً درصد ریسک را افزایش دهید.";
                } elseif (str_contains($errorMessage, 'price')) {
                    return "قیمت سفارش نامعتبر است.\n" .
                           "لطفاً قیمت صحیح وارد کنید.";
                } else {
                    return "اطلاعات سفارش نامعتبر است.\n" .
                           "لطفاً اطلاعات وارد شده را بررسی کنید.";
                }
                
            case '110003': // Order quantity exceeds upper limit
                return "مقدار سفارش از حد مجاز بیشتر است.\n" .
                       "لطفاً درصد ریسک را کاهش دهید.";
                
            case '110012': // Order quantity is lower than the minimum
                return "مقدار سفارش کمتر از حداقل مجاز است.\n" .
                       "لطفاً درصد ریسک را افزایش دهید یا تعداد مراحل را کاهش دهید.";
                
            case '110025': // Order would immediately trigger
                return "سفارش شما بلافاصله اجرا می‌شود.\n" .
                       "برای سفارش محدود، قیمت مناسب‌تری انتخاب کنید.";
                
            default:
                // Generic error handling
                if (str_contains($errorMessage, 'Insufficient balance')) {
                    return "موجودی حساب شما کافی نیست.\n" .
                           "لطفاً ابتدا حساب خود را شارژ کنید.";
                } elseif (str_contains($errorMessage, 'minimum limit')) {
                    return "مقدار سفارش کمتر از حداقل مجاز است.\n" .
                           "لطفاً درصد ریسک را افزایش دهید یا تعداد مراحل را کاهش دهید.";
                } else {
                    // Return a generic but helpful message
                    return "خطا در ایجاد سفارش رخ داد.\n" .
                           "لطفاً اطلاعات وارد شده را بررسی کرده و دوباره تلاش کنید.";
                }
        }
    }

    public function index()
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        
        $threeDaysAgo = now()->subDays(3);

        $orders = Order::forUser(auth()->id())
            ->where(function ($query) use ($threeDaysAgo) {
                $query->whereIn('status', ['pending', 'filled'])
                      ->orWhere('updated_at', '>=', $threeDaysAgo);
            })
            ->latest('updated_at')
            ->paginate(20);

        return view('orders_list', [
            'orders' => $orders,
            'hasActiveExchange' => $exchangeStatus['hasActiveExchange'],
            'exchangeMessage' => $exchangeStatus['message']
        ]);
    }

    public function create()
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        
        $symbol = 'ETHUSDT';
        $marketPrice = '0'; // Default value in case of an error
        
        if ($exchangeStatus['hasActiveExchange']) {
            try {
                $exchangeService = $this->getExchangeService();
                $tickerInfo = $exchangeService->getTickerInfo($symbol);
                $price = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);
                $marketPrice = (string)round($price);
            } catch (\Exception $e) {
                // Check if this is an access permission error and update validation
                $user = auth()->user();
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                
                if ($currentExchange) {
                    try {
                        $this->handleApiException($e, $currentExchange, 'futures');
                    } catch (\Exception $handledException) {
                        // Log the error but continue with default price
                        \Illuminate\Support\Facades\Log::error("Could not fetch market price: " . $e->getMessage());
                    }
                }
            }
        }
        
        return view('set_order', [
            'marketPrice' => $marketPrice,
            'hasActiveExchange' => $exchangeStatus['hasActiveExchange'],
            'exchangeMessage' => $exchangeStatus['message'],
            'user' => auth()->user()
        ]);
    }

    public function store(Request $request)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return back()->withErrors(['msg' => $exchangeStatus['message']])->withInput();
        }
        
        $validated = $request->validate([
            'entry1' => 'required|numeric',
            'entry2' => 'required|numeric',
            'tp'     => 'required|numeric',
            'sl'     => 'required|numeric',
            'steps'  => 'required|integer|min:1',
            'expire' => 'required|integer|min:1',
            'risk_percentage' => 'required|numeric|min:0.1',
        ]);

        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            // Get the current user
            $user = auth()->user();
            
            // Apply strict mode conditions only if user has strict mode enabled
            if ($user->future_strict_mode) {
                // Check for recent loss (only in strict mode)
                $lastLoss = Trade::forUser(auth()->id())
                    ->where('pnl', '<', 0)
                    ->latest('closed_at')
                    ->first();

                if ($lastLoss && now()->diffInMinutes($lastLoss->closed_at) < 60) {
                    $remainingTime = 60 - now()->diffInMinutes($lastLoss->closed_at);
                    return back()->withErrors(['msg' => "به دلیل ضرر در معامله اخیر، تا {$remainingTime} دقیقه دیگر نمی‌توانید معامله جدیدی ثبت کنید. (حالت سخت‌گیرانه فعال)"])->withInput();
                }

                // Validate against active filled order's zones (only in strict mode)
                $filledOrder = Order::forUser(auth()->id())->where('status', 'filled')->first();
                if ($filledOrder) {
                    $newAvgEntry = ($request->input('entry1') + $request->input('entry2')) / 2;
                    $newSide = ($request->input('sl') > $newAvgEntry) ? 'Sell' : 'Buy';

                    // Define the zones
                    $lossZoneMin = min($filledOrder->entry_price, $filledOrder->sl);
                    $lossZoneMax = max($filledOrder->entry_price, $filledOrder->sl);
                    $profitZoneMin = min($filledOrder->entry_price, $filledOrder->tp);
                    $profitZoneMax = max($filledOrder->entry_price, $filledOrder->tp);

                    // Check Loss Zone (No-Go Zone) - only in strict mode
                    if ($newAvgEntry >= $lossZoneMin && $newAvgEntry <= $lossZoneMax) {
                        return back()->withErrors(['msg' => 'قیمت ورود جدید در محدوده ضرر معامله فعال قرار دارد و مجاز نیست. (حالت سخت‌گیرانه فعال)'])->withInput();
                    }

                    // Check Profit Zone (Conditional Zone) - only in strict mode
                    if ($newAvgEntry >= $profitZoneMin && $newAvgEntry <= $profitZoneMax) {
                        if (strtolower($newSide) === strtolower($filledOrder->side)) {
                            return back()->withErrors(['msg' => 'ثبت سفارش هم‌جهت در محدوده سود معامله فعال مجاز نیست. (حالت سخت‌گیرانه فعال)'])->withInput();
                        }
                    }
                }
            }
            // Apply strict mode risk percentage cap (only in strict mode)
            if ($user->future_strict_mode) {
                // Cap risk percentage to 10% in strict mode
                $riskPercentage = min((float)$validated['risk_percentage'], 10.0);
            } else {
                // Allow higher risk percentage in non-strict mode
                $riskPercentage = (float)$validated['risk_percentage'];
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
            $tickerInfo = $exchangeService->getTickerInfo($symbol);
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
            $balanceInfo = $exchangeService->getWalletBalance('UNIFIED', 'USDT');
            $usdtBalanceData = $balanceInfo['list'][0] ?? null;
            if (!$usdtBalanceData || ! $usdtBalanceData['totalEquity']) {
                throw new \Exception('امکان دریافت موجودی کیف پول از صرافی وجود ندارد.');
            }
            $capitalUSD = min((float) $usdtBalanceData['totalWalletBalance'] , (float) $usdtBalanceData['totalEquity']);

            $maxLossUSD = $capitalUSD * ($riskPercentage / 100.0);

            $slDistance = abs($avgEntry - (float) $validated['sl']);

            if ($slDistance <= 0) {
                return back()->withErrors(['sl' => 'حد ضرر باید متفاوت از قیمت ورود باشد.'])->withInput();
            }
            $amount = $maxLossUSD / $slDistance;

            // Get Market Precision via Service
            $instrumentInfo = $exchangeService->getInstrumentsInfo($symbol);
            $instrumentData = $instrumentInfo['list'][0];
            $amountPrec = strlen(substr(strrchr($instrumentData['lotSizeFilter']['qtyStep'], "."), 1));
            $pricePrec = (int) $instrumentData['priceScale'];
            
            // Get minimum order quantity from instrument data
            $minOrderQty = (float)($instrumentData['lotSizeFilter']['minOrderQty'] ?? 0.01);
            $qtyStep = (float)($instrumentData['lotSizeFilter']['qtyStep'] ?? 0.01);
            
            $amount = round($amount, $amountPrec);

            // Create Orders via Service
            $amountPerStep = round($amount / $steps, $amountPrec);
            
            // Critical fix: Ensure quantity doesn't become zero after rounding
            if ($amountPerStep <= 0 || $amountPerStep < $minOrderQty) {
                // If the calculated amount per step is too small, adjust it
                $amountPerStep = max($minOrderQty, $qtyStep);
                
                // Recalculate total amount based on minimum viable step amount
                $totalMinAmount = $amountPerStep * $steps;
                $requiredMinLoss = $totalMinAmount * $slDistance;
                
                throw new \Exception(
                    "مقدار محاسبه شده برای هر مرحله ({$amount}/{$steps} = " . round($amount/$steps, 8) . ") کمتر از حداقل مجاز صرافی است.\n" .
                    "حداقل مقدار مجاز: {$minOrderQty}\n" .
                    "برای {$steps} مرحله، حداقل ریسک مورد نیاز: " . round($requiredMinLoss, 2) . " USDT\n" .
                    "لطفاً درصد ریسک را افزایش دهید یا تعداد مراحل را کاهش دهید."
                );
            }
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

                $responseData = $exchangeService->createOrder($orderParams);

                Order::create([
                    'user_id'        => auth()->id(),
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
            Log::error('Futures order creation failed: ' . $e->getMessage());
            
            // Parse Bybit error message for user-friendly response
            $userFriendlyMessage = $this->parseBybitError($e->getMessage());
            
            return back()->withErrors(['msg' => $userFriendlyMessage])->withInput();
        }

        return back()->with('success', "سفارش شما با موفقیت ثبت شد.");
    }

    public function destroy(Order $order)
    {
        // Check if user has active exchange
        $redirectResponse = $this->checkActiveExchange();
        if ($redirectResponse) {
            return $redirectResponse;
        }
        
        $status = $order->status;

        // Logic for 'pending' orders (Revoke)
        if ($status === 'pending') {
            try {
                if ($order->order_id) {
                    $exchangeService = $this->getExchangeService();
                    $exchangeService->cancelOrder($order->order_id, $order->symbol);
                }
            } catch (\Exception $e) {
                // If cancellation fails (e.g., order already filled or canceled), log it but proceed to delete from our DB.
                \Illuminate\Support\Facades\Log::warning("Could not cancel order {$order->order_id} on exchange during deletion. It might have been already filled/canceled. Error: " . $e->getMessage());
            }
        }
        // For 'expired' orders, we just delete them from the DB.
        // For 'pending' orders, we also delete them after trying to cancel.

        if ($status === 'pending' || $status === 'expired') {
            $order->delete();
            return redirect()->route('orders.index')->with('success', "سفارش {$status} با موفقیت حذف شد.");
        }

        // For any other status, do nothing.
        return redirect()->route('orders.index')->withErrors(['msg' => 'این سفارش قابل حذف نیست.']);
    }

    public function close(Request $request, Order $order)
    {
        // Check if user has active exchange
        $redirectResponse = $this->checkActiveExchange();
        if ($redirectResponse) {
            return $redirectResponse;
        }
        
        $validated = $request->validate([
            'price_distance' => 'required|numeric|min:0',
        ]);

        if ($order->status !== 'filled') {
            return redirect()->route('orders.index')->withErrors(['msg' => 'فقط سفارش‌های پر شده قابل بستن هستند.']);
        }

        try {
            $exchangeService = $this->getExchangeService();
            $symbol = $order->symbol;
            $priceDistance = (float)$validated['price_distance'];

            // 1. Cancel the existing TP order, if it exists
            // Note: In the new architecture, we don't have a TP order to cancel.
            // The user is manually closing the position.

            // 2. Get current market price
            $tickerInfo = $exchangeService->getTickerInfo($symbol);
            $marketPrice = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);
            if ($marketPrice === 0) {
                throw new \Exception('امکان دریافت قیمت لحظه‌ای بازار برای ثبت سفارش بسته شدن وجود ندارد.');
            }

            // 3. Calculate the new closing price
            $instrumentInfo = $exchangeService->getInstrumentsInfo($symbol);
            $pricePrec = (int) $instrumentInfo['list'][0]['priceScale'];

            $closePrice = ($order->side === 'buy')
                ? $marketPrice + $priceDistance
                : $marketPrice - $priceDistance;
            $closePrice = round($closePrice, $pricePrec);

            // 4. Create the new closing order
            $closeSide = ($order->side === 'buy') ? 'Sell' : 'Buy';
            $newTpOrderParams = [
                'category' => 'linear',
                'symbol' => $symbol,
                'side' => $closeSide,
                'orderType' => 'Limit',
                'qty' => (string)$order->amount,
                'price' => (string)$closePrice,
                'reduceOnly' => true,
                'timeInForce' => 'GTC',
            ];

            $exchangeService->createOrder($newTpOrderParams);

            return redirect()->route('orders.index')->with('success', "سفارش بسته شدن دستی با قیمت {$closePrice} ثبت شد.");

        } catch (\Exception $e) {
            Log::error('Futures order close failed: ' . $e->getMessage());
            
            // Parse Bybit error message for user-friendly response
            $userFriendlyMessage = $this->parseBybitError($e->getMessage());
            
            return redirect()->route('orders.index')->withErrors(['msg' => $userFriendlyMessage]);
        }
    }
}
