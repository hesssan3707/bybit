<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Trade;
use App\Models\UserBan;
use App\Models\UserAccountSetting;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use App\Traits\HandlesExchangeAccess;
use App\Traits\ParsesExchangeErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FuturesController extends Controller
{
    private function resolveCapitalUSD(ExchangeApiServiceInterface $exchangeService): float
    {
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
    use HandlesExchangeAccess, ParsesExchangeErrors;
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



    public function index()
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();

        $threeDaysAgo = now()->subDays(3);

        // Get current exchange to filter by account type
        $user = auth()->user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;

        $ordersQuery = Order::forUser(auth()->id());

        // Filter by current account type (demo/real) if exchange is available
        if ($currentExchange) {
            $ordersQuery->accountType($currentExchange->is_demo_active);
        }

        $orders = $ordersQuery->where(function ($query) use ($threeDaysAgo) {
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

        // Get user's default settings
        $defaultRisk = UserAccountSetting::getDefaultRisk($user->id);
        $defaultFutureOrderSteps = UserAccountSetting::getDefaultFutureOrderSteps($user->id);
        $defaultExpirationMinutes = UserAccountSetting::getDefaultExpirationTime($user->id);

        // Apply strict mode limitations for risk only
        if ($user->future_strict_mode) {
            // In strict mode, limit risk to 10% if user's default is higher
            if ($defaultRisk !== null) {
                $defaultRisk = min($defaultRisk, 10);
            }
        }

        // Compute active ban for UI (strict mode only)
        $activeBan = null;
        $banRemainingSeconds = null;
        try {
            if ($user && ($user->future_strict_mode ?? false)) {
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                $activeBan = UserBan::active()
                    ->forUser($user->id)
                    ->accountType($isDemo)
                    ->orderBy('ends_at', 'desc')
                    ->first();
                if ($activeBan) {
                    $banRemainingSeconds = max(0, $activeBan->ends_at->diffInSeconds(now()));
                }
            }
        } catch (\Throwable $e) {
            // silent failure
        }

        return view('futures.set_order', [
            'marketPrice' => $marketPrice,
            'hasActiveExchange' => $exchangeStatus['hasActiveExchange'],
            'exchangeMessage' => $exchangeStatus['message'],
            'user' => $user,
            'availableMarkets' => $availableMarkets,
            'selectedMarket' => $selectedMarket,
            'defaultFutureOrderSteps' => $defaultFutureOrderSteps,
            'defaultExpiration' => $defaultExpirationMinutes,
            'defaultRisk' => $defaultRisk,
            'currentSymbol' => $symbol, // Pass the current symbol for proper price display
            'activeBan' => $activeBan,
            'banRemainingSeconds' => $banRemainingSeconds,
        ]);
    }

    public function store(Request $request)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return back()->withErrors(['msg' => $exchangeStatus['message']])->withInput();
        }

        // Enforce active bans before creating orders (strict mode only)
        try {
            $user = auth()->user();
            if ($user && ($user->future_strict_mode ?? false)) {
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                $activeBan = UserBan::active()
                    ->forUser($user->id)
                    ->accountType($isDemo)
                    ->orderBy('ends_at', 'desc')
                    ->first();
                if ($activeBan) {
                    $remaining = $activeBan->ends_at->diffForHumans(null, true);
                    return back()
                        ->withErrors(['msg' => 'مدیریت ریسک فعال است. لطفاً ' . $remaining . ' صبر کنید تا امکان ثبت سفارش مجدد فراهم شود.'])
                        ->withInput();
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $validated = $request->validate([
            'symbol' => 'required|string|in:BTCUSDT,ETHUSDT,ADAUSDT,DOTUSDT,BNBUSDT,XRPUSDT,SOLUSDT,TRXUSDT,DOGEUSDT,LTCUSDT',
            'entry1' => 'required|numeric',
            'entry2' => 'nullable|numeric',
            'tp'     => 'required|numeric',
            'sl'     => 'required|numeric',
            'steps'  => 'required|integer|min:1|max:8',
            'expire' => 'nullable|integer|min:1|max:999',
            'risk_percentage' => 'required|numeric|min:0.1',
            'cancel_price' => 'nullable|numeric',
        ]);

        // If entry2 is not provided (steps=1 case), set it equal to entry1
        if (!isset($validated['entry2'])) {
            $validated['entry2'] = $validated['entry1'];
        }

        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();

            // Get the current user
            $user = auth()->user();

            // Apply strict mode conditions only if user has strict mode enabled
            if ($user->future_strict_mode) {

                if (!$user->selected_market) {
                    return back()->withErrors(['msg' => 'برای حالت سخت‌گیرانه، باید بازار انتخابی تنظیم شده باشد.'])->withInput();
                }
                if ($validated['symbol'] !== $user->selected_market) {
                    return back()->withErrors(['symbol' => "در حالت سخت‌گیرانه، تنها می‌توانید در بازار {$user->selected_market} معامله کنید."])->withInput();
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
            $sideLower = strtolower($side);

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

            // Prevent placing multiple orders with the same direction if there's
            // an existing pending order or an open trade on the same side (for current exchange & symbol)
            $currentExchange = $user->currentExchange ?? $user->defaultExchange;
            if ($currentExchange) {
                $hasPendingSameSide = Order::where('user_exchange_id', $currentExchange->id)
                    ->where('is_demo', $currentExchange->is_demo_active)
                    ->where('status', 'pending')
                    ->where('symbol', $symbol)
                    ->where('side', $sideLower)
                    ->exists();

                $hasOpenSameSideTrade = Trade::where('user_exchange_id', $currentExchange->id)
                    ->where('is_demo', $currentExchange->is_demo_active)
                    ->whereNull('closed_at')
                    ->where('symbol', $symbol)
                    ->where('side', $sideLower)
                    ->exists();

                if ($hasPendingSameSide || $hasOpenSameSideTrade) {
                    return back()->withErrors([
                        'msg' => 'ثبت سفارش جدید در همین جهت امکان‌پذیر نیست؛ شما یک سفارش در انتظار یا معامله باز در همین جهت دارید. لطفاً ابتدا آن را لغو یا ببندید.'
                    ])->withInput();
                }
            }

            // Fetch live wallet balance instead of using a static .env variable
            $capitalUSD = $this->resolveCapitalUSD($exchangeService);

            $maxLossUSD = $capitalUSD * ($riskPercentage / 100.0);

            $slDistance = abs($avgEntry - (float) $validated['sl']);
            $tpDistance = abs($avgEntry - (float) $validated['tp']);

            if ($slDistance <= 0) {
                return back()->withErrors(['sl' => 'حد ضرر باید متفاوت از قیمت ورود باشد.'])->withInput();
            }
            // Enforce configured minimum RR ratio when strict mode is active
            if ($user->future_strict_mode) {
                $minRrStr = \App\Models\UserAccountSetting::getMinRrRatio($user->id);
                if (!is_string($minRrStr) || !preg_match('/^\d+:\d+$/', $minRrStr)) {
                    $minRrStr = '3:1'; // loss:profit minima (e.g., 3:1 => loss three times profit)
                }
                // Interpret value as loss:profit minima => require profit/loss strictly greater than (profitPart/lossPart)
                [$lossPart, $profitPart] = array_map('floatval', explode(':', $minRrStr));
                if ($lossPart <= 0) { $lossPart = 1.0; }
                $minProfitOverLoss = $profitPart / $lossPart; // e.g. 3:1 => 1/3; 1:2 => 2.0
                // Strictly greater-than: tpDistance > minProfitOverLoss * slDistance
                if ($tpDistance <= ($minProfitOverLoss * $slDistance)) {
                    return back()->withErrors(['tp' => "در حالت سخت‌گیرانه، حد سود باید بیشتر از نسبت انتخاب‌شده باشد. نسبت حداقل (ضرر:سود): {$minRrStr}"])->withInput();
                }
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

                // Get user's current active exchange
                $user = auth()->user();
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                if (!$currentExchange) {
                    throw new \Exception('لطفاً ابتدا در صفحه پروفایل، صرافی مورد نظر خود را فعال کنید.');
                }

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

                // Always add hedge mode parameters based on exchange
                $this->addHedgeModeParameters($orderParams, $currentExchange->exchange_name, $side);

                $responseData = $exchangeService->createOrder($orderParams);

                Order::create([
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
                    'amount'           => $finalQty, // Use the rounded quantity that was sent to Bybit
                    'balance_at_creation' => $capitalUSD,
                    'entry_low'        => $entry1,
                    'entry_high'       => $entry2,
                    'cancel_price'     => isset($validated['cancel_price']) ? (float)$validated['cancel_price'] : null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Futures order creation failed: ' . $e->getMessage());

            // Parse Bybit error message for user-friendly response
            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());

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
            return redirect()->route('futures.pnl_history')
                ->withErrors(['msg' => $exchangeStatus['message']]);
        }

        // Verify order belongs to user
        $user = auth()->user();
        $userExchangeIds = $user->activeExchanges()->pluck('id')->toArray();
        if (!in_array($order->user_exchange_id, $userExchangeIds)) {
            return redirect()->route('futures.pnl_history')
                ->withErrors(['msg' => 'شما مجاز به بستن این سفارش نیستید.']);
        }

        // پیدا کردن معامله مرتبط باز
        $trade = \App\Models\Trade::whereNull('closed_at')
            ->where('order_id', $order->order_id)
            ->where('user_exchange_id', $order->user_exchange_id)
            ->first();

        // محیط محلی: عدم اتصال به صرافی و بروزرسانی حداقلی معامله
        if (app()->environment('local')) {
            if ($trade) {
                $trade->avg_exit_price = $order->average_price ?? $trade->avg_entry_price;
                $trade->pnl = 0;
                $trade->closed_at = now();
                $trade->save();
                // Create manual-close ban (strict mode only)
                try {
                    if ($user->future_strict_mode) {
                        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                        $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                        if (!\App\Models\UserBan::where('trade_id', $trade->id)->where('ban_type', 'manual_close')->exists()) {
                            \App\Models\UserBan::create([
                                'user_id' => $user->id,
                                'is_demo' => $isDemo,
                                'trade_id' => $trade->id,
                                'ban_type' => 'manual_close',
                                'starts_at' => now(),
                                'ends_at' => now()->addDays(3),
                            ]);
                        }
                    }
                } catch (\Throwable $e) {}
                return redirect()->route('futures.pnl_history')->with('success', 'موقعیت به صورت آزمایشی در محیط محلی بسته شد.');
            }
            return redirect()->route('futures.pnl_history')->withErrors(['msg' => 'موقعیت باز مرتبط یافت نشد.']);
        }

        try {
            $exchangeService = $this->getExchangeService();
            $symbol = $order->symbol;

            // تشخیص صرافی فعال کاربر
            $currentExchange = $user->currentExchange ?? $user->defaultExchange;
            if (!$currentExchange) {
                throw new \Exception('No active exchange found');
            }

            // تعیین مقدار و سمت موقعیت برای بستن
            $openSide = $trade ? ($trade->side ?? null) : (ucfirst($order->side) ?: null);
            $qty = $trade ? (float)$trade->qty : (float)($order->filled_quantity ?? $order->amount ?? 0);

            if (!$openSide || $qty <= 0) {
                return redirect()->route('futures.pnl_history')->withErrors(['msg' => 'اطلاعات موقعیت برای بستن ناقص است.']);
            }

            // Note: manual close limits are enforced via bans; no inline cooldown here

            // ارسال دستور بستن موقعیت از طریق رابط واحد صرافی‌ها
            $exchangeService->closePosition($symbol, $openSide, $qty);

            // دریافت لیست PnL بسته شده و بروزرسانی معامله مشابه منطق lifecycle
            $startTime = $order->created_at ? $order->created_at->subMinutes(15)->timestamp * 1000 : null;
            $rawClosed = $exchangeService->getClosedPnl($symbol, 100, $startTime);

            // نرمال‌سازی رویدادهای کلوز بین صرافی‌ها
            $list = (is_array($rawClosed) && isset($rawClosed['list'])) ? $rawClosed['list'] : $rawClosed;
            $events = [];
            if (is_array($list)) {
                foreach ($list as $item) {
                    if (!is_array($item)) { continue; }
                    $orderId = $item['orderId'] ?? ($item['order_id'] ?? null);
                    $sym = $item['symbol'] ?? null;
                    $sideRaw = $item['side'] ?? null;
                    $qtyVal = $item['qty'] ?? ($item['size'] ?? null);
                    $avgEntry = $item['avgEntryPrice'] ?? ($item['avg_entry_price'] ?? ($item['avgPrice'] ?? ($item['entryPrice'] ?? null)));
                    $avgExit = $item['avgExitPrice'] ?? ($item['avg_exit_price'] ?? ($item['closePrice'] ?? ($item['avgPrice'] ?? null)));
                    $pnl = $item['closedPnl'] ?? ($item['realisedPnl'] ?? ($item['realizedPnl'] ?? 0));
                    $closedAt = $item['updatedTime'] ?? ($item['createdTime'] ?? ($item['closedAt'] ?? null));

                    // Normalize side
                    $side = null;
                    if ($sideRaw !== null) {
                        $s = strtolower((string)$sideRaw);
                        if ($s === 'buy' || $s === 'long') { $side = 'Buy'; }
                        elseif ($s === 'sell' || $s === 'short') { $side = 'Sell'; }
                        else { $side = $sideRaw; }
                    }

                    if (!$sym) { continue; }

                    $events[] = [
                        'orderId' => $orderId,
                        'symbol' => $sym,
                        'side' => $side,
                        'qty' => $qtyVal !== null ? (float)$qtyVal : null,
                        'avgEntryPrice' => $avgEntry !== null ? (float)$avgEntry : null,
                        'avgExitPrice' => $avgExit !== null ? (float)$avgExit : null,
                        'realizedPnl' => (float)$pnl,
                        'closedAt' => $closedAt ? (int)$closedAt : null,
                    ];
                }
            }

            // تطبیق رویداد براساس شناسه سفارش یا نماد/سمت و اندازه‌ها
            $positionMode = $currentExchange->position_mode;
            $matched = null;
            foreach ($events as $e) {
                $idMatch = isset($e['orderId']) && (string)$e['orderId'] === (string)$order->order_id;
                $fieldsMatch = (($e['symbol'] ?? null) === $symbol)
                    && ($positionMode === 'hedge' ? (($e['side'] ?? null) === $openSide) : true)
                    && (isset($e['qty']) ? abs((float)$e['qty'] - (float)$qty) < 1e-8 : true)
                    && ($trade && isset($e['avgEntryPrice']) ? abs((float)$e['avgEntryPrice'] - (float)$trade->avg_entry_price) < 1e-8 : true);
                if ($idMatch || $fieldsMatch) {
                    $matched = $e;
                    break;
                }
            }

            if ($trade) {
                if ($matched) {
                    if (array_key_exists('avgExitPrice', $matched) && $matched['avgExitPrice'] !== null) {
                        $trade->avg_exit_price = $matched['avgExitPrice'];
                    } else {
                        $trade->avg_exit_price = $order->average_price ?? $trade->avg_entry_price;
                    }
                    if (array_key_exists('realizedPnl', $matched) && $matched['realizedPnl'] !== null) {
                        $trade->pnl = $matched['realizedPnl'];
                    }
                    $trade->closed_at = isset($matched['closedAt']) && $matched['closedAt']
                        ? \Carbon\Carbon::createFromTimestampMs($matched['closedAt'])
                        : now();
                    $trade->closed_by_user = 1;
                    $trade->save();
                    // Manual-close ban (strict mode only)
                    try {
                        if ($user->future_strict_mode) {
                            $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                            $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                            if (!\App\Models\UserBan::where('trade_id', $trade->id)->where('ban_type', 'manual_close')->exists()) {
                                \App\Models\UserBan::create([
                                    'user_id' => $user->id,
                                    'is_demo' => $isDemo,
                                    'trade_id' => $trade->id,
                                    'ban_type' => 'manual_close',
                                    'starts_at' => now(),
                                    'ends_at' => now()->addDays(3),
                                ]);
                            }
                        }
                    } catch (\Throwable $e) {}
                } else {
                    // حداقل بروزرسانی در صورت نبود رویداد
                    $trade->avg_exit_price = $order->average_price ?? $trade->avg_entry_price;
                    $trade->pnl = 0;
                    $trade->closed_at = now();
                    $trade->closed_by_user = 1;
                    $trade->save();
                    // Manual-close ban (strict mode only)
                    try {
                        if ($user->future_strict_mode) {
                            $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                            $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                            if (!\App\Models\UserBan::where('trade_id', $trade->id)->where('ban_type', 'manual_close')->exists()) {
                                \App\Models\UserBan::create([
                                    'user_id' => $user->id,
                                    'is_demo' => $isDemo,
                                    'trade_id' => $trade->id,
                                    'ban_type' => 'manual_close',
                                    'starts_at' => now(),
                                    'ends_at' => now()->addDays(3),
                                ]);
                            }
                        }
                    } catch (\Throwable $e) {}
                }
            }

            return redirect()->route('futures.pnl_history')->with('success', 'درخواست بستن موقعیت ارسال شد و سوابق PnL به‌روزرسانی شد.');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Futures market close failed: ' . $e->getMessage());

            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());

            return redirect()->route('futures.pnl_history')->withErrors(['msg' => $userFriendlyMessage]);
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

    /**
     * Display P&L history for the authenticated user
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

        // Closed trades (paginate) and order by closed_at desc
        $closedTradesQuery = clone $tradesQuery;
        $closedTrades = $closedTradesQuery->whereNotNull('closed_at')->latest('closed_at')->paginate(20);

        // Open trades (closed_at is null)
        $openTradesQuery = Trade::forUser(auth()->id());
        if ($currentExchange) {
            $openTradesQuery->accountType($currentExchange->is_demo_active);
        }
        $openTrades = $openTradesQuery->whereNull('closed_at')->get();

        // Map open trade order_ids to Order model ids for close actions
        $orderModelByOrderId = [];
        if ($openTrades->count() > 0) {
            $orderIds = $openTrades->pluck('order_id')->filter()->unique()->values()->all();
            if (!empty($orderIds)) {
                $ordersQuery = Order::forUser(auth()->id());
                if ($currentExchange) {
                    $ordersQuery->accountType($currentExchange->is_demo_active);
                }
                $orders = $ordersQuery->whereIn('order_id', $orderIds)->get();
                foreach ($orders as $o) {
                    $orderModelByOrderId[$o->order_id] = $o->id;
                }
            }
        }

        return view('futures.pnl_history', [
            'closedTrades' => $closedTrades,
            'openTrades' => $openTrades,
            'orderModelByOrderId' => $orderModelByOrderId,
        ]);
    }

    /**
     * Display trading journal for the authenticated user
     */
    public function journal(Request $request)
    {
        $user = auth()->user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;

        // Base query for synchronized trades
        $tradesQuery = Trade::forUser($user->id)
            ->where('synchronized', 1)
            ->whereNotNull('closed_at');

        if ($currentExchange) {
            $tradesQuery->accountType($currentExchange->is_demo_active);
        }

        // Filtering logic
        $month = $request->input('month', 'last6months');
        $side = $request->input('side', 'all');

        if ($month === 'last6months') {
            $tradesQuery->where('closed_at', '>=', now()->subMonths(6));
        } else {
            // Assumes month is in 'YYYY-MM' format
            $tradesQuery->whereYear('closed_at', '=', substr($month, 0, 4))
                ->whereMonth('closed_at', '=', substr($month, 5, 2));
        }

        if ($side !== 'all') {
            $tradesQuery->where('side', $side);
        }

        $trades = $tradesQuery->with('order')->latest('closed_at')->get();

        // Calculate statistics
        $totalPnl = $trades->sum('pnl');
        $totalProfits = $trades->where('pnl', '>', 0)->sum('pnl');
        $totalLosses = $trades->where('pnl', '<', 0)->sum('pnl');
        $totalTrades = $trades->count();
        $biggestProfit = $trades->max('pnl') ?? 0;
        $biggestLoss = $trades->min('pnl') ?? 0;

        $profitableTrades = $trades->where('pnl', '>', 0);
        $losingTrades = $trades->where('pnl', '<', 0);

        $profitableTradesCount = $profitableTrades->count();
        $losingTradesCount = $losingTrades->count();

        // Calculate average risk percentage and Risk/Reward Ratio from related orders
        $totalRiskPercentage = 0;
        $totalRRR = 0;
        $riskCalculatedTrades = 0;
        $rrrCalculatedTrades = 0;

        foreach ($trades as $trade) {
            if ($trade->order && $trade->order->entry_price > 0) {
                // Average Risk %
                $totalRiskPercentage += abs($trade->order->entry_price - $trade->order->sl) / $trade->order->entry_price * 100;
                $riskCalculatedTrades++;

                // Risk/Reward Ratio
                $slDistance = abs($trade->order->entry_price - $trade->order->sl);
                if ($slDistance > 0) {
                    $tpDistance = abs($trade->order->tp - $trade->order->entry_price);
                    $totalRRR += $tpDistance / $slDistance;
                    $rrrCalculatedTrades++;
                }
            }
        }
        $averageRisk = $riskCalculatedTrades > 0 ? $totalRiskPercentage / $riskCalculatedTrades : 0;
        $averageRRR = $rrrCalculatedTrades > 0 ? $totalRRR / $rrrCalculatedTrades : 0;

        // Get the first trade in the period to find the initial capital
        $firstTrade = $trades->sortBy('closed_at')->first();
        $initialCapital = $firstTrade && $firstTrade->order ? (float) $firstTrade->order->balance_at_creation : 1000;

        // Fallback for older trades without balance_at_creation
        if ($initialCapital == 1000) {
            try {
                $exchangeService = $this->getExchangeService();
                $resolvedCurrent = $this->resolveCapitalUSD($exchangeService);
                if ($resolvedCurrent > 0) {
                    $initialCapital = $resolvedCurrent - $totalPnl;
                }
            } catch (\Exception $e) {
                Log::error('Could not resolve initial capital for journal page: ' . $e->getMessage());
            }
        }

        // Avoid division by zero
        $initialCapital = $initialCapital > 0 ? $initialCapital : 1;

        // Per-trade percent calculations based on each trade's balance_at_creation
        $perTradePercents = $trades->map(function ($trade) use ($initialCapital) {
            $capital = ($trade->order && (float)($trade->order->balance_at_creation) > 0)
                ? (float)$trade->order->balance_at_creation
                : $initialCapital;
            return $capital > 0 ? ((float)$trade->pnl / $capital) * 100.0 : 0.0;
        });

        // Aggregate percents
        $totalPnlPercent = $perTradePercents->sum();
        $totalProfitPercent = $trades->zip($perTradePercents)->reduce(function ($carry, $pair) {
            [$trade, $percent] = $pair;
            return $carry + ($trade->pnl > 0 ? $percent : 0);
        }, 0);
        $totalLossPercent = $trades->zip($perTradePercents)->reduce(function ($carry, $pair) {
            [$trade, $percent] = $pair;
            return $carry + ($trade->pnl < 0 ? $percent : 0);
        }, 0);


        // Prepare data for charts
        $pnlChartData = $trades->sortBy('closed_at')->map(function ($trade, $index) {
            return [
                'x' => 'Trade ' . ($index + 1), // Use trade index as category
                'y' => (float)$trade->pnl,
                'date' => $trade->closed_at->format('Y-m-d H:i') // For tooltip
            ];
        })->values();

        $sortedTrades = $trades->sortBy('closed_at');

        $cumulativePnl = $sortedTrades->reduce(function ($carry, $trade) {
            $lastPnl = $carry->last()['y'] ?? 0;
            $carry->push([
                'x' => $trade->closed_at->format('Y-m-d H:i'),
                'y' => $lastPnl + (float)$trade->pnl,
            ]);
            return $carry;
        }, collect())->values();

        // Cumulative percent as running compound of per-trade percents (ordered by closed_at)
        $sortedPercents = $sortedTrades->values()->map(function ($trade) use ($initialCapital) {
            $capital = ($trade->order && (float)($trade->order->balance_at_creation) > 0)
                ? (float)$trade->order->balance_at_creation
                : $initialCapital;
            return $capital > 0 ? ((float)$trade->pnl / $capital) * 100.0 : 0.0;
        });

        $cumulativePnlPercent = $sortedPercents->reduce(function ($carry, $percent) use ($sortedTrades) {
            $index = $carry->count();
            $trade = $sortedTrades->values()->get($index);
            $last = $carry->last()['y'] ?? 0;
            // Convert percentage to multiplier and compound multiplicatively
            $multiplier = 1 + ($percent / 100.0);
            $newValue = ($last / 100.0 + 1) * $multiplier - 1; // Convert last to multiplier, multiply, convert back to percent
            $carry->push([
                'x' => $trade->closed_at->format('Y-m-d H:i'),
                'y' => $newValue * 100.0,
            ]);
            return $carry;
        }, collect())->values();


        // Get available months for the filter dropdown
        $availableMonths = Trade::forUser($user->id)
            ->where('synchronized', 1)
            ->whereNotNull('closed_at')
            ->selectRaw("DATE_FORMAT(closed_at, '%Y-%m') as month")
            ->distinct()
            ->orderBy('month', 'desc')
            ->pluck('month');

        // User Rankings
        $isDemo = $currentExchange ? $currentExchange->is_demo_active : 0;

        $baseQuery = DB::table('trades')
            ->join('user_exchanges', 'trades.user_exchange_id', '=', 'user_exchanges.id')
            ->where('trades.synchronized', 1)
            ->whereNotNull('trades.closed_at');

        if ($month === 'last6months') {
            $baseQuery->where('trades.closed_at', '>=', now()->subMonths(6));
        } else {
            $baseQuery->whereYear('trades.closed_at', '=', substr($month, 0, 4))
                ->whereMonth('trades.closed_at', '=', substr($month, 5, 2));
        }

        $rankings = $baseQuery
            ->select('user_exchanges.user_id', DB::raw('SUM(trades.pnl) as total_pnl'))
            ->groupBy('user_exchanges.user_id')
            ->orderBy('total_pnl', 'desc')
            ->get();

        $pnlRank = $rankings->search(fn($r) => $r->user_id == $user->id);
        $pnlRank = $pnlRank !== false ? $pnlRank + 1 : null;

        // PNL Percentage Ranking
        $allTradesQuery = DB::table('trades')
            ->join('user_exchanges', 'trades.user_exchange_id', '=', 'user_exchanges.id')
            ->join('orders', 'trades.order_id', '=', 'orders.order_id')
            ->where('trades.synchronized', 1)
            ->whereNotNull('trades.closed_at')
            ->whereNotNull('orders.balance_at_creation')
            ->where('orders.balance_at_creation', '>', 0)
            ->select('user_exchanges.user_id', 'trades.pnl', 'trades.closed_at', 'orders.balance_at_creation');

        if ($month === 'last6months') {
            $allTradesQuery->where('trades.closed_at', '>=', now()->subMonths(6));
        } else {
            $allTradesQuery->whereYear('trades.closed_at', '=', substr($month, 0, 4))
                ->whereMonth('trades.closed_at', '=', substr($month, 5, 2));
        }

        $allTrades = $allTradesQuery->get();

        $userStats = $allTrades->groupBy('user_id')->map(function ($userTrades) {
            // Sum per-trade percents for user
            $sumPercent = $userTrades->reduce(function ($carry, $t) {
                $capital = (float) $t->balance_at_creation;
                $percent = $capital > 0 ? ((float)$t->pnl / $capital) * 100.0 : 0.0;
                return $carry + $percent;
            }, 0.0);

            $firstTrade = $userTrades->sortBy('closed_at')->first();
            return [
                'user_id' => $firstTrade->user_id,
                'pnl_percent' => $sumPercent,
            ];
        });

        $percentRankings = $userStats->sortByDesc('pnl_percent')->values();
        $pnlPercentRank = $percentRankings->search(fn($r) => $r['user_id'] == $user->id);
        $pnlPercentRank = $pnlPercentRank !== false ? $pnlPercentRank + 1 : null;


        return view('futures.journal', compact(
            'trades',
            'totalPnl',
            'totalProfits',
            'totalLosses',
            'totalTrades',
            'biggestProfit',
            'biggestLoss',
            'averageRisk',
            'averageRRR',
            'profitableTradesCount',
            'losingTradesCount',
            'pnlChartData',
            'cumulativePnl',
            'availableMonths',
            'month',
            'side',
            'totalPnlPercent',
            'totalProfitPercent',
            'totalLossPercent',
            'pnlRank',
            'pnlPercentRank',
            'cumulativePnlPercent'
        ));
    }
    /**
     * Add hedge mode parameters to order based on exchange
     *
     * @param array $orderParams Reference to order parameters array
     * @param string $exchangeName Name of the exchange (bybit, binance, bingx)
     * @param string $side Original position side ('Buy' or 'Sell') - NOT the closing order side
     */
    private function addHedgeModeParameters(array &$orderParams, string $exchangeName, string $side)
    {
        switch (strtolower($exchangeName)) {
            case 'bybit':
                // Bybit hedge mode: positionIdx indicates which position to affect
                // positionIdx = 1 for LONG positions (original Buy orders)
                // positionIdx = 2 for SHORT positions (original Sell orders)
                $orderParams['positionIdx'] = ($side === 'Buy') ? 1 : 2;
                break;

            case 'binance':
                // Binance hedge mode: positionSide indicates which position to affect
                // positionSide = 'LONG' for LONG positions (original Buy orders)
                // positionSide = 'SHORT' for SHORT positions (original Sell orders)
                $orderParams['positionSide'] = ($side === 'Buy') ? 'LONG' : 'SHORT';
                break;

            case 'bingx':
                // BingX hedge mode: positionSide indicates which position to affect
                // positionSide = 'LONG' for LONG positions (original Buy orders)
                // positionSide = 'SHORT' for SHORT positions (original Sell orders)
                $orderParams['positionSide'] = ($side === 'Buy') ? 'LONG' : 'SHORT';
                break;
        }
    }
}
