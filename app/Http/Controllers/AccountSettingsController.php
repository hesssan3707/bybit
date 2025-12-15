<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAccountSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Services\BanService;
use Carbon\Carbon;

class AccountSettingsController extends Controller
{
    /**
     * Display the account settings page
     */
    public function index()
    {
        $user = Auth::user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        // Get current settings for current mode
        $defaultRisk = UserAccountSetting::getDefaultRisk($user->id, $isDemo);
        $defaultFutureOrderSteps = UserAccountSetting::getDefaultFutureOrderSteps($user->id, $isDemo);
        $defaultExpirationTime = UserAccountSetting::getDefaultExpirationTime($user->id, $isDemo);
        $minRrRatio = UserAccountSetting::getMinRrRatio($user->id, $isDemo);
        $tvDefaultInterval = UserAccountSetting::getTradingViewDefaultInterval($user->id, $isDemo);
        
        // Fetch strict limits
        $strictSettings = UserAccountSetting::getUserSettings($user->id, $isDemo);
        $weeklyLimitEnabled = $strictSettings['weekly_limit_enabled'] ?? false;
        $monthlyLimitEnabled = $strictSettings['monthly_limit_enabled'] ?? false;
        $weeklyProfitLimit = $strictSettings['weekly_profit_limit'] ?? null;
        $weeklyLossLimit = $strictSettings['weekly_loss_limit'] ?? null;
        $monthlyProfitLimit = $strictSettings['monthly_profit_limit'] ?? null;
        $monthlyLossLimit = $strictSettings['monthly_loss_limit'] ?? null;

        $weeklyPnlPercent = null;
        $monthlyPnlPercent = null;

        if ($user->future_strict_mode) {
            $banService = app(BanService::class);

            $startOfWeek = Carbon::now(config('app.timezone'))->startOfWeek(Carbon::MONDAY);
            $endOfWeek = $startOfWeek->copy()->endOfWeek(Carbon::SUNDAY);
            $weeklyPnlPercent = $banService->getPeriodPnlPercent($user->id, $isDemo, $startOfWeek, $endOfWeek);

            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();
            $monthlyPnlPercent = $banService->getPeriodPnlPercent($user->id, $isDemo, $startOfMonth, $endOfMonth);
        }

        return view('account-settings.index', compact(
            'user',
            'defaultRisk',
            'defaultExpirationTime',
            'defaultFutureOrderSteps',
            'minRrRatio',
            'isDemo',
            'weeklyLimitEnabled',
            'monthlyLimitEnabled',
            'weeklyProfitLimit',
            'weeklyLossLimit',
            'monthlyProfitLimit',
            'monthlyLossLimit',
            'weeklyPnlPercent',
            'monthlyPnlPercent',
            'tvDefaultInterval'
        ));
    }

    /**
     * Update the account settings
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        // Validate input - allow empty strings to remove default values
        $validatedData = $request->validate([
            'default_risk' => 'nullable|numeric|min:1|max:80',
            'default_future_order_steps' => 'nullable|integer|min:1|max:8',
            'default_expiration_time' => 'nullable|integer|min:1|max:1000',
            'tv_default_interval' => 'nullable|string',
        ]);

        try {
            // Update default risk with strict mode validation
            // If field is present in request, process it (even if empty)
            if ($request->has('default_risk')) {
                $riskValue = $request->default_risk;
                // Empty string or null means remove default value
                if ($riskValue === '' || $riskValue === null) {
                    UserAccountSetting::setDefaultRisk($user->id, null, $isDemo);
                } else {
                    UserAccountSetting::setDefaultRisk($user->id, $validatedData['default_risk'], $isDemo);
                }
            }

            // Update default future order steps
            // If field is present in request, process it (even if empty)
            if ($request->has('default_future_order_steps')) {
                $futureOrderSteps = $request->default_future_order_steps;
                // Empty string or null means remove default value
                if ($futureOrderSteps === '' || $futureOrderSteps === null) {
                    UserAccountSetting::setDefaultFutureOrderSteps($user->id, 1, $isDemo);
                } else {
                    UserAccountSetting::setDefaultFutureOrderSteps($user->id, $validatedData['default_future_order_steps'], $isDemo);
                }
            }

            // Update default expiration time
            // If field is present in request, process it (even if empty)
            if ($request->has('default_expiration_time')) {
                $expirationTime = $request->default_expiration_time;
                // Empty string or null means remove default value
                if ($expirationTime === '' || $expirationTime === null) {
                    UserAccountSetting::setDefaultExpirationTime($user->id, null, $isDemo);
                } else {
                    UserAccountSetting::setDefaultExpirationTime($user->id, $validatedData['default_expiration_time'], $isDemo);
                }
            }

            if ($request->has('tv_default_interval')) {
                $interval = $request->tv_default_interval;
                if ($interval === '' || $interval === null) {
                    UserAccountSetting::setTradingViewDefaultInterval($user->id, null, $isDemo);
                } else {
                    UserAccountSetting::setTradingViewDefaultInterval($user->id, $interval, $isDemo);
                }
            }

            return redirect()->route('account-settings.index')->with('success', 'تنظیمات حساب کاربری با موفقیت به‌روزرسانی شد.');

        } catch (\InvalidArgumentException $e) {
            return redirect()->route('account-settings.index')
                            ->with('error', $e->getMessage());
        }
    }



    /**
     * Get user settings for API/AJAX calls
     */
    public function getSettings()
    {
        $user = Auth::user();
        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        return response()->json([
            'default_risk' => UserAccountSetting::getDefaultRisk($user->id, $isDemo),
            'default_expiration_time' => UserAccountSetting::getDefaultExpirationTime($user->id, $isDemo),
            'strict_mode' => $user->future_strict_mode,
        ]);
    }
    /**
     * Update strict mode trading limits
     */
    public function updateStrictLimits(Request $request)
    {
        $user = Auth::user();
        if (!$user->future_strict_mode) {
            return redirect()->back()->withErrors(['msg' => 'حالت سخت‌گیرانه فعال نیست.']);
        }

        $currentExchange = $user->currentExchange ?? $user->defaultExchange;
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        $fields = [
            'weekly_profit_limit' => 'حد سود هفتگی',
            'weekly_loss_limit' => 'حد ضرر هفتگی',
            'monthly_profit_limit' => 'حد سود ماهانه',
            'monthly_loss_limit' => 'حد ضرر ماهانه',
            'weekly_limit_enabled' => 'فعال‌سازی محدودیت هفتگی',
            'monthly_limit_enabled' => 'فعال‌سازی محدودیت ماهانه',
        ];

        $validated = $request->validate([
            'weekly_profit_limit' => 'nullable|numeric|min:1|max:25',
            'weekly_loss_limit' => 'nullable|numeric|min:1|max:30',
            'monthly_profit_limit' => 'nullable|numeric|min:3|max:60',
            'monthly_loss_limit' => 'nullable|numeric|min:3|max:50',
            'weekly_limit_enabled' => 'nullable|boolean',
            'monthly_limit_enabled' => 'nullable|boolean',
        ], [], $fields);

        // Check for existing settings (Immutability)
        $existingSettings = UserAccountSetting::getUserSettings($user->id, $isDemo);
        $isSet = fn($key) => isset($existingSettings[$key]) && $existingSettings[$key] !== null;

        // Check enabled flags immutability (cannot disable once enabled)
        if (($isSet('weekly_limit_enabled') && $existingSettings['weekly_limit_enabled']) && 
            (isset($request->weekly_limit_enabled) && !$request->boolean('weekly_limit_enabled'))) {
            return redirect()->back()->withErrors(['msg' => 'غیرفعال کردن محدودیت هفتگی امکان‌پذیر نیست.']);
        }
        
        if (($isSet('monthly_limit_enabled') && $existingSettings['monthly_limit_enabled']) && 
            (isset($request->monthly_limit_enabled) && !$request->boolean('monthly_limit_enabled'))) {
            return redirect()->back()->withErrors(['msg' => 'غیرفعال کردن محدودیت ماهانه امکان‌پذیر نیست.']);
        }

        // Check values immutability
        // $fields is already defined above

        foreach ($fields as $key => $label) {
            if ($isSet($key) && isset($validated[$key])) {
                if (abs((float)$validated[$key] - (float)$existingSettings[$key]) > 0.001) {
                    return redirect()->back()->withErrors(['msg' => "تغییر مقدار {$label} امکان‌پذیر نیست."]);
                }
            }
        }

        // Validate relationships (Weekly <= Monthly)
        // Only check if Monthly is enabled (either in DB or in Request)
        $monthlyEnabled = ($existingSettings['monthly_limit_enabled'] ?? false) || $request->boolean('monthly_limit_enabled');
        $weeklyEnabled = ($existingSettings['weekly_limit_enabled'] ?? false) || $request->boolean('weekly_limit_enabled');

        if ($weeklyEnabled && $monthlyEnabled) {
            // Determine effective values
            $wp = isset($existingSettings['weekly_profit_limit']) ? (float)$existingSettings['weekly_profit_limit'] : (float)$validated['weekly_profit_limit'];
            $mp = isset($existingSettings['monthly_profit_limit']) ? (float)$existingSettings['monthly_profit_limit'] : (float)$validated['monthly_profit_limit'];
            
            if ($wp > $mp) {
                 return redirect()->back()->withErrors(['msg' => 'حد سود هفتگی نمی‌تواند بیشتر از حد سود ماهانه باشد.']);
            }

            $wl = isset($existingSettings['weekly_loss_limit']) ? (float)$existingSettings['weekly_loss_limit'] : (float)$validated['weekly_loss_limit'];
            $ml = isset($existingSettings['monthly_loss_limit']) ? (float)$existingSettings['monthly_loss_limit'] : (float)$validated['monthly_loss_limit'];

            if ($wl > $ml) {
                 return redirect()->back()->withErrors(['msg' => 'حد ضرر هفتگی نمی‌تواند بیشتر از حد ضرر ماهانه باشد.']);
            }
        }

        try {
            // Save settings
            // Save Weekly Settings
            if ($request->has('weekly_limit_enabled') && $request->boolean('weekly_limit_enabled')) {
                UserAccountSetting::setUserSetting($user->id, 'weekly_limit_enabled', true, 'boolean', $isDemo);
                
                if (isset($validated['weekly_profit_limit']) && !$isSet('weekly_profit_limit')) {
                    UserAccountSetting::setUserSetting($user->id, 'weekly_profit_limit', $validated['weekly_profit_limit'], 'decimal', $isDemo);
                }
                if (isset($validated['weekly_loss_limit']) && !$isSet('weekly_loss_limit')) {
                    UserAccountSetting::setUserSetting($user->id, 'weekly_loss_limit', $validated['weekly_loss_limit'], 'decimal', $isDemo);
                }
            }

            // Save Monthly Settings
            if ($request->has('monthly_limit_enabled') && $request->boolean('monthly_limit_enabled')) {
                UserAccountSetting::setUserSetting($user->id, 'monthly_limit_enabled', true, 'boolean', $isDemo);
                
                if (isset($validated['monthly_profit_limit']) && !$isSet('monthly_profit_limit')) {
                    UserAccountSetting::setUserSetting($user->id, 'monthly_profit_limit', $validated['monthly_profit_limit'], 'decimal', $isDemo);
                }
                if (isset($validated['monthly_loss_limit']) && !$isSet('monthly_loss_limit')) {
                    UserAccountSetting::setUserSetting($user->id, 'monthly_loss_limit', $validated['monthly_loss_limit'], 'decimal', $isDemo);
                }
            }

            return redirect()->back()->with('success', 'محدودیت‌های جدید با موفقیت اعمال شدند.');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['msg' => 'خطا در ذخیره تنظیمات: ' . $e->getMessage()]);
        }
    }
}
