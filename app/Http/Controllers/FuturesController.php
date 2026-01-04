<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Trade;
use App\Models\UserBan;
use App\Models\UserAccountSetting;
use App\Models\FuturesFundingSnapshot;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use App\Traits\HandlesExchangeAccess;
use App\Traits\ParsesExchangeErrors;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class FuturesController extends Controller
{
    private function resolveCapitalUSD(ExchangeApiServiceInterface $exchangeService): float
    {
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



    public function index(Request $request)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();

        $threeDaysAgo = now()->subDays(3);

        // Get current exchange to filter by account type
        $user = auth()->user();
        $currentExchange = $user->getCurrentExchange();

        $ordersQuery = Order::forUser(auth()->id());

        // Filter by current account type (demo/real) if exchange is available
        if ($currentExchange) {
            $ordersQuery->accountType($currentExchange->is_demo_active);
        }

        // Read filters
        $from = $request->input('from');
        $to = $request->input('to');
        $symbol = $request->input('symbol');
        $hasAnyFilter = filled($from) || filled($to) || filled($symbol);

        // Apply filters when present
        if ($hasAnyFilter) {
            // Apply symbol filter unless strict mode is active
            $strict = (bool) ($user->future_strict_mode ?? false);
            if (!$strict && filled($symbol)) {
                $ordersQuery->where('symbol', $symbol);
            }

            // Date range filters on updated_at
            if (filled($from)) {
                try {
                    $fromDate = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
                    $ordersQuery->where('updated_at', '>=', $fromDate);
                } catch (\Throwable $e) {}
            }
            if (filled($to)) {
                try {
                    $toDate = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
                    $ordersQuery->where('updated_at', '<=', $toDate);
                } catch (\Throwable $e) {}
            }
        } else {
            // Default window: show pending/filled or recent updates (last 3 days)
            $ordersQuery->where(function ($query) use ($threeDaysAgo) {
                $query->whereIn('status', ['pending', 'filled'])
                    ->orWhere('updated_at', '>=', $threeDaysAgo);
            });
        }

        $perPage = 20;
        $page = max(1, (int)($request->input('page', 1)));

        $highlightOid = $request->input('highlight_oid');
        if ($highlightOid) {
            try {
                $target = (clone $ordersQuery)->where('order_id', $highlightOid)->first();
                if ($target) {
                    $beforeCount = (clone $ordersQuery)
                        ->where(function ($q) use ($target) {
                            $q->where('updated_at', '>', $target->updated_at)
                              ->orWhere(function ($q2) use ($target) {
                                  $q2->where('updated_at', $target->updated_at)
                                     ->where('id', '>', $target->id);
                              });
                        })
                        ->count();
                    $page = intdiv($beforeCount, $perPage) + 1;
                }
            } catch (\Throwable $e) {
                // Ignore positioning failures; fall back to default page
            }
        }

        $orders = $ordersQuery->latest('updated_at')->paginate($perPage, ['*'], 'page', $page);

        // Build symbol options for filter dropdown (distinct for user + account type)
        $symbolOptions = Order::forUser(auth()->id())
            ->when($currentExchange, function ($q) use ($currentExchange) {
                $q->accountType($currentExchange->is_demo_active);
            })
            ->whereNotNull('symbol')
            ->select('symbol')
            ->distinct()
            ->orderBy('symbol')
            ->pluck('symbol')
            ->toArray();

        return view('futures.orders_list', [
            'orders' => $orders,
            'hasActiveExchange' => $exchangeStatus['hasActiveExchange'],
            'exchangeMessage' => $exchangeStatus['message'],
            'filterSymbols' => $symbolOptions,
            'initialHighlightOid' => $highlightOid,
        ]);
    }

    public function storeStrategyFeedback(Request $request, \App\Models\Order $order)
    {
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return back()->withErrors(['msg' => $exchangeStatus['message']])->withInput();
        }

        $user = auth()->user();
        $userExchangeIds = $user->activeExchanges()->pluck('id')->toArray();
        if (!in_array($order->user_exchange_id, $userExchangeIds)) {
            return back()->withErrors(['msg' => 'شما مجاز به ثبت پاسخ برای این سفارش نیستید.']);
        }

        if ($order->status !== 'filled') {
            return back()->withErrors(['msg' => 'تنها سفارش‌های تکمیل‌شده قابل ثبت پاسخ هستند.']);
        }

        $validated = $request->validate([
            'answer' => 'required|string|in:yes,no,chart_no_load,no_strategy',
        ]);

        $exists = \App\Models\OrderStrategyFeedback::where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'شما قبلاً برای این سفارش پاسخ داده‌اید.'], 200);
            }
            return back()->withErrors(['msg' => 'شما قبلاً برای این سفارش پاسخ داده‌اید.']);
        }

        \App\Models\OrderStrategyFeedback::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'answer' => $validated['answer'],
        ]);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'پاسخ شما ثبت شد.']);
        }
        return back()->with('success', 'پاسخ شما ثبت شد.');
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

        $marketRiskLevel = 'normal';
        $marketRiskMessage = null;

        if ($exchangeStatus['hasActiveExchange']) {
            try {
                $exchangeService = $this->getExchangeService();
                $tickerInfo = $exchangeService->getTickerInfo($symbol);
                $price = (float)($tickerInfo['list'][0]['lastPrice'] ?? 0);
                $marketPrice = (string)$price;
            } catch (\Exception $e) {
                // Check if this is an access permission error and update validation
                $currentExchange = $user->getCurrentExchange();

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
        $currentExchange = $user->getCurrentExchange();
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;
        $defaultRisk = UserAccountSetting::getDefaultRisk($user->id, $isDemo);
        $defaultFutureOrderSteps = UserAccountSetting::getDefaultFutureOrderSteps($user->id, $isDemo);
        $defaultExpirationMinutes = UserAccountSetting::getDefaultExpirationTime($user->id, $isDemo);
        $tvDefaultInterval = UserAccountSetting::getTradingViewDefaultInterval($user->id, $isDemo);

        $weeklyProfitLimit = null;
        $weeklyLossLimit = null;
        $monthlyProfitLimit = null;
        $monthlyLossLimit = null;
        $weeklyPnlPercent = null;
        $monthlyPnlPercent = null;
        if ($user->future_strict_mode) {
            $settings = \App\Models\UserAccountSetting::getUserSettings($user->id, $isDemo);
            $weeklyProfitLimit = isset($settings['weekly_profit_limit']) ? (float)$settings['weekly_profit_limit'] : null;
            $weeklyLossLimit = isset($settings['weekly_loss_limit']) ? (float)$settings['weekly_loss_limit'] : null;
            $monthlyProfitLimit = isset($settings['monthly_profit_limit']) ? (float)$settings['monthly_profit_limit'] : null;
            $monthlyLossLimit = isset($settings['monthly_loss_limit']) ? (float)$settings['monthly_loss_limit'] : null;

            if (($weeklyProfitLimit !== null && $weeklyProfitLimit > 0) || ($weeklyLossLimit !== null && $weeklyLossLimit > 0)) {
                $banService = new \App\Services\BanService();
                $startOfWeek = \Carbon\Carbon::now(config('app.timezone'))->startOfWeek(\Carbon\Carbon::MONDAY);
                $endOfWeek = $startOfWeek->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                $weeklyPnlPercent = $banService->getPeriodPnlPercent($user->id, $isDemo, $startOfWeek, $endOfWeek);
            }

            if (($monthlyProfitLimit !== null && $monthlyProfitLimit > 0) || ($monthlyLossLimit !== null && $monthlyLossLimit > 0)) {
                $banService = isset($banService) ? $banService : new \App\Services\BanService();
                $startOfMonth = now()->startOfMonth();
                $endOfMonth = now()->endOfMonth();
                $monthlyPnlPercent = $banService->getPeriodPnlPercent($user->id, $isDemo, $startOfMonth, $endOfMonth);
            }
        }

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
        $banMessage = null;
        try {
            if ($user && ($user->future_strict_mode ?? false)) {
                $currentExchange = $user->getCurrentExchange();
                $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                
                // First, quick check for existing active ban
                $activeBan = UserBan::active()
                    ->forUser($user->id)
                    ->accountType($isDemo)
                    ->whereIn('ban_type', ['single_loss', 'double_loss', 'exchange_force_close', 'weekly_profit_limit', 'weekly_loss_limit', 'monthly_profit_limit', 'monthly_loss_limit', 'self_ban_time', 'self_ban_price'])
                    ->orderBy('ends_at', 'desc')
                    ->first();
                
                // Only run heavy ban detection if:
                // 1. User doesn't already have a ban AND
                // 2. User has closed trades within last 5 minutes
                if (!$activeBan && $currentExchange) {
                    $hasRecentClosedTrades = Trade::where('user_exchange_id', $currentExchange->id)
                        ->where('is_demo', $isDemo)
                        ->whereNotNull('closed_at')
                        ->where('closed_at', '>=', now()->subMinutes(5))
                        ->exists();
                    
                    if ($hasRecentClosedTrades) {
                        $banService = new \App\Services\BanService();
                        $banService->checkAndCreateHistoricalBans($user->id, $isDemo);
                        
                        // Check again after creating bans
                        $activeBan = UserBan::active()
                            ->forUser($user->id)
                            ->accountType($isDemo)
                            ->whereIn('ban_type', ['single_loss', 'double_loss', 'exchange_force_close', 'weekly_profit_limit', 'weekly_loss_limit', 'monthly_profit_limit', 'monthly_loss_limit'])
                            ->orderBy('ends_at', 'desc')
                            ->first();
                    }
                }
                
                if ($activeBan) {
                    $banRemainingSeconds = max(0, $activeBan->ends_at->diffInSeconds(now()));
                    $banMessage = \App\Services\BanService::getPersianBanMessage($activeBan);
                }
            }
        } catch (\Throwable $e) {
            // silent failure
        }

        try {
            $riskLevel = Cache::get('market:risk_level');
            $riskMessage = Cache::get('market:risk_message');

            if ($riskLevel === 'critical') {
                $marketRiskLevel = 'critical';
                if (is_string($riskMessage) && $riskMessage !== '') {
                    $marketRiskMessage = $riskMessage;
                } else {
                    $marketRiskMessage = 'هشدار: بر اساس وضعیت کلی بازار آتی (تجمیع فاندینگ و اوپن اینترست در صرافی‌های جهانی)، شرایط فعلی پرریسک است. لطفاً با احتیاط و اندازه ریسک محافظه‌کارانه ادامه دهید.';
                }
            } else {
                $riskStatus = Cache::get('market:risk');
                if ($riskStatus === 'risky') {
                    $marketRiskLevel = 'critical';
                    $marketRiskMessage = 'هشدار: بر اساس وضعیت کلی بازار آتی (تجمیع فاندینگ و اوپن اینترست در صرافی‌های جهانی)، شرایط فعلی پرریسک است. لطفاً با احتیاط و اندازه ریسک محافظه‌کارانه ادامه دهید.';
                }
            }
        } catch (\Throwable $e) {
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
            'banMessage' => $banMessage,
            'tvDefaultInterval' => $tvDefaultInterval,
            'marketRiskLevel' => $marketRiskLevel,
            'marketRiskMessage' => $marketRiskMessage,
            'weeklyProfitLimit' => $weeklyProfitLimit,
            'weeklyLossLimit' => $weeklyLossLimit,
            'monthlyProfitLimit' => $monthlyProfitLimit,
            'monthlyLossLimit' => $monthlyLossLimit,
            'weeklyPnlPercent' => $weeklyPnlPercent,
            'monthlyPnlPercent' => $monthlyPnlPercent,
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
                $currentExchange = $user->getCurrentExchange();
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
            $defaultRisk = \App\Models\UserAccountSetting::getDefaultRisk($user->id, (bool)$order->is_demo) ?? 10;
        }

        // Apply strict mode cap
        if ($user->future_strict_mode) {
            $defaultRisk = min($defaultRisk, 10);
        }
        
        // Get user defaults (to mirror create page behavior in UI)
        $defaultFutureOrderSteps = \App\Models\UserAccountSetting::getDefaultFutureOrderSteps($user->id, (bool)$order->is_demo);
        $defaultExpirationMinutes = \App\Models\UserAccountSetting::getDefaultExpirationTime($user->id, (bool)$order->is_demo);
        $tvDefaultInterval = \App\Models\UserAccountSetting::getTradingViewDefaultInterval($user->id, (bool)$order->is_demo);

        // Compute active opening-ban for UI when strict mode is active
        $activeBan = null;
        $banRemainingSeconds = null;
        try {
            if ($user && ($user->future_strict_mode ?? false)) {
                $currentExchange = $user->getCurrentExchange();
                $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                $activeBan = \App\Models\UserBan::active()
                    ->forUser($user->id)
                    ->accountType($isDemo)
                    ->whereIn('ban_type', ['single_loss', 'double_loss', 'exchange_force_close', 'weekly_profit_limit', 'weekly_loss_limit', 'monthly_profit_limit', 'monthly_loss_limit', 'self_ban_time', 'self_ban_price'])
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
            'tvDefaultInterval' => $tvDefaultInterval,
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
                $currentExchange = $user->getCurrentExchange();
                $isDemo = (bool)($currentExchange?->is_demo_active ?? false);
                $activeBan = \App\Models\UserBan::active()
                    ->forUser($user->id)
                    ->accountType($isDemo)
                    ->whereIn('ban_type', ['single_loss', 'double_loss', 'exchange_force_close', 'weekly_profit_limit', 'weekly_loss_limit', 'monthly_profit_limit', 'monthly_loss_limit', 'self_ban_time', 'self_ban_price'])
                    ->orderBy('ends_at', 'desc')
                    ->first();
                if ($activeBan) {
                    $remainingSeconds = max(0, $activeBan->ends_at->diffInSeconds(now()));
                    $remainingFa = $this->formatFaDuration($remainingSeconds);
                    return back()
                        ->withErrors(['msg' => \App\Services\BanService::getPersianBanMessage($activeBan) . ' ' . 'زمان باقی‌مانده: ' . $remainingFa . '.'])
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
            'risk_percentage' => 'required|numeric|min:0.1|max:100',
            'cancel_price' => 'nullable|numeric',
        ]);

        // If entry2 is not provided (steps=1 case), set it equal to entry1
        if (!isset($validated['entry2'])) {
            $validated['entry2'] = $validated['entry1'];
        }

        try {
            DB::beginTransaction();

            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();

            // Get the current user
            $user = auth()->user();

            // Apply strict mode conditions only if user has strict mode enabled
            if ($user->future_strict_mode) {

                if (!$user->selected_market) {
                    throw new \Exception('برای حالت سخت‌گیرانه، باید بازار انتخابی تنظیم شده باشد.');
                }
                if ($validated['symbol'] !== $user->selected_market) {
                    throw new \Exception("در حالت سخت‌گیرانه، تنها می‌توانید در بازار {$user->selected_market} معامله کنید.");
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
                    throw new \Exception("برای معامله خرید، قیمت ورود ({$avgEntry}) نمی‌تواند بالاتر از قیمت بازار ({$marketPrice}) باشد.");
                }
                if ($side === 'Sell' && $avgEntry < $marketPrice) {
                    throw new \Exception("برای معامله فروش، قیمت ورود ({$avgEntry}) نمی‌تواند پایین‌تر از قیمت بازار ({$marketPrice}) باشد.");
                }
            }

            // Prevent placing multiple orders with the same direction if there's
            // an existing pending order or an open trade on the same side (for current exchange & symbol)
            $currentExchange = $user->getCurrentExchange();
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
                    throw new \Exception('ثبت سفارش جدید در همین جهت امکان‌پذیر نیست؛ شما یک سفارش در انتظار یا معامله باز در همین جهت دارید. لطفاً ابتدا آن را لغو یا ببندید.');
                }
            }

            // Fetch live wallet balance
            $capitalUSD = $this->resolveCapitalUSD($exchangeService);

            // Resolve leverage to ensure accurate max affordable quantity calculation
            // We fetch it once and store it in UserAccountSettings to avoid repeated API calls
            // Cache expires after 3 days
            $leverage = null;
            try {
                $currentExchange = $user->getCurrentExchange();
                if ($currentExchange) {
                    $isDemo = (bool)$currentExchange->is_demo_active;
                    $leverageKey = "leverage_{$symbol}_{$currentExchange->id}";
                    
                    // Check cache with expiration (3 days)
                    $setting = \App\Models\UserAccountSetting::where('user_id', $user->id)
                        ->where('key', $leverageKey)
                        ->where('is_demo', $isDemo)
                        ->first();
                        
                    $isExpired = false;
                    if ($setting) {
                        // Check if updated_at is older than 3 days
                        if ($setting->updated_at < now()->subDays(3)) {
                            $isExpired = true;
                        } else {
                            // Cast value based on type stored
                            $leverage = (float)$setting->value;
                        }
                    }

                    if (!$setting || $isExpired) {
                        // Fetch max leverage from exchange
                        // We use max leverage to ensure user can use their full balance if they want
                        $maxLeverage = $exchangeService->getMaxLeverage($symbol);

                        // If exchange is Bybit, cap leverage at 55x
                        if ($currentExchange->exchange_name === 'bybit') {
                            $maxLeverage = min($maxLeverage, 55);
                        }

                        // Set leverage on exchange to max
                        // This ensures that if user manually reduced it, we bump it back up
                        try {
                            $exchangeService->setLeverage($symbol, $maxLeverage);
                        } catch (\Exception $e) {
                            // Ignore error if setting leverage fails (e.g. "leverage not modified")
                            // We proceed with the max leverage we found
                        }

                        // Store in cache
                        \App\Models\UserAccountSetting::setUserSetting($user->id, $leverageKey, $maxLeverage, 'decimal', $isDemo);
                        $leverage = (float)$maxLeverage;
                    }
                }
            } catch (\Exception $e) {
                // Log warning but proceed (will skip downsizing check)
                Log::warning("Could not resolve leverage for {$symbol}: " . $e->getMessage());
            }

            $maxLossUSD = $capitalUSD * ($riskPercentage / 100.0);

            $slDistance = abs($avgEntry - (float) $validated['sl']);
            $tpDistance = abs($avgEntry - (float) $validated['tp']);

            if ($slDistance <= 0) {
                throw new \Exception('حد ضرر باید متفاوت از قیمت ورود باشد.');
            }
            // Enforce minimum SL/TP distance of 0.2% of entry price (avgEntry)
            $minDistance = 0.002 * $avgEntry;
            if ($slDistance < $minDistance) {
                throw new \Exception('حد ضرر باید حداقل ۰٫۲٪ فاصله از قیمت ورود داشته باشد.');
            }
            if ($tpDistance < $minDistance) {
                throw new \Exception('حد سود باید حداقل ۰٫۲٪ فاصله از قیمت ورود داشته باشد.');
            }
            // Enforce configured minimum RR ratio when strict mode is active
            if ($user->future_strict_mode) {
                $currentExchange = $user->getCurrentExchange();
                $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;
                $minRrStr = \App\Models\UserAccountSetting::getMinRrRatio($user->id, $isDemo);
                if (!is_string($minRrStr) || !preg_match('/^\d+:\d+$/', $minRrStr)) {
                    $minRrStr = '3:1'; // loss:profit minima (e.g., 3:1 => loss three times profit)
                }
                // Interpret value as loss:profit minima => require profit/loss strictly greater than (profitPart/lossPart)
                [$lossPart, $profitPart] = array_map('floatval', explode(':', $minRrStr));
                if ($lossPart <= 0) { $lossPart = 1.0; }
                $minProfitOverLoss = $profitPart / $lossPart; // e.g. 3:1 => 1/3; 1:2 => 2.0
                // Strictly greater-than: tpDistance > minProfitOverLoss * slDistance
                if ($tpDistance <= ($minProfitOverLoss * $slDistance)) {
                    throw new \Exception("در حالت سخت‌گیرانه، حد سود باید بیشتر از نسبت انتخاب‌شده باشد. نسبت حداقل (ضرر:سود): {$minRrStr}");
                }
            }
            // Base quantity from risk
            $originalAmount = $maxLossUSD / $slDistance;

            // Auto-downsize by available balance to avoid insufficient margin errors
            // We use the actual leverage if available. If not, we skip this check to avoid incorrect downsizing.
            $maxAffordableQtyTotal = $originalAmount;
            
            if ($leverage && $avgEntry > 0) {
                // Max Notional = Capital * Leverage
                // Max Qty = Max Notional / Entry Price
                $maxAffordableQtyTotal = ($capitalUSD * $leverage) / $avgEntry;
            }

            $downsized = false;
            // Only apply downsizing if we have a valid leverage and the calculated amount exceeds affordable limit
            if ($leverage && $originalAmount > $maxAffordableQtyTotal) {
                $amount = $maxAffordableQtyTotal;
                $downsized = true;
            } else {
                $amount = $originalAmount;
            }

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
                $currentExchange = $user->getCurrentExchange();
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
                    'initial_risk_percent' => round((float)$riskPercentage, 2),
                    'entry_low'        => $entry1,
                    'entry_high'       => $entry2,
                    'cancel_price'     => isset($validated['cancel_price']) ? (float)$validated['cancel_price'] : null,
                ]);
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Futures order creation failed: ' . $e->getMessage());

            // Parse Bybit error message for user-friendly response
            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());

            return back()->withErrors(['msg' => $userFriendlyMessage])->withInput();
        }

        $successMsg = "سفارش شما با موفقیت ثبت شد.";
        if (isset($downsized) && $downsized) {
            $successMsg .= " توجه: به دلیل محدودیت موجودی، اندازه موقعیت به حداکثر مقدار قابل تأمین تنظیم شد.";
        }
        return back()->with('success', $successMsg);
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
            'risk_percentage' => 'nullable|numeric|min:0.1|max:100',
            'cancel_price' => 'nullable|numeric',
        ]);

        if (!isset($validated['entry2'])) {
            $validated['entry2'] = $validated['entry1'];
        }

        // Lock the order to prevent race conditions with enforcer
        $order->is_locked = true;
        $order->save();

        try {
            // Strict mode: keep symbol unchanged; only validate if user's selected market conflicts
            if ($user->future_strict_mode && $user->selected_market) {
                // We do not change symbol during edit; if mismatch exists, allow edit but keep symbol as-is
            }

            // Ensure leverage is optimized before updating order
            $exchangeService = null;
            try {
                $exchangeService = $this->getExchangeService();
                $symbol = $order->symbol;
                $isDemo = (bool)$order->is_demo;
                
                $userExchange = $order->userExchange;
                if ($userExchange) {
                    $leverageKey = "leverage_{$symbol}_{$userExchange->id}";
                    
                    // Check cache with expiration (3 days)
                    $setting = \App\Models\UserAccountSetting::where('user_id', $user->id)
                        ->where('key', $leverageKey)
                        ->where('is_demo', $isDemo)
                        ->first();
                        
                    $leverageNeedsUpdate = false;
                    if (!$setting || $setting->updated_at < now()->subDays(3)) {
                        $leverageNeedsUpdate = true;
                    }

                    if ($leverageNeedsUpdate) {
                        // Fetch max leverage and set it on exchange
                        $maxLeverage = $exchangeService->getMaxLeverage($symbol);
                        
                        try {
                            $exchangeService->setLeverage($symbol, $maxLeverage);
                            // Update cache
                            \App\Models\UserAccountSetting::setUserSetting($user->id, $leverageKey, $maxLeverage, 'decimal', $isDemo);
                        } catch (\Exception $e) {
                            // Ignore leverage setting errors, continue with edit
                            Log::warning("Could not set leverage during order update for {$symbol}: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore leverage optimization errors, continue with edit
                Log::warning("Leverage optimization failed during order update: " . $e->getMessage());
            }

            if (!$exchangeService) {
                $exchangeService = $this->getExchangeService();
            }

            // Get Instrument Info for precision and quantity steps
            $instruments = $exchangeService->getInstrumentsInfo($symbol);
            $inst = null;
            if (isset($instruments['list'])) {
                foreach ($instruments['list'] as $i) {
                    if ($i['symbol'] === $symbol) {
                        $inst = $i;
                        break;
                    }
                }
            }
            
            if (!$inst) {
                 throw new \Exception("اطلاعات نماد {$symbol} یافت نشد.");
            }

            $pricePrec = (int) $inst['priceScale'];
            $qtyStep = (float) $inst['lotSizeFilter']['qtyStep'];
            $minQty = (float) $inst['lotSizeFilter']['minOrderQty'];
            
            $qtyStepStr = (string) $qtyStep;
            $amountPrec = (strpos($qtyStepStr, '.') !== false) ? strlen(substr($qtyStepStr, strpos($qtyStepStr, '.') + 1)) : 0;

            $newEntryPrice = round((float)$validated['entry1'], $pricePrec);
            $newSl = round((float)$validated['sl'], $pricePrec);
            $newTp = round((float)$validated['tp'], $pricePrec);

            // Recalculate Volume
            $riskPercentage = isset($validated['risk_percentage']) ? (float)$validated['risk_percentage'] : (float)$order->initial_risk_percent;
            $capitalUSD = (float)$order->balance_at_creation;
            if ($capitalUSD <= 0) {
                $capitalUSD = $this->resolveCapitalUSD($exchangeService);
            }

            $maxLossUSD = $capitalUSD * ($riskPercentage / 100.0);
            $avgEntry = $newEntryPrice; // For single order edit, entry is entry1
            $slDistance = abs($avgEntry - $newSl);
            
            if ($slDistance <= 0) {
                throw new \Exception('حد ضرر نمی‌تواند برابر با قیمت ورود باشد.');
            }

            $originalAmount = $maxLossUSD / $slDistance;
            
            // Resolve leverage for downsizing check
            $leverage = null;
            try {
                $leverageKey = "leverage_{$symbol}_{$order->user_exchange_id}";
                $setting = \App\Models\UserAccountSetting::where('user_id', $user->id)
                    ->where('key', $leverageKey)
                    ->where('is_demo', $isDemo)
                    ->first();
                if ($setting) {
                    $leverage = (float)$setting->value;
                }
            } catch (\Exception $e) {}

            // Downsize if needed
            $maxAffordableQtyTotal = $originalAmount;
            if ($leverage && $avgEntry > 0) {
                $maxAffordableQtyTotal = ($capitalUSD * $leverage) / $avgEntry;
            }
            
            $amount = ($leverage && $originalAmount > $maxAffordableQtyTotal) ? $maxAffordableQtyTotal : $originalAmount;
            
            $finalQty = $this->calculateOrderQuantity($amount, $qtyStep, $amountPrec);
            
            if ($finalQty < $minQty) {
                 throw new \Exception("مقدار سفارش ({$finalQty}) کمتر از حداقل مجاز ({$minQty}) است.");
            }

            $oldEntryPrice = round((float)$order->entry_price, $pricePrec);
            $oldQty = (float)$order->amount;

            $priceTol = (pow(10, -$pricePrec) / 2);
            $qtyTol = max($qtyStep / 2, 1e-12);

            $corePriceChanged = abs($newEntryPrice - $oldEntryPrice) > $priceTol;
            $coreQtyChanged = abs($finalQty - $oldQty) > $qtyTol;
            $coreParamsToAmend = [];
            if ($coreQtyChanged) {
                $coreParamsToAmend['qty'] = (string)$finalQty;
            }
            if ($corePriceChanged) {
                $coreParamsToAmend['price'] = (string)$newEntryPrice;
            }

            $shouldSendAmend = count($coreParamsToAmend) > 0;
            $params = [
                'symbol' => $symbol,
                'orderId' => $order->order_id,
            ];
            if ($shouldSendAmend) {
                $params = array_merge($params, $coreParamsToAmend);
                if ($newSl != $order->sl) {
                    $params['stopLoss'] = (string)$newSl;
                }
                if ($newTp != $order->tp) {
                    $params['takeProfit'] = (string)$newTp;
                }
            }

            try {
                DB::beginTransaction();

                if ($shouldSendAmend && count($params) > 2) {
                    $result = $exchangeService->amendOrder($params);
                    
                    // Check if orderId changed (BingX Cancel-Replace)
                    if (isset($result['orderId']) && $result['orderId'] != $order->order_id) {
                        $order->order_id = $result['orderId'];
                    }
                }

                // If successful (or no API call needed), update DB
                $order->entry_price = $newEntryPrice;
                $order->entry_low   = min((float)$validated['entry1'], (float)$validated['entry2']);
                $order->entry_high  = max((float)$validated['entry1'], (float)$validated['entry2']);
                $order->sl          = $newSl;
                $order->tp          = $newTp;
                $order->expire_minutes = isset($validated['expire']) ? (int)$validated['expire'] : $order->expire_minutes;
                $order->cancel_price   = isset($validated['cancel_price']) ? (float)$validated['cancel_price'] : null;
                $order->amount         = $finalQty;
                $order->initial_risk_percent = $riskPercentage;
                $order->is_locked      = false; // Unlock
                
                $order->save();
                
                DB::commit();

                return redirect()->route('futures.orders')->with('success', 'سفارش با موفقیت ویرایش شد.');

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e; // Re-throw to be caught by outer catch
            }

        } catch (\Exception $e) {
            // Unlock on error
            $order->is_locked = false;
            $order->save();

            $msg = $e->getMessage();
            // Handle Cancel-Replace failure (BingX)
            if (str_contains($msg, 'REPLACE_FAILED')) {
                // The original order was canceled, but new one failed.
                // We must delete the local order to stay in sync.
                try {
                    $order->delete();
                } catch (\Throwable $t) {}
                return redirect()->route('futures.orders')->withErrors(['msg' => 'سفارش در صرافی لغو شد اما ایجاد سفارش جدید با خطا مواجه شد. سفارش محلی حذف گردید. جزئیات: ' . $msg]);
            }
            
            return redirect()->back()->withErrors(['msg' => 'خطا در ویرایش سفارش: ' . $msg]);
        }
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

        try {
            DB::beginTransaction();

            // Logic for 'pending' orders (Revoke)
            if ($status === 'pending') {
                Log::info("Attempting to cancel pending order {$order->id} with exchange order ID: {$order->order_id}");
                if ($order->order_id) {
                    $exchangeService = $this->getExchangeService();
                    // Note: cancelOrderWithSymbol might throw. If it does, we catch it.
                    // But for 'destroy', if exchange cancel fails (e.g. already filled), 
                    // we usually want to proceed with DB delete anyway?
                    // The original code caught exception and proceeded.
                    // If we use transaction, if we catch and proceed, we commit.
                    // If we rethrow, we rollback (order not deleted).
                    // User wants "Process complete or not at all".
                    // If exchange cancel fails, we probably shouldn't delete locally unless it's a "not found" error.
                    // But original logic was: "If cancellation fails... log it but proceed to delete".
                    // I will maintain this behavior but wrap the delete in transaction.
                    try {
                        $exchangeService->cancelOrderWithSymbol($order->order_id, $order->symbol);
                        Log::info("Successfully cancelled order {$order->order_id} on exchange", [
                            'local_order_id' => $order->id,
                            'exchange_order_id' => $order->order_id,
                            'symbol' => $order->symbol,
                            'user_exchange_id' => $order->user_exchange_id
                        ]);
                    } catch (\Exception $e) {
                        Log::warning("Could not cancel order {$order->order_id} on exchange during deletion. It might have been already filled/canceled. Error: " . $e->getMessage(), [
                            'local_order_id' => $order->id,
                            'exchange_order_id' => $order->order_id,
                            'symbol' => $order->symbol,
                            'user_exchange_id' => $order->user_exchange_id,
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    Log::warning("Order {$order->id} has no exchange order ID, skipping exchange cancellation");
                }
            }

            if ($status === 'pending' || $status === 'expired') {
                $order->delete();
                Log::info("Successfully deleted order {$order->id} from database", [
                    'order_id' => $order->order_id,
                    'status' => $status,
                    'user_exchange_id' => $order->user_exchange_id
                ]);
                
                DB::commit();
                return redirect()->route('futures.orders')->with('success', "سفارش {$status} با موفقیت حذف شد.");
            }

            DB::commit(); // Nothing done if not pending or expired
            // For any other status, do nothing.
            return redirect()->route('futures.orders')->withErrors(['msg' => 'این سفارش قابل حذف نیست.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order deletion failed: ' . $e->getMessage());
            return redirect()->route('futures.orders')->withErrors(['msg' => 'خطا در حذف سفارش: ' . $e->getMessage()]);
        }
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
            DB::beginTransaction();

            // Get precision info when not in local environment
            $pricePrec = 2;
            $qtyStr = (string) $order->amount; // already stored as final precision at creation



            $exchangeService = \App\Services\Exchanges\ExchangeFactory::createForUserExchange($userExchange);

            // Ensure leverage is optimized before resending order
            try {
                $symbol = $order->symbol;
                $isDemo = (bool)$order->is_demo;
                $leverageKey = "leverage_{$symbol}_{$userExchange->id}";
                
                // Check cache with expiration (3 days)
                $setting = \App\Models\UserAccountSetting::where('user_id', $user->id)
                    ->where('key', $leverageKey)
                    ->where('is_demo', $isDemo)
                    ->first();
                    
                $leverageNeedsUpdate = false;
                if (!$setting || $setting->updated_at < now()->subDays(3)) {
                    $leverageNeedsUpdate = true;
                }

                if ($leverageNeedsUpdate) {
                    // Fetch max leverage and set it on exchange
                    $maxLeverage = $exchangeService->getMaxLeverage($symbol);
                    
                    try {
                        $exchangeService->setLeverage($symbol, $maxLeverage);
                        // Update cache
                        \App\Models\UserAccountSetting::setUserSetting($user->id, $leverageKey, $maxLeverage, 'decimal', $isDemo);
                    } catch (\Exception $e) {
                        // Ignore leverage setting errors, continue with resend
                        Log::warning("Could not set leverage during order resend for {$symbol}: " . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                // Ignore leverage optimization errors, continue with resend
                Log::warning("Leverage optimization failed during order resend: " . $e->getMessage());
            }

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
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
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
            $currentExchange = $user->getCurrentExchange();
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



        try {
            DB::beginTransaction();
            
            $exchangeService = $this->getExchangeService();
            $symbol = $order->symbol;

            // تشخیص صرافی فعال کاربر
            $currentExchange = $user->getCurrentExchange();
            if (!$currentExchange) {
                throw new \Exception('No active exchange found');
            }

            // تعیین مقدار و سمت موقعیت برای بستن
            $openSide = $trade ? ($trade->side ?? null) : (ucfirst($order->side) ?: null);
            $qty = $trade ? (float)$trade->qty : (float)($order->filled_quantity ?? $order->amount ?? 0);

            if (!$openSide || $qty <= 0) {
                // We must rollback if we throw, but here we return redirect.
                // Since we haven't done any DB changes yet, rollback is cheap.
                DB::rollBack();
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

            DB::commit();
            return redirect()->route('futures.pnl_history')->with('success', 'درخواست بستن موقعیت ارسال شد و سوابق PnL به‌روزرسانی شد.');

        } catch (\Exception $e) {
            DB::rollBack();
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
        $currentExchange = $user->getCurrentExchange();
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



        // Production: close all open positions via exchange services
        try {
            DB::beginTransaction();
            
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

            DB::commit();
            return redirect()->route('futures.pnl_history')->with('success', 'درخواست بستن همه موقعیت‌ها ارسال شد.');
        } catch (\Exception $e) {
            DB::rollBack();
            $userFriendlyMessage = $this->parseExchangeError($e->getMessage());
            return redirect()->route('futures.pnl_history')->withErrors(['msg' => $userFriendlyMessage]);
        }
    }

    /**
     * API method to get market price for a symbol (requires authentication)
     */
    public function getMarketPrice($symbol)
    {
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $supportedMarkets = ['BTCUSDT', 'ETHUSDT', 'ADAUSDT', 'DOTUSDT', 'BNBUSDT', 'XRPUSDT', 'SOLUSDT', 'TRXUSDT', 'DOGEUSDT', 'LTCUSDT'];

        if (!in_array($symbol, $supportedMarkets)) {
            return response()->json([
                'success' => false,
                'message' => 'نماد ارز پشتیبانی نمی‌شود'
            ], 400);
        }

        try {
            $user = auth()->user();
            $currentExchange = $user->getCurrentExchange();
            $exchangeName = $currentExchange ? strtolower($currentExchange->exchange_name) : null;

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
    public function pnlHistory(Request $request)
    {
        $tradesQuery = Trade::forUser(auth()->id());

        // Filter by current account type (demo/real)
        $user = auth()->user();
        $currentExchange = $user->getCurrentExchange();
        if ($currentExchange) {
            $tradesQuery->accountType($currentExchange->is_demo_active);
        }

        // Read filters
        $from = $request->input('from');
        $to = $request->input('to');
        $symbol = $request->input('symbol');
        $strict = (bool) ($user->future_strict_mode ?? false);

        // Closed trades (paginate) and order by closed_at desc
        $closedTradesQuery = clone $tradesQuery;
        $closedTradesQuery->whereNotNull('closed_at');
        if (!$strict && filled($symbol)) {
            $closedTradesQuery->where('symbol', $symbol);
        }
        if (filled($from)) {
            try {
                $fromDate = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
                $closedTradesQuery->where('closed_at', '>=', $fromDate);
            } catch (\Throwable $e) {}
        }
        if (filled($to)) {
            try {
                $toDate = Carbon::createFromFormat('Y-m-d', $to)->endOfDay();
                $closedTradesQuery->where('closed_at', '<=', $toDate);
            } catch (\Throwable $e) {}
        }
        $perPage = 20;
        $page = max(1, (int)($request->input('page', 1)));

        $highlightOid = $request->input('highlight_oid');
        if ($highlightOid) {
            try {
                $target = (clone $closedTradesQuery)->where('order_id', $highlightOid)->first();
                if ($target) {
                    $beforeCount = (clone $closedTradesQuery)
                        ->where(function ($q) use ($target) {
                            $q->where('closed_at', '>', $target->closed_at)
                              ->orWhere(function ($q2) use ($target) {
                                  $q2->where('closed_at', $target->closed_at)
                                     ->where('id', '>', $target->id);
                              });
                        })
                        ->count();
                    $page = intdiv($beforeCount, $perPage) + 1;
                }
            } catch (\Throwable $e) {
                // ignore positioning failure
            }
        }

        $closedTrades = $closedTradesQuery->latest('closed_at')->paginate($perPage, ['*'], 'page', $page);

        // Open trades (closed_at is null)
        $openTradesQuery = Trade::forUser(auth()->id());
        if ($currentExchange) {
            $openTradesQuery->accountType($currentExchange->is_demo_active);
        }
        if (!$strict && filled($symbol)) {
            $openTradesQuery->where('symbol', $symbol);
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

        // Build symbol options for filter dropdown (distinct for user + account type)
        $symbolOptions = Trade::forUser(auth()->id())
            ->when($currentExchange, function ($q) use ($currentExchange) {
                $q->accountType($currentExchange->is_demo_active);
            })
            ->whereNotNull('symbol')
            ->select('symbol')
            ->distinct()
            ->orderBy('symbol')
            ->pluck('symbol')
            ->toArray();

        return view('futures.pnl_history', [
            'closedTrades' => $closedTrades,
            'openTrades' => $openTrades,
            'orderModelByOrderId' => $orderModelByOrderId,
            'strictModeActive' => $strictModeActive,
            'manualCloseBanActive' => $manualCloseBanActive,
            'manualCloseBanEndsAt' => $manualCloseBanEndsAt,
            'manualCloseBanRemainingFa' => $manualCloseBanRemainingFa,
            'filterSymbols' => $symbolOptions,
            'initialHighlightOid' => $highlightOid,
        ]);
    }

    /**
     * Display trading journal for the authenticated user
     */
    public function journal(Request $request)
    {
        $user = auth()->user();
        $currentExchange = $user->getCurrentExchange();
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

            // Fallback: if series arrays are empty (e.g., stale cache), compute on-demand
            $seriesEmpty = empty($metrics['pnl_per_trade']) && empty($metrics['cum_pnl']) && empty($metrics['cum_pnl_percent']);
            if ($seriesEmpty) {
                $service = new \App\Services\JournalPeriodService();
                $ids = null;
                if ($userExchangeId !== 'all') {
                    $ueValid = \App\Models\UserExchange::where('id', $userExchangeId)
                        ->where('user_id', $user->id)
                        ->first();
                    if ($ueValid && ((bool)$ueValid->is_demo_active === $isDemo)) {
                        $ids = [$ueValid->id];
                    }
                }
                $metrics = $service->computeMetricsFor($selectedPeriod, $ids, $side);
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
        

        // Percent aggregates from series (compounded)
        $perTradePercent = collect($metrics['per_trade_percent'] ?? []);
        $pnlPerTrade = collect($metrics['pnl_per_trade'] ?? []);
        $totalPnlPercentCompound = (float) $perTradePercent->reduce(function ($carry, $percentItem) {
            $percentVal = is_array($percentItem) ? ((float)($percentItem['y'] ?? 0)) : 0.0;
            return $carry * (1.0 + ($percentVal / 100.0));
        }, 1.0);
        $totalPnlPercent = ($totalPnlPercentCompound - 1.0) * 100.0;

        $totalProfitCompound = (float) $pnlPerTrade->zip($perTradePercent)->reduce(function ($carry, $pair) {
            [$pnlItem, $percentItem] = $pair;
            $pnlVal = is_array($pnlItem) ? ((float)($pnlItem['y'] ?? 0)) : 0.0;
            $percentVal = is_array($percentItem) ? ((float)($percentItem['y'] ?? 0)) : 0.0;
            if ($pnlVal > 0) {
                return $carry * (1.0 + ($percentVal / 100.0));
            }
            return $carry;
        }, 1.0);
        $totalProfitPercent = ($totalProfitCompound - 1.0) * 100.0;

        $totalLossCompound = (float) $pnlPerTrade->zip($perTradePercent)->reduce(function ($carry, $pair) {
            [$pnlItem, $percentItem] = $pair;
            $pnlVal = is_array($pnlItem) ? ((float)($pnlItem['y'] ?? 0)) : 0.0;
            $percentVal = is_array($percentItem) ? ((float)($percentItem['y'] ?? 0)) : 0.0;
            if ($pnlVal < 0) {
                return $carry * (1.0 + ($percentVal / 100.0));
            }
            return $carry;
        }, 1.0);
        $totalLossPercent = ($totalLossCompound - 1.0) * 100.0;

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

            $allTrades = $percentQuery->orderBy('trades.closed_at', 'asc')->get();
            $userStats = $allTrades->groupBy('user_id')->map(function ($userTrades) {
                $compound = 1.0;
                foreach ($userTrades as $t) {
                    $capital = (float) ($t->balance_at_creation ?? 0.0);
                    if ($capital <= 0.0) { continue; }
                    $percent = ((float)$t->pnl / $capital) * 100.0;
                    $compound = $compound * (1.0 + ($percent / 100.0));
                }
                $firstTrade = collect($userTrades)->first();
                return [
                    'user_id' => $firstTrade->user_id,
                    'pnl_percent' => ($compound - 1.0) * 100.0,
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
            'perTradePercentSeries' => $metrics['per_trade_percent'] ?? [],
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
            // Determine timeframe: query param or user default, allowed list
            $allowedTfs = ['1m','5m','15m','1h','4h'];
            $currentExchange = $user->getCurrentExchange();
            $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;
            $userDefaultTf = \App\Models\UserAccountSetting::getUserSetting($user->id, 'default_timeframe', '15m', $isDemo);
            $tfRequested = strtolower((string)$request->query('tf', $userDefaultTf));
            $timeframe = in_array($tfRequested, $allowedTfs, true) ? $tfRequested : '15m';

            // Read-only path: use stored snapshot only
            $snapshot = $order->candleData;
            $tfToColumn = [
                '1m' => 'candles_m1',
                '5m' => 'candles_m5',
                '15m' => 'candles_m15',
                '1h' => 'candles_h1',
                '4h' => 'candles_h4',
            ];
            $column = $tfToColumn[$timeframe] ?? 'candles_m15';

            if (!$snapshot || empty($snapshot->$column)) {
                return response()->json([
                    'success' => false,
                    'message' => 'داده‌های مورد نیاز در دسترس نیست.',
                ]);
            }

            $candles = $snapshot->$column;
            $startTs = (int)($candles[0]['time'] ?? ($snapshot->entry_time?->getTimestamp() ?? 0));
            $endTs = (int)($candles[count($candles)-1]['time'] ?? ($snapshot->exit_time?->getTimestamp() ?? 0));
            $trade = $order->trade;
            $exitPrice = $trade ? (float)($trade->avg_exit_price ?? 0) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'symbol' => $order->symbol,
                    'side' => $order->side,
                    'timeframe' => $timeframe,
                    'entry' => (float)$order->entry_price,
                    'tp' => (float)$order->tp,
                    'sl' => (float)$order->sl,
                    'exit' => $exitPrice > 0 ? $exitPrice : null,
                    'exit_at' => $snapshot->exit_time ? $snapshot->exit_time->getTimestamp() : null,
                    'filled_at' => $snapshot->entry_time ? $snapshot->entry_time->getTimestamp() : null,
                    'window' => ['start' => (int)$startTs, 'end' => (int)$endTs],
                    'candles' => $candles,
                ],
            ]);
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
