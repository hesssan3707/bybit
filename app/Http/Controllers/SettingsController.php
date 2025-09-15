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
        $user = Auth::user();
        
        return view('settings.index', [
            'user' => $user
        ]);
    }
    
    /**
     * Activate Future Strict Mode with Market Selection
     */
    public function activateFutureStrictMode(Request $request)
    {
        $request->validate([
            'selected_market' => 'required|string|in:BTCUSDT,ETHUSDT,ADAUSDT,DOTUSDT,BNBUSDT,XRPUSDT,SOLUSDT,TRXUSDT,DOGEUSDT,LTCUSDT'
        ]);
        
        try {
            $user = Auth::user();
            
            // Check if already activated
            if ($user->future_strict_mode) {
                return response()->json([
                    'success' => false,
                    'message' => 'حالت سخت‌گیرانه آتی قبلاً فعال شده است'
                ]);
            }

            // Check for open positions or pending orders
            foreach ($user->activeExchanges as $exchange) {
                $exchangeService = ExchangeFactory::createForUserExchange($exchange);
                $openOrders = $exchangeService->getOpenOrders();
                if (!empty($openOrders['list'])) {
                    return response()->json([
                        'success' => false,
                        'message' => "لطفاً قبل از فعال‌سازی حالت سخت‌گیرانه، تمام سفارشات باز خود را در صرافی {$exchange->exchange_name} ببندید."
                    ]);
                }
                $positions = $exchangeService->getPositions();
                foreach ($positions['list'] as $position) {
                    if (isset($position['positionAmt']) && (float)$position['positionAmt'] > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => "لطفاً قبل از فعال‌سازی حالت سخت‌گیرانه، تمام موقعیت‌های باز خود را در صرافی {$exchange->exchange_name} ببندید."
                        ]);
                    }
                }
            }

            // Switch all active exchanges to hedge mode
            foreach ($user->activeExchanges as $exchange) {
                try {
                    $exchangeService = ExchangeFactory::createForUserExchange($exchange);
                    $exchangeService->switchPositionMode(true); // true for hedge mode
                } catch (\Exception $e) {
                    Log::error("Failed to switch {$exchange->exchange_name} to hedge mode for user {$user->id}", ['error' => $e->getMessage()]);
                    // Decide if we should fail the whole process or just log the error
                    return response()->json([
                        'success' => false,
                        'message' => "خطا در تغییر حالت صرافی {$exchange->exchange_name} به حالت Hedge. لطفاً دوباره تلاش کنید."
                    ], 500);
                }
            }
            
            // Activate Future Strict Mode with selected market
            $user->update([
                'future_strict_mode' => true,
                'future_strict_mode_activated_at' => now(),
                'selected_market' => $request->selected_market
            ]);
            
            Log::info('Future Strict Mode activated with market selection', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'selected_market' => $request->selected_market,
                'activated_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "حالت سخت‌گیرانه آتی با موفقیت فعال شد. شما تنها می‌توانید در بازار {$request->selected_market} معامله کنید. این حالت غیرقابل بازگشت است."
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
