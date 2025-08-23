<?php

namespace App\Http\Controllers;

use App\Models\SpotOrder;
use App\Services\Exchanges\BybitApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SpotTradingController extends Controller
{
    protected $bybitApiService;

    public function __construct(BybitApiService $bybitApiService)
    {
        $this->bybitApiService = $bybitApiService;
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
            $instrumentInfo = $this->bybitApiService->getSpotInstrumentsInfo($validated['symbol']);
            
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
            
            // Round quantity to proper precision
            $qty = $this->roundToStep($validated['qty'], $qtyStep);
            
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
            $result = $this->bybitApiService->createSpotOrder($orderParams);

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
            $balanceData = $this->bybitApiService->getSpotAccountBalance();
            
            if (empty($balanceData['list'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No balance data found'
                ], 404);
            }

            $account = $balanceData['list'][0];
            $currencies = [];
            
            // Process each currency balance
            if (isset($account['coin']) && is_array($account['coin'])) {
                foreach ($account['coin'] as $coin) {
                    $currencies[] = [
                        'currency' => $coin['coin'],
                        'walletBalance' => (float)$coin['walletBalance'],
                        'transferBalance' => (float)$coin['transferBalance'],
                        'bonus' => (float)$coin['bonus'],
                        'usdValue' => isset($coin['usdValue']) ? (float)$coin['usdValue'] : null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Account balance retrieved successfully',
                'data' => [
                    'accountType' => $account['accountType'] ?? 'SPOT',
                    'totalEquity' => (float)($account['totalEquity'] ?? 0),
                    'totalWalletBalance' => (float)($account['totalWalletBalance'] ?? 0),
                    'totalMarginBalance' => (float)($account['totalMarginBalance'] ?? 0),
                    'totalAvailableBalance' => (float)($account['totalAvailableBalance'] ?? 0),
                    'currencies' => $currencies
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get account balance failed: ' . $e->getMessage());
            
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
            $validated = $request->validate([
                'symbol' => 'required|string',
            ]);

            $result = $this->bybitApiService->getSpotTickerInfo($validated['symbol']);

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
            $validated = $request->validate([
                'orderId' => 'required|string',
                'symbol' => 'required|string',
            ]);

            $result = $this->bybitApiService->cancelSpotOrder(
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
            $result = $this->bybitApiService->getSpotInstrumentsInfo();
            
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

    // Web View Methods

    /**
     * Display spot orders list view
     */
    public function spotOrdersView(Request $request)
    {
        $orders = SpotOrder::forUser(auth()->id())
            ->latest('created_at')
            ->paginate(20);

        return view('spot.orders', compact('orders'));
    }

    /**
     * Display spot balances view
     */
    public function spotBalancesView()
    {
        try {
            $balanceData = $this->bybitApiService->getSpotAccountBalance();
            
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
                                'transferBalance' => (float)$coin['transferBalance'],
                                'bonus' => (float)$coin['bonus'],
                                'usdValue' => isset($coin['usdValue']) ? (float)$coin['usdValue'] : null,
                            ];
                        }
                    }
                }
                
                return view('spot.balances', compact('balances', 'totalEquity', 'totalWalletBalance'));
            }
            
            return view('spot.balances', [
                'balances' => [],
                'totalEquity' => 0,
                'totalWalletBalance' => 0,
                'error' => 'No balance data available'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch spot balances for view: ' . $e->getMessage());
            
            return view('spot.balances', [
                'balances' => [],
                'totalEquity' => 0,
                'totalWalletBalance' => 0,
                'error' => 'Failed to fetch balance data: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Display create spot order form
     */
    public function createSpotOrderView()
    {
        try {
            // Get available trading pairs
            $pairsData = $this->bybitApiService->getSpotInstrumentsInfo();
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
            
            return view('spot.create_order', compact('tradingPairs'));
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch trading pairs for create order view: ' . $e->getMessage());
            
            return view('spot.create_order', [
                'tradingPairs' => [],
                'error' => 'Failed to fetch trading pairs: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle spot order creation from web form
     */
    public function storeSpotOrderFromWeb(Request $request)
    {
        try {
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
            $instrumentInfo = $this->bybitApiService->getSpotInstrumentsInfo($validated['symbol']);
            
            if (empty($instrumentInfo['list'])) {
                return back()->withErrors(['symbol' => 'Invalid trading symbol'])->withInput();
            }

            $instrument = $instrumentInfo['list'][0];
            $qtyStep = (float)$instrument['lotSizeFilter']['qtyStep'];
            $priceStep = (float)$instrument['priceFilter']['tickSize'];
            
            $qty = $this->roundToStep($validated['qty'], $qtyStep);

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

            $result = $this->bybitApiService->createSpotOrder($orderParams);

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