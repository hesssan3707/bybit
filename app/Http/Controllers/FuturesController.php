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

        // Compute active opening-ban for UI (independent of strict mode)
        $activeBan = null;
        $banRemainingSeconds = null;
        try {
            if ($user && ($user->future_strict_mode ?? false)) {
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                $activeBan = UserBan::active()
                    ->forUser($user->id)
                    ->accountType($isDemo)
                    ->whereIn('ban_type', ['single_loss', 'double_loss', 'exchange_force_close'])
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

    public function edit(\App\Models\Order $order)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => $exchangeStatus['message']]);
        }

        // Verify order belongs to authenticated user via user_exchange
        $user = auth()->user();
        $userExchangeIds = $user->activeExchanges()->pluck('id')->toArray();
        if (!in_array($order->user_exchange_id, $userExchangeIds)) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => 'شما مجاز به ویرایش این سفارش نیستید.']);
        }

        // Only allow editing of pending orders
        if ($order->status !== 'pending') {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => 'تنها سفارش‌های در انتظار قابل ویرایش هستند.']);
        }

        $availableMarkets = ['BTCUSDT', 'ETHUSDT', 'ADAUSDT', 'DOTUSDT', 'BNBUSDT', 'XRPUSDT', 'SOLUSDT', 'TRXUSDT', 'DOGEUSDT', 'LTCUSDT'];
        $selectedMarket = null;
        $symbol = $order->symbol;

        if ($user->future_strict_mode && $user->selected_market) {
            // Strict mode: enforce selected market
            $selectedMarket = $user->selected_market;
            $symbol = $selectedMarket;
        }

        // Fetch market price for TradingView initialization (match create())
        $marketPrice = '0';
        if ($exchangeStatus['hasActiveExchange']) {
            try {
                $exchangeService = $this->getExchangeService();
                $tickerInfo = $exchangeService->getTickerInfo($symbol);
                $price = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);
                $marketPrice = (string)$price;
            } catch (\Exception $e) {
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                if ($currentExchange) {
                    try {
                        $this->handleApiException($e, $currentExchange, 'futures');
                    } catch (\Exception $handledException) {
                        \Illuminate\Support\Facades\Log::error("Could not fetch market price (edit): " . $e->getMessage());
                    }
                }
            }
        }

        // Prefill values from order
        $entry1 = (float)($order->entry_low ?? $order->entry_price);
        $entry2 = (float)($order->entry_high ?? $order->entry_price);
        $sl = (float)$order->sl;
        $tp = (float)$order->tp;
        $steps = (int)($order->steps ?? 1);
        $expireMinutes = $order->expire_minutes;
        $cancelPrice = $order->cancel_price;

        // Compute a reasonable default risk percentage using saved balance
        $avgEntry = ($entry1 + $entry2) / 2.0;
        $slDistance = abs($avgEntry - $sl);
        $capitalUSD = (float)($order->balance_at_creation ?? 0);
        $defaultRisk = null;
        if ($capitalUSD > 0 && $slDistance > 0) {
            $defaultRisk = round(($order->amount * $slDistance / $capitalUSD) * 100, 2);
        } else {
            $defaultRisk = \App\Models\UserAccountSetting::getDefaultRisk($user->id) ?? 10;
        }

        // Apply strict mode cap
        if ($user->future_strict_mode) {
            $defaultRisk = min($defaultRisk, 10);
        }
        
        // Get user defaults (to mirror create page behavior in UI)
        $defaultFutureOrderSteps = \App\Models\UserAccountSetting::getDefaultFutureOrderSteps($user->id);
        $defaultExpirationMinutes = \App\Models\UserAccountSetting::getDefaultExpirationTime($user->id);

        // Compute active opening-ban for UI when strict mode is active
        $activeBan = null;
        $banRemainingSeconds = null;
        try {
            if ($user && ($user->future_strict_mode ?? false)) {
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                $activeBan = \App\Models\UserBan::active()
                    ->forUser($user->id)
                    ->accountType($isDemo)
                    ->whereIn('ban_type', ['single_loss', 'double_loss', 'exchange_force_close'])
                    ->orderBy('ends_at', 'desc')
                    ->first();
                if ($activeBan) {
                    $banRemainingSeconds = max(0, $activeBan->ends_at->diffInSeconds(now()));
                }
            }
        } catch (\Throwable $e) {
            // silent failure for UI rendering
        }

        return view('futures.edit_order', [
            'marketPrice' => $marketPrice,
            'hasActiveExchange' => $exchangeStatus['hasActiveExchange'],
            'exchangeMessage' => $exchangeStatus['message'],
            'user' => $user,
            'availableMarkets' => $availableMarkets,
            'selectedMarket' => $selectedMarket,
            'prefill' => [
                'symbol' => $order->symbol,
                'entry1' => $entry1,
                'entry2' => $entry2,
                'sl' => $sl,
                'tp' => $tp,
                'steps' => $steps,
                'expire' => $expireMinutes,
                'risk_percentage' => $defaultRisk,
                'cancel_price' => $cancelPrice,
            ],
            'order' => $order,
            'currentSymbol' => $symbol,
            'defaultFutureOrderSteps' => $defaultFutureOrderSteps,
            'defaultExpiration' => $defaultExpirationMinutes,
            'defaultRisk' => $defaultRisk,
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

        // Enforce opening-ban types before creating orders (independent of strict mode)
        try {
            $user = auth()->user();
            if ($user && ($user->future_strict_mode ?? false)) {
                $currentExchange = $user->currentExchange ?? $user->defaultExchange;
                $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                $activeBan = \App\Models\UserBan::active()
                    ->forUser($user->id)
                    ->accountType($isDemo)
                    ->whereIn('ban_type', ['single_loss', 'double_loss', 'exchange_force_close'])
                    ->orderBy('ends_at', 'desc')
                    ->first();
                if ($activeBan) {
                    $remainingSeconds = max(0, $activeBan->ends_at->diffInSeconds(now()));
                    $remainingFa = $this->formatFaDuration($remainingSeconds);
                    return back()
                        ->withErrors(['msg' => 'ثبت سفارش جدید موقتاً محدود شده است. لطفاً ' . $remainingFa . ' صبر کنید.'])
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
                $pendingQuery = Order::where('user_exchange_id', $currentExchange->id)
                    ->where('is_demo', $currentExchange->is_demo_active)
                    ->where('status', 'pending')
                    ->where('symbol', $symbol)
                    ->where('side', $sideLower);
                // Exclude the order being edited if provided
                if ($request->filled('order_id')) {
                    $pendingQuery->where('id', '!=', (int)$request->input('order_id'));
                }
                $hasPendingSameSide = $pendingQuery->exists();

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

    public function update(Request $request, Order $order)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return back()->withErrors(['msg' => $exchangeStatus['message']])->withInput();
        }

        // Verify order belongs to authenticated user via user_exchange
        $user = auth()->user();
        $userExchangeIds = $user->activeExchanges()->pluck('id')->toArray();
        if (!in_array($order->user_exchange_id, $userExchangeIds)) {
            return back()->withErrors(['msg' => 'شما مجاز به ویرایش این سفارش نیستید.'])->withInput();
        }

        // Only allow editing of pending orders
        if ($order->status !== 'pending') {
            return back()->withErrors(['msg' => 'تنها سفارش‌های در انتظار قابل ویرایش هستند.'])->withInput();
        }

        $validated = $request->validate([
            'entry1' => 'required|numeric',
            'entry2' => 'nullable|numeric',
            'tp'     => 'required|numeric',
            'sl'     => 'required|numeric',
            'steps'  => 'nullable|integer|min:1|max:8',
            'expire' => 'nullable|integer|min:1|max:999',
            'risk_percentage' => 'nullable|numeric|min:0.1',
            'cancel_price' => 'nullable|numeric',
        ]);

        if (!isset($validated['entry2'])) {
            $validated['entry2'] = $validated['entry1'];
        }

        // Strict mode: keep symbol unchanged; only validate if user's selected market conflicts
        if ($user->future_strict_mode && $user->selected_market) {
            // We do not change symbol during edit; if mismatch exists, allow edit but keep symbol as-is
        }

        // Validate entry price against market price when not in local environment
        $pricePrec = 2;
        $avgEntry = ((float)$validated['entry1'] + (float)$validated['entry2']) / 2.0;
        if (!app()->environment('local')) {
            try {
                $exchangeService = $this->getExchangeService();
                $instrumentInfo = $exchangeService->getInstrumentsInfo($order->symbol);

                $instrumentData = null;
                if (!empty($instrumentInfo['list'])) {
                    foreach ($instrumentInfo['list'] as $instrument) {
                        if (($instrument['symbol'] ?? null) === $order->symbol) { $instrumentData = $instrument; break; }
                    }
                }
                if ($instrumentData && isset($instrumentData['priceScale'])) {
                    $pricePrec = (int)$instrumentData['priceScale'];
                }

                $tickerInfo = $exchangeService->getTickerInfo($order->symbol);
                $marketPrice = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);
                if ($marketPrice > 0) {
                    $side = ($validated['sl'] > $avgEntry) ? 'Sell' : 'Buy';
                    if ($side === 'Buy' && $avgEntry > $marketPrice) {
                        return back()->withErrors(['msg' => "برای معامله خرید، قیمت ورود ({$avgEntry}) نمی‌تواند بالاتر از قیمت بازار ({$marketPrice}) باشد."])->withInput();
                    }
                    if ($side === 'Sell' && $avgEntry < $marketPrice) {
                        return back()->withErrors(['msg' => "برای معامله فروش، قیمت ورود ({$avgEntry}) نمی‌تواند پایین‌تر از قیمت بازار ({$marketPrice}) باشد."])->withInput();
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Update validation skipped due to market fetch error: ' . $e->getMessage());
            }
        }

        // Apply DB-only update of order fields
        $order->entry_price = round((float)$validated['entry1'], $pricePrec);
        $order->entry_low   = min((float)$validated['entry1'], (float)$validated['entry2']);
        $order->entry_high  = max((float)$validated['entry1'], (float)$validated['entry2']);
        $order->sl          = round((float)$validated['sl'], $pricePrec);
        $order->tp          = round((float)$validated['tp'], $pricePrec);
        $order->expire_minutes = isset($validated['expire']) ? (int)$validated['expire'] : $order->expire_minutes;
        $order->cancel_price   = isset($validated['cancel_price']) ? (float)$validated['cancel_price'] : null;
        $order->save();

        return redirect()->route('futures.orders')->with('success', 'سفارش با موفقیت ویرایش شد.');
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

    /**
     * Resend an expired order (reactivate existing record) within 30 minutes of expiration
     * - Multi-exchange: use the order's own exchange for API calls
     * - Local environment: skip exchange calls, just reset DB state
     */
    public function resend(Order $order)
    {
        // Check active exchange (for user messaging and access consistency)
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => $exchangeStatus['message']]);
        }

        // Verify order belongs to authenticated user via user_exchange
        $user = auth()->user();
        $userExchangeIds = $user->activeExchanges()->pluck('id')->toArray();
        if (!in_array($order->user_exchange_id, $userExchangeIds)) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => 'شما مجاز به ارسال مجدد این سفارش نیستید.']);
        }

        // Only allow resend for expired orders within window
        if ($order->status !== 'expired' || !$order->closed_at) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => 'فقط سفارش‌های منقضی قابل ارسال مجدد هستند.']);
        }
        $minutesSinceClose = now()->diffInMinutes($order->closed_at);
        if ($minutesSinceClose > 30) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => 'مهلت ارسال مجدد این سفارش به پایان رسیده است.']);
        }

        // Resolve exchange service bound to the original order's account
        $userExchange = $order->userExchange;
        if (!$userExchange || !$userExchange->is_active) {
            return redirect()->route('futures.orders')
                ->withErrors(['msg' => 'حساب صرافی مرتبط با این سفارش فعال نیست.']);
        }

        // Prepare new link id and parameters
        $newOrderLinkId = (string) \Illuminate\Support\Str::uuid();

        try {
            // Get precision info when not in local environment
            $pricePrec = 2;
            $qtyStr = (string) $order->amount; // already stored as final precision at creation

            if (!app()->environment('local')) {
                $exchangeService = \App\Services\Exchanges\ExchangeFactory::createForUserExchange($userExchange);

                // Validate market vs entry consistency
                try {
                    $instrumentInfo = $exchangeService->getInstrumentsInfo($order->symbol);
                    $instrumentData = null;
                    if (!empty($instrumentInfo['list'])) {
                        foreach ($instrumentInfo['list'] as $instrument) {
                            if (($instrument['symbol'] ?? null) === $order->symbol) { $instrumentData = $instrument; break; }
                        }
                    }
                    if ($instrumentData && isset($instrumentData['priceScale'])) {
                        $pricePrec = (int)$instrumentData['priceScale'];
                    }
                } catch (\Throwable $e) {
                    // Continue with defaults if instrument info fails
                }

                $finalPrice = round((float)$order->entry_price, $pricePrec);

                // Build order params (limit order, same SL)
                $sideUpper = ($order->side === 'sell') ? 'Sell' : 'Buy';
                $params = [
                    'category' => 'linear',
                    'symbol' => $order->symbol,
                    'side' => $sideUpper,
                    'orderType' => 'Limit',
                    'qty' => $qtyStr,
                    'price' => (string) $finalPrice,
                    'timeInForce' => 'GTC',
                    'stopLoss'  => (string) round((float)$order->sl, $pricePrec),
                    'orderLinkId' => $newOrderLinkId,
                ];

                // Hedge mode parameters per exchange
                $this->addHedgeModeParameters($params, $userExchange->exchange_name, $sideUpper);

                // Create order on exchange
                $responseData = $exchangeService->createOrder($params);

                // Reset the DB record as reactivated pending
                $order->order_id = $responseData['orderId'] ?? null;
                $order->order_link_id = $newOrderLinkId;
                $order->status = 'pending';
                $order->created_at = now();
                $order->closed_at = null;
                $order->filled_at = null;
                $order->filled_quantity = null;
                $order->save();
            } else {
                // Local: skip exchange calls, just reset DB state
                $finalPrice = round((float)$order->entry_price, $pricePrec);
                $order->order_id = null; // no remote id
                $order->order_link_id = $newOrderLinkId;
                $order->status = 'pending';
                $order->created_at = now();
                $order->closed_at = null;
                $order->filled_at = null;
                $order->filled_quantity = null;
                $order->save();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Futures order resend failed: ' . $e->getMessage());
            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());
            return redirect()->route('futures.orders')->withErrors(['msg' => $userFriendlyMessage]);
        }

        return redirect()->route('futures.orders')->with('success', 'ارسال مجدد سفارش با موفقیت انجام شد.');
    }

    public function close(Request $request, Order $order)
    {
        // Strict-mode: block manual closes if an active close-only ban exists for current account type
        try {
            $user = auth()->user();
            $currentExchange = $user->currentExchange ?? $user->defaultExchange;
            if ($user && $user->future_strict_mode) {
                $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                $activeBan = \App\Models\UserBan::where('user_id', $user->id)
                    ->where('is_demo', $isDemo)
                    ->where('ban_type', 'manual_close')
                    ->where('ends_at', '>', now())
                    ->orderByDesc('ends_at')
                    ->first();
                if ($activeBan) {
                    $remainingSeconds = max(0, $activeBan->ends_at->diffInSeconds(now()));
                    $remainingFa = $this->formatFaDuration($remainingSeconds);
                    return redirect()->route('futures.pnl_history')
                        ->withErrors(['msg' => 'شما مجاز به بستن دستی موقعیت نیستید. لطفاً ' . $remainingFa . ' صبر کنید.']);
                }
            }
        } catch (\Throwable $e) {}

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
                // Create manual-close ban (close-only) only in strict mode
                try {
                    if ($user->future_strict_mode) {
                        $hasActive = \App\Models\UserBan::active()
                            ->forUser($user->id)
                            ->accountType($isDemo)
                            ->where('ban_type', 'manual_close')
                            ->exists();
                        if (!$hasActive) {
                            \App\Models\UserBan::create([
                                'user_id' => $user->id,
                                'is_demo' => $isDemo,
                                'trade_id' => $trade->id,
                                'ban_type' => 'manual_close',
                                'starts_at' => now(),
                                'ends_at' => now()->addDays(7),
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

            // Mark intent and let lifecycle settle PnL/exit details
            if ($trade) {
                $trade->closed_by_user = 1;
                $trade->save();
            }

            // Manual-close ban (close-only) only in strict mode
            try {
                if ($user->future_strict_mode) {
                    $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                    $hasActive = \App\Models\UserBan::active()
                        ->forUser($user->id)
                        ->accountType($isDemo)
                        ->where('ban_type', 'manual_close')
                        ->exists();
                    if (!$hasActive) {
                        \App\Models\UserBan::create([
                            'user_id' => $user->id,
                            'is_demo' => $isDemo,
                            'trade_id' => $trade?->id,
                            'ban_type' => 'manual_close',
                            'starts_at' => now(),
                            'ends_at' => now()->addDays(7),
                        ]);
                    }
                }
            } catch (\Throwable $e) {}

            return redirect()->route('futures.pnl_history')->with('success', 'درخواست بستن موقعیت ارسال شد و سوابق PnL به‌روزرسانی شد.');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Futures market close failed: ' . $e->getMessage());

            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());

            return redirect()->route('futures.pnl_history')->withErrors(['msg' => $userFriendlyMessage]);
        }
    }

    /**
     * Close all open positions for current account (demo/real)
     */
    public function closeAll(Request $request)
    {
        $user = auth()->user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        $isDemo = (bool)($currentExchange?->is_demo_active ?? false);

        // Strict-mode: block if an active manual_close ban exists
        if ($user->future_strict_mode) {
            $activeBan = UserBan::where('user_id', $user->id)
                ->where('is_demo', $isDemo)
                ->where('ban_type', 'manual_close')
                ->where('ends_at', '>', now())
                ->orderByDesc('ends_at')
                ->first();
            if ($activeBan) {
                return redirect()->route('futures.pnl_history')
                    ->withErrors(['msg' => 'شما مجاز به بستن دستی موقعیت نیستید تا تاریخ ' . $activeBan->ends_at->format('Y-m-d H:i') . '']);
            }
        }

        // Check active exchange
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return redirect()->route('futures.pnl_history')
                ->withErrors(['msg' => $exchangeStatus['message']]);
        }

        // Local environment: simulate closes, skip exchange calls
        if (app()->environment('local')) {
            $openTradesQuery = Trade::forUser($user->id);
            if ($currentExchange) {
                $openTradesQuery->accountType($isDemo);
            }
            $openTrades = $openTradesQuery->whereNull('closed_at')->get();
            foreach ($openTrades as $trade) {
                $trade->avg_exit_price = $trade->avg_entry_price;
                $trade->pnl = 0;
                $trade->closed_at = now();
                $trade->closed_by_user = 1;
                $trade->save();
            }
            // Create a single manual_close ban (7 days) in strict mode
            try {
                if ($user->future_strict_mode) {
                    UserBan::create([
                        'user_id' => $user->id,
                        'is_demo' => $isDemo,
                        'trade_id' => null,
                        'ban_type' => 'manual_close',
                        'starts_at' => now(),
                        'ends_at' => now()->addDays(7),
                    ]);
                }
            } catch (\Throwable $e) {}

            return redirect()->route('futures.pnl_history')->with('success', 'همه موقعیت‌ها در محیط محلی به‌صورت آزمایشی بسته شدند.');
        }

        // Production: close all open positions via exchange services
        try {
            $exchangeService = $this->getExchangeService();
            $positionsRaw = $exchangeService->getPositions();

            // Normalize positions list across exchanges
            $list = $positionsRaw['list'] ?? ($positionsRaw['result']['list'] ?? []);
            foreach ($list as $pos) {
                // Determine symbol
                $symbol = $pos['symbol'] ?? ($pos['instrument'] ?? null);
                if (!$symbol) { continue; }

                // Determine qty and side across exchanges
                $qty = null; $side = null;
                if (isset($pos['size'])) { // Bybit/BingX style
                    $qty = (float)$pos['size'];
                    if (isset($pos['side'])) {
                        $s = strtolower((string)$pos['side']);
                        $side = ($s === 'buy' || $s === 'long') ? 'Buy' : (($s === 'sell' || $s === 'short') ? 'Sell' : null);
                    } elseif (isset($pos['positionSide'])) {
                        $ps = strtoupper((string)$pos['positionSide']);
                        $side = ($ps === 'LONG') ? 'Buy' : (($ps === 'SHORT') ? 'Sell' : null);
                    }
                } elseif (isset($pos['positionAmt'])) { // Binance style
                    $amt = (float)$pos['positionAmt'];
                    $qty = abs($amt);
                    if (isset($pos['positionSide'])) {
                        $ps = strtoupper((string)$pos['positionSide']);
                        $side = ($ps === 'LONG') ? 'Buy' : (($ps === 'SHORT') ? 'Sell' : null);
                    } else {
                        $side = $amt > 0 ? 'Buy' : ($amt < 0 ? 'Sell' : null);
                    }
                }

                if ($qty && $qty > 0 && $side) {
                    try {
                        $exchangeService->closePosition($symbol, $side, $qty);
                    } catch (\Exception $e) {
                        Log::warning('Failed to close position during closeAll', [
                            'symbol' => $symbol,
                            'side' => $side,
                            'qty' => $qty,
                            'message' => $e->getMessage(),
                        ]);
                        // Continue closing others
                    }
                }
            }

            // Single manual_close ban (7 days) in strict mode
            try {
                if ($user->future_strict_mode) {
                    UserBan::create([
                        'user_id' => $user->id,
                        'is_demo' => $isDemo,
                        'trade_id' => null,
                        'ban_type' => 'manual_close',
                        'starts_at' => now(),
                        'ends_at' => now()->addDays(7),
                    ]);
                }
            } catch (\Throwable $e) {}

            return redirect()->route('futures.pnl_history')->with('success', 'درخواست بستن همه موقعیت‌ها ارسال شد.');
        } catch (\Exception $e) {
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

        // Ban flags for UI gating (independent of strict mode)
        $strictModeActive = (bool)($user->future_strict_mode ?? false);
        $manualCloseBanActive = false;
        $manualCloseBanEndsAt = null;
        $manualCloseBanRemainingFa = null;
        if ($currentExchange) {
            try {
                $activeBan = UserBan::where('user_id', $user->id)
                    ->where('is_demo', $currentExchange->is_demo_active)
                    ->where('ban_type', 'manual_close')
                    ->where('ends_at', '>', now())
                    ->orderByDesc('ends_at')
                    ->first();
                if ($activeBan) {
                    $manualCloseBanActive = true;
                    $manualCloseBanEndsAt = $activeBan->ends_at;
                    $secs = max(0, $activeBan->ends_at->diffInSeconds(now()));
                    $manualCloseBanRemainingFa = $this->formatFaDuration($secs);
                }
            } catch (\Throwable $e) {}
        }

        return view('futures.pnl_history', [
            'closedTrades' => $closedTrades,
            'openTrades' => $openTrades,
            'orderModelByOrderId' => $orderModelByOrderId,
            'strictModeActive' => $strictModeActive,
            'manualCloseBanActive' => $manualCloseBanActive,
            'manualCloseBanEndsAt' => $manualCloseBanEndsAt,
            'manualCloseBanRemainingFa' => $manualCloseBanRemainingFa,
        ]);
    }

    /**
     * Display trading journal for the authenticated user
     */
    public function journal(Request $request)
    {
        $user = auth()->user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        // Use current exchange account type (demo/real)
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        $side = $request->input('side', 'all'); // 'all' | 'buy' | 'sell'
        $userExchangeId = $request->input('user_exchange_id', 'all');

        // Ensure default period exists and pick selected period
        $service = new \App\Services\JournalPeriodService();
        $service->ensureDefaultPeriod($user->id, $isDemo);

        $selectedPeriodId = $request->input('period_id');
        $selectedPeriod = null;
        if ($selectedPeriodId) {
            $selectedPeriod = \App\Models\UserPeriod::forUser($user->id)
                ->accountType($isDemo)
                ->where('id', $selectedPeriodId)
                ->first();
        }
        if (!$selectedPeriod) {
            // Prefer the most recently started active period; fallback to latest default
            $selectedPeriod = \App\Models\UserPeriod::forUser($user->id)
                ->accountType($isDemo)
                ->where('is_active', true)
                ->orderBy('started_at', 'desc')
                ->first();
            if (!$selectedPeriod) {
                $selectedPeriod = \App\Models\UserPeriod::forUser($user->id)
                    ->accountType($isDemo)
                    ->default()
                    ->orderBy('started_at', 'desc')
                    ->first();
            }
        }

        // Load metrics (precomputed for all exchanges, computed on-demand for a specific account)
        $metrics = [
            'trade_count' => 0,
            'total_pnl' => 0.0,
            'profits_sum' => 0.0,
            'losses_sum' => 0.0,
            'biggest_profit' => 0.0,
            'biggest_loss' => 0.0,
            'wins' => 0,
            'losses' => 0,
            'avg_risk_percent' => 0.0,
            'avg_rrr' => 0.0,
            'pnl_per_trade' => [],
            'per_trade_percent' => [],
            'cum_pnl' => [],
            'cum_pnl_percent' => [],
        ];

        if ($selectedPeriod) {
            if ($userExchangeId !== 'all') {
                // Validate ownership and compute metrics for specific account
                $ue = \App\Models\UserExchange::where('id', $userExchangeId)
                    ->where('user_id', $user->id)
                    ->first();
                if ($ue && ((bool)$ue->is_demo_active === $isDemo)) {
                    $metrics = (new \App\Services\JournalPeriodService())->
                        computeMetricsFor($selectedPeriod, [$ue->id], $side);
                }
            } else {
                // Use precomputed metrics on the period
                if ($side === 'buy') {
                    $metrics = $selectedPeriod->metrics_buy ?? $metrics;
                } elseif ($side === 'sell') {
                    $metrics = $selectedPeriod->metrics_sell ?? $metrics;
                } else {
                    $metrics = $selectedPeriod->metrics_all ?? $metrics;
                }
            }
        }

        // Map metrics to view variables
        $totalPnl = (float)($metrics['total_pnl'] ?? 0.0);
        $totalProfits = (float)($metrics['profits_sum'] ?? 0.0);
        $totalLosses = (float)($metrics['losses_sum'] ?? 0.0);
        $totalTrades = (int)($metrics['trade_count'] ?? 0);
        $biggestProfit = (float)($metrics['biggest_profit'] ?? 0.0);
        $biggestLoss = (float)($metrics['biggest_loss'] ?? 0.0);
        $profitableTradesCount = (int)($metrics['wins'] ?? 0);
        $losingTradesCount = (int)($metrics['losses'] ?? 0);
        $averageRisk = (float)($metrics['avg_risk_percent'] ?? 0.0);
        $averageRRR = (float)($metrics['avg_rrr'] ?? 0.0);

        $pnlChartData = $metrics['pnl_per_trade'] ?? [];
        $cumulativePnl = $metrics['cum_pnl'] ?? [];
        $cumulativePnlPercent = $metrics['cum_pnl_percent'] ?? [];

        // Percent aggregates from series
        $perTradePercent = collect($metrics['per_trade_percent'] ?? []);
        $pnlPerTrade = collect($metrics['pnl_per_trade'] ?? []);
        $totalPnlPercent = (float) $perTradePercent->sum('y');
        $totalProfitPercent = (float) $pnlPerTrade->zip($perTradePercent)->reduce(function ($carry, $pair) {
            [$pnlItem, $percentItem] = $pair;
            $pnlVal = is_array($pnlItem) ? ($pnlItem['y'] ?? 0) : 0;
            $percentVal = is_array($percentItem) ? ($percentItem['y'] ?? 0) : 0;
            return $carry + ($pnlVal > 0 ? $percentVal : 0);
        }, 0.0);
        $totalLossPercent = (float) $pnlPerTrade->zip($perTradePercent)->reduce(function ($carry, $pair) {
            [$pnlItem, $percentItem] = $pair;
            $pnlVal = is_array($pnlItem) ? ($pnlItem['y'] ?? 0) : 0;
            $percentVal = is_array($percentItem) ? ($percentItem['y'] ?? 0) : 0;
            return $carry + ($pnlVal < 0 ? $percentVal : 0);
        }, 0.0);

        // Rankings within the selected period window and filters
        $pnlRank = null;
        $pnlPercentRank = null;
        if ($selectedPeriod) {
            $rankBase = DB::table('trades')
                ->join('user_exchanges', 'trades.user_exchange_id', '=', 'user_exchanges.id')
                ->where('trades.synchronized', 1)
                ->whereNotNull('trades.closed_at')
                ->where('trades.is_demo', $isDemo)
                ->whereBetween('trades.closed_at', [
                    $selectedPeriod->started_at,
                    $selectedPeriod->ended_at ?? now(),
                ]);

            if ($side !== 'all') {
                $rankBase->whereIn('trades.side', [$side, ucfirst($side)]);
            }
            if ($userExchangeId !== 'all') {
                $rankBase->where('trades.user_exchange_id', $userExchangeId);
            }

            $rankings = $rankBase
                ->select('user_exchanges.user_id', DB::raw('SUM(trades.pnl) as total_pnl'))
                ->groupBy('user_exchanges.user_id')
                ->orderBy('total_pnl', 'desc')
                ->get();

            $pIndex = $rankings->search(fn($r) => $r->user_id == $user->id);
            $pnlRank = $pIndex !== false ? ($pIndex + 1) : null;

            // Percent ranking
            $percentQuery = DB::table('trades')
                ->join('user_exchanges', 'trades.user_exchange_id', '=', 'user_exchanges.id')
                ->join('orders', 'trades.order_id', '=', 'orders.order_id')
                ->where('trades.synchronized', 1)
                ->whereNotNull('trades.closed_at')
                ->where('trades.is_demo', $isDemo)
                ->whereNotNull('orders.balance_at_creation')
                ->where('orders.balance_at_creation', '>', 0)
                ->whereBetween('trades.closed_at', [
                    $selectedPeriod->started_at,
                    $selectedPeriod->ended_at ?? now(),
                ])
                ->select('user_exchanges.user_id', 'trades.pnl', 'trades.closed_at', 'orders.balance_at_creation');

            if ($side !== 'all') {
                $percentQuery->whereIn('trades.side', [$side, ucfirst($side)]);
            }
            if ($userExchangeId !== 'all') {
                $percentQuery->where('trades.user_exchange_id', $userExchangeId);
            }

            $allTrades = $percentQuery->get();
            $userStats = $allTrades->groupBy('user_id')->map(function ($userTrades) {
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
            $ppIndex = $percentRankings->search(fn($r) => $r['user_id'] == $user->id);
            $pnlPercentRank = $ppIndex !== false ? ($ppIndex + 1) : null;
        }

        // Build selectors: periods and exchanges
        // Cleanup: remove ended periods with no records (trade_count = 0)
        try {
            \App\Models\UserPeriod::forUser($user->id)
                ->accountType($isDemo)
                ->where('is_active', false)
                ->whereNotNull('ended_at')
                ->get()
                ->each(function ($p) {
                    $tc = (int)($p->metrics_all['trade_count'] ?? 0);
                    if ($tc === 0) { $p->delete(); }
                });
        } catch (\Throwable $e) {}

        $periods = \App\Models\UserPeriod::forUser($user->id)
            ->accountType($isDemo)
            ->orderByDesc('is_default')
            ->orderBy('started_at', 'desc')
            ->get();

        $exchanges = \App\Models\UserExchange::where('user_id', $user->id)
            ->where('is_demo_active', $isDemo)
            ->get();

        return view('futures.journal', [
            'side' => $side,
            'selectedPeriod' => $selectedPeriod,
            'periods' => $periods,
            'exchangeOptions' => $exchanges,
            'userExchangeId' => $userExchangeId,
            'totalPnl' => $totalPnl,
            'totalProfits' => $totalProfits,
            'totalLosses' => $totalLosses,
            'totalTrades' => $totalTrades,
            'biggestProfit' => $biggestProfit,
            'biggestLoss' => $biggestLoss,
            'averageRisk' => $averageRisk,
            'averageRRR' => $averageRRR,
            'profitableTradesCount' => $profitableTradesCount,
            'losingTradesCount' => $losingTradesCount,
            'pnlChartData' => $pnlChartData,
            'cumulativePnl' => $cumulativePnl,
            'totalPnlPercent' => $totalPnlPercent,
            'totalProfitPercent' => $totalProfitPercent,
            'totalLossPercent' => $totalLossPercent,
            'pnlRank' => $pnlRank,
            'pnlPercentRank' => $pnlPercentRank,
            'cumulativePnlPercent' => $cumulativePnlPercent,
        ]);
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

    /**
     * Format a duration in seconds into Persian words: X روز و Y ساعت و Z دقیقه
     */
    private function formatFaDuration(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) { $parts[] = $days . ' روز'; }
        if ($hours > 0 || $days > 0) { $parts[] = $hours . ' ساعت'; }
        $parts[] = $minutes . ' دقیقه';

        return implode(' و ', $parts);
    }

    /**
     * Normalize klines response from different exchanges to a unified array
     * [ { time: epoch_seconds, open: float, high: float, low: float, close: float } ]
     */
    private function normalizeKlines(string $exchangeName, $raw): array
    {
        $name = strtolower($exchangeName);
        $candles = [];

        try {
            if ($name === 'binance') {
                // Raw is numeric arrays: [openTime, open, high, low, close, volume, closeTime, ...]
                foreach ((array)$raw as $row) {
                    if (!is_array($row) || count($row) < 5) { continue; }
                    $candles[] = [
                        'time' => (int) floor(((int)$row[0]) / 1000),
                        'open' => (float) $row[1],
                        'high' => (float) $row[2],
                        'low'  => (float) $row[3],
                        'close'=> (float) $row[4],
                    ];
                }
            } elseif ($name === 'bybit') {
                // Raw is result object, data may be in ['list'] with arrays: [start, open, high, low, close, volume, turnover]
                $list = is_array($raw) && array_key_exists('list', $raw) ? ($raw['list'] ?? []) : (array)$raw;
                foreach ((array)$list as $row) {
                    if (is_array($row)) {
                        // Numeric indexed array
                        if (isset($row[0], $row[1], $row[2], $row[3], $row[4])) {
                            $t = (int) $row[0];
                            $candles[] = [
                                'time' => (int) floor($t / 1000),
                                'open' => (float) $row[1],
                                'high' => (float) $row[2],
                                'low'  => (float) $row[3],
                                'close'=> (float) $row[4],
                            ];
                            continue;
                        }
                        // Associative array
                        $t = isset($row['start']) ? (int)$row['start'] : (int)($row['openTime'] ?? $row['startTime'] ?? 0);
                        $o = (float)($row['open'] ?? 0);
                        $h = (float)($row['high'] ?? 0);
                        $l = (float)($row['low'] ?? 0);
                        $c = (float)($row['close'] ?? 0);
                        if ($t > 0 && $o && $h && $l && $c) {
                            $candles[] = [
                                'time' => (int) floor($t / 1000),
                                'open' => $o, 'high' => $h, 'low' => $l, 'close' => $c,
                            ];
                        }
                    }
                }
            } else { // bingx
                // Raw is ['data'] -> may be arrays: [openTime, open, high, low, close, volume]
                $rows = is_array($raw) && array_key_exists('kline', $raw) ? ($raw['kline'] ?? []) : ((array)$raw);
                foreach ($rows as $row) {
                    if (!is_array($row)) { continue; }
                    if (isset($row[0], $row[1], $row[2], $row[3], $row[4])) {
                        $candles[] = [
                            'time' => (int) floor(((int)$row[0]) / 1000),
                            'open' => (float) $row[1],
                            'high' => (float) $row[2],
                            'low'  => (float) $row[3],
                            'close'=> (float) $row[4],
                        ];
                        continue;
                    }
                    // Associative fallback
                    $t = isset($row['openTime']) ? (int)$row['openTime'] : (int)($row['time'] ?? 0);
                    $o = (float)($row['open'] ?? 0);
                    $h = (float)($row['high'] ?? 0);
                    $l = (float)($row['low'] ?? 0);
                    $c = (float)($row['close'] ?? 0);
                    if ($t && $o && $h && $l && $c) {
                        $candles[] = [
                            'time' => (int) floor($t / 1000),
                            'open' => $o, 'high' => $h, 'low' => $l, 'close' => $c,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to normalize klines: ' . $e->getMessage());
        }

        // Sort by time ascending
        usort($candles, function($a, $b) { return $a['time'] <=> $b['time']; });
        return $candles;
    }

    /**
     * Provide static chart snapshot data for a filled order
     */
    public function chartData(Request $request, Order $order)
    {
        // Check active exchange access for the view route group
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return response()->json(['success' => false, 'message' => $exchangeStatus['message']], 403);
        }

        // Verify ownership
        $user = auth()->user();
        $userExchangeIds = $user->activeExchanges()->pluck('id')->toArray();
        if (!in_array($order->user_exchange_id, $userExchangeIds)) {
            return response()->json(['success' => false, 'message' => 'شما مجاز به مشاهده این سفارش نیستید.'], 403);
        }

        // Only for filled orders
        if (strtolower($order->status) !== 'filled') {
            return response()->json(['success' => false, 'message' => 'فقط برای سفارش‌های اجرا شده قابل نمایش است.'], 400);
        }

        try {
            // Use the exchange account tied to this order
            $userExchange = $order->userExchange;
            $exchangeService = ExchangeFactory::createForUserExchange($userExchange);
            $exchangeName = method_exists($exchangeService, 'getExchangeName') ? $exchangeService->getExchangeName() : 'bybit';

            // Determine timeframe: query param or user default, allowed list
            $allowedTfs = ['1m','5m','15m','1h','4h'];
            $userDefaultTf = \App\Models\UserAccountSetting::getUserSetting($user->id, 'default_timeframe', '15m');
            $tfRequested = strtolower((string)$request->query('tf', $userDefaultTf));
            $timeframe = in_array($tfRequested, $allowedTfs, true) ? $tfRequested : '15m';

            // Compute window anchored to entry and exit
            $tfSeconds = match ($timeframe) {
                '1m' => 60,
                '5m' => 300,
                '15m' => 900,
                '1h' => 3600,
                '4h' => 14400,
                default => 900,
            };
            // Entry timestamp: prefer filled_at, then created_at; final fallback resolved after candle fetch
            $entryAt = $order->filled_at ? $order->filled_at->getTimestamp() : ($order->created_at ? $order->created_at->getTimestamp() : null);
            $trade = $order->trade; // hasOne relation
            $exitAt = $trade ? ($trade->closed_at ? $trade->closed_at->getTimestamp() : null) : null;

            // Provisional window: if entry missing, anchor around "now"; will re-anchor after candle fetch
            $provisionalEntry = $entryAt ?? (int) floor(now()->getTimestamp());
            $startTs = $provisionalEntry - (60 * $tfSeconds);
            $endTs = ($exitAt ?? ($provisionalEntry + (10 * $tfSeconds))) + 0; // include 10 candles after exit; if no exit, after entry

            // Request enough candles to cover window; filter afterwards
            $barsBetween = ($exitAt && $entryAt) ? max(0, (int)floor(($exitAt - $entryAt) / $tfSeconds)) : 10;
            $limit = min(500, 60 + $barsBetween + 10 + 50); // add margin

            if (!method_exists($exchangeService, 'getKlines')) {
                throw new \Exception('این صرافی از دریافت کندل‌ها پشتیبانی نمی‌کند.');
            }
            // Localhost: skip exchange call and generate synthetic candles
            if (app()->environment('local')) {
                $candles = $this->generateSyntheticCandles($startTs, $endTs, $tfSeconds, (float)$order->entry_price, $order->side);
            } else {
                $raw = $exchangeService->getKlines($order->symbol, $timeframe, $limit);
                $candles = $this->normalizeKlines($exchangeName, $raw);
                // Filter to requested time window
                $candles = array_values(array_filter($candles, function($c) use ($startTs, $endTs) {
                    $t = (int)($c['time'] ?? 0);
                    return $t >= (int)floor($startTs) && $t <= (int)floor($endTs);
                }));
                // Fallback: if empty due to API window/limit, synthesize
                if (count($candles) === 0) {
                    $candles = $this->generateSyntheticCandles($startTs, $endTs, $tfSeconds, (float)$order->entry_price, $order->side);
                }
            }

            // If original entry timestamp was missing, re-anchor window to earliest candle
            if (!$entryAt && count($candles) > 0) {
                $earliest = $candles[0]['time'];
                // Recompute window relative to earliest candle
                $entryAt = (int) $earliest;
                $startTs = $entryAt - (60 * $tfSeconds);
                $endTs = ($exitAt ?? ($entryAt + (10 * $tfSeconds)));
                // Re-filter candles to the re-anchored window
                $candles = array_values(array_filter($candles, function($c) use ($startTs, $endTs) {
                    $t = (int)($c['time'] ?? 0);
                    return $t >= (int)floor($startTs) && $t <= (int)floor($endTs);
                }));
            }

            // Build overlay levels and exit info
            $exitPrice = $trade ? (float)($trade->avg_exit_price ?? 0) : 0;

            $payload = [
                'success' => true,
                'data' => [
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'timeframe' => $timeframe,
                    'entry' => (float)$order->entry_price,
                    'tp' => (float)$order->tp,
                    'sl' => (float)$order->sl,
                    'exit' => $exitPrice > 0 ? $exitPrice : null,
                    'exit_at' => $exitAt,
                    // Provide the resolved anchor for frontend alignment; keep original filled_at for metadata
                    'filled_at' => $order->filled_at ? $order->filled_at->getTimestamp() : null,
                    'window' => ['start' => (int)$startTs, 'end' => (int)$endTs],
                    'candles' => $candles,
                ],
            ];

            return response()->json($payload);
        } catch (\Exception $e) {
            $msg = $this->parseExchangeError($e->getMessage());
            return response()->json(['success' => false, 'message' => $msg], 500);
        }
    }

    /**
     * Generate synthetic candles for local/testing without exchange access.
     */
    private function generateSyntheticCandles(int $startTs, int $endTs, int $tfSeconds, float $basePrice, string $side): array
    {
        $candles = [];
        $steps = max(1, (int)floor(($endTs - $startTs) / $tfSeconds));
        $price = max(0.0001, $basePrice);
        $bias = $side === 'Buy' ? 0.0008 : -0.0008; // small directional drift
        for ($i = 0; $i <= $steps; $i++) {
            $t = $startTs + ($i * $tfSeconds);
            // random-like variation using deterministic sin pattern
            $delta = sin($i * 0.35) * 0.003 + $bias;
            $open = $price;
            $close = max(0.0001, $price * (1 + $delta));
            $high = max($open, $close) * (1 + 0.0015);
            $low  = min($open, $close) * (1 - 0.0015);
            $candles[] = [
                'time' => (int)$t,
                'open' => (float)$open,
                'high' => (float)$high,
                'low'  => (float)$low,
                'close'=> (float)$close,
            ];
            $price = $close;
        }
        return $candles;
    }
}
