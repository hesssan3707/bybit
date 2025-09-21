<?php

namespace App\Services\Exchanges;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceSpotApiService implements ExchangeApiServiceInterface
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $recvWindow = 5000;

    public function __construct(?bool $isDemo = null)
    {
        // Use parameter if provided, otherwise default to false (real account)
        $isDemo = $isDemo ?? false;
        
        // Spot API URLs - different from futures
        $this->baseUrl = $isDemo ? 'https://testnet.binance.vision' : 'https://api.binance.com';
    }

    public function setCredentials(string $apiKey, string $apiSecret): void
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    private function generateSignature(string $query): string
    {
        if (!$this->apiSecret) {
            throw new \Exception('کلید API تنظیم نشده است');
        }
        return hash_hmac('sha256', $query, $this->apiSecret);
    }

    private function sendRequestWithoutCredentials(string $method, string $endpoint, array $params = [])
    {
        $headers = [];
        $client = Http::withHeaders($headers)->timeout(10)->connectTimeout(5);

        $response = $client->{$method}("{$this->baseUrl}{$endpoint}", $params);

        $responseData = $response->json();

        if ($response->failed() || (isset($responseData['code']) && $responseData['code'] != 0)) {
            $errorCode = $responseData['code'] ?? 'N/A';
            $errorMsg = $responseData['msg'] ?? 'خطای ناشناخته';
            
            throw new \Exception("خطای API بایننس: کد {$errorCode}, پیام: {$errorMsg}");
        }

        return $responseData;
    }

    private function sendRequest(string $method, string $endpoint, array $params = [], $isPost = false)
    {
        if (!$this->apiKey || !$this->apiSecret) {
            throw new \Exception('اطلاعات API تنظیم نشده است. لطفاً تنظیمات صرافی را بررسی کنید.');
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
        } else {
            $response = $client->get("{$this->baseUrl}{$endpoint}", $params);
        }

        $responseData = $response->json();

        if ($response->failed() || (isset($responseData['code']) && $responseData['code'] != 0)) {
            $errorCode = $responseData['code'] ?? 'N/A';
            $errorMsg = $responseData['msg'] ?? 'خطای ناشناخته';
            
            throw new \Exception("خطای API بایننس: کد {$errorCode}, پیام: {$errorMsg}");
        }

        return $responseData;
    }

    // Spot Trading Methods Implementation

    public function getSpotInstrumentsInfo(string $symbol = null): array
    {
        $info = $this->sendRequestWithoutCredentials('get', '/api/v3/exchangeInfo');
        $symbols = collect($info['symbols']);

        // Filter only TRADING status symbols
        $symbols = $symbols->where('status', 'TRADING');

        if ($symbol) {
            $symbols = $symbols->where('symbol', $symbol);
        }

        return ['list' => $symbols->values()->all()];
    }

    public function createSpotOrder(array $orderData): array
    {
        return $this->sendRequest('post', '/api/v3/order', $orderData, true);
    }

    public function getSpotOrder(string $orderId): array
    {
        // Binance requires symbol for spot order lookup
        throw new \Exception('بایننس برای دریافت اطلاعات سفارش نقدی نیاز به نماد دارد. از getSpotOrderWithSymbol استفاده کنید.');
    }

    public function getSpotOrderWithSymbol(string $orderId, string $symbol): array
    {
        return $this->sendRequest('get', '/api/v3/order', ['symbol' => $symbol, 'orderId' => $orderId]);
    }

    public function cancelSpotOrder(string $orderId): array
    {
        // Binance requires symbol for spot order cancellation
        throw new \Exception('بایننس برای لغو سفارش نقدی نیاز به نماد دارد. از cancelSpotOrderWithSymbol استفاده کنید.');
    }

    public function cancelSpotOrderWithSymbol(string $orderId, string $symbol): array
    {
        return $this->sendRequest('delete', '/api/v3/order', ['symbol' => $symbol, 'orderId' => $orderId]);
    }

    public function getOpenSpotOrders(string $symbol = null): array
    {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        return $this->sendRequest('get', '/api/v3/openOrders', $params);
    }

    public function getSpotOrderHistory(string $symbol = null, int $limit = 50): array
    {
        if (!$symbol) {
            throw new \Exception('بایننس برای دریافت تاریخچه سفارشات نقدی نیاز به نماد دارد.');
        }
        
        $params = ['symbol' => $symbol, 'limit' => $limit];
        return $this->sendRequest('get', '/api/v3/allOrders', $params);
    }

    public function getSpotAccountBalance(): array
    {
        $accountInfo = $this->sendRequest('get', '/api/v3/account');
        return $accountInfo['balances'] ?? [];
    }

    public function getSpotTickerInfo(string $symbol): array
    {
        return $this->sendRequestWithoutCredentials('get', '/api/v3/ticker/24hr', ['symbol' => $symbol]);
    }

    public function checkSpotAccess(): array
    {
        try {
            $this->sendRequest('get', '/api/v3/account');
            return [
                'success' => true,
                'message' => 'دسترسی به معاملات نقدی تأیید شد',
                'details' => []
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در دسترسی به معاملات نقدی: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    // Futures methods - not supported in spot service
    public function getAccountBalance(): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getWalletBalance(string $accountType = 'UNIFIED', ?string $coin = null): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function createOrder(array $orderData): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getOrder(string $orderId): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function cancelOrder(string $orderId): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function cancelOrderWithSymbol(string $orderId, string $symbol): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getOpenOrders(string $symbol = null): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getConditionalOrders(string $symbol): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getOrderHistory(string $symbol = null, int $limit = 50): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getPositions(string $symbol = null): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function closePosition(string $symbol, string $side, float $qty): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function setStopLoss(string $symbol, float $stopLoss, string $side): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function setStopLossAdvanced(array $params): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getInstrumentsInfo(string $symbol = null): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getTickerInfo(string $symbol): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getClosedPnl(string $symbol, int $limit = 50, ?int $startTime = null): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getHistoryOrder(string $orderId): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function setTradingStop(array $params): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function switchPositionMode(bool $hedgeMode): array
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getPositionIdx(array $position): int
    {
        throw new \Exception('این سرویس فقط برای معاملات نقدی است. برای معاملات آتی از BinanceApiService استفاده کنید.');
    }

    public function getExchangeName(): string
    {
        return 'binance_spot';
    }

    public function testConnection(): bool
    {
        try {
            $this->sendRequestWithoutCredentials('get', '/api/v3/ping');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRateLimits(): array
    {
        $info = $this->sendRequestWithoutCredentials('get', '/api/v3/exchangeInfo');
        return $info['rateLimits'] ?? [];
    }

    public function checkFuturesAccess(): array
    {
        return [
            'success' => false,
            'message' => 'این سرویس فقط برای معاملات نقدی است',
            'details' => []
        ];
    }

    public function checkIPAccess(): array
    {
        try {
            $this->testConnection();
            return [
                'success' => true,
                'message' => 'دسترسی IP تأیید شد',
                'details' => []
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در دسترسی IP: ' . $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ];
        }
    }

    public function validateAPIAccess(): array
    {
        $spotAccess = $this->checkSpotAccess();
        $futuresAccess = $this->checkFuturesAccess();
        $ipAccess = $this->checkIPAccess();

        return [
            'spot' => $spotAccess,
            'futures' => $futuresAccess,
            'ip' => $ipAccess,
            'overall' => $spotAccess['success'] && $ipAccess['success']
        ];
    }

    public function getAccountInfo(): array
    {
        $accountInfo = $this->sendRequest('get', '/api/v3/account');
        
        return [
            'positionMode' => 'spot', // Spot trading doesn't have position modes
            'hedgeMode' => false,
            'details' => $accountInfo
        ];
    }
}