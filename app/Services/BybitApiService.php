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
            ? Http::withHeaders($headers)->get("{$this->baseUrl}{$endpoint}", $params)
            : Http::withHeaders($headers)->post("{$this->baseUrl}{$endpoint}", $params);

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

    public function getClosedPnl(string $symbol, int $limit = 1)
    {
        return $this->sendRequest('GET', '/v5/position/closed-pnl', [
            'category' => 'linear',
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
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
}
