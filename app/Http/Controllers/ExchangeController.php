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

            // Switch to hedge mode after making exchange default
            try {
                $exchangeService = ExchangeFactory::create($exchange);
                $exchangeService->switchPositionMode(true);
                Log::info('Hedge mode activated during exchange switch', [
                    'user_id' => auth()->id(),
                    'exchange_id' => $exchange->id,
                    'exchange_name' => $exchange->exchange_name
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to activate hedge mode during exchange switch', [
                    'user_id' => auth()->id(),
                    'exchange_id' => $exchange->id,
                    'exchange_name' => $exchange->exchange_name,
                    'error' => $e->getMessage()
                ]);
                // Continue with exchange switch even if hedge mode fails
            }

            return redirect()->route('profile.index');

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
    public function testRealConnection(Request $request, UserExchange $exchange)
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

            // Get real credentials from request body (for testing new credentials) or from database (for saved credentials)
            $apiKey = $request->input('api_key') ?: $exchange->api_key;
            $apiSecret = $request->input('api_secret') ?: $exchange->api_secret;

            if (empty($apiKey) || empty($apiSecret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'اطلاعات حساب واقعی وارد نشده است'
                ]);
            }

            // Test with real credentials using ExchangeFactory
            $exchangeService = ExchangeFactory::create($exchange->exchange_name, $apiKey, $apiSecret, false);
            $balance = $exchangeService->getWalletBalance('UNIFIED', 'USDT');
            
            if (isset($balance['list'][0])) {
                return response()->json([
                    'success' => true,
                    'message' => 'اتصال حساب واقعی موفق'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در اتصال حساب واقعی - نتوانست موجودی را دریافت کند'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Real exchange connection test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در تست اتصال حساب واقعی: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test demo account connection
     */
    public function testDemoConnection(Request $request, UserExchange $exchange)
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

            // Get demo credentials from request body (for testing new credentials) or from database (for saved credentials)
            $demoApiKey = $request->input('demo_api_key') ?: $exchange->demo_api_key;
            $demoApiSecret = $request->input('demo_api_secret') ?: $exchange->demo_api_secret;

            if (empty($demoApiKey) || empty($demoApiSecret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'اطلاعات حساب دمو وارد نشده است'
                ]);
            }

            // Test with demo credentials using ExchangeFactory
            $exchangeService = ExchangeFactory::create($exchange->exchange_name, $demoApiKey, $demoApiSecret, true);
            $balance = $exchangeService->getWalletBalance('UNIFIED', 'USDT');
            
            if (isset($balance['list'][0])) {
                return response()->json([
                    'success' => true,
                    'message' => 'اتصال حساب دمو موفق'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در اتصال حساب دمو - نتوانست موجودی را دریافت کند'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Demo exchange connection test failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطا در تست اتصال حساب دمو: ' . $e->getMessage()
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

            // Use ExchangeFactory::create for proper connection testing
            $exchangeService = ExchangeFactory::create($exchangeName, $apiKey, $apiSecret, $isDemo);
            
            // Test connection by checking wallet balance
            // Different exchanges use different account types
            $accountType = strtolower($exchangeName) === 'bybit' ? 'UNIFIED' : 'FUTURES';
            $balance = $exchangeService->getWalletBalance($accountType, 'USDT');
            
            // Validate balance response based on exchange type
            $isValidBalance = false;
            if ($balance !== null) {
                if (strtolower($exchangeName) === 'bybit') {
                    // Bybit returns data in 'list' array
                    $isValidBalance = isset($balance['list']) && !empty($balance['list']);
                } else {
                    // Binance and BingX return array directly or in 'list'
                    $isValidBalance = (isset($balance['list']) && !empty($balance['list'])) || 
                                     (is_array($balance) && !empty($balance));
                }
            }
            
            if ($isValidBalance) {
                return response()->json([
                    'success' => true,
                    'message' => $isDemo ? 'اتصال حساب دمو موفق' : 'اتصال حساب واقعی موفق'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $isDemo ? 'خطا در اتصال حساب دمو - نتوانست موجودی را دریافت کند' : 'خطا در اتصال حساب واقعی - نتوانست موجودی را دریافت کند'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('API connection test failed: ' . $e->getMessage());
            
            // Extract meaningful error message from exchange API
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'API key is invalid') !== false) {
                $errorMessage = 'کلید API نامعتبر است';
            } elseif (strpos($errorMessage, 'Invalid signature') !== false) {
                $errorMessage = 'امضای API نامعتبر است';
            } elseif (strpos($errorMessage, 'IP not allowed') !== false) {
                $errorMessage = 'IP شما مجاز نیست';
            } elseif (strpos($errorMessage, 'Permission denied') !== false) {
                $errorMessage = 'دسترسی مجاز نیست';
            } else {
                $errorMessage = $isDemo ? 'خطا در تست اتصال حساب دمو: ' . $errorMessage : 'خطا در تست اتصال حساب واقعی: ' . $errorMessage;
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage
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

        // Custom validation: user must provide either real or demo credentials (or both), or have existing credentials
        $validator->after(function ($validator) use ($request, $exchange) {
            $hasRealCredentials = !empty($request->api_key) && !empty($request->api_secret);
            $hasDemoCredentials = !empty($request->demo_api_key) && !empty($request->demo_api_secret);
            $hasExistingRealCredentials = $exchange->hasRealCredentials();
            $hasExistingDemoCredentials = $exchange->hasDemoCredentials();
            
            // Check if user will have at least one set of credentials after update
            $willHaveRealCredentials = $hasRealCredentials || ($hasExistingRealCredentials && !$hasRealCredentials);
            $willHaveDemoCredentials = $hasDemoCredentials || ($hasExistingDemoCredentials && !$hasDemoCredentials);
            
            if (!$willHaveRealCredentials && !$willHaveDemoCredentials && !$hasRealCredentials && !$hasDemoCredentials) {
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
            // Prepare update data - only include fields that user provided
            $updateData = [
                'is_active' => false,
                'is_default' => false,
                'status' => 'pending',
                'activation_requested_at' => now(),
                'user_reason' => $request->reason,
                'admin_notes' => null,
            ];

            // Only update real credentials if provided
            $hasRealCredentials = !empty($request->api_key) && !empty($request->api_secret);
            if ($hasRealCredentials) {
                $updateData['api_key'] = $request->api_key;
                $updateData['api_secret'] = $request->api_secret;
            }

            // Only update demo credentials if provided
            $hasDemoCredentials = !empty($request->demo_api_key) && !empty($request->demo_api_secret);
            if ($hasDemoCredentials) {
                $updateData['demo_api_key'] = $request->demo_api_key;
                $updateData['demo_api_secret'] = $request->demo_api_secret;
            }

            // Deactivate exchange and request new approval
            $exchange->update($updateData);

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
            return response()->json([
                'success' => false,
                'message' => 'دسترسی غیرمجاز',
                'is_demo_mode' => $exchange->is_demo_active
            ], 403);
        }

        if (!$exchange->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'صرافی فعال نیست',
                'is_demo_mode' => $exchange->is_demo_active
            ]);
        }

        $isDemoMode = $request->input('is_demo_mode');

        try {
            if ($isDemoMode) {
                $exchange->switchToDemo();
            } else {
                $exchange->switchToReal();
            }

            // Refresh the model to get the latest state from database
            $exchange->refresh();

            return response()->json([
                'success' => true,
                'is_demo_mode' => $exchange->is_demo_active
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'is_demo_mode' => $exchange->is_demo_active
            ]);
        }
    }
}
