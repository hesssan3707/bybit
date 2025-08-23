<?php

namespace App\Services\Exchanges;

use Illuminate\Support\Facades\Http;

class BybitApiService implements ExchangeApiServiceInterface
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $recvWindow = 5000;

    public function __construct()
    {
        // Don't initialize API credentials from .env - they will be set via setCredentials()
        $this->apiKey = null;
        $this->apiSecret = null;
        $isTestnet = env('BYBIT_TESTNET', false);
        $this->baseUrl = $isTestnet ? 'https://api-testnet.bybit.com' : 'https://api.bybit.com';
    }

    public function setCredentials(string $apiKey, string $apiSecret): void
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    private function generateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->apiSecret);
    }

    private function sendRequest(string $method, string $endpoint, array $params = [])
    {
        // Validate API credentials are set
        if (!$this->apiKey || !$this->apiSecret) {
            throw new \Exception('API credentials not set. Please ensure the exchange is properly configured.');
        }

        $timestamp = intval(microtime(true) * 1000);

        $payloadToSign = '';
        if ($method === 'GET') {
            $queryString = http_build_query($params, '', '&');
            $payloadToSign = $timestamp . $this->apiKey . $this->recvWindow . $queryString;
        } else { // POST
            $jsonPayload = json_encode($params);
            $payloadToSign = $timestamp . $this->apiKey . $this->recvWindow . $jsonPayload;
        }

        $signature = $this->generateSignature($payloadToSign);

        $headers = [
            'X-BAPI-API-KEY' => $this->apiKey,
            'X-BAPI-SIGN' => $signature,
            'X-BAPI-TIMESTAMP' => $timestamp,
            'X-BAPI-RECV-WINDOW' => $this->recvWindow,
            'Content-Type' => 'application/json',
        ];

        $response = $method === 'GET'
            ? Http::withHeaders($headers)->timeout(10)->connectTimeout(5)->get("{$this->baseUrl}{$endpoint}", $params)
            : Http::withHeaders($headers)->timeout(10)->connectTimeout(5)->post("{$this->baseUrl}{$endpoint}", $params);

        $responseData = $response->json();

        if ($response->failed() || ($responseData['retCode'] ?? 0) !== 0) {
            $errorCode = $responseData['retCode'] ?? 'N/A';
            $errorMsg = $responseData['retMsg'] ?? 'Unknown error';
            $requestBody = json_encode($params);
            // Add the full response body to the exception message for better debugging.
            $fullResponse = $response->body();
            throw new \Exception(
                "Bybit API Error on {$endpoint}. Code: {$errorCode}, Msg: {$errorMsg}, Request: {$requestBody}, Full Response: {$fullResponse}"
            );
        }

        return $responseData['result'];
    }

    public function getInstrumentsInfo(): array
    {
        $params = ['category' => 'linear'];
        return $this->sendRequest('GET', '/v5/market/instruments-info', $params);
    }

    public function createOrder(array $orderData): array
    {
        return $this->sendRequest('POST', '/v5/order/create', $orderData);
    }

    public function getOpenOrdersBySymbol(string $symbol): array
    {
        return $this->sendRequest('GET', '/v5/order/realtime', ['category' => 'linear', 'symbol' => $symbol]);
    }

    public function getHistoryOrder(string $orderId): array
    {
        return $this->sendRequest('GET', '/v5/order/history', ['category' => 'linear', 'orderId' => $orderId]);
    }

    public function cancelOrderWithSymbol(string $orderId, string $symbol): array
    {
        return $this->sendRequest('POST', '/v5/order/cancel', ['category' => 'linear', 'orderId' => $orderId, 'symbol' => $symbol]);
    }

    public function getPositionInfo(string $symbol): array
    {
        return $this->sendRequest('GET', '/v5/position/list', ['category' => 'linear', 'symbol' => $symbol]);
    }

    public function setTradingStop(array $params): array
    {
        return $this->sendRequest('POST', '/v5/position/set-trading-stop', $params);
    }

    public function getClosedPnl(string $symbol, int $limit = 50, ?int $startTime = null): array
    {
        $params = [
            'category' => 'linear',
            'symbol' => $symbol,
            'limit' => $limit,
        ];

        if ($startTime) {
            $params['startTime'] = $startTime;
        }

        return $this->sendRequest('GET', '/v5/position/closed-pnl', $params);
    }

    public function getWalletBalance(string $accountType = 'UNIFIED', ?string $coin = null): array
    {
        $params = ['accountType' => $accountType];
        if ($coin) {
            $params['coin'] = $coin;
        }
        return $this->sendRequest('GET', '/v5/account/wallet-balance', $params);
    }

    public function getTickerInfo(string $symbol): array
    {
        return $this->sendRequest('GET', '/v5/market/tickers', ['category' => 'linear', 'symbol' => $symbol]);
    }

    /**
     * Create a spot trading order
     * 
     * @param array $orderData - Order parameters including:
     *   - side: 'Buy' or 'Sell'
     *   - symbol: Trading pair (e.g., 'BTCUSDT')
     *   - orderType: 'Market' or 'Limit'
     *   - qty: Order quantity
     *   - price: Order price (required for Limit orders)
     */
    public function createSpotOrder(array $orderData): array
    {
        $orderParams = [
            'category' => 'spot',
            'symbol' => $orderData['symbol'],
            'side' => $orderData['side'],
            'orderType' => $orderData['orderType'],
            'qty' => (string)$orderData['qty'],
        ];

        // Add price for limit orders
        if ($orderData['orderType'] === 'Limit' && isset($orderData['price'])) {
            $orderParams['price'] = (string)$orderData['price'];
        }

        // Add optional parameters
        if (isset($orderData['timeInForce'])) {
            $orderParams['timeInForce'] = $orderData['timeInForce'];
        } else {
            $orderParams['timeInForce'] = 'GTC'; // Default
        }

        if (isset($orderData['orderLinkId'])) {
            $orderParams['orderLinkId'] = $orderData['orderLinkId'];
        }

        return $this->sendRequest('POST', '/v5/order/create', $orderParams);
    }

    /**
     * Get spot account balance with detailed breakdown by currency
     */
    public function getSpotAccountBalance(): array
    {
        return $this->sendRequest('GET', '/v5/account/wallet-balance', ['accountType' => 'SPOT']);
    }

    /**
     * Get spot trading symbols information
     */
    public function getSpotInstrumentsInfo(): array
    {
        $params = ['category' => 'spot'];
        return $this->sendRequest('GET', '/v5/market/instruments-info', $params);
    }

    /**
     * Get spot ticker information
     */
    public function getSpotTickerInfo(string $symbol): array
    {
        return $this->sendRequest('GET', '/v5/market/tickers', ['category' => 'spot', 'symbol' => $symbol]);
    }

    public function getSpotOrderHistory(string $symbol = null, int $limit = 50): array
    {
        $params = [
            'category' => 'spot',
            'limit' => $limit,
        ];
        
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        
        return $this->sendRequest('GET', '/v5/order/history', $params);
    }

    public function cancelSpotOrderWithSymbol(string $orderId, string $symbol): array
    {
        return $this->sendRequest('POST', '/v5/order/cancel', [
            'category' => 'spot',
            'orderId' => $orderId,
            'symbol' => $symbol
        ]);
    }

    // Interface implementation methods
    public function getAccountBalance(): array
    {
        return $this->getWalletBalance();
    }

    public function getOrder(string $orderId): array
    {
        return $this->getHistoryOrder($orderId);
    }

    public function getSpotOrder(string $orderId): array
    {
        return $this->sendRequest('GET', '/v5/order/history', [
            'category' => 'spot',
            'orderId' => $orderId
        ]);
    }

    public function cancelOrder(string $orderId): array
    {
        // Note: Bybit requires symbol for cancellation, this is a compatibility method
        throw new \Exception('Use cancelOrder with symbol parameter for Bybit');
    }

    public function cancelSpotOrder(string $orderId): array
    {
        // Note: Bybit requires symbol for cancellation, this is a compatibility method
        throw new \Exception('Use cancelSpotOrder with symbol parameter for Bybit');
    }

    public function getOpenOrders(string $symbol = null): array
    {
        $params = ['category' => 'linear'];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        return $this->sendRequest('GET', '/v5/order/realtime', $params);
    }

    public function getOpenSpotOrders(string $symbol = null): array
    {
        $params = ['category' => 'spot'];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        return $this->sendRequest('GET', '/v5/order/realtime', $params);
    }

    public function getOrderHistory(string $symbol = null, int $limit = 50): array
    {
        $params = [
            'category' => 'linear',
            'limit' => $limit,
        ];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        return $this->sendRequest('GET', '/v5/order/history', $params);
    }

    public function getPositions(string $symbol = null): array
    {
        $params = ['category' => 'linear'];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        return $this->sendRequest('GET', '/v5/position/list', $params);
    }

    public function closePosition(string $symbol, string $side, float $qty): array
    {
        return $this->createOrder([
            'category' => 'linear',
            'symbol' => $symbol,
            'side' => $side === 'Buy' ? 'Sell' : 'Buy', // Opposite side to close
            'orderType' => 'Market',
            'qty' => (string)$qty,
            'reduceOnly' => true,
        ]);
    }

    public function setStopLoss(string $symbol, float $stopLoss, string $side): array
    {
        return $this->setTradingStop([
            'category' => 'linear',
            'symbol' => $symbol,
            'stopLoss' => (string)$stopLoss,
            'positionIdx' => 0, // One-way mode
        ]);
    }

    public function getExchangeName(): string
    {
        return 'bybit';
    }

    public function testConnection(): bool
    {
        try {
            $this->sendRequest('GET', '/v5/market/time');
            return true;
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
}
