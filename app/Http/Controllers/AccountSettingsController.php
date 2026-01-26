<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAccountSetting;
use App\Models\User;
use App\Models\UserBan;
use App\Models\Trade;
use Illuminate\Support\Facades\Auth;
use App\Services\BanService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountSettingsController extends Controller
{
    private function getGoalMeta(int $userId, bool $isDemo, string $period): array
    {
        $enabledKey = $period . '_limit_enabled';
        $row = UserAccountSetting::where('user_id', $userId)
            ->where('key', $enabledKey)
            ->where('is_demo', $isDemo)
            ->first();

        if (!$row || !$row->created_at) {
            return [
                'created_at' => null,
                'unlock_at' => null,
                'can_modify' => false,
                'days_remaining' => null,
            ];
        }

        $createdAt = $row->created_at;
        $unlockAt = $createdAt->copy()->addMonths(3);
        $now = now();
        $canModify = $now->greaterThanOrEqualTo($unlockAt);
        $daysRemaining = $canModify ? 0 : max(0, $now->diffInDays($unlockAt));

        return [
            'created_at' => $createdAt,
            'unlock_at' => $unlockAt,
            'can_modify' => $canModify,
            'days_remaining' => $daysRemaining,
        ];
    }

    private function recomputeStrictMaxRisk(int $userId, bool $isDemo): void
    {
        $updatedSettings = UserAccountSetting::getUserSettings($userId, $isDemo);
        $computedStrictMaxRisk = UserAccountSetting::calculateStrictMaxRiskFromGoals(
            $updatedSettings['weekly_loss_limit'] ?? null,
            $updatedSettings['monthly_loss_limit'] ?? null
        );
        UserAccountSetting::setStrictMaxRisk($userId, $computedStrictMaxRisk, $isDemo);

        $strictMaxRisk = UserAccountSetting::getStrictMaxRisk($userId, $isDemo);
        $defaultRisk = UserAccountSetting::getDefaultRisk($userId, $isDemo);
        if ($defaultRisk !== null && (float)$defaultRisk > (float)$strictMaxRisk) {
            UserAccountSetting::setDefaultRisk($userId, (float)$strictMaxRisk, $isDemo);
        }
    }

    /**
     * Display the account settings page
     */
    public function index()
    {
        $user = Auth::user();
        $currentExchange = $user->getCurrentExchange();
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        // Get current settings for current mode
        $defaultRisk = UserAccountSetting::getDefaultRisk($user->id, $isDemo);
        $defaultFutureOrderSteps = UserAccountSetting::getDefaultFutureOrderSteps($user->id, $isDemo);
        $defaultExpirationTime = UserAccountSetting::getDefaultExpirationTime($user->id, $isDemo);
        $minRrRatio = UserAccountSetting::getMinRrRatio($user->id, $isDemo);
        $tvDefaultInterval = UserAccountSetting::getTradingViewDefaultInterval($user->id, $isDemo);
        $strictMaxRisk = UserAccountSetting::getStrictMaxRisk($user->id, $isDemo);
        
        // Fetch strict limits
        $strictSettings = UserAccountSetting::getUserSettings($user->id, $isDemo);
        $weeklyLimitEnabled = $strictSettings['weekly_limit_enabled'] ?? false;
        $monthlyLimitEnabled = $strictSettings['monthly_limit_enabled'] ?? false;
        $weeklyProfitLimit = $strictSettings['weekly_profit_limit'] ?? null;
        $weeklyLossLimit = $strictSettings['weekly_loss_limit'] ?? null;
        $monthlyProfitLimit = $strictSettings['monthly_profit_limit'] ?? null;
        $monthlyLossLimit = $strictSettings['monthly_loss_limit'] ?? null;

        $weeklyGoalMeta = $this->getGoalMeta((int)$user->id, (bool)$isDemo, 'weekly');
        $monthlyGoalMeta = $this->getGoalMeta((int)$user->id, (bool)$isDemo, 'monthly');

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

        $selfBanTime = null;
        $selfBanPrice = null;
        if ($user->future_strict_mode) {
            $selfBanTime = UserBan::active()
                ->forUser($user->id)
                ->accountType($isDemo)
                ->where('ban_type', 'self_ban_time')
                ->orderByDesc('ends_at')
                ->first();

            $selfBanPrice = UserBan::active()
                ->forUser($user->id)
                ->accountType($isDemo)
                ->where('ban_type', 'self_ban_price')
                ->where('lifted_by_price', false)
                ->orderByDesc('ends_at')
                ->first();
        }

        return view('account-settings.index', compact(
            'user',
            'defaultRisk',
            'defaultExpirationTime',
            'defaultFutureOrderSteps',
            'minRrRatio',
            'strictMaxRisk',
            'isDemo',
            'weeklyLimitEnabled',
            'monthlyLimitEnabled',
            'weeklyProfitLimit',
            'weeklyLossLimit',
            'monthlyProfitLimit',
            'monthlyLossLimit',
            'weeklyPnlPercent',
            'monthlyPnlPercent',
            'tvDefaultInterval',
            'selfBanTime',
            'selfBanPrice',
            'weeklyGoalMeta',
            'monthlyGoalMeta'
        ));
    }

    /**
     * Update the account settings
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        $currentExchange = $user->getCurrentExchange();
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
        $currentExchange = $user->getCurrentExchange();
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        return response()->json([
            'default_risk' => UserAccountSetting::getDefaultRisk($user->id, $isDemo),
            'default_expiration_time' => UserAccountSetting::getDefaultExpirationTime($user->id, $isDemo),
            'strict_mode' => $user->future_strict_mode,
            'strict_max_risk' => UserAccountSetting::getStrictMaxRisk($user->id, $isDemo),
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

        $currentExchange = $user->getCurrentExchange();
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
            return redirect()->back()->withErrors(['msg' => 'غیرفعال کردن محدودیت هفتگی از این بخش امکان‌پذیر نیست. پس از ۳ ماه می‌توانید آن را حذف کنید.']);
        }
        
        if (($isSet('monthly_limit_enabled') && $existingSettings['monthly_limit_enabled']) && 
            (isset($request->monthly_limit_enabled) && !$request->boolean('monthly_limit_enabled'))) {
            return redirect()->back()->withErrors(['msg' => 'غیرفعال کردن محدودیت ماهانه از این بخش امکان‌پذیر نیست. پس از ۳ ماه می‌توانید آن را حذف کنید.']);
        }

        // Check values immutability
        // $fields is already defined above

        $weeklyMeta = $this->getGoalMeta((int)$user->id, (bool)$isDemo, 'weekly');
        $monthlyMeta = $this->getGoalMeta((int)$user->id, (bool)$isDemo, 'monthly');

        foreach ($fields as $key => $label) {
            if ($isSet($key) && isset($validated[$key])) {
                if (abs((float)$validated[$key] - (float)$existingSettings[$key]) > 0.001) {
                    $meta = str_starts_with($key, 'weekly_') ? $weeklyMeta : (str_starts_with($key, 'monthly_') ? $monthlyMeta : null);
                    if ($meta && !$meta['can_modify'] && $meta['unlock_at']) {
                        $unlockFa = $meta['unlock_at']->format('Y/m/d');
                        return redirect()->back()->withErrors(['msg' => "تغییر مقدار {$label} تا تاریخ {$unlockFa} امکان‌پذیر نیست."]);
                    }
                    return redirect()->back()->withErrors(['msg' => "برای تغییر {$label} باید ابتدا هدف را حذف کنید و دوباره ثبت کنید."]);
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

            $this->recomputeStrictMaxRisk((int)$user->id, (bool)$isDemo);

            return redirect()->back()->with('success', 'محدودیت‌های جدید با موفقیت اعمال شدند.');

        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['msg' => 'خطا در ذخیره تنظیمات: ' . $e->getMessage()]);
        }
    }

    public function deleteStrictGoals(Request $request, string $period)
    {
        $user = Auth::user();
        if (!$user->future_strict_mode) {
            return redirect()->back()->withErrors(['msg' => 'حالت سخت‌گیرانه فعال نیست.']);
        }

        $period = strtolower(trim($period));
        if (!in_array($period, ['weekly', 'monthly'], true)) {
            return redirect()->back()->withErrors(['msg' => 'درخواست نامعتبر است.']);
        }

        $currentExchange = $user->getCurrentExchange();
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        $hasOpenTrades = Trade::forUser($user->id)
            ->accountType($isDemo)
            ->whereNull('closed_at')
            ->exists();
        if ($hasOpenTrades) {
            return redirect()->back()->withErrors(['msg' => 'برای حذف اهداف، نباید معامله باز داشته باشید.']);
        }

        $meta = $this->getGoalMeta((int)$user->id, (bool)$isDemo, $period);
        if (!$meta['created_at'] || !$meta['unlock_at']) {
            return redirect()->back()->withErrors(['msg' => 'هدف ثبت نشده است.']);
        }
        if (!$meta['can_modify']) {
            $unlockFa = $meta['unlock_at']->format('Y/m/d');
            return redirect()->back()->withErrors(['msg' => "امکان حذف این هدف تا تاریخ {$unlockFa} وجود ندارد."]);
        }

        $keys = $period === 'weekly'
            ? ['weekly_limit_enabled', 'weekly_profit_limit', 'weekly_loss_limit']
            : ['monthly_limit_enabled', 'monthly_profit_limit', 'monthly_loss_limit'];

        try {
            DB::transaction(function () use ($user, $isDemo, $keys) {
                UserAccountSetting::where('user_id', $user->id)
                    ->where('is_demo', $isDemo)
                    ->whereIn('key', $keys)
                    ->delete();
            });

            $this->recomputeStrictMaxRisk((int)$user->id, (bool)$isDemo);

            return redirect()->back()->with('success', 'اهداف با موفقیت حذف شدند.');
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['msg' => 'خطا در حذف اهداف: ' . $e->getMessage()]);
        }
    }

    public function renewStrictGoals(Request $request, string $period)
    {
        $user = Auth::user();
        if (!$user->future_strict_mode) {
            return redirect()->back()->withErrors(['msg' => 'حالت سخت‌گیرانه فعال نیست.']);
        }

        $period = strtolower(trim($period));
        if (!in_array($period, ['weekly', 'monthly'], true)) {
            return redirect()->back()->withErrors(['msg' => 'درخواست نامعتبر است.']);
        }

        $currentExchange = $user->getCurrentExchange();
        $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;

        $meta = $this->getGoalMeta((int)$user->id, (bool)$isDemo, $period);
        if (!$meta['created_at'] || !$meta['unlock_at']) {
            return redirect()->back()->withErrors(['msg' => 'هدف ثبت نشده است.']);
        }
        if (!$meta['can_modify']) {
            $unlockFa = $meta['unlock_at']->format('Y/m/d');
            return redirect()->back()->withErrors(['msg' => "امکان تمدید این هدف تا تاریخ {$unlockFa} وجود ندارد."]);
        }

        $keys = $period === 'weekly'
            ? ['weekly_limit_enabled', 'weekly_profit_limit', 'weekly_loss_limit']
            : ['monthly_limit_enabled', 'monthly_profit_limit', 'monthly_loss_limit'];

        $now = now();

        try {
            UserAccountSetting::where('user_id', $user->id)
                ->where('is_demo', $isDemo)
                ->whereIn('key', $keys)
                ->update(['created_at' => $now, 'updated_at' => $now]);

            return redirect()->back()->with('success', 'اهداف با موفقیت تمدید شدند.');
        } catch (\Throwable $e) {
            return redirect()->back()->withErrors(['msg' => 'خطا در تمدید اهداف: ' . $e->getMessage()]);
        }
    }

    public function updateSelfBan(Request $request)
    {
        $user = Auth::user();
        if (!$user->future_strict_mode) {
            return redirect()->back()->withErrors(['msg' => 'حالت سخت‌گیرانه فعال نیست.']);
        }

        $currentExchange = $user->getCurrentExchange();
        if (!$currentExchange) {
            return redirect()->back()->withErrors(['msg' => 'برای تنظیم مسدودسازی دستی، ابتدا صرافی فعال کنید.']);
        }

        $isDemo = (bool)$currentExchange->is_demo_active;
        $effectiveUserId = ($user->isInvestor() ? (int)$user->parent_id : (int)$user->id);

        $mode = (string)$request->input('self_ban_mode', '');
        $timeOptions = ['30','60','120','240','720','1440','2880','4320','10080','43200'];

        if ($mode === 'time') {
            $validated = $request->validate([
                'duration_minutes' => 'required|in:' . implode(',', $timeOptions),
            ], [], [
                'duration_minutes' => 'مدت زمان مسدودسازی',
            ]);

            $endsAt = now()->addMinutes((int)$validated['duration_minutes']);

            $existing = UserBan::active()
                ->where('user_id', $effectiveUserId)
                ->where('is_demo', $isDemo)
                ->where('ban_type', 'self_ban_time')
                ->orderByDesc('ends_at')
                ->first();

            if ($existing) {
                $existing->starts_at = now();
                $existing->ends_at = $endsAt;
                $existing->save();
            } else {
                UserBan::create([
                    'user_id' => $effectiveUserId,
                    'is_demo' => $isDemo,
                    'ban_type' => 'self_ban_time',
                    'starts_at' => now(),
                    'ends_at' => $endsAt,
                ]);
            }

            return redirect()->back()->with('success', 'مسدودسازی دستی با موفقیت فعال شد.');
        }

        if ($mode === 'price') {
            $validated = $request->validate([
                'duration_minutes' => 'required|in:' . implode(',', $timeOptions),
                'price_below' => 'nullable|numeric|min:0',
                'price_above' => 'nullable|numeric|min:0',
            ], [], [
                'duration_minutes' => 'حداکثر مدت مسدودسازی',
                'price_below' => 'قیمت پایین‌تر از',
                'price_above' => 'قیمت بالاتر از',
            ]);

            $below = ($request->input('price_below') !== null && $request->input('price_below') !== '') ? (float)$validated['price_below'] : null;
            $above = ($request->input('price_above') !== null && $request->input('price_above') !== '') ? (float)$validated['price_above'] : null;

            if ($below === null && $above === null) {
                return redirect()->back()->withErrors(['msg' => 'حداقل یکی از فیلدهای قیمت پایین‌تر یا قیمت بالاتر باید پر شود.']);
            }

            if ($below !== null && $above !== null && $below >= $above) {
                return redirect()->back()->withErrors(['msg' => 'قیمت پایین‌تر باید از قیمت بالاتر کمتر باشد.']);
            }

            $endsAt = now()->addMinutes((int)$validated['duration_minutes']);

            $existing = UserBan::active()
                ->where('user_id', $effectiveUserId)
                ->where('is_demo', $isDemo)
                ->where('ban_type', 'self_ban_price')
                ->orderByDesc('ends_at')
                ->first();

            if ($existing) {
                $existing->price_below = $below;
                $existing->price_above = $above;
                $existing->lifted_by_price = false;
                $existing->starts_at = now();
                $existing->ends_at = $endsAt;
                $existing->save();
            } else {
                UserBan::create([
                    'user_id' => $effectiveUserId,
                    'is_demo' => $isDemo,
                    'ban_type' => 'self_ban_price',
                    'price_below' => $below,
                    'price_above' => $above,
                    'lifted_by_price' => false,
                    'starts_at' => now(),
                    'ends_at' => $endsAt,
                ]);
            }

            return redirect()->back()->with('success', 'مسدودسازی دستی بر اساس قیمت با موفقیت فعال شد.');
        }

        return redirect()->back()->withErrors(['msg' => 'درخواست نامعتبر است.']);
    }
}
