<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BybitApiService
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $recvWindow = 5000;

    public function __construct()
    {
        $this->apiKey = env('BYBIT_API_KEY');
        $this->apiSecret = env('BYBIT_API_SECRET');
        $isTestnet = env('BYBIT_TESTNET', false);
        $this->baseUrl = $isTestnet ? 'https://api-testnet.bybit.com' : 'https://api.bybit.com';
    }

    private function generateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->apiSecret);
    }

    private function sendRequest(string $method, string $endpoint, array $params = [])
    {
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

    public function getInstrumentsInfo(string $symbol)
    {
        return $this->sendRequest('GET', '/v5/market/instruments-info', ['category' => 'linear', 'symbol' => $symbol]);
    }

    public function createOrder(array $params)
    {
        return $this->sendRequest('POST', '/v5/order/create', $params);
    }

    public function getOpenOrders(string $symbol)
    {
        return $this->sendRequest('GET', '/v5/order/realtime', ['category' => 'linear', 'symbol' => $symbol]);
    }

    public function getHistoryOrder(string $orderId)
    {
        return $this->sendRequest('GET', '/v5/order/history', ['category' => 'linear', 'orderId' => $orderId]);
    }

    public function cancelOrder(string $orderId, string $symbol)
    {
        return $this->sendRequest('POST', '/v5/order/cancel', ['category' => 'linear', 'orderId' => $orderId, 'symbol' => $symbol]);
    }

    public function getPositionInfo(string $symbol)
    {
        return $this->sendRequest('GET', '/v5/position/list', ['category' => 'linear', 'symbol' => $symbol]);
    }

    public function setTradingStop(array $params)
    {
        return $this->sendRequest('POST', '/v5/position/set-trading-stop', $params);
    }

    public function getClosedPnl(string $symbol, int $limit = 50, ?int $startTime = null)
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

    public function getWalletBalance(string $accountType = 'UNIFIED', ?string $coin = null)
    {
        $params = ['accountType' => $accountType];
        if ($coin) {
            $params['coin'] = $coin;
        }
        return $this->sendRequest('GET', '/v5/account/wallet-balance', $params);
    }

    public function getTickerInfo(string $symbol)
    {
        return $this->sendRequest('GET', '/v5/market/tickers', ['category' => 'linear', 'symbol' => $symbol]);
    }

    /**
     * Create a spot trading order
     * 
     * @param array $params - Order parameters including:
     *   - side: 'Buy' or 'Sell'
     *   - symbol: Trading pair (e.g., 'BTCUSDT')
     *   - orderType: 'Market' or 'Limit'
     *   - qty: Order quantity
     *   - price: Order price (required for Limit orders)
     */
    public function createSpotOrder(array $params)
    {
        $orderParams = [
            'category' => 'spot',
            'symbol' => $params['symbol'],
            'side' => $params['side'],
            'orderType' => $params['orderType'],
            'qty' => (string)$params['qty'],
        ];

        // Add price for limit orders
        if ($params['orderType'] === 'Limit' && isset($params['price'])) {
            $orderParams['price'] = (string)$params['price'];
        }

        // Add optional parameters
        if (isset($params['timeInForce'])) {
            $orderParams['timeInForce'] = $params['timeInForce'];
        } else {
            $orderParams['timeInForce'] = 'GTC'; // Default
        }

        if (isset($params['orderLinkId'])) {
            $orderParams['orderLinkId'] = $params['orderLinkId'];
        }

        return $this->sendRequest('POST', '/v5/order/create', $orderParams);
    }

    /**
     * Get spot account balance with detailed breakdown by currency
     */
    public function getSpotAccountBalance()
    {
        return $this->sendRequest('GET', '/v5/account/wallet-balance', ['accountType' => 'SPOT']);
    }

    /**
     * Get spot trading symbols information
     */
    public function getSpotInstrumentsInfo(?string $symbol = null)
    {
        $params = ['category' => 'spot'];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        return $this->sendRequest('GET', '/v5/market/instruments-info', $params);
    }

    /**
     * Get spot ticker information
     */
    public function getSpotTickerInfo(string $symbol)
    {
        return $this->sendRequest('GET', '/v5/market/tickers', ['category' => 'spot', 'symbol' => $symbol]);
    }

    /**
     * Get spot order history
     */
    public function getSpotOrderHistory(?string $symbol = null, int $limit = 50)
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

    /**
     * Cancel spot order
     */
    public function cancelSpotOrder(string $orderId, string $symbol)
    {
        return $this->sendRequest('POST', '/v5/order/cancel', [
            'category' => 'spot',
            'orderId' => $orderId,
            'symbol' => $symbol
        ]);
    }
}
