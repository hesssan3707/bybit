<?php

namespace App\Http\Controllers;

use App\Models\SpotOrder;
use App\Services\Exchanges\ExchangeFactory;
use App\Services\Exchanges\ExchangeApiServiceInterface;
use App\Traits\HandlesExchangeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SpotTradingController extends Controller
{
    /**
     * Parse Bybit API error message and return user-friendly message
     * 
     * @param string $errorMessage
     * @return string
     */
    private function parseBybitError($errorMessage)
    {
        // Extract error code if present
        $errorCode = null;
        if (preg_match('/Code: (\d+)/', $errorMessage, $matches)) {
            $errorCode = $matches[1];
        }
        
        // Extract symbol if present
        $symbol = null;
        if (preg_match('/"symbol":"([^"]+)"/', $errorMessage, $matches)) {
            $symbol = $matches[1];
        }
        
        // Extract side if present  
        $side = null;
        if (preg_match('/"side":"([^"]+)"/', $errorMessage, $matches)) {
            $side = $matches[1] === 'Buy' ? 'خرید' : 'فروش';
        }
        
        // Extract quantity if present
        $qty = null;
        if (preg_match('/"qty":"([^"]+)"/', $errorMessage, $matches)) {
            $qty = $matches[1];
        }
        
        // Map specific error codes to user-friendly messages
        switch ($errorCode) {
            case '170131': // Insufficient balance
                $coinName = 'USDT';
                if ($symbol) {
                    // Extract quote coin from symbol (e.g., ETHUSDT -> USDT)
                    if (str_ends_with($symbol, 'USDT')) {
                        $coinName = 'USDT';
                    } elseif (str_ends_with($symbol, 'USDC')) {
                        $coinName = 'USDC';
                    } elseif (str_ends_with($symbol, 'BTC')) {
                        $coinName = 'BTC';
                    }
                }
                return "موجودی {$coinName} شما برای این معامله کافی نیست.\n" .
                       "برای سفارش {$side} {$symbol}، ابتدا موجودی {$coinName} خود را شارژ کنید.";
                
            case '170130': // Order value too small
                return "مقدار سفارش خیلی کم است.\n" .
                       "لطفاً مقدار بیشتری وارد کنید یا با قیمت بالاتری سفارش دهید.";
                
            case '110001': // Order does not exist
                return "سفارش مورد نظر یافت نشد.\n" .
                       "احتمالاً سفارش قبلاً اجرا یا لغو شده است.";
                
            case '110003': // Order quantity exceeds upper limit
                return "مقدار سفارش از حد مجاز بیشتر است.\n" .
                       "لطفاً مقدار کمتری وارد کنید.";
                
            case '110004': // Order price exceeds upper limit
                return "قیمت سفارش از حد مجاز بیشتر است.\n" .
                       "لطفاً قیمت کمتری وارد کنید.";
                
            case '110005': // Order price is lower than the minimum
                return "قیمت سفارش کمتر از حد مجاز است.\n" .
                       "لطفاً قیمت بالاتری وارد کنید.";
                
            case '110012': // Order quantity is lower than the minimum
                return "مقدار سفارش کمتر از حداقل مجاز است.\n" .
                       "لطفاً مقدار بیشتری وارد کنید.";
                
            case '10001': // Parameter error
                if (str_contains($errorMessage, 'qty')) {
                    return "مقدار سفارش نامعتبر است.\n" .
                           "لطفاً مقدار صحیح وارد کنید.";
                } elseif (str_contains($errorMessage, 'price')) {
                    return "قیمت سفارش نامعتبر است.\n" .
                           "لطفاً قیمت صحیح وارد کنید.";
                } else {
                    return "اطلاعات سفارش نامعتبر است.\n" .
                           "لطفاً اطلاعات وارد شده را بررسی کنید.";
                }
                
            case '10002': // Invalid API key
                return "کلید API نامعتبر است.\n" .
                       "لطفاً تنظیمات صرافی خود را بررسی کنید.";
                
            case '10003': // Missing required parameter
                return "اطلاعات ضروری سفارش ناقص است.\n" .
                       "لطفاً تمام فیلدهای مورد نیاز را پر کنید.";
                
            case '10015': // IP not allowed
                return "آدرس IP شما مجاز نیست.\n" .
                       "لطفاً IP فعلی را به لیست مجاز صرافی اضافه کنید.";
                
            case '110025': // Order would immediately trigger
                return "سفارش شما بلافاصله اجرا می‌شود.\n" .
                       "برای سفارش محدود، قیمت مناسب‌تری انتخاب کنید.";
                
            case '110026': // Market is closed
                return "بازار در حال حاضر بسته است.\n" .
                       "لطفاً در ساعات کاری بازار مجدداً تلاش کنید.";
                
            case '170213': // Wallet locked
                return "کیف پول شما قفل است.\n" .
                       "لطفاً با پشتیبانی صرافی تماس بگیرید.";
                
            default:
                // Generic error handling
                if (str_contains($errorMessage, 'Insufficient balance')) {
                    return "موجودی حساب شما کافی نیست.\n" .
                           "لطفاً ابتدا حساب خود را شارژ کنید.";
                } elseif (str_contains($errorMessage, 'Invalid symbol')) {
                    return "جفت ارز انتخاب شده معتبر نیست.\n" .
                           "لطفاً جفت ارز صحیح را انتخاب کنید.";
                } elseif (str_contains($errorMessage, 'Order not found')) {
                    return "سفارش یافت نشد.\n" .
                           "احتمالاً سفارش قبلاً اجرا یا لغو شده است.";
                } else {
                    // Return a generic but helpful message
                    return "خطا در ایجاد سفارش رخ داد.\n" .
                           "لطفاً اطلاعات وارد شده را بررسی کرده و دوباره تلاش کنید.";
                }
        }
    }
    /**
     * Get the exchange service for the authenticated user
     */
    private function getExchangeService(): ExchangeApiServiceInterface
    {
        if (!auth()->check()) {
            throw new \Exception('User not authenticated');
        }

        try {
            return ExchangeFactory::createForUser(auth()->id());
        } catch (\Exception $e) {
            throw new \Exception('لطفاً ابتدا در صفحه پروفایل، صرافی مورد نظر خود را فعال کنید.');
        }
    }

    /**
     * Check if user has an active exchange and return status
     */
    private function checkActiveExchange()
    {
        try {
            $this->getExchangeService();
            return ['hasActiveExchange' => true, 'message' => null];
        } catch (\Exception $e) {
            return [
                'hasActiveExchange' => false, 
                'message' => 'برای استفاده از این قسمت، لطفاً ابتدا صرافی خود را فعال کنید.'
            ];
        }
    }

    /**
     * Create a spot trading order
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSpotOrder(Request $request)
    {
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $validated = $request->validate([
                'side' => 'required|string|in:Buy,Sell',
                'symbol' => 'required|string', // e.g., BTCUSDT
                'orderType' => 'required|string|in:Market,Limit',
                'qty' => 'required|numeric|min:0.00000001',
                'price' => 'nullable|numeric|min:0.00000001', // Required for Limit orders
                'timeInForce' => 'nullable|string|in:GTC,IOC,FOK',
            ]);

            // Validate that price is provided for limit orders
            if ($validated['orderType'] === 'Limit' && !isset($validated['price'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Price is required for Limit orders'
                ], 400);
            }

            // Get instrument info to validate symbol and get precision
            $instrumentInfo = $exchangeService->getSpotInstrumentsInfo($validated['symbol']);
            
            if (empty($instrumentInfo['list'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid trading symbol: ' . $validated['symbol']
                ], 400);
            }

            $instrument = $instrumentInfo['list'][0];
            
            // Format quantity and price according to instrument precision
            $lotSizeFilter = $instrument['lotSizeFilter'] ?? [];
            $priceFilter = $instrument['priceFilter'] ?? [];
            $qtyStep = (float)($lotSizeFilter['qtyStep'] ?? $lotSizeFilter['basePrecision'] ?? 0.00000001);
            $priceStep = (float)($priceFilter['tickSize'] ?? $priceFilter['quotePrecision'] ?? 0.01);
            
            // Validate and adjust quantity to meet minimum requirements
            $qty = $this->validateAndAdjustQuantity($validated['qty'], $instrument);
            
            $orderParams = [
                'side' => $validated['side'],
                'symbol' => $validated['symbol'],
                'orderType' => $validated['orderType'],
                'qty' => $qty,
                'orderLinkId' => (string) Str::uuid(),
            ];

            // Add price for limit orders
            if ($validated['orderType'] === 'Limit') {
                $price = $this->roundToStep($validated['price'], $priceStep);
                $orderParams['price'] = $price;
            }

            // Add timeInForce if provided
            if (isset($validated['timeInForce'])) {
                $orderParams['timeInForce'] = $validated['timeInForce'];
            }

            // Create the order
            $result = $exchangeService->createSpotOrder($orderParams);

            // Extract base and quote coins from symbol
            $baseCoin = substr($orderParams['symbol'], 0, -4); // Remove last 4 chars (usually USDT)
            $quoteCoin = substr($orderParams['symbol'], -4); // Last 4 chars
            
            // Handle special cases for quote coins
            if (str_ends_with($orderParams['symbol'], 'USDC')) {
                $baseCoin = substr($orderParams['symbol'], 0, -4);
                $quoteCoin = 'USDC';
            } elseif (str_ends_with($orderParams['symbol'], 'BTC')) {
                $baseCoin = substr($orderParams['symbol'], 0, -3);
                $quoteCoin = 'BTC';
            } elseif (str_ends_with($orderParams['symbol'], 'ETH')) {
                $baseCoin = substr($orderParams['symbol'], 0, -3);
                $quoteCoin = 'ETH';
            }

            // Get user's current active exchange ID
            $user = $request->user();
            $currentExchange = $user->currentExchange ?? $user->defaultExchange;
            if (!$currentExchange) {
                return response()->json([
                    'success' => false,
                    'message' => 'لطفاً ابتدا در صفحه پروفایل، صرافی مورد نظر خود را فعال کنید.'
                ], 400);
            }

            // Save order to database
            $spotOrder = SpotOrder::create([
                'user_exchange_id' => $currentExchange->id,
                'order_id' => $result['orderId'] ?? null,
                'order_link_id' => $orderParams['orderLinkId'],
                'symbol' => $orderParams['symbol'],
                'base_coin' => $baseCoin,
                'quote_coin' => $quoteCoin,
                'side' => $orderParams['side'],
                'order_type' => $orderParams['orderType'],
                'qty' => $qty,
                'price' => $orderParams['price'] ?? null,
                'time_in_force' => $orderParams['timeInForce'] ?? 'GTC',
                'status' => 'New', // Initial status
                'order_created_at' => now(),
                'raw_response' => $result,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Spot order created successfully',
                'data' => [
                    'id' => $spotOrder->id,
                    'orderId' => $result['orderId'] ?? null,
                    'orderLinkId' => $result['orderLinkId'] ?? null,
                    'symbol' => $validated['symbol'],
                    'side' => $validated['side'],
                    'orderType' => $validated['orderType'],
                    'qty' => $qty,
                    'price' => $orderParams['price'] ?? null,
                    'status' => 'New',
                    'created_at' => $spotOrder->created_at,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Spot order creation failed: ' . $e->getMessage());
            
            // Parse Bybit error message for user-friendly response
            $userFriendlyMessage = $this->parseBybitError($e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => $userFriendlyMessage
            ], 500);
        }
    }

    /**
     * Get account balance separated by each currency
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAccountBalance()
    {
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $balanceData = $exchangeService->getSpotAccountBalance();
            
            if (empty($balanceData['list'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No balance data found'
                ], 404);
            }
            
            // Process balance data...
            $balances = [];
            foreach ($balanceData['list'] as $balance) {
                $balances[] = [
                    'coin' => $balance['coin'],
                    'walletBalance' => $balance['walletBalance'],
                    'transferBalance' => $balance['transferBalance'] ?? $balance['walletBalance'],
                    'locked' => $balance['locked'] ?? '0'
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $balances
            ]);
            
        } catch (\Exception $e) {
            // Handle API access validation dynamically
            $user = auth()->user();
            $currentExchange = $user->currentExchange ?? $user->defaultExchange;
            
            if ($currentExchange) {
                try {
                    $this->handleApiException($e, $currentExchange, 'spot_balance');
                } catch (\Exception $handledException) {
                    // Exception was handled and validation updated
                    $errorMessage = $this->getAccessLimitationMessage('balance', $currentExchange->exchange_name);
                    
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage,
                        'access_updated' => true
                    ], 403);
                }
            }
            
            Log::error('Failed to get account balance: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get account balance: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get spot order history
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderHistory(Request $request)
    {
        try {
            $validated = $request->validate([
                'symbol' => 'nullable|string',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $symbol = $validated['symbol'] ?? null;
            $limit = $validated['limit'] ?? 20;

            // Get orders for the authenticated user only
            $query = SpotOrder::forUser($request->user()->id);
            
            if ($symbol) {
                $query->where('symbol', $symbol);
            }
            
            $orders = $query->latest('created_at')
                          ->limit($limit)
                          ->get();

            return response()->json([
                'success' => true,
                'message' => 'Order history retrieved successfully',
                'data' => $orders
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Get order history failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get order history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get spot market ticker information
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTickerInfo(Request $request)
    {
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $validated = $request->validate([
                'symbol' => 'required|string',
            ]);

            $result = $exchangeService->getSpotTickerInfo($validated['symbol']);

            return response()->json([
                'success' => true,
                'message' => 'Ticker information retrieved successfully',
                'data' => $result
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Get ticker info failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get ticker information: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a spot order
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelOrder(Request $request)
    {
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $validated = $request->validate([
                'orderId' => 'required|string',
                'symbol' => 'required|string',
            ]);

            $result = $exchangeService->cancelSpotOrder(
                $validated['orderId'],
                $validated['symbol']
            );

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $result
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Cancel order failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available spot trading pairs
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTradingPairs()
    {
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $result = $exchangeService->getSpotInstrumentsInfo();
            
            $tradingPairs = [];
            if (isset($result['list']) && is_array($result['list'])) {
                foreach ($result['list'] as $instrument) {
                    $lotSizeFilter = $instrument['lotSizeFilter'] ?? [];
                    $priceFilter = $instrument['priceFilter'] ?? [];
                    
                    $tradingPairs[] = [
                        'symbol' => $instrument['symbol'] ?? 'Unknown',
                        'baseCoin' => $instrument['baseCoin'] ?? 'Unknown',
                        'quoteCoin' => $instrument['quoteCoin'] ?? 'Unknown',
                        'status' => $instrument['status'] ?? 'Unknown',
                        'minOrderQty' => $lotSizeFilter['minOrderQty'] ?? $lotSizeFilter['minQty'] ?? '0.00000001',
                        'maxOrderQty' => $lotSizeFilter['maxOrderQty'] ?? $lotSizeFilter['maxQty'] ?? '1000000',
                        'qtyStep' => $lotSizeFilter['qtyStep'] ?? $lotSizeFilter['basePrecision'] ?? '0.00000001',
                        'tickSize' => $priceFilter['tickSize'] ?? $priceFilter['quotePrecision'] ?? '0.01',
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Trading pairs retrieved successfully',
                'data' => $tradingPairs
            ]);

        } catch (\Exception $e) {
            Log::error('Get trading pairs failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get trading pairs: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Round a number to the nearest step
     * 
     * @param float $number
     * @param float $step
     * @return float
     */
    private function roundToStep($number, $step)
    {
        if ($step == 0) {
            return $number;
        }
        
        return round($number / $step) * $step;
    }

    /**
     * Validate and adjust quantity to meet minimum requirements
     * 
     * @param float $qty
     * @param array $instrument
     * @return float
     * @throws \Exception
     */
    private function validateAndAdjustQuantity($qty, $instrument)
    {
        // Handle different data structures defensively
        $lotSizeFilter = $instrument['lotSizeFilter'] ?? [];
        $qtyStep = (float)($lotSizeFilter['qtyStep'] ?? $lotSizeFilter['basePrecision'] ?? 0.00000001);
        $minOrderQty = (float)($lotSizeFilter['minOrderQty'] ?? $lotSizeFilter['minQty'] ?? 0.00000001);
        
        // If we couldn't get proper values, throw descriptive error
        if ($qtyStep <= 0) {
            throw new \Exception("خطا در دریافت اطلاعات دقت مقدار از صرافی. لطفاً دوباره تلاش کنید.");
        }
        
        if ($minOrderQty <= 0) {
            throw new \Exception("خطا در دریافت حداقل مقدار مجاز از صرافی. لطفاً دوباره تلاش کنید.");
        }
        
        // Additional validation before processing
        if ($qty <= 0) {
            throw new \Exception("مقدار سفارش باید بزرگتر از صفر باشد. حداقل مجاز: {$minOrderQty}");
        }
        
        // If input quantity is already less than minimum, reject immediately
        if ($qty < $minOrderQty) {
            throw new \Exception("مقدار وارد شده ({$qty}) کمتر از حداقل مجاز است. حداقل: {$minOrderQty}");
        }
        
        // Round to step
        $adjustedQty = $this->roundToStep($qty, $qtyStep);
        
        // Ensure rounding didn't make it zero or negative
        if ($adjustedQty <= 0) {
            throw new \Exception("مقدار پس از تنظیم دقت صفر شد. مقدار ورودی: {$qty}, حداقل مجاز: {$minOrderQty}, دقت: {$qtyStep}");
        }
        
        // Final check after rounding
        if ($adjustedQty < $minOrderQty) {
            throw new \Exception("مقدار پس از تنظیم دقت ({$adjustedQty}) کمتر از حداقل مجاز است. حداقل: {$minOrderQty}");
        }
        
        // Additional safety check - ensure it's not effectively zero
        if ((string)$adjustedQty === '0' || (string)$adjustedQty === '0.0' || (string)$adjustedQty === '0.00000000') {
            throw new \Exception("مقدار نهایی صفر محاسبه شد. مقدار ورودی: {$qty}, حداقل مجاز: {$minOrderQty}");
        }
        
        return $adjustedQty;
    }

    // Web View Methods

    /**
     * Display spot orders list view
     */
    public function spotOrdersView(Request $request)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        
        $orders = SpotOrder::forUser(auth()->id())
            ->latest('created_at')
            ->paginate(20);

        return view('spot.orders', [
            'orders' => $orders,
            'hasActiveExchange' => $exchangeStatus['hasActiveExchange'],
            'exchangeMessage' => $exchangeStatus['message']
        ]);
    }

    /**
     * Display spot balances view
     */
    public function spotBalancesView()
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        
        if (!$exchangeStatus['hasActiveExchange']) {
            return view('spot.balances', [
                'balances' => [],
                'totalEquity' => 0,
                'totalWalletBalance' => 0,
                'hasActiveExchange' => false,
                'exchangeMessage' => $exchangeStatus['message'],
                'error' => null
            ]);
        }
        
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $balanceData = $exchangeService->getSpotAccountBalance();
            
            $balances = [];
            if (!empty($balanceData['list'])) {
                $account = $balanceData['list'][0];
                $totalEquity = (float)($account['totalEquity'] ?? 0);
                $totalWalletBalance = (float)($account['totalWalletBalance'] ?? 0);
                
                if (isset($account['coin']) && is_array($account['coin'])) {
                    foreach ($account['coin'] as $coin) {
                        if ((float)$coin['walletBalance'] > 0) {
                            $balances[] = [
                                'currency' => $coin['coin'],
                                'walletBalance' => (float)$coin['walletBalance'],
                                'transferBalance' => (float)($coin['transferBalance'] ?? $coin['walletBalance']),
                                'bonus' => (float)($coin['bonus'] ?? 0),
                                'usdValue' => isset($coin['usdValue']) ? (float)$coin['usdValue'] : null,
                            ];
                        }
                    }
                }
                
                return view('spot.balances', [
                    'balances' => $balances,
                    'totalEquity' => $totalEquity,
                    'totalWalletBalance' => $totalWalletBalance,
                    'hasActiveExchange' => true,
                    'exchangeMessage' => null,
                    'error' => null
                ]);
            }
            
            return view('spot.balances', [
                'balances' => [],
                'totalEquity' => 0,
                'totalWalletBalance' => 0,
                'hasActiveExchange' => true,
                'exchangeMessage' => null,
                'error' => 'No balance data available'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch spot balances for view: ' . $e->getMessage());
            
            return view('spot.balances', [
                'balances' => [],
                'totalEquity' => 0,
                'totalWalletBalance' => 0,
                'hasActiveExchange' => true,
                'exchangeMessage' => null,
                'error' => 'Failed to fetch balance data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Display create spot order form
     */
    public function createSpotOrderView()
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        
        if (!$exchangeStatus['hasActiveExchange']) {
            return view('spot.create_order', [
                'tradingPairs' => [],
                'favoriteMarkets' => [],
                'hasActiveExchange' => false,
                'exchangeMessage' => $exchangeStatus['message'],
                'error' => null
            ]);
        }
        
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            // Get available trading pairs
            $pairsData = $exchangeService->getSpotInstrumentsInfo();
            $tradingPairs = [];
            
            if (isset($pairsData['list']) && is_array($pairsData['list'])) {
                foreach ($pairsData['list'] as $instrument) {
                    if ($instrument['status'] === 'Trading') {
                        $tradingPairs[] = [
                            'symbol' => $instrument['symbol'],
                            'baseCoin' => $instrument['baseCoin'],
                            'quoteCoin' => $instrument['quoteCoin'],
                        ];
                    }
                }
            }
            
            // Get favorite markets (popular/high volume pairs)
            $favoriteMarkets = $this->getFavoriteMarkets($tradingPairs);
            
            return view('spot.create_order', [
                'tradingPairs' => $tradingPairs,
                'favoriteMarkets' => $favoriteMarkets,
                'hasActiveExchange' => true,
                'exchangeMessage' => null,
                'error' => null
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch trading pairs for create order view: ' . $e->getMessage());
            
            return view('spot.create_order', [
                'tradingPairs' => [],
                'favoriteMarkets' => [],
                'hasActiveExchange' => true,
                'exchangeMessage' => null,
                'error' => 'Failed to fetch trading pairs: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle spot order creation from web form
     */
    public function storeSpotOrderFromWeb(Request $request)
    {
        // Check if user has active exchange
        $exchangeStatus = $this->checkActiveExchange();
        if (!$exchangeStatus['hasActiveExchange']) {
            return back()->withErrors(['msg' => $exchangeStatus['message']])->withInput();
        }
        
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $validated = $request->validate([
                'side' => 'required|string|in:Buy,Sell',
                'symbol' => 'required|string',
                'orderType' => 'required|string|in:Market,Limit',
                'qty' => 'required|numeric|min:0.00000001',
                'price' => 'nullable|numeric|min:0.00000001',
                'timeInForce' => 'nullable|string|in:GTC,IOC,FOK',
            ]);

            // Validate that price is provided for limit orders
            if ($validated['orderType'] === 'Limit' && !isset($validated['price'])) {
                return back()->withErrors(['price' => 'Price is required for Limit orders'])->withInput();
            }

            // Use the same logic as API method
            $instrumentInfo = $exchangeService->getSpotInstrumentsInfo($validated['symbol']);
            
            if (empty($instrumentInfo['list'])) {
                return back()->withErrors(['symbol' => 'Invalid trading symbol: ' . $validated['symbol']])->withInput();
            }

            $instrument = $instrumentInfo['list'][0];
            
            $lotSizeFilter = $instrument['lotSizeFilter'] ?? [];
            $priceFilter = $instrument['priceFilter'] ?? [];
            $qtyStep = (float)($lotSizeFilter['qtyStep'] ?? $lotSizeFilter['basePrecision'] ?? 0.00000001);
            $priceStep = (float)($priceFilter['tickSize'] ?? $priceFilter['quotePrecision'] ?? 0.01);
            
            // Validate and adjust quantity to meet minimum requirements
            $qty = $this->validateAndAdjustQuantity($validated['qty'], $instrument);

            $orderParams = [
                'side' => $validated['side'],
                'symbol' => $validated['symbol'],
                'orderType' => $validated['orderType'],
                'qty' => $qty,
                'orderLinkId' => (string) Str::uuid(),
            ];

            if ($validated['orderType'] === 'Limit') {
                $price = $this->roundToStep($validated['price'], $priceStep);
                $orderParams['price'] = $price;
            }

            if (isset($validated['timeInForce'])) {
                $orderParams['timeInForce'] = $validated['timeInForce'];
            }

            $result = $exchangeService->createSpotOrder($orderParams);

            // Save to database (same logic as API method)
            $baseCoin = substr($orderParams['symbol'], 0, -4);
            $quoteCoin = substr($orderParams['symbol'], -4);
            
            if (str_ends_with($orderParams['symbol'], 'USDC')) {
                $baseCoin = substr($orderParams['symbol'], 0, -4);
                $quoteCoin = 'USDC';
            } elseif (str_ends_with($orderParams['symbol'], 'BTC')) {
                $baseCoin = substr($orderParams['symbol'], 0, -3);
                $quoteCoin = 'BTC';
            } elseif (str_ends_with($orderParams['symbol'], 'ETH')) {
                $baseCoin = substr($orderParams['symbol'], 0, -3);
                $quoteCoin = 'ETH';
            }

            // Get user's current active exchange ID
            $user = auth()->user();
            $currentExchange = $user->currentExchange ?? $user->defaultExchange;
            if (!$currentExchange) {
                return back()->withErrors(['msg' => 'لطفاً ابتدا در صفحه پروفایل، صرافی مورد نظر خود را فعال کنید.'])->withInput();
            }

            SpotOrder::create([
                'user_exchange_id' => $currentExchange->id,
                'order_id' => $result['orderId'] ?? null,
                'order_link_id' => $orderParams['orderLinkId'],
                'symbol' => $orderParams['symbol'],
                'base_coin' => $baseCoin,
                'quote_coin' => $quoteCoin,
                'side' => $orderParams['side'],
                'order_type' => $orderParams['orderType'],
                'qty' => $qty,
                'price' => $orderParams['price'] ?? null,
                'time_in_force' => $orderParams['timeInForce'] ?? 'GTC',
                'status' => 'New',
                'order_created_at' => now(),
                'raw_response' => $result,
            ]);

            return redirect()->route('spot.orders.view')
                ->with('success', 'Spot order created successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Spot order creation from web failed: ' . $e->getMessage());
            
            // Parse Bybit error message for user-friendly response
            $userFriendlyMessage = $this->parseBybitError($e->getMessage());
            
            return back()->withErrors(['error' => $userFriendlyMessage])->withInput();
        }
    }

    /**
     * Get favorite/popular markets based on common trading pairs
     * Since we can't get actual favorites from exchange, we'll use popular pairs
     */
    private function getFavoriteMarkets(array $tradingPairs): array
    {
        // Define popular trading pairs that users typically favor
        $popularSymbols = [
            'BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'ADAUSDT', 'XRPUSDT', 
            'SOLUSDT', 'DOGEUSDT', 'DOTUSDT', 'LINKUSDT', 'LTCUSDT',
            'MATICUSDT', 'AVAXUSDT', 'UNIUSDT', 'ATOMUSDT', 'FILUSDT'
        ];
        
        $favorites = [];
        
        // Filter trading pairs to get only the popular ones that are available
        foreach ($popularSymbols as $symbol) {
            foreach ($tradingPairs as $pair) {
                if ($pair['symbol'] === $symbol) {
                    $favorites[] = $pair;
                    break;
                }
            }
        }
        
        return $favorites;
    }

    /**
     * Cancel spot order from web interface
     */
    public function cancelSpotOrderFromWeb(Request $request)
    {
        try {
            // Get user's active exchange service
            $exchangeService = $this->getExchangeService();
            
            $validated = $request->validate([
                'orderId' => 'required|string',
                'symbol' => 'required|string',
            ]);

            // Cancel order via exchange API
            $result = $exchangeService->cancelSpotOrderWithSymbol(
                $validated['orderId'],
                $validated['symbol']
            );

            // Update order status in local database
            $spotOrder = SpotOrder::where('order_id', $validated['orderId'])
                                ->where('symbol', $validated['symbol'])
                                ->forUser(auth()->id())
                                ->first();

            if ($spotOrder) {
                $spotOrder->update([
                    'status' => 'Cancelled',
                    'updated_at' => now()
                ]);
            }

            return redirect()->route('spot.orders.view')
                ->with('success', 'سفارش با موفقیت لغو شد.');

        } catch (\Exception $e) {
            Log::error('Cancel spot order from web failed: ' . $e->getMessage());
            
            // Parse error for user-friendly message
            $userFriendlyMessage = $this->parseBybitError($e->getMessage());
            
            return back()->withErrors(['error' => $userFriendlyMessage]);
        }
    }
}