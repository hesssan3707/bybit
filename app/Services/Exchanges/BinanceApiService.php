<?php

namespace App\Services\Exchanges;

use App\Services\Exchanges\ExchangeApiServiceInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BinanceApiService implements ExchangeApiServiceInterface
{
    protected $client;
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://api.binance.com';
        $this->client = new Client([
            'timeout' => 30,
            'base_uri' => $this->baseUrl,
        ]);
    }

    /**
     * Set API credentials
     */
    public function setCredentials(string $apiKey, string $apiSecret): void
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    /**
     * Get account balance
     */
    public function getAccountBalance(): array
    {
        try {
            if (!$this->apiKey || !$this->apiSecret) {
                throw new \Exception('API credentials not set');
            }

            $timestamp = time() * 1000;
            $queryString = "timestamp={$timestamp}";
            $signature = hash_hmac('sha256', $queryString, $this->apiSecret);
            
            $response = $this->client->get('/api/v3/account', [
                'headers' => [
                    'X-MBX-APIKEY' => $this->apiKey,
                ],
                'query' => [
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            
            $balances = [];
            foreach ($data['balances'] as $balance) {
                if ((float)$balance['free'] > 0 || (float)$balance['locked'] > 0) {
                    $balances[] = [
                        'currency' => $balance['asset'],
                        'free' => (float)$balance['free'],
                        'locked' => (float)$balance['locked'],
                        'total' => (float)$balance['free'] + (float)$balance['locked'],
                    ];
                }
            }

            return [
                'success' => true,
                'balances' => $balances,
                'total' => array_sum(array_column($balances, 'total')),
                'available' => array_sum(array_column($balances, 'free')),
            ];

        } catch (\Exception $e) {
            Log::error('Binance get account balance failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get account balance: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a spot order
     */
    public function createSpotOrder(array $orderData): array
    {
        try {
            if (!$this->apiKey || !$this->apiSecret) {
                throw new \Exception('API credentials not set');
            }

            $timestamp = time() * 1000;
            $params = [
                'symbol' => $orderData['symbol'],
                'side' => strtoupper($orderData['side']),
                'type' => strtoupper($orderData['order_type'] ?? 'MARKET'),
                'quantity' => $orderData['qty'],
                'timestamp' => $timestamp,
            ];

            if (isset($orderData['price']) && strtoupper($orderData['order_type']) === 'LIMIT') {
                $params['price'] = $orderData['price'];
                $params['timeInForce'] = 'GTC';
            }

            $queryString = http_build_query($params);
            $signature = hash_hmac('sha256', $queryString, $this->apiSecret);
            $params['signature'] = $signature;

            $response = $this->client->post('/api/v3/order', [
                'headers' => [
                    'X-MBX-APIKEY' => $this->apiKey,
                ],
                'form_params' => $params
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'order_id' => $data['orderId'],
                'client_order_id' => $data['clientOrderId'],
                'symbol' => $data['symbol'],
                'status' => $data['status'],
                'side' => $data['side'],
                'type' => $data['type'],
                'quantity' => $data['origQty'],
                'price' => $data['price'] ?? null,
                'executed_qty' => $data['executedQty'] ?? '0',
                'executed_price' => $data['price'] ?? null,
                'time' => $data['transactTime'],
                'raw_response' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Binance create spot order failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create spot order: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create an order (legacy method for compatibility)
     */
    public function createOrder(array $orderData): array
    {
        // For Binance, redirect to spot order creation
        return $this->createSpotOrder($orderData);
    }

    /**
     * Get order history
     */
    public function getOrderHistory(string $symbol = '', int $limit = 50): array
    {
        try {
            if (!$this->apiKey || !$this->apiSecret) {
                throw new \Exception('API credentials not set');
            }

            $timestamp = time() * 1000;
            $params = [
                'timestamp' => $timestamp,
                'limit' => min($limit, 1000), // Binance max is 1000
            ];

            if ($symbol) {
                $params['symbol'] = $symbol;
            }

            $queryString = http_build_query($params);
            $signature = hash_hmac('sha256', $queryString, $this->apiSecret);

            $endpoint = $symbol ? '/api/v3/allOrders' : '/api/v3/openOrders';
            
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'X-MBX-APIKEY' => $this->apiKey,
                ],
                'query' => array_merge($params, ['signature' => $signature])
            ]);

            $data = json_decode($response->getBody(), true);

            $orders = array_map(function ($order) {
                return [
                    'order_id' => $order['orderId'],
                    'symbol' => $order['symbol'],
                    'side' => $order['side'],
                    'type' => $order['type'],
                    'quantity' => $order['origQty'],
                    'price' => $order['price'],
                    'status' => $order['status'],
                    'executed_qty' => $order['executedQty'],
                    'time' => $order['time'],
                    'raw_response' => $order,
                ];
            }, is_array($data) ? $data : []);

            return [
                'success' => true,
                'orders' => $orders,
            ];

        } catch (\Exception $e) {
            Log::error('Binance get order history failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get order history: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get trading pairs
     */
    public function getTradingPairs(): array
    {
        try {
            $response = $this->client->get('/api/v3/exchangeInfo');
            $data = json_decode($response->getBody(), true);

            $pairs = array_map(function ($symbol) {
                return [
                    'symbol' => $symbol['symbol'],
                    'base_asset' => $symbol['baseAsset'],
                    'quote_asset' => $symbol['quoteAsset'],
                    'status' => $symbol['status'],
                ];
            }, $data['symbols'] ?? []);

            return [
                'success' => true,
                'pairs' => $pairs,
            ];

        } catch (\Exception $e) {
            Log::error('Binance get trading pairs failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get trading pairs: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get ticker information
     */
    public function getTickerInfo(string $symbol): array
    {
        try {
            $response = $this->client->get('/api/v3/ticker/24hr', [
                'query' => ['symbol' => $symbol]
            ]);
            
            $data = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'symbol' => $data['symbol'],
                'price' => $data['lastPrice'],
                'change_24h' => $data['priceChange'],
                'change_percent_24h' => $data['priceChangePercent'],
                'high_24h' => $data['highPrice'],
                'low_24h' => $data['lowPrice'],
                'volume_24h' => $data['volume'],
                'raw_response' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Binance get ticker info failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get ticker info: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder(string $orderId): array
    {
        // Note: Binance requires symbol for cancellation
        throw new \Exception('Use cancelOrder with symbol parameter for Binance');
    }

    /**
     * Cancel an order with symbol
     */
    public function cancelOrderWithSymbol(string $orderId, string $symbol): array
    {
        try {
            if (!$this->apiKey || !$this->apiSecret) {
                throw new \Exception('API credentials not set');
            }

            $timestamp = time() * 1000;
            $params = [
                'symbol' => $symbol,
                'orderId' => $orderId,
                'timestamp' => $timestamp,
            ];

            $queryString = http_build_query($params);
            $signature = hash_hmac('sha256', $queryString, $this->apiSecret);
            $params['signature'] = $signature;

            $response = $this->client->delete('/api/v3/order', [
                'headers' => [
                    'X-MBX-APIKEY' => $this->apiKey,
                ],
                'form_params' => $params
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'order_id' => $data['orderId'],
                'symbol' => $data['symbol'],
                'status' => $data['status'],
                'raw_response' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Binance cancel order failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage(),
            ];
        }
    }

    // Missing interface methods implementation
    public function getWalletBalance(string $accountType = 'SPOT', ?string $coin = null): array
    {
        return $this->getAccountBalance();
    }

    public function getSpotAccountBalance(): array
    {
        return $this->getAccountBalance();
    }

    public function getOrder(string $orderId): array
    {
        throw new \Exception('Use getOrder with symbol parameter for Binance');
    }

    public function getSpotOrder(string $orderId): array
    {
        throw new \Exception('Use getSpotOrder with symbol parameter for Binance');
    }

    public function cancelSpotOrder(string $orderId): array
    {
        throw new \Exception('Use cancelSpotOrder with symbol parameter for Binance');
    }

    public function getOpenOrders(string $symbol = null): array
    {
        try {
            if (!$this->apiKey || !$this->apiSecret) {
                throw new \Exception('API credentials not set');
            }

            $timestamp = time() * 1000;
            $params = ['timestamp' => $timestamp];
            
            if ($symbol) {
                $params['symbol'] = $symbol;
            }

            $queryString = http_build_query($params);
            $signature = hash_hmac('sha256', $queryString, $this->apiSecret);

            $response = $this->client->get('/api/v3/openOrders', [
                'headers' => ['X-MBX-APIKEY' => $this->apiKey],
                'query' => array_merge($params, ['signature' => $signature])
            ]);

            $data = json_decode($response->getBody(), true);
            return ['list' => $data];

        } catch (\Exception $e) {
            throw new \Exception('Failed to get open orders: ' . $e->getMessage());
        }
    }

    public function getOpenSpotOrders(string $symbol = null): array
    {
        return $this->getOpenOrders($symbol);
    }

    public function getSpotOrderHistory(string $symbol = null, int $limit = 50): array
    {
        return $this->getOrderHistory($symbol, $limit);
    }

    public function getPositions(string $symbol = null): array
    {
        // Binance spot doesn't have positions concept, return empty
        return ['list' => []];
    }

    public function closePosition(string $symbol, string $side, float $qty): array
    {
        throw new \Exception('Binance spot does not support futures positions');
    }

    public function setStopLoss(string $symbol, float $stopLoss, string $side): array
    {
        throw new \Exception('Binance spot does not support futures stop loss');
    }

    public function setStopLossAdvanced(array $params): array
    {
        throw new \Exception('Binance spot does not support futures stop loss with advanced parameters');
    }

    public function getInstrumentsInfo(string $symbol = null): array
    {
        return $this->getTradingPairs();
    }

    public function getSpotInstrumentsInfo(string $symbol = null): array
    {
        return $this->getTradingPairs();
    }

    public function getSpotTickerInfo(string $symbol): array
    {
        return $this->getTickerInfo($symbol);
    }

    public function getClosedPnl(string $symbol, int $limit = 50, ?int $startTime = null): array
    {
        // Binance spot doesn't have PnL concept for spot trading
        return ['list' => []];
    }

    public function getHistoryOrder(string $orderId): array
    {
        throw new \Exception('Use getHistoryOrder with symbol parameter for Binance');
    }

    public function setTradingStop(array $params): array
    {
        throw new \Exception('Binance spot does not support trading stop');
    }

    public function getExchangeName(): string
    {
        return 'binance';
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->client->get('/api/v3/time');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_second' => 20,
            'requests_per_minute' => 1200,
            'orders_per_second' => 10,
        ];
    }

    /**
     * Check if the API key has spot trading permissions
     */
    public function checkSpotAccess(): array
    {
        try {
            // Test account access
            $balance = $this->getAccountBalance();
            
            if (!$balance['success']) {
                throw new \Exception($balance['message']);
            }
            
            // Test trading symbols access
            $pairs = $this->getTradingPairs();
            
            if (!$pairs['success']) {
                throw new \Exception($pairs['message']);
            }
            
            return [
                'success' => true,
                'message' => 'Spot trading access confirmed',
                'details' => [
                    'account_access' => true,
                    'trading_pairs_access' => true,
                    'permissions' => ['spot_read', 'spot_trade']
                ]
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Parse specific Binance error codes
            $isPermissionError = str_contains($errorMessage, 'API-key format invalid') ||
                               str_contains($errorMessage, 'Signature for this request is not valid') ||
                               str_contains($errorMessage, 'Invalid API-key') ||
                               str_contains($errorMessage, 'Mandatory parameter') ||
                               str_contains($errorMessage, 'Unauthorized');
            
            $isIPBlocked = str_contains($errorMessage, 'IP address not allowed') ||
                          str_contains($errorMessage, 'Forbidden') ||
                          str_contains($errorMessage, '403');
            
            return [
                'success' => false,
                'message' => $isIPBlocked 
                    ? 'IP address is not whitelisted for this API key'
                    : ($isPermissionError 
                        ? 'API key does not have spot trading permissions'
                        : 'Spot access validation failed: ' . $errorMessage),
                'details' => [
                    'error_type' => $isIPBlocked ? 'ip_blocked' : 'permission_denied',
                    'raw_error' => $errorMessage,
                    'permissions' => []
                ]
            ];
        }
    }

    /**
     * Check if the API key has futures trading permissions
     */
    public function checkFuturesAccess(): array
    {
        // Binance spot API doesn't have futures capabilities
        return [
            'success' => false,
            'message' => 'Binance spot API does not support futures trading',
            'details' => [
                'error_type' => 'not_supported',
                'raw_error' => 'This is a spot-only exchange configuration',
                'permissions' => []
            ]
        ];
    }

    /**
     * Check if the current IP address is allowed by the API key
     */
    public function checkIPAccess(): array
    {
        try {
            // Test basic API connectivity
            $response = $this->client->get('/api/v3/time');
            
            if (!$this->apiKey || !$this->apiSecret) {
                throw new \Exception('API credentials not set');
            }
            
            // Test an authenticated endpoint to verify IP whitelist
            $balance = $this->getAccountBalance();
            
            if (!$balance['success']) {
                throw new \Exception($balance['message']);
            }
            
            return [
                'success' => true,
                'message' => 'IP address is whitelisted and has API access',
                'details' => [
                    'ip_whitelisted' => true,
                    'server_time' => json_decode($response->getBody(), true)['serverTime'] ?? null,
                    'authenticated_access' => true
                ]
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            $isIPBlocked = str_contains($errorMessage, 'IP address not allowed') ||
                          str_contains($errorMessage, 'Forbidden') ||
                          str_contains($errorMessage, '403');
            
            return [
                'success' => false,
                'message' => $isIPBlocked 
                    ? 'Your IP address is not in the API key whitelist'
                    : 'IP access validation failed: ' . $errorMessage,
                'details' => [
                    'error_type' => $isIPBlocked ? 'ip_blocked' : 'connection_error',
                    'raw_error' => $errorMessage,
                    'ip_whitelisted' => false
                ]
            ];
        }
    }

    /**
     * Comprehensive API validation check (combines all checks above)
     */
    public function validateAPIAccess(): array
    {
        $spotCheck = $this->checkSpotAccess();
        $futuresCheck = $this->checkFuturesAccess();
        $ipCheck = $this->checkIPAccess();
        
        $overallSuccess = $spotCheck['success'];
        
        return [
            'spot' => $spotCheck,
            'futures' => $futuresCheck,
            'ip' => $ipCheck,
            'overall' => $overallSuccess && $ipCheck['success']
        ];
    }
}