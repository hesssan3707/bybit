<?php

namespace App\Services\Exchanges;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceApiService implements ExchangeApiServiceInterface
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $recvWindow = 5000;

    public function __construct()
    {
        // Testnet URL: https://testnet.binancefuture.com
        $isTestnet = env('BINANCE_TESTNET', false);
        $this->baseUrl = $isTestnet ? 'https://testnet.binancefuture.com' : 'https://fapi.binance.com';
    }

    public function setCredentials(string $apiKey, string $apiSecret): void
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    private function generateSignature(string $query): string
    {
        if (!$this->apiSecret) {
            throw new \Exception('API secret is not set');
        }
        return hash_hmac('sha256', $query, $this->apiSecret);
    }

    private function sendRequest(string $method, string $endpoint, array $params = [], $isPost = false)
    {
        if (!$this->apiKey || !$this->apiSecret) {
            throw new \Exception('API credentials not set. Please ensure the exchange is properly configured.');
        }

        $timestamp = intval(microtime(true) * 1000);
        $params['timestamp'] = $timestamp;
        $params['recvWindow'] = $this->recvWindow;

        $queryString = http_build_query($params, '', '&');
        $signature = $this->generateSignature($queryString);
        $params['signature'] = $signature;

        $headers = [
            'X-MBX-APIKEY' => $this->apiKey,
        ];

        $client = Http::withHeaders($headers)->timeout(10)->connectTimeout(5);

        if ($isPost) {
            $response = $client->post("{$this->baseUrl}{$endpoint}", $params);
        } else if ($method === 'delete') {
            $response = $client->delete("{$this->baseUrl}{$endpoint}", $params);
        }
        else {
            $response = $client->get("{$this->baseUrl}{$endpoint}", $params);
        }

        $responseData = $response->json();

        if ($response->failed() || (isset($responseData['code']) && $responseData['code'] != 200)) {
            $errorCode = $responseData['code'] ?? 'N/A';
            $errorMsg = $responseData['msg'] ?? 'Unknown error';
            $requestBody = json_encode($params);
            $fullResponse = $response->body();
            throw new \Exception(
                "Binance API Error on {$endpoint}. Code: {$errorCode}, Msg: {$errorMsg}, Request: {$requestBody}, Full Response: {$fullResponse}"
            );
        }

        return $responseData;
    }

    public function getInstrumentsInfo(string $symbol = null): array
    {
        $info = $this->sendRequest('get', '/fapi/v1/exchangeInfo');
        $symbols = collect($info['symbols']);

        if ($symbol) {
            $symbols = $symbols->where('symbol', $symbol);
        }

        return ['list' => $symbols->values()->all()];
    }

    public function createOrder(array $orderData): array
    {
        return $this->sendRequest('post', '/fapi/v1/order', $orderData, true);
    }

    public function getOpenOrdersBySymbol(string $symbol): array
    {
        return $this->sendRequest('get', '/fapi/v1/openOrders', ['symbol' => $symbol]);
    }

    public function getHistoryOrder(string $orderId): array
    {
        throw new \Exception('Binance requires symbol to get order history. Use getOrder with symbol.');
    }

    public function cancelOrderWithSymbol(string $orderId, string $symbol): array
    {
        return $this->sendRequest('delete', '/fapi/v1/order', ['symbol' => $symbol, 'orderId' => $orderId]);
    }

    public function getPositions(string $symbol = null): array
    {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        $positions = $this->sendRequest('get', '/fapi/v2/positionRisk', $params);
        return ['list' => $positions];
    }

    public function setTradingStop(array $params): array
    {
        // Binance uses the 'STOP_MARKET' or 'TAKE_PROFIT_MARKET' order type to set SL/TP
        // This requires creating a new order.
        $orderParams = [
            'symbol' => $params['symbol'],
            'side' => $params['side'] === 'Buy' ? 'SELL' : 'BUY', // Opposite side to close
            'positionSide' => $params['positionSide'],
            'type' => 'STOP_MARKET',
            'stopPrice' => (string)$params['stopLoss'],
            'closePosition' => 'true', // This indicates it's a SL/TP order
        ];
        return $this->createOrder($orderParams);
    }

    public function switchPositionMode(bool $hedgeMode): array
    {
        return $this->sendRequest('post', '/fapi/v1/positionSide/dual', [
            'dualSidePosition' => $hedgeMode ? 'true' : 'false'
        ], true);
    }

    public function getPositionIdx(array $position): int
    {
        if (isset($position['positionSide'])) {
            $side = strtoupper($position['positionSide']);
            if ($side === 'LONG') {
                return 1;
            } elseif ($side === 'SHORT') {
                return 2;
            }
        }
        return 0; // One-way mode
    }

    public function getClosedPnl(string $symbol, int $limit = 50, ?int $startTime = null): array
    {
        $params = [
            'symbol' => $symbol,
            'limit' => $limit,
        ];

        if ($startTime) {
            $params['startTime'] = $startTime;
        }

        return $this->sendRequest('get', '/fapi/v1/userTrades', $params);
    }

    public function getWalletBalance(string $accountType = 'FUTURES', ?string $coin = null): array
    {
        // Binance futures balance is fetched from /fapi/v2/balance
        $balanceInfo = $this->sendRequest('get', '/fapi/v2/balance');
        $filteredBalance = [];
        if($coin) {
            foreach($balanceInfo as $balance) {
                if($balance['asset'] === $coin) {
                    $filteredBalance[] = $balance;
                }
            }
            return ['list' => $filteredBalance];
        }
        return ['list' => $balanceInfo];
    }

    public function getTickerInfo(string $symbol): array
    {
        $ticker = $this->sendRequest('get', '/fapi/v1/ticker/24hr', ['symbol' => $symbol]);
        // Binance returns a single object for a single symbol, not a list
        return ['list' => [$ticker]];
    }

    // Interface implementation methods that need to be adapted or implemented
    public function getAccountBalance(): array
    {
        return $this->getWalletBalance();
    }

    public function getOrder(string $orderId): array
    {
        throw new \Exception('Use getOrder with symbol parameter for Binance');
    }

    public function cancelOrder(string $orderId): array
    {
        throw new \Exception('Use cancelOrder with symbol parameter for Binance');
    }

    public function getOpenOrders(string $symbol = null): array
    {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        $orders = $this->sendRequest('get', '/fapi/v1/openOrders', $params);
        return ['list' => $orders];
    }

    public function getConditionalOrders(string $symbol): array
    {
        // Binance uses STOP_MARKET/TAKE_PROFIT_MARKET which are fetched as open orders
        return $this->getOpenOrders($symbol);
    }

    public function getOrderHistory(string $symbol = null, int $limit = 50): array
    {
        $params = ['limit' => $limit];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        $orders = $this->sendRequest('get', '/fapi/v1/allOrders', $params);
        return ['list' => $orders];
    }

    public function closePosition(string $symbol, string $side, float $qty): array
    {
        return $this->createOrder([
            'symbol' => $symbol,
            'side' => $side === 'Buy' ? 'Sell' : 'Buy', // Opposite side to close
            'type' => 'MARKET',
            'quantity' => (string)$qty,
            'reduceOnly' => 'true',
        ]);
    }

    public function setStopLoss(string $symbol, float $stopLoss, string $side): array
    {
        return $this->setTradingStop([
            'symbol' => $symbol,
            'stopLoss' => (string)$stopLoss,
            'side' => $side,
            'positionSide' => $side === 'Buy' ? 'LONG' : 'SHORT',
        ]);
    }

    public function setStopLossAdvanced(array $params): array
    {
        return $this->setTradingStop($params);
    }

    public function getExchangeName(): string
    {
        return 'binance';
    }

    public function testConnection(): bool
    {
        try {
            $this->sendRequest('get', '/fapi/v1/ping');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRateLimits(): array
    {
        // These are example values, refer to Binance documentation for actual limits
        return [
            'requests_per_minute' => 2400,
            'orders_per_minute' => 1200,
            'orders_per_10_seconds' => 300,
        ];
    }

    public function checkFuturesAccess(): array
    {
        try {
            $this->getWalletBalance();
            return ['success' => true, 'message' => 'Futures access confirmed.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Futures access validation failed: ' . $e->getMessage()];
        }
    }

    // Spot methods - should not be used for futures, but need to exist for the interface
    public function createSpotOrder(array $orderData): array
    {
        throw new \Exception('This service is for futures trading, not spot.');
    }

    public function getSpotAccountBalance(): array
    {
        throw new \Exception('This service is for futures trading, not spot.');
    }

    public function getSpotInstrumentsInfo(string $symbol = null): array
    {
        throw new \Exception('This service is for futures trading, not spot.');
    }

    public function getSpotTickerInfo(string $symbol): array
    {
        throw new \Exception('This service is for futures trading, not spot.');
    }

    public function getSpotOrderHistory(string $symbol = null, int $limit = 50): array
    {
        throw new \Exception('This service is for futures trading, not spot.');
    }

    public function cancelSpotOrderWithSymbol(string $orderId, string $symbol): array
    {
        throw new \Exception('This service is for futures trading, not spot.');
    }

    public function getSpotOrder(string $orderId): array
    {
        throw new \Exception('This service is for futures trading, not spot.');
    }

    public function cancelSpotOrder(string $orderId): array
    {
        throw new \Exception('This service is for futures trading, not spot.');
    }

    public function getOpenSpotOrders(string $symbol = null): array
    {
        throw new \Exception('This service is for futures trading, not spot.');
    }

    public function checkSpotAccess(): array
    {
        return ['success' => false, 'message' => 'This is a futures-only service.'];
    }

    public function checkIPAccess(): array
    {
        try {
            $this->testConnection();
            return ['success' => true, 'message' => 'IP access confirmed.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'IP access validation failed: ' . $e->getMessage()];
        }
    }

    public function validateAPIAccess(): array
    {
        $futuresCheck = $this->checkFuturesAccess();
        $ipCheck = $this->checkIPAccess();

        return [
            'spot' => ['success' => false, 'message' => 'Not applicable.'],
            'futures' => $futuresCheck,
            'ip' => $ipCheck,
            'overall' => $futuresCheck['success'] && $ipCheck['success']
        ];
    }
}
