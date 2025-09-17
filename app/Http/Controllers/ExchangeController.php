<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserExchange;
use App\Services\Exchanges\ExchangeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ExchangeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show exchange management page in profile
     */
    public function index()
    {
        $user = auth()->user();
        $exchanges = $user->exchanges()->latest()->get();
        $availableExchanges = UserExchange::getAvailableExchanges();
        
        return view('exchanges.index', compact('exchanges', 'availableExchanges'));
    }

    /**
     * Show form to request new exchange activation
     */
    public function create()
    {
        $availableExchanges = UserExchange::getAvailableExchanges();
        $user = auth()->user();
        $userExchangeNames = $user->exchanges()->pluck('exchange_name')->toArray();
        
        // Filter out exchanges user already has
        $availableExchanges = array_diff_key($availableExchanges, array_flip($userExchangeNames));
        
        return view('exchanges.create', compact('availableExchanges'));
    }

    /**
     * Store exchange activation request
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exchange_name' => 'required|string|in:' . implode(',', ExchangeFactory::getSupportedExchanges()),
            'api_key' => 'nullable|string|min:8',
            'api_secret' => 'nullable|string|min:8',
            'demo_api_key' => 'nullable|string|min:8',
            'demo_api_secret' => 'nullable|string|min:8',
            'reason' => 'nullable|string|max:500',
        ]);

        // Custom validation: user must provide either real or demo credentials (or both)
        $validator->after(function ($validator) use ($request) {
            $hasRealCredentials = !empty($request->api_key) && !empty($request->api_secret);
            $hasDemoCredentials = !empty($request->demo_api_key) && !empty($request->demo_api_secret);
            
            if (!$hasRealCredentials && !$hasDemoCredentials) {
                $validator->errors()->add('credentials', 'حداقل یکی از اطلاعات حساب واقعی یا دمو باید وارد شود.');
            }
            
            // If real credentials are provided, both key and secret are required
            if ((!empty($request->api_key) && empty($request->api_secret)) || 
                (empty($request->api_key) && !empty($request->api_secret))) {
                $validator->errors()->add('api_credentials', 'در صورت وارد کردن اطلاعات حساب واقعی، هر دو فیلد کلید و رمز باید پر شوند.');
            }
            
            // If demo credentials are provided, both key and secret are required
            if ((!empty($request->demo_api_key) && empty($request->demo_api_secret)) || 
                (empty($request->demo_api_key) && !empty($request->demo_api_secret))) {
                $validator->errors()->add('demo_credentials', 'در صورت وارد کردن اطلاعات حساب دمو، هر دو فیلد کلید و رمز باید پر شوند.');
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $user = auth()->user();
            
            // Check if exchange already exists for user
            $existing = $user->exchanges()->where('exchange_name', $request->exchange_name)->first();
            
            if ($existing) {
                if ($existing->is_active) {
                    return back()->withErrors(['exchange_name' => 'این صرافی قبلاً فعال است.']);
                }
                if ($existing->status === 'pending') {
                    return back()->withErrors(['exchange_name' => 'درخواست فعال‌سازی این صرافی در انتظار بررسی است.']);
                }
                
                // Update existing rejected/suspended exchange
                $existing->update([
                    'api_key' => $request->api_key,
                    'api_secret' => $request->api_secret,
                    'demo_api_key' => $request->demo_api_key,
                    'demo_api_secret' => $request->demo_api_secret,
                    'status' => 'pending',
                    'activation_requested_at' => now(),
                    'user_reason' => $request->reason,
                    'admin_notes' => null,
                ]);
                
                return redirect()->route('exchanges.index')
                    ->with('success', 'درخواست به‌روزرسانی صرافی ارسال شد.');
            }

            // Create new exchange request
            UserExchange::createExchangeRequest(
                $user->id,
                $request->exchange_name,
                $request->api_key,
                $request->api_secret,
                $request->reason,
                $request->demo_api_key,
                $request->demo_api_secret
            );

            return redirect()->route('exchanges.index')
                ->with('success', 'درخواست فعال‌سازی صرافی ارسال شد و در انتظار تأیید مدیر است.');

        } catch (\Exception $e) {
            Log::error('Exchange activation request failed: ' . $e->getMessage());
            return back()->withErrors(['general' => 'خطا در ارسال درخواست.'])->withInput();
        }
    }

    /**
     * Switch to a different exchange (make it default)
     */
    public function switchTo(UserExchange $exchange)
    {
        try {
            if ($exchange->user_id !== auth()->id()) {
                abort(403, 'دسترسی غیرمجاز');
            }

            if (!$exchange->is_active) {
                return back()->withErrors(['msg' => 'این صرافی فعال نیست.']);
            }

            $exchange->makeDefault();

            $user = auth()->user();
            $exchangeService = ExchangeFactory::createForUserExchange($exchange);
            // Always switch to hedge mode when switching exchanges
            $exchangeService->switchPositionMode(true);

            return redirect()->route('exchanges.index')
                ->with('success', "صرافی پیش‌فرض به {$exchange->exchange_display_name} تغییر یافت.");

        } catch (\Exception $e) {
            Log::error('Exchange switch failed: ' . $e->getMessage());
            return back()->withErrors(['msg' => 'خطا در تغییر صرافی.']);
        }
    }

    /**
     * Test exchange connection
     */
    public function testConnection(UserExchange $exchange)
    {
        try {
            if ($exchange->user_id !== auth()->id()) {
                abort(403, 'دسترسی غیرمجاز');
            }

            if (!$exchange->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'صرافی فعال نیست'
                ]);
            }

            $isConnected = ExchangeFactory::testUserExchangeConnection($exchange);

            return response()->json([
                'success' => $isConnected,
                'message' => $isConnected ? 'اتصال موفق' : 'خطا در اتصال'
            ]);

        } catch (\Exception $e) {
            Log::error('Exchange connection test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در تست اتصال'
            ]);
        }
    }

    /**
     * Test real account connection
     */
    public function testRealConnection(UserExchange $exchange)
    {
        try {
            if ($exchange->user_id !== auth()->id()) {
                abort(403, 'دسترسی غیرمجاز');
            }

            if (!$exchange->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'صرافی فعال نیست'
                ]);
            }

            if (empty($exchange->api_key) || empty($exchange->api_secret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'اطلاعات حساب واقعی وارد نشده است'
                ]);
            }

            // Test with real credentials
            $isConnected = ExchangeFactory::testUserExchangeConnection($exchange, false);

            return response()->json([
                'success' => $isConnected,
                'message' => $isConnected ? 'اتصال حساب واقعی موفق' : 'خطا در اتصال حساب واقعی'
            ]);

        } catch (\Exception $e) {
            Log::error('Real exchange connection test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در تست اتصال حساب واقعی'
            ]);
        }
    }

    /**
     * Test demo account connection
     */
    public function testDemoConnection(UserExchange $exchange)
    {
        try {
            if ($exchange->user_id !== auth()->id()) {
                abort(403, 'دسترسی غیرمجاز');
            }

            if (!$exchange->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'صرافی فعال نیست'
                ]);
            }

            if (empty($exchange->demo_api_key) || empty($exchange->demo_api_secret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'اطلاعات حساب دمو وارد نشده است'
                ]);
            }

            // Test with demo credentials
            $isConnected = ExchangeFactory::testUserExchangeConnection($exchange, true);

            return response()->json([
                'success' => $isConnected,
                'message' => $isConnected ? 'اتصال حساب دمو موفق' : 'خطا در اتصال حساب دمو'
            ]);

        } catch (\Exception $e) {
            Log::error('Demo exchange connection test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در تست اتصال حساب دمو'
            ]);
        }
    }

    /**
     * Test connection for API (used in create form)
     */
    public function testConnectionApi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'exchange_name' => 'required|string|in:' . implode(',', ExchangeFactory::getSupportedExchanges()),
                'api_key' => 'required|string|min:8',
                'api_secret' => 'required|string|min:8',
                'is_demo' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'اطلاعات وارد شده نامعتبر است'
                ]);
            }

            $isDemo = $request->boolean('is_demo', false);
            $exchangeName = $request->exchange_name;
            $apiKey = $request->api_key;
            $apiSecret = $request->api_secret;

            // Create a temporary exchange instance for testing
            $tempExchange = new UserExchange([
                'exchange_name' => $exchangeName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'demo_api_key' => $isDemo ? $apiKey : null,
                'demo_api_secret' => $isDemo ? $apiSecret : null,
                'is_demo_mode' => $isDemo,
                'user_id' => auth()->id()
            ]);

            $isConnected = ExchangeFactory::testUserExchangeConnection($tempExchange, $isDemo);

            return response()->json([
                'success' => $isConnected,
                'message' => $isConnected 
                    ? ($isDemo ? 'اتصال حساب دمو موفق' : 'اتصال حساب واقعی موفق')
                    : ($isDemo ? 'خطا در اتصال حساب دمو' : 'خطا در اتصال حساب واقعی')
            ]);

        } catch (\Exception $e) {
            Log::error('API connection test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در تست اتصال'
            ]);
        }
    }

    /**
     * Show edit form for exchange credentials
     */
    public function edit(UserExchange $exchange)
    {
        if ($exchange->user_id !== auth()->id()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        return view('exchanges.edit', compact('exchange'));
    }

    /**
     * Update exchange credentials
     */
    public function update(Request $request, UserExchange $exchange)
    {
        if ($exchange->user_id !== auth()->id()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        $validator = Validator::make($request->all(), [
            'api_key' => 'nullable|string|min:8',
            'api_secret' => 'nullable|string|min:8',
            'demo_api_key' => 'nullable|string|min:8',
            'demo_api_secret' => 'nullable|string|min:8',
            'reason' => 'nullable|string|max:500',
        ]);

        // Custom validation: user must provide either real or demo credentials (or both)
        $validator->after(function ($validator) use ($request) {
            $hasRealCredentials = !empty($request->api_key) && !empty($request->api_secret);
            $hasDemoCredentials = !empty($request->demo_api_key) && !empty($request->demo_api_secret);
            
            if (!$hasRealCredentials && !$hasDemoCredentials) {
                $validator->errors()->add('credentials', 'حداقل یکی از اطلاعات حساب واقعی یا دمو باید وارد شود.');
            }
            
            // If real credentials are provided, both key and secret are required
            if ((!empty($request->api_key) && empty($request->api_secret)) || 
                (empty($request->api_key) && !empty($request->api_secret))) {
                $validator->errors()->add('api_credentials', 'در صورت وارد کردن اطلاعات حساب واقعی، هر دو فیلد کلید و رمز باید پر شوند.');
            }
            
            // If demo credentials are provided, both key and secret are required
            if ((!empty($request->demo_api_key) && empty($request->demo_api_secret)) || 
                (empty($request->demo_api_key) && !empty($request->demo_api_secret))) {
                $validator->errors()->add('demo_credentials', 'در صورت وارد کردن اطلاعات حساب دمو، هر دو فیلد کلید و رمز باید پر شوند.');
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Deactivate exchange and request new approval
            $exchange->update([
                'api_key' => $request->api_key,
                'api_secret' => $request->api_secret,
                'demo_api_key' => $request->demo_api_key,
                'demo_api_secret' => $request->demo_api_secret,
                'is_active' => false,
                'is_default' => false,
                'status' => 'pending',
                'activation_requested_at' => now(),
                'user_reason' => $request->reason,
                'admin_notes' => null,
            ]);

            return redirect()->route('exchanges.index')
                ->with('success', 'درخواست به‌روزرسانی اطلاعات صرافی ارسال شد.');

        } catch (\Exception $e) {
            Log::error('Exchange update failed: ' . $e->getMessage());
            return back()->withErrors(['general' => 'خطا در به‌روزرسانی.'])->withInput();
        }
    }

    /**
     * Switch between demo and real mode
     */
    public function switchMode(Request $request, UserExchange $exchange)
    {
        if ($exchange->user_id !== auth()->id()) {
            abort(403, 'دسترسی غیرمجاز');
        }

        if (!$exchange->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'صرافی فعال نیست'
            ]);
        }

        $isDemoMode = $request->input('is_demo_mode');

        try {
            if ($isDemoMode) {
                $exchange->switchToDemo();
                $message = 'به حالت دمو تغییر یافت';
            } else {
                $exchange->switchToReal();
                $message = 'به حالت واقعی تغییر یافت';
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
