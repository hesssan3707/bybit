<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAccountSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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

        return view('account-settings.index', compact('user', 'defaultRisk', 'defaultExpirationTime' , 'defaultFutureOrderSteps', 'minRrRatio', 'isDemo'));
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
}
