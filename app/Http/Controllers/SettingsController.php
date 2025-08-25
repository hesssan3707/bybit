<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettingsController extends Controller
{
    /**
     * Display the settings page
     */
    public function index()
    {
        $user = Auth::user();
        
        return view('settings.index', [
            'user' => $user
        ]);
    }
    
    /**
     * Activate Future Strict Mode
     */
    public function activateFutureStrictMode(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Check if already activated
            if ($user->future_strict_mode) {
                return response()->json([
                    'success' => false,
                    'message' => 'حالت سخت‌گیرانه آتی قبلاً فعال شده است'
                ]);
            }
            
            // Activate Future Strict Mode
            $user->update([
                'future_strict_mode' => true,
                'future_strict_mode_activated_at' => now()
            ]);
            
            Log::info('Future Strict Mode activated', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'activated_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'حالت سخت‌گیرانه آتی با موفقیت فعال شد. این حالت غیرقابل بازگشت است.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to activate Future Strict Mode', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در فعال‌سازی حالت سخت‌گیرانه آتی'
            ], 500);
        }
    }
}
