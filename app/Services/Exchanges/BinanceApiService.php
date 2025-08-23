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
     * Test connection to exchange
     */
    public function testConnection(): array
    {
        try {
            $response = $this->client->get('/api/v3/time');
            $data = json_decode($response->getBody(), true);
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'server_time' => $data['serverTime'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Binance connection test failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
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
    public function cancelOrder(string $symbol, string $orderId): array
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
}