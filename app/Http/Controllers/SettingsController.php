<?php

namespace App\Http\Controllers;

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
                'selected_market' => 'required|string|in:BTCUSDT,ETHUSDT,ADAUSDT,DOTUSDT,BNBUSDT,XRPUSDT,SOLUSDT,TRXUSDT,DOGEUSDT,LTCUSDT'
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
            foreach ($user->activeExchanges as $exchange) {
                Log::info('Checking exchange for open positions/orders', [
                    'user_id' => $user->id,
                    'exchange_id' => $exchange->id,
                    'exchange_name' => $exchange->exchange_name
                ]);

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
                    Log::error('Error checking exchange status', [
                        'user_id' => $user->id,
                        'exchange_id' => $exchange->id,
                        'exchange_name' => $exchange->exchange_name,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => "خطا در بررسی وضعیت صرافی {$exchange->exchange_name}. لطفاً اتصال اینترنت و تنظیمات API را بررسی کنید."
                    ], 500);
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
            
            // Switch all active exchanges to hedge mode
            $hedgeModeErrors = [];
            foreach ($user->activeExchanges as $exchange) {
                try {
                    Log::info('Switching exchange to hedge mode', [
                        'user_id' => $user->id,
                        'exchange_id' => $exchange->id,
                        'exchange_name' => $exchange->exchange_name
                    ]);

                    $exchangeService = ExchangeFactory::createForUserExchange($exchange);
                    $exchangeService->switchPositionMode(true); // true for hedge mode
                    
                    Log::info('Successfully switched to hedge mode', [
                        'user_id' => $user->id,
                        'exchange_name' => $exchange->exchange_name
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to switch {$exchange->exchange_name} to hedge mode for user {$user->id}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $hedgeModeErrors[] = $exchange->exchange_name;
                }
            }

            // If there were hedge mode errors, warn the user but don't fail the activation
            $message = "حالت سخت‌گیرانه آتی با موفقیت فعال شد. شما تنها می‌توانید در بازار {$request->selected_market} معامله کنید. این حالت غیرقابل بازگشت است.";
            
            if (!empty($hedgeModeErrors)) {
                $message .= " توجه: تغییر حالت به Hedge Mode در صرافی‌های " . implode(', ', $hedgeModeErrors) . " با مشکل مواجه شد. لطفاً به صورت دستی این تنظیم را در صرافی انجام دهید.";
            }
            
            Log::info('Strict mode activation completed successfully', [
                'user_id' => $user->id,
                'selected_market' => $request->selected_market,
                'hedge_mode_errors' => $hedgeModeErrors
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
