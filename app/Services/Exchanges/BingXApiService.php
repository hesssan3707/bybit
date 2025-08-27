<?php

namespace App\Services\Exchanges;

use App\Services\Exchanges\ExchangeApiServiceInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BingXApiService implements ExchangeApiServiceInterface
{
    protected $client;
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = 'https://open-api.bingx.com';
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
            
            $response = $this->client->get('/openApi/spot/v1/account', [
                'headers' => [
                    'X-BX-APIKEY' => $this->apiKey,
                ],
                'query' => [
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (!$data['success'] || !isset($data['data']['balances'])) {
                throw new \Exception('Invalid response from BingX API');
            }

            $balances = [];
            foreach ($data['data']['balances'] as $balance) {
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
            Log::error('BingX get account balance failed: ' . $e->getMessage());
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

            $response = $this->client->post('/openApi/spot/v1/trade/order', [
                'headers' => [
                    'X-BX-APIKEY' => $this->apiKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $params
            ]);

            $data = json_decode($response->getBody(), true);

            if (!$data['success']) {
                throw new \Exception($data['msg'] ?? 'Unknown error from BingX API');
            }

            $orderInfo = $data['data'];

            return [
                'success' => true,
                'order_id' => $orderInfo['orderId'],
                'client_order_id' => $orderInfo['clientOrderId'] ?? null,
                'symbol' => $orderInfo['symbol'],
                'status' => $orderInfo['status'],
                'side' => $orderInfo['side'],
                'type' => $orderInfo['type'],
                'quantity' => $orderInfo['origQty'],
                'price' => $orderInfo['price'] ?? null,
                'executed_qty' => $orderInfo['executedQty'] ?? '0',
                'executed_price' => $orderInfo['price'] ?? null,
                'time' => $orderInfo['transactTime'] ?? $timestamp,
                'raw_response' => $orderInfo,
            ];

        } catch (\Exception $e) {
            Log::error('BingX create spot order failed: ' . $e->getMessage());
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
        // For BingX, redirect to spot order creation
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
                'limit' => min($limit, 1000), // BingX max is 1000
            ];

            if ($symbol) {
                $params['symbol'] = $symbol;
            }

            $queryString = http_build_query($params);
            $signature = hash_hmac('sha256', $queryString, $this->apiSecret);

            $endpoint = $symbol ? '/openApi/spot/v1/trade/allOrders' : '/openApi/spot/v1/trade/openOrders';
            
            $response = $this->client->get($endpoint, [
                'headers' => [
                    'X-BX-APIKEY' => $this->apiKey,
                ],
                'query' => array_merge($params, ['signature' => $signature])
            ]);

            $data = json_decode($response->getBody(), true);

            if (!$data['success']) {
                throw new \Exception($data['msg'] ?? 'Unknown error from BingX API');
            }

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
            }, $data['data'] ?? []);

            return [
                'success' => true,
                'orders' => $orders,
            ];

        } catch (\Exception $e) {
            Log::error('BingX get order history failed: ' . $e->getMessage());
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
            $response = $this->client->get('/openApi/spot/v1/common/symbols');
            $data = json_decode($response->getBody(), true);

            if (!$data['success']) {
                throw new \Exception($data['msg'] ?? 'Unknown error from BingX API');
            }

            $pairs = array_map(function ($symbol) {
                return [
                    'symbol' => $symbol['symbol'],
                    'base_asset' => $symbol['baseAsset'],
                    'quote_asset' => $symbol['quoteAsset'],
                    'status' => $symbol['status'],
                ];
            }, $data['data']['symbols'] ?? []);

            return [
                'success' => true,
                'pairs' => $pairs,
            ];

        } catch (\Exception $e) {
            Log::error('BingX get trading pairs failed: ' . $e->getMessage());
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
            $response = $this->client->get('/openApi/spot/v1/ticker/24hr', [
                'query' => ['symbol' => $symbol]
            ]);
            
            $data = json_decode($response->getBody(), true);

            if (!$data['success']) {
                throw new \Exception($data['msg'] ?? 'Unknown error from BingX API');
            }

            $ticker = $data['data'];

            return [
                'success' => true,
                'symbol' => $ticker['symbol'],
                'price' => $ticker['lastPrice'],
                'change_24h' => $ticker['priceChange'],
                'change_percent_24h' => $ticker['priceChangePercent'],
                'high_24h' => $ticker['highPrice'],
                'low_24h' => $ticker['lowPrice'],
                'volume_24h' => $ticker['volume'],
                'raw_response' => $ticker,
            ];

        } catch (\Exception $e) {
            Log::error('BingX get ticker info failed: ' . $e->getMessage());
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
        // Note: BingX requires symbol for cancellation
        throw new \Exception('Use cancelOrder with symbol parameter for BingX');
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

            $response = $this->client->delete('/openApi/spot/v1/trade/order', [
                'headers' => [
                    'X-BX-APIKEY' => $this->apiKey,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $params
            ]);

            $data = json_decode($response->getBody(), true);

            if (!$data['success']) {
                throw new \Exception($data['msg'] ?? 'Unknown error from BingX API');
            }

            $orderInfo = $data['data'];

            return [
                'success' => true,
                'order_id' => $orderInfo['orderId'],
                'symbol' => $orderInfo['symbol'],
                'status' => $orderInfo['status'],
                'raw_response' => $orderInfo,
            ];

        } catch (\Exception $e) {
            Log::error('BingX cancel order failed: ' . $e->getMessage());
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
        throw new \Exception('Use getOrder with symbol parameter for BingX');
    }

    public function getSpotOrder(string $orderId): array
    {
        throw new \Exception('Use getSpotOrder with symbol parameter for BingX');
    }

    public function cancelSpotOrder(string $orderId): array
    {
        throw new \Exception('Use cancelSpotOrder with symbol parameter for BingX');
    }

    public function cancelSpotOrderWithSymbol(string $orderId, string $symbol): array
    {
        return $this->cancelOrderWithSymbol($orderId, $symbol);
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

            $response = $this->client->get('/openApi/spot/v1/trade/openOrders', [
                'headers' => ['X-BX-APIKEY' => $this->apiKey],
                'query' => array_merge($params, ['signature' => $signature])
            ]);

            $data = json_decode($response->getBody(), true);
            
            if (!$data['success']) {
                throw new \Exception($data['msg'] ?? 'Unknown error from BingX API');
            }
            
            return ['list' => $data['data'] ?? []];

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
        // BingX spot doesn't have positions concept, return empty
        return ['list' => []];
    }

    public function closePosition(string $symbol, string $side, float $qty): array
    {
        throw new \Exception('BingX spot does not support futures positions');
    }

    public function setStopLoss(string $symbol, float $stopLoss, string $side): array
    {
        throw new \Exception('BingX spot does not support futures stop loss');
    }

    public function setStopLossAdvanced(array $params): array
    {
        throw new \Exception('BingX spot does not support futures stop loss with advanced parameters');
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
        // BingX spot doesn't have PnL concept for spot trading
        return ['list' => []];
    }

    public function getHistoryOrder(string $orderId): array
    {
        throw new \Exception('Use getHistoryOrder with symbol parameter for BingX');
    }

    public function setTradingStop(array $params): array
    {
        throw new \Exception('BingX spot does not support trading stop');
    }

    public function getExchangeName(): string
    {
        return 'bingx';
    }

    public function testConnection(): bool
    {
        try {
            $response = $this->client->get('/openApi/spot/v1/time');
            $data = json_decode($response->getBody(), true);
            return $response->getStatusCode() === 200 && ($data['success'] ?? false);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_second' => 10,
            'requests_per_minute' => 600,
            'orders_per_second' => 5,
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
            
            // Parse specific BingX error codes
            $isPermissionError = str_contains($errorMessage, 'Invalid API-key') ||
                               str_contains($errorMessage, 'Invalid signature') ||
                               str_contains($errorMessage, 'API key not found') ||
                               str_contains($errorMessage, 'Unauthorized') ||
                               str_contains($errorMessage, 'Permission denied');
            
            $isIPBlocked = str_contains($errorMessage, 'IP not allowed') ||
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
        // BingX spot API doesn't have futures capabilities in this configuration
        return [
            'success' => false,
            'message' => 'BingX spot API does not support futures trading',
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
            $response = $this->client->get('/openApi/spot/v1/time');
            $timeData = json_decode($response->getBody(), true);
            
            if (!$timeData['success']) {
                throw new \Exception('Failed to get server time');
            }
            
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
                    'server_time' => $timeData['data']['serverTime'] ?? null,
                    'authenticated_access' => true
                ]
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            $isIPBlocked = str_contains($errorMessage, 'IP not allowed') ||
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