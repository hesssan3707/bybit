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
     * Calculate order quantity with proper rounding based on exchange's quantity step
     *
     * @param float $rawQty Raw calculated quantity
     * @param float $qtyStep Exchange's quantity step
     * @param int $amountPrec Decimal precision for quantity
     * @return float Properly rounded quantity
     */
    private function calculateOrderQuantity(float $rawQty, float $qtyStep, int $amountPrec): float
    {
        // Handle edge case where qtyStep is 0 or invalid
        if ($qtyStep <= 0) {
            throw new \Exception('Invalid quantity step received from exchange');
        }

        // For whole number steps (e.g., 1, 10, 100)
        if ($qtyStep >= 1) {
            $finalQty = round($rawQty / $qtyStep) * $qtyStep;
        } else {
            // For decimal steps, use step-based rounding
            $finalQty = round($rawQty / $qtyStep) * $qtyStep;

            // Then apply decimal precision to avoid floating point issues
            $finalQty = round($finalQty, $amountPrec);
        }

        // Ensure we don't return 0 due to rounding issues
        if ($finalQty <= 0 && $rawQty > 0) {
            // If rounding resulted in 0 but original was positive, use minimum step
            $finalQty = $qtyStep;
        }

        return $finalQty;
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

            case '110007': // Available balance not enough for new order
                return "موجودی قابل استفاده شما برای ایجاد سفارش جدید کافی نیست.\n" .
                       "برای سفارش {$side} {$symbol}، ابتدا موجودی USDT خود را شارژ کنید یا سفارشات باز خود را بررسی کنید.";

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
                if (str_contains($errorMessage, 'Insufficient balance') || str_contains($errorMessage, 'not enough for new order')) {
                    return "موجودی حساب شما کافی نیست.\n" .
                           "لطفاً ابتدا حساب خود را شارژ کنید یا سفارشات باز خود را بررسی کنید.";
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

        return view('futures.orders_list', [
            'orders' => $orders,
            'hasActiveExchange' => $exchangeStatus['hasActiveExchange'],
            'exchangeMessage' => $exchangeStatus['message']
        ]);
    }

    public function create()
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();

        $user = auth()->user();
        $availableMarkets = ['BTCUSDT', 'ETHUSDT', 'ADAUSDT', 'DOTUSDT', 'BNBUSDT', 'XRPUSDT', 'SOLUSDT', 'TRXUSDT', 'DOGEUSDT', 'LTCUSDT'];
        $selectedMarket = null;
        $marketPrice = '0'; // Default value in case of an error

        // Determine which symbol to use for price fetching
        if ($user->future_strict_mode && $user->selected_market) {
            // Strict mode: use user's selected market
            $selectedMarket = $user->selected_market;
            $symbol = $selectedMarket;
        } else {
            // Non-strict mode: use default market (first in list)
            $symbol = $availableMarkets[0]; // Use BTCUSDT as default instead of ETHUSDT
        }

        if ($exchangeStatus['hasActiveExchange']) {
            try {
                $exchangeService = $this->getExchangeService();
                $tickerInfo = $exchangeService->getTickerInfo($symbol);
                $price = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);
                $marketPrice = (string)$price;
            } catch (\Exception $e) {
                // Check if this is an access permission error and update validation
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

        // Set default expiration based on strict mode
        $defaultExpiration = $user->future_strict_mode ? 15 : 999;

        return view('futures.set_order', [
            'marketPrice' => $marketPrice,
            'hasActiveExchange' => $exchangeStatus['hasActiveExchange'],
            'exchangeMessage' => $exchangeStatus['message'],
            'user' => $user,
            'availableMarkets' => $availableMarkets,
            'selectedMarket' => $selectedMarket,
            'defaultExpiration' => $defaultExpiration,
            'currentSymbol' => $symbol // Pass the current symbol for proper price display
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
            'symbol' => 'required|string|in:BTCUSDT,ETHUSDT,ADAUSDT,DOTUSDT,BNBUSDT,XRPUSDT,SOLUSDT,TRXUSDT,DOGEUSDT,LTCUSDT',
            'entry1' => 'required|numeric',
            'entry2' => 'required|numeric',
            'tp'     => 'required|numeric',
            'sl'     => 'required|numeric',
            'steps'  => 'required|integer|min:1',
            'expire' => 'required|integer|min:1',
            'risk_percentage' => 'required|numeric|min:0.1',
            'cancel_price' => 'nullable|numeric',
        ]);

        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();

            // Get the current user
            $user = auth()->user();

            // Validate market selection for strict mode users
            if ($user->future_strict_mode) {
                if (!$user->selected_market) {
                    return back()->withErrors(['msg' => 'برای حالت سخت‌گیرانه، باید بازار انتخابی تنظیم شده باشد.'])->withInput();
                }
                if ($validated['symbol'] !== $user->selected_market) {
                    return back()->withErrors(['symbol' => "در حالت سخت‌گیرانه، تنها می‌توانید در بازار {$user->selected_market} معامله کنید."])->withInput();
                }
            }

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
                
                // Check position mode requirement for strict mode users
                $userExchange = $user->activeExchanges()->first();
                if ($userExchange) {
                    $dbPositionMode = $userExchange->position_mode;
                    
                    // If database shows one-way mode, don't allow order placement
                    if ($dbPositionMode === 'one-way') {
                        return back()->withErrors(['msg' => 'در حالت سخت‌گیرانه، نمی‌توانید در حالت one-way معامله کنید. لطفاً ابتدا حالت hedge را فعال کنید.'])->withInput();
                    }
                    
                    // If database shows hedge mode, verify with exchange
                    if ($dbPositionMode === 'hedge') {
                        $accountInfo = $exchangeService->getAccountInfo();
                        if (!$accountInfo['hedgeMode']) {
                            // Update database if exchange shows different mode
                            $userExchange->update(['position_mode' => 'one-way']);
                            return back()->withErrors(['msg' => 'حالت صرافی با پایگاه داده همخوانی ندارد. لطفاً حالت hedge را مجدداً فعال کنید.'])->withInput();
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
            $symbol = $validated['symbol'];
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

            // Get Market Precision via Service - request specific symbol to ensure accuracy
            $instrumentInfo = $exchangeService->getInstrumentsInfo($symbol);

            // Validate that we got instrument data
            if (empty($instrumentInfo['list'])) {
                throw new \Exception("Unable to get instrument information for {$symbol} from exchange. Please try again.");
            }

            // Find the specific instrument for our symbol
            $instrumentData = null;
            foreach ($instrumentInfo['list'] as $instrument) {
                if ($instrument['symbol'] === $symbol) {
                    $instrumentData = $instrument;
                    break;
                }
            }

            // Validate we found our specific symbol
            if (!$instrumentData) {
                throw new \Exception("Symbol {$symbol} not found in exchange instrument list. Please check if this symbol is supported.");
            }

            // Validate required fields exist
            if (!isset($instrumentData['lotSizeFilter']['qtyStep']) || !isset($instrumentData['lotSizeFilter']['minOrderQty'])) {
                throw new \Exception("Incomplete instrument data received for {$symbol}. Missing lot size filter information.");
            }

            $qtyStep = (float) $instrumentData['lotSizeFilter']['qtyStep'];
            $minQty = (float) $instrumentData['lotSizeFilter']['minOrderQty'];
            $pricePrec = (int) $instrumentData['priceScale'];

            // Calculate decimal places for quantity precision
            $qtyStepStr = (string) $qtyStep;
            $amountPrec = (strpos($qtyStepStr, '.') !== false) ? strlen(substr($qtyStepStr, strpos($qtyStepStr, '.') + 1)) : 0;

            // Create Orders via Service
            $amountPerStep = $amount / $steps;
            $stepSize = ($steps > 1) ? (($entry2 - $entry1) / ($steps - 1)) : 0;

            // Validate that calculated amount can meet minimum requirements before creating any orders
            $testQty = $this->calculateOrderQuantity($amountPerStep, $qtyStep, $amountPrec);
            if ($testQty < $minQty) {
                $requiredRisk = ($minQty * abs($avgEntry - (float) $validated['sl']) / $capitalUSD) * 100;
                throw new \Exception("مقدار محاسبه شده ({$testQty}) کمتر از حداقل مجاز ({$minQty}) است. لطفاً درصد ریسک را به حداقل {$requiredRisk}% افزایش دهید یا تعداد مراحل را کاهش دهید.");
            }

            foreach (range(0, $steps - 1) as $i) {
                $price = $entry1 + ($stepSize * $i);

                $orderLinkId = (string) Str::uuid();

                // Calculate final quantity using improved precision
                $finalQty = $this->calculateOrderQuantity($amountPerStep, $qtyStep, $amountPrec);

                // Final safety check (should not trigger due to pre-validation above)
                if ($finalQty < $minQty) {
                    throw new \Exception("مقدار سفارش ({$finalQty}) کمتر از حداقل مجاز ({$minQty}) است. لطفاً درصد ریسک را افزایش دهید.");
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

                // Get user's current active exchange ID
                $user = auth()->user();
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                if (!$currentExchange) {
                    throw new \Exception('لطفاً ابتدا در صفحه پروفایل، صرافی مورد نظر خود را فعال کنید.');
                }

                Order::create([
                    'user_exchange_id' => $currentExchange->id,
                    'order_id'         => $responseData['orderId'] ?? null,
                    'order_link_id'    => $orderLinkId,
                    'symbol'           => $symbol,
                    'entry_price'      => $finalPrice,
                    'tp'               => (float)$validated['tp'],
                    'sl'               => (float)$validated['sl'],
                    'steps'            => $steps,
                    'expire_minutes'   => (int)$validated['expire'],
                    'status'           => 'pending',
                    'side'             => strtolower($side),
                    'amount'           => $finalQty, // Use the rounded quantity that was sent to Bybit
                    'entry_low'        => $entry1,
                    'entry_high'       => $entry2,
                    'cancel_price'     => isset($validated['cancel_price']) ? (float)$validated['cancel_price'] : null,
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
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => $exchangeStatus['message']]);
        }

        // Verify order belongs to user (through user exchange relationship)
        $user = auth()->user();
        $userExchangeIds = $user->activeExchanges()->pluck('id')->toArray();

        if (!in_array($order->user_exchange_id, $userExchangeIds)) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => 'شما مجاز به حذف این سفارش نیستید.']);
        }

        $status = $order->status;

        // Logic for 'pending' orders (Revoke)
        if ($status === 'pending') {
            Log::info("Attempting to cancel pending order {$order->id} with exchange order ID: {$order->order_id}");
            try {
                if ($order->order_id) {
                    $exchangeService = $this->getExchangeService();
                    $exchangeService->cancelOrderWithSymbol($order->order_id, $order->symbol);
                    Log::info("Successfully cancelled order {$order->order_id} on exchange", [
                        'local_order_id' => $order->id,
                        'exchange_order_id' => $order->order_id,
                        'symbol' => $order->symbol,
                        'user_exchange_id' => $order->user_exchange_id
                    ]);
                } else {
                    Log::warning("Order {$order->id} has no exchange order ID, skipping exchange cancellation");
                }
            } catch (\Exception $e) {
                // If cancellation fails (e.g., order already filled or canceled), log it but proceed to delete from our DB.
                Log::warning("Could not cancel order {$order->order_id} on exchange during deletion. It might have been already filled/canceled. Error: " . $e->getMessage(), [
                    'local_order_id' => $order->id,
                    'exchange_order_id' => $order->order_id,
                    'symbol' => $order->symbol,
                    'user_exchange_id' => $order->user_exchange_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        // For 'expired' orders, we just delete them from the DB.
        // For 'pending' orders, we also delete them after trying to cancel.

        if ($status === 'pending' || $status === 'expired') {
            $order->delete();
            Log::info("Successfully deleted order {$order->id} from database", [
                'order_id' => $order->order_id,
                'status' => $status,
                'user_exchange_id' => $order->user_exchange_id
            ]);
            return redirect()->route('futures.orders')->with('success', "سفارش {$status} با موفقیت حذف شد.");
        }

        // For any other status, do nothing.
        return redirect()->route('futures.orders')->withErrors(['msg' => 'این سفارش قابل حذف نیست.']);
    }

    public function close(Request $request, Order $order)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => $exchangeStatus['message']]);
        }

        // Verify order belongs to user
        $user = auth()->user();
        $userExchangeIds = $user->activeExchanges()->pluck('id')->toArray();
        if (!in_array($order->user_exchange_id, $userExchangeIds)) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => 'شما مجاز به بستن این سفارش نیستید.']);
        }

        if ($order->status !== 'filled') {
            return redirect()->route('futures.orders')->withErrors(['msg' => 'فقط سفارش‌های پر شده قابل بستن هستند.']);
        }

        try {
            $exchangeService = $this->getExchangeService();
            $symbol = $order->symbol;

            // Create a market order to close the position instantly
            $closeSide = ($order->side === 'buy') ? 'Sell' : 'Buy';

            // Get proper quantity precision for this symbol
            $instrumentInfo = $exchangeService->getInstrumentsInfo($symbol);
            $qtyStep = (float) $instrumentInfo['list'][0]['lotSizeFilter']['qtyStep'];

            $closeQty = $order->amount;
            if ($qtyStep >= 1) {
                // For whole number steps, ensure quantity is a multiple of the step
                $closeQty = round($closeQty / $qtyStep) * $qtyStep;
            } else {
                // For decimal steps, round to the appropriate precision
                $amountPrec = (strpos((string)$qtyStep, '.') !== false) ? strlen(substr((string)$qtyStep, strpos((string)$qtyStep, '.') + 1)) : 0;
                $closeQty = round($closeQty, $amountPrec);
            }

            $marketCloseParams = [
                'category' => 'linear',
                'symbol' => $symbol,
                'side' => $closeSide,
                'orderType' => 'Market',
                'qty' => (string)$closeQty,
                'reduceOnly' => true,
            ];

            $exchangeService->createOrder($marketCloseParams);

            return redirect()->route('futures.orders')->with('success', 'سفارش شما برای بسته شدن در قیمت لحظه‌ای بازار با موفقیت ثبت شد.');

        } catch (\Exception $e) {
            Log::error('Futures market close failed: ' . $e->getMessage());

            $userFriendlyMessage = $this->parseBybitError($e->getMessage());

            return redirect()->route('futures.orders')->withErrors(['msg' => $userFriendlyMessage]);
        }
    }

    /**
     * API method to get market price for a symbol (requires authentication)
     */
    public function getMarketPrice($symbol)
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        // Validate symbol is in supported markets
        $supportedMarkets = ['BTCUSDT', 'ETHUSDT', 'ADAUSDT', 'DOTUSDT', 'BNBUSDT', 'XRPUSDT', 'SOLUSDT', 'TRXUSDT', 'DOGEUSDT', 'LTCUSDT'];

        if (!in_array($symbol, $supportedMarkets)) {
            return response()->json([
                'success' => false,
                'message' => 'نماد ارز پشتیبانی نمی‌شود'
            ], 400);
        }

        try {
            // Get user's exchange service (requires active exchange)
            $exchangeService = $this->getExchangeService();
            $tickerInfo = $exchangeService->getTickerInfo($symbol);
            $price = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);

            if ($price <= 0) {
                throw new \Exception('قیمت معتبر دریافت نشد');
            }

            return response()->json([
                'success' => true,
                'symbol' => $symbol,
                'price' => (string)$price,
                'raw_price' => $price
            ]);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Market price fetch failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت قیمت بازار: ' . $e->getMessage()
            ], 500);
        }
    }
}
