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
        
        // Get current settings
        $defaultRisk = UserAccountSetting::getDefaultRisk($user->id);
        $defaultExpirationTime = UserAccountSetting::getDefaultExpirationTime($user->id);
        
        return view('account-settings.index', compact('user', 'defaultRisk', 'defaultExpirationTime'));
    }

    /**
     * Update the account settings
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        // Validate input
        $validatedData = $request->validate([
            'default_risk' => 'nullable|numeric|min:1|max:80',
            'default_expiration_time' => 'nullable|integer|min:1|max:1000',
        ]);

        try {
            // Update default risk with strict mode validation
            if ($request->has('default_risk') && $request->default_risk !== null) {
                UserAccountSetting::setDefaultRisk($user->id, $validatedData['default_risk']);
            }

            // Update default expiration time
            if ($request->has('default_expiration_time')) {
                $expirationTime = $request->default_expiration_time ?: null;
                UserAccountSetting::setDefaultExpirationTime($user->id, $expirationTime);
            }

            return redirect()->route('account-settings.index')
                            ->with('success', 'تنظیمات حساب کاربری با موفقیت به‌روزرسانی شد.');

        } catch (\InvalidArgumentException $e) {
            return redirect()->route('account-settings.index')
                            ->with('error', $e->getMessage());
        }
    }

    /**
     * Update strict mode setting
     */
    public function updateStrictMode(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'future_strict_mode' => 'required|boolean'
        ]);

        // If enabling strict mode, auto-update risk if it's > 10%
        if ($request->future_strict_mode) {
            $currentRisk = UserAccountSetting::getDefaultRisk($user->id);
            if ($currentRisk && $currentRisk > 10) {
                UserAccountSetting::setUserSetting($user->id, 'default_risk', 10, 'decimal');
            }
        }

        $user->update([
            'future_strict_mode' => $request->future_strict_mode
        ]);

        $message = $request->future_strict_mode 
            ? 'حالت سخت‌گیرانه آتی فعال شد.' 
            : 'حالت سخت‌گیرانه آتی غیرفعال شد.';

        return redirect()->route('account-settings.index')
                        ->with('success', $message);
    }

    /**
     * Reset settings to default
     */
    public function reset()
    {
        $user = Auth::user();
        
        // Reset to default values
        UserAccountSetting::setUserSetting($user->id, 'default_risk', null, 'decimal');
        UserAccountSetting::setDefaultExpirationTime($user->id, null);

        return redirect()->route('account-settings.index')
                        ->with('success', 'تنظیمات به حالت پیش‌فرض بازگردانده شد.');
    }

    /**
     * Get user settings for API/AJAX calls
     */
    public function getSettings()
    {
        $user = Auth::user();
        
        return response()->json([
            'default_risk' => UserAccountSetting::getDefaultRisk($user->id),
            'default_expiration_time' => UserAccountSetting::getDefaultExpirationTime($user->id),
            'strict_mode' => $user->future_strict_mode,
        ]);
    }
}
