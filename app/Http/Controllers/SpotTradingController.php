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
    use HandlesExchangeAccess;
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
                    'message' => 'Invalid trading symbol'
                ], 400);
            }

            $instrument = $instrumentInfo['list'][0];
            
            // Format quantity and price according to instrument precision
            $qtyStep = (float)$instrument['lotSizeFilter']['qtyStep'];
            $priceStep = (float)$instrument['priceFilter']['tickSize'];
            
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

            // Save order to database
            $spotOrder = SpotOrder::create([
                'user_id' => $request->user()->id,
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
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create spot order: ' . $e->getMessage()
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
                    $tradingPairs[] = [
                        'symbol' => $instrument['symbol'],
                        'baseCoin' => $instrument['baseCoin'],
                        'quoteCoin' => $instrument['quoteCoin'],
                        'status' => $instrument['status'],
                        'minOrderQty' => $instrument['lotSizeFilter']['minOrderQty'],
                        'maxOrderQty' => $instrument['lotSizeFilter']['maxOrderQty'],
                        'qtyStep' => $instrument['lotSizeFilter']['qtyStep'],
                        'tickSize' => $instrument['priceFilter']['tickSize'],
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
        $qtyStep = (float)$instrument['lotSizeFilter']['qtyStep'];
        $minOrderQty = (float)$instrument['lotSizeFilter']['minOrderQty'];
        
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
            
            return view('spot.create_order', [
                'tradingPairs' => $tradingPairs,
                'hasActiveExchange' => true,
                'exchangeMessage' => null,
                'error' => null
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch trading pairs for create order view: ' . $e->getMessage());
            
            return view('spot.create_order', [
                'tradingPairs' => [],
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
                return back()->withErrors(['symbol' => 'Invalid trading symbol'])->withInput();
            }

            $instrument = $instrumentInfo['list'][0];
            $qtyStep = (float)$instrument['lotSizeFilter']['qtyStep'];
            $priceStep = (float)$instrument['priceFilter']['tickSize'];
            
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

            SpotOrder::create([
                'user_id' => auth()->id(),
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
            
            return back()->withErrors(['error' => 'Failed to create spot order: ' . $e->getMessage()])->withInput();
        }
    }
}