<?php

namespace App\Http\Controllers;

use App\Models\UserAccountSetting;
use App\Services\Exchanges\ExchangeFactory;
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
        // Redirect to account-settings since all settings are now consolidated there
        return redirect()->route('account-settings.index');
    }
    
    /**
     * Activate Future Strict Mode with Market Selection
     */
    public function activateFutureStrictMode(Request $request)
    {
        Log::info('Strict mode activation attempt', [
            'user_id' => Auth::id(),
            'request_data' => $request->all()
        ]);

        try {
            $request->validate([
                'selected_market' => 'required|string|in:BTCUSDT,ETHUSDT,ADAUSDT,DOTUSDT,BNBUSDT,XRPUSDT,SOLUSDT,TRXUSDT,DOGEUSDT,LTCUSDT',
                // Accept loss:profit minima values (3:1, 2:1, 1:1, 1:2)
                'min_rr_ratio' => 'required|string|in:3:1,2:1,1:1,1:2'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed for strict mode activation', [
                'user_id' => Auth::id(),
                'errors' => $e->errors()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'بازار انتخابی معتبر نیست. لطفاً یکی از بازارهای موجود را انتخاب کنید.'
            ], 422);
        }
        
        try {
            $user = Auth::user();
            
            Log::info('User data for strict mode activation', [
                'user_id' => $user->id,
                'current_strict_mode' => $user->future_strict_mode,
                'active_exchanges_count' => $user->activeExchanges->count()
            ]);
            
            // Check if already activated
            if ($user->future_strict_mode) {
                Log::warning('Strict mode already activated', ['user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'حالت سخت‌گیرانه آتی قبلاً فعال شده است'
                ]);
            }

            // Check if user has any active exchanges
            if ($user->activeExchanges->count() === 0) {
                Log::warning('No active exchanges found for user', ['user_id' => $user->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'شما هیچ صرافی فعالی ندارید. لطفاً ابتدا یک صرافی را پیکربندی و فعال کنید.'
                ]);
            }

            // Check for open positions or pending orders
            $skippedExchanges = [];
            foreach ($user->activeExchanges as $exchange) {
                Log::info('Checking exchange for open positions/orders', [
                    'user_id' => $user->id,
                    'exchange_id' => $exchange->id,
                    'exchange_name' => $exchange->exchange_name,
                    'is_demo_active' => $exchange->is_demo_active
                ]);

                // Skip API checks for demo accounts since they don't have real positions/orders
                if ($exchange->is_demo_active) {
                    Log::info('Skipping API checks for demo account', [
                        'user_id' => $user->id,
                        'exchange_name' => $exchange->exchange_name
                    ]);
                    continue;
                }

                try {
                    $exchangeService = ExchangeFactory::createForUserExchange($exchange);
                    
                    // Check open orders
                    $openOrders = $exchangeService->getOpenOrders();
                    if (!empty($openOrders['list'])) {
                        Log::warning('Open orders found', [
                            'user_id' => $user->id,
                            'exchange_name' => $exchange->exchange_name,
                            'orders_count' => count($openOrders['list'])
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => "لطفاً قبل از فعال‌سازی حالت سخت‌گیرانه، تمام سفارشات باز خود را در صرافی {$exchange->exchange_name} ببندید."
                        ]);
                    }
                    
                    // Check open positions
                    $positions = $exchangeService->getPositions();
                    foreach ($positions['list'] as $position) {
                        if (isset($position['positionAmt']) && (float)$position['positionAmt'] > 0) {
                            Log::warning('Open positions found', [
                                'user_id' => $user->id,
                                'exchange_name' => $exchange->exchange_name,
                                'position' => $position
                            ]);
                            return response()->json([
                                'success' => false,
                                'message' => "لطفاً قبل از فعال‌سازی حالت سخت‌گیرانه، تمام موقعیت‌های باز خود را در صرافی {$exchange->exchange_name} ببندید."
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Skipping exchange due to connection error', [
                        'user_id' => $user->id,
                        'exchange_id' => $exchange->id,
                        'exchange_name' => $exchange->exchange_name,
                        'error' => $e->getMessage()
                    ]);
                    $skippedExchanges[] = $exchange->exchange_name;
                    continue; // Skip this exchange and continue with others
                }
            }

            // Activate Future Strict Mode with selected market
            Log::info('Activating strict mode', [
                'user_id' => $user->id,
                'selected_market' => $request->selected_market
            ]);

            $user->update([
                'future_strict_mode' => true,
                'future_strict_mode_activated_at' => now(),
                'selected_market' => $request->selected_market
            ]);

            // Persist minimum RR ratio selection (default to 3:1 if not provided)
            $selectedRr = $request->input('min_rr_ratio', '3:1');
            $currentExchange = $user->getCurrentExchange();
            $isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;
            UserAccountSetting::setMinRrRatio($user->id, $selectedRr, $isDemo);

            // Build success message
            $message = "حالت سخت‌گیرانه آتی با موفقیت فعال شد. شما تنها می‌توانید در بازار {$request->selected_market} معامله کنید. حداقل نسبت سود به ضرر روی {$selectedRr} تنظیم شد. این حالت غیرقابل بازگشت است.";
            
            // Add warning for skipped exchanges during validation
            if (!empty($skippedExchanges)) {
                $message .= " توجه: بررسی وضعیت صرافی‌های " . implode(', ', $skippedExchanges) . " به دلیل مشکل اتصال امکان‌پذیر نبود و نادیده گرفته شد.";
            }
            
            Log::info('Strict mode activation completed successfully', [
                'user_id' => $user->id,
                'selected_market' => $request->selected_market,
                'skipped_exchanges' => $skippedExchanges
            ]);

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to activate Future Strict Mode', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'خطا در فعال‌سازی حالت سخت‌گیرانه آتی: ' . $e->getMessage()
            ], 500);
        }
    }
}
