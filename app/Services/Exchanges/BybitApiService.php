<?php

namespace App\Services\Exchanges;

use GuzzleHttp\Client;
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
        if (!$this->apiSecret) {
            throw new \Exception('API secret is not set');
        }
        return hash_hmac('sha256', $payload, $this->apiSecret);
    }

    private function sendRequestWithoutCredentials(string $method, string $endpoint, array $params = [])
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $response = Http::withHeaders($headers)->timeout(10)->connectTimeout(5)->{$method}("{$this->baseUrl}{$endpoint}", $params);

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

    public function getInstrumentsInfo(string $symbol = null): array
    {
        $params = ['category' => 'linear'];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
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

    public function switchPositionMode(bool $hedgeMode): array
    {
        $mode = $hedgeMode ? 3 : 0;
        return $this->sendRequest('POST', '/v5/position/switch-mode', [
            'category' => 'linear',
            'mode' => $mode,
            'coin' => 'USDT'
        ]);
    }

    public function getPositionIdx(array $position): int
    {
        // First try to get positionIdx directly from the position data
        if (isset($position['positionIdx']) && is_numeric($position['positionIdx'])) {
            return (int)$position['positionIdx'];
        }

        // Fallback: Try to determine from side in hedge mode
        if (isset($position['side'])) {
            $side = strtolower($position['side']);
            // In hedge mode:
            // positionIdx = 1 for Buy side (long positions)
            // positionIdx = 2 for Sell side (short positions)
            if ($side === 'buy') {
                return 1;
            } elseif ($side === 'sell') {
                return 2;
            }
        }

        // Default to one-way mode
        // positionIdx = 0 for one-way mode (both buy and sell in same position)
        return 0;
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
        return $this->sendRequestWithoutCredentials('GET', '/v5/market/tickers', ['category' => 'linear', 'symbol' => $symbol]);
    }
    public function getKlines(string $symbol , string $interval , $limit): array
    {
        return $this->sendRequestWithoutCredentials('GET', '/v5/market/kline', ['category' => 'linear' , 'interval' => $interval, 'symbol' => $symbol , 'limit' => $limit]);
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
     * Note: Bybit uses UNIFIED account type for both spot and derivatives trading
     */
    public function getSpotAccountBalance(): array
    {
        return $this->sendRequest('GET', '/v5/account/wallet-balance', ['accountType' => 'UNIFIED']);
    }

    /**
     * Get spot trading symbols information
     */
    public function getSpotInstrumentsInfo(string $symbol = null): array
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

    /**
     * Get conditional orders (including stop loss orders) for a symbol
     * These are orders with triggerPrice set (stop loss, take profit, conditional orders)
     */
    public function getConditionalOrders(string $symbol): array
    {
        $params = [
            'category' => 'linear',
            'symbol' => $symbol,
            'orderFilter' => 'StopOrder', // Filter for conditional orders only
        ];
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
            'tpslMode' => 'Full', // Required parameter for Bybit API v5
        ]);
    }

    /**
     * Enhanced method to set stop loss with additional parameters
     * Useful for preserving existing take profit and other settings
     */
    public function setStopLossAdvanced(array $params): array
    {
        // Validate required parameters
        if (!isset($params['symbol']) || empty($params['symbol'])) {
            throw new \Exception('Symbol is required for setStopLossAdvanced');
        }

        if (!isset($params['positionIdx']) && !array_key_exists('positionIdx', $params)) {
            throw new \Exception('positionIdx is required for Bybit API v5 set-trading-stop endpoint');
        }

        // Ensure required parameters are set with proper defaults
        $params['category'] = $params['category'] ?? 'linear';
        $params['tpslMode'] = $params['tpslMode'] ?? 'Full';
        $params['positionIdx'] = isset($params['positionIdx']) ? (int)$params['positionIdx'] : 0;

        // Validate tpslMode
        if (!in_array($params['tpslMode'], ['Full', 'Partial'])) {
            throw new \Exception('tpslMode must be either "Full" or "Partial"');
        }

        // For Partial mode, validate required parameters
        if ($params['tpslMode'] === 'Partial') {
            if (isset($params['stopLoss']) && $params['stopLoss'] !== '0' && !isset($params['slSize'])) {
                throw new \Exception('slSize is required when setting stopLoss in Partial mode');
            }
            if (isset($params['takeProfit']) && $params['takeProfit'] !== '0' && !isset($params['tpSize'])) {
                throw new \Exception('tpSize is required when setting takeProfit in Partial mode');
            }
            // In Partial mode, if both TP and SL are set, their sizes must be equal
            if (isset($params['tpSize']) && isset($params['slSize']) && $params['tpSize'] !== $params['slSize']) {
                throw new \Exception('tpSize and slSize must be equal in Partial mode when both are specified');
            }
        }

        // Log the parameters for debugging
        \Log::debug('BybitApiService setStopLossAdvanced called', [
            'symbol' => $params['symbol'],
            'tpslMode' => $params['tpslMode'],
            'positionIdx' => $params['positionIdx'],
            'stopLoss' => $params['stopLoss'] ?? 'not_set',
            'takeProfit' => $params['takeProfit'] ?? 'not_set',
            'all_params' => $params
        ]);

        try {
            return $this->setTradingStop($params);
        } catch (\Exception $e) {
            // Enhanced error logging
            \Log::error('BybitApiService setStopLossAdvanced failed', [
                'symbol' => $params['symbol'],
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            // Re-throw with more context
            throw new \Exception(
                "Failed to set stop loss for {$params['symbol']}: " . $e->getMessage()
            );
        }
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

    /**
     * Check if the API key has spot trading permissions
     * Note: Bybit uses UNIFIED accounts which provide access to both spot and futures
     */
    public function checkSpotAccess(): array
    {
        try {
            // For Bybit, test UNIFIED wallet access (which includes spot trading)
            $balance = $this->getWalletBalance('UNIFIED');

            // Test spot instruments access
            $instruments = $this->getSpotInstrumentsInfo();

            return [
                'success' => true,
                'message' => 'Spot trading access confirmed via UNIFIED account',
                'details' => [
                    'wallet_access' => true,
                    'instruments_access' => true,
                    'account_type' => 'UNIFIED',
                    'permissions' => ['spot_read', 'spot_trade', 'unified_account']
                ]
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Special handling for Bybit error 10001 with "accountType only support UNIFIED"
            // This actually indicates the user HAS UNIFIED account access
            if (str_contains($errorMessage, '10001') && str_contains($errorMessage, 'accountType only support UNIFIED')) {
                try {
                    // If we get this error, it means user has UNIFIED account
                    // Let's test UNIFIED account directly
                    $unifiedBalance = $this->getWalletBalance('UNIFIED');
                    return [
                        'success' => true,
                        'message' => 'Spot trading access confirmed via UNIFIED account (detected from API response)',
                        'details' => [
                            'wallet_access' => true,
                            'instruments_access' => true,
                            'account_type' => 'UNIFIED',
                            'permissions' => ['spot_read', 'spot_trade', 'unified_account'],
                            'detection_method' => 'error_analysis'
                        ]
                    ];
                } catch (\Exception $unifiedError) {
                    // If UNIFIED also fails, then it's a real permission issue
                    return [
                        'success' => false,
                        'message' => 'No access to UNIFIED account: ' . $unifiedError->getMessage(),
                        'details' => [
                            'error_type' => 'permission_denied',
                            'raw_error' => $unifiedError->getMessage(),
                            'permissions' => []
                        ]
                    ];
                }
            }

            // Parse other specific Bybit error codes
            $isPermissionError = str_contains($errorMessage, '10001') || // Invalid API key
                               str_contains($errorMessage, '10003') || // Missing required parameter
                               str_contains($errorMessage, '10004') || // Invalid signature
                               str_contains($errorMessage, '10005') || // Permission denied
                               str_contains($errorMessage, '10006'); // Too many requests

            $isIPBlocked = str_contains($errorMessage, '10015') || // IP not in whitelist
                          str_contains($errorMessage, '403');

            return [
                'success' => false,
                'message' => $isIPBlocked
                    ? 'IP address is not whitelisted for this API key'
                    : ($isPermissionError
                        ? 'API key does not have trading permissions'
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
     * Note: Bybit uses UNIFIED accounts which provide access to both spot and futures
     */
    public function checkFuturesAccess(): array
    {
        try {
            // Test futures wallet access via UNIFIED account
            $balance = $this->getWalletBalance('UNIFIED');

            // Test futures instruments access
            $instruments = $this->getInstrumentsInfo();

            // Test position list access (futures specific)
            $positions = $this->getPositions();

            return [
                'success' => true,
                'message' => 'Futures trading access confirmed via UNIFIED account',
                'details' => [
                    'wallet_access' => true,
                    'instruments_access' => true,
                    'positions_access' => true,
                    'account_type' => 'UNIFIED',
                    'permissions' => ['contract_read', 'contract_trade', 'unified_account']
                ]
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            // Parse specific Bybit error codes
            $isPermissionError = str_contains($errorMessage, '10001') || // Invalid API key
                               str_contains($errorMessage, '10003') || // Missing required parameter
                               str_contains($errorMessage, '10004') || // Invalid signature
                               str_contains($errorMessage, '10005') || // Permission denied
                               str_contains($errorMessage, '10006'); // Too many requests

            $isIPBlocked = str_contains($errorMessage, '10015') || // IP not in whitelist
                          str_contains($errorMessage, '403');

            return [
                'success' => false,
                'message' => $isIPBlocked
                    ? 'IP address is not whitelisted for this API key'
                    : ($isPermissionError
                        ? 'API key does not have futures trading permissions'
                        : 'Futures access validation failed: ' . $errorMessage),
                'details' => [
                    'error_type' => $isIPBlocked ? 'ip_blocked' : 'permission_denied',
                    'raw_error' => $errorMessage,
                    'permissions' => []
                ]
            ];
        }
    }

    /**
     * Check if the current IP address is allowed by the API key
     */
    public function checkIPAccess(): array
    {
        try {
            // Test basic API connectivity
            $serverTime = $this->sendRequest('GET', '/v5/market/time');

            // Test an authenticated endpoint to verify IP whitelist
            $balance = $this->getWalletBalance('UNIFIED');

            return [
                'success' => true,
                'message' => 'IP address is whitelisted and has API access',
                'details' => [
                    'ip_whitelisted' => true,
                    'server_time' => $serverTime['timeSecond'] ?? null,
                    'authenticated_access' => true
                ]
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            $isIPBlocked = str_contains($errorMessage, '10015') || // IP not in whitelist
                          str_contains($errorMessage, '403') ||
                          str_contains($errorMessage, 'Forbidden');

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

        $overallSuccess = $spotCheck['success'] || $futuresCheck['success'];

        return [
            'spot' => $spotCheck,
            'futures' => $futuresCheck,
            'ip' => $ipCheck,
            'overall' => $overallSuccess && $ipCheck['success']
        ];
    }
}
