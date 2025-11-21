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

    public function __construct(?bool $isDemo = null)
    {
        // Don't initialize API credentials from .env - they will be set via setCredentials()
        $this->apiKey = null;
        $this->apiSecret = null;
        
        // Use parameter if provided, otherwise default to false (real account)
        $isDemo = $isDemo ?? false;
        
        $this->baseUrl = $isDemo ? 'https://api-demo.bybit.com' : 'https://api.bybit.com';
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
            $fullResponse = $response->body();
            
            // Provide better error messages for demo account issues
            $isDemoMode = strpos($this->baseUrl, 'testnet') !== false;
            $userFriendlyMessage = $this->getUserFriendlyErrorMessage($errorCode, $errorMsg, $isDemoMode);
            
            throw new \Exception($userFriendlyMessage);
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
        $result = $this->sendRequest('POST', '/v5/position/switch-mode', [
            'category' => 'linear',
            'mode' => $mode,
            'coin' => 'USDT'
        ]);

        // Duplicate info log removed; rely on higher-level controller logging

        return $result;
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
        // Normalize interval for Bybit v5: minutes numeric ('1','3','5','15','30','60','120','240','360','720'), or 'D','W','M'
        $normalized = $interval;
        $lower = strtolower($interval);
        if (preg_match('/^\d+m$/', $lower)) {
            // convert like '15m' -> '15'
            $normalized = (string)intval($lower);
        } elseif (preg_match('/^\d+h$/', $lower)) {
            // convert like '1h' -> '60'
            $normalized = (string)(intval($lower) * 60);
        } elseif (in_array(strtoupper($interval), ['D','W','M'])) {
            $normalized = strtoupper($interval);
        } elseif ($lower === '1day') {
            $normalized = 'D';
        } elseif ($lower === '60min') {
            $normalized = '60';
        } elseif (preg_match('/^\d+$/', $lower)) {
            // already numeric minutes
            $normalized = $lower;
        }
    
        return $this->sendRequestWithoutCredentials('GET', '/v5/market/kline', ['category' => 'linear', 'interval' => $normalized, 'symbol' => $symbol , 'limit' => $limit]);
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
        } else {
            // Bybit API requires either symbol, baseCoin, or settleCoin for linear category
            // Using USDT as default settleCoin to get all USDT-settled orders
            $params['settleCoin'] = 'USDT';
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

    public function getOrderHistory(string $symbol = null, int $limit = 50, ?int $startTime = null): array
    {
        // Aggregate across 7-day chunks to respect Bybit's order history window
        $nowMs = (int) (microtime(true) * 1000);
        $chunkSizeMs = 7 * 24 * 60 * 60 * 1000; // 7 days in ms
        $fromMs = $startTime ?? ($nowMs - $chunkSizeMs);

        // Ensure fromMs is not in the future
        if ($fromMs > $nowMs) {
            return ['list' => []];
        }

        $ordersById = [];

        for ($chunkStart = $fromMs; $chunkStart < $nowMs; $chunkStart += $chunkSizeMs) {
            $chunkEnd = min($chunkStart + $chunkSizeMs - 1, $nowMs);

            $params = [
                'category' => 'linear',
                'limit' => $limit,
            ];
            if ($symbol) {
                $params['symbol'] = $symbol;
            } else {
                // When no symbol provided, specify settleCoin to fetch USDT-settled orders
                $params['settleCoin'] = 'USDT';
            }
            // Use chunked time window
            $params['startTime'] = $chunkStart;
            $params['endTime'] = $chunkEnd;

            try {
                $resp = $this->sendRequest('GET', '/v5/order/history', $params);
                // Bybit v5 typically returns structure: ['result' => ['list' => [...]]]
                $list = $resp['result']['list'] ?? ($resp['list'] ?? []);

                foreach ($list as $item) {
                    $oid = $item['orderId'] ?? ($item['id'] ?? null);
                    if ($oid === null) {
                        // Fallback key to avoid losing entries without orderId
                        $ordersById[md5(json_encode($item))] = $item;
                    } else {
                        // Deduplicate by orderId
                        $ordersById[$oid] = $item;
                    }
                }
            } catch (\Exception $e) {
                // Best-effort aggregation: skip failed chunk and continue
                continue;
            }
        }

        return ['list' => array_values($ordersById)];
    }

    public function getPositions(string $symbol = null): array
    {
        $params = ['category' => 'linear'];
        if ($symbol) {
            $params['symbol'] = $symbol;
        } else {
            // Bybit API requires either symbol or settleCoin for linear category
            // Using USDT as default settleCoin to get all USDT-settled positions
            $params['settleCoin'] = 'USDT';
        }
        return $this->sendRequest('GET', '/v5/position/list', $params);
    }

    public function closePosition(string $symbol, string $side, float $qty): array
    {
        // Determine correct positionIdx for Bybit to avoid "position idx not match position mode" (10001)
        // Try to find the matching active position for this symbol and side
        $positionsList = [];
        try {
            $positionsResult = $this->getPositions($symbol);
            $positionsList = $positionsResult['list'] ?? ($positionsResult['result']['list'] ?? []);
        } catch (\Exception $e) {
            // If positions lookup fails, proceed with safe fallbacks below
        }

        $positionIdx = 0;
        $matched = null;
        foreach ($positionsList as $pos) {
            if ((($pos['symbol'] ?? '') === $symbol) &&
                (strtolower($pos['side'] ?? '') === strtolower($side)) &&
                ((float)($pos['size'] ?? 0) > 0)) {
                $matched = $pos;
                break;
            }
        }

        if ($matched) {
            // Use exchange-provided positionIdx when available or derive via helper
            $positionIdx = $this->getPositionIdx($matched);
        } else {
            // Fallback:
            // - Hedge mode: Buy -> 1 (long), Sell -> 2 (short)
            // - One-way mode: 0
            $positionIdx = ($side === 'Buy') ? 1 : (($side === 'Sell') ? 2 : 0);
        }

        // Build reduce-only market order payload with explicit positionIdx
        $orderPayload = [
            'category' => 'linear',
            'symbol' => $symbol,
            'side' => $side === 'Buy' ? 'Sell' : 'Buy', // Opposite side to close
            'orderType' => 'Market',
            'qty' => (string)$qty,
            'reduceOnly' => true,
            'positionIdx' => $positionIdx,
        ];

        return $this->createOrder($orderPayload);
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

        // Removed verbose debug log to reduce noise; errors are still logged below

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

    /**
     * Get account information including position mode status
     * For Bybit, we check the position mode by querying a specific symbol position
     * to get the actual position mode setting, regardless of position size
     */
    public function getAccountInfo(): array
    {
        try {
            // Query position for a specific symbol to determine position mode
            // Even if there's no position, Bybit returns the position mode info
            $response = $this->sendRequest('GET', '/v5/position/list', [
                'category' => 'linear',
                'symbol' => 'BTCUSDT' // Use a common symbol to check position mode
            ]);

            $hedgeMode = false;
            $positionMode = 'one-way';

            if (!empty($response['list'])) {
                $position = $response['list'][0];
                // Check positionIdx to determine mode
                // positionIdx > 0 indicates hedge mode
                // positionIdx = 0 indicates one-way mode
                if (isset($position['positionIdx']) && (int)$position['positionIdx'] > 0) {
                    $hedgeMode = true;
                    $positionMode = 'hedge';
                }
            } else {
                // If no position data returned, try to get all positions
                $allPositions = $this->getPositions();
                if (isset($allPositions['list']) && !empty($allPositions['list'])) {
                    foreach ($allPositions['list'] as $position) {
                        if (isset($position['positionIdx']) && (int)$position['positionIdx'] > 0) {
                            $hedgeMode = true;
                            $positionMode = 'hedge';
                            break;
                        }
                    }
                }
                // If still no positions found, don't assume mode - keep database value
                // Only update if we have definitive information
                if (!$hedgeMode && empty($allPositions['list'])) {
                    // Don't update database when we can't determine the mode
                    return [
                        'positionMode' => 'unknown',
                        'hedgeMode' => false,
                        'details' => [
                            'exchange' => 'bybit',
                            'method' => 'no_positions_found',
                            'note' => 'Cannot determine position mode without positions'
                        ]
                    ];
                }
            }

            return [
                'positionMode' => $positionMode,
                'hedgeMode' => $hedgeMode,
                'details' => [
                    'exchange' => 'bybit',
                    'method' => 'symbol_position_query',
                    'symbol_checked' => 'BTCUSDT'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'positionMode' => 'unknown',
                'hedgeMode' => false,
                'details' => [
                    'error' => $e->getMessage(),
                    'exchange' => 'bybit'
                ]
            ];
        }
    }

    /**
     * Get user-friendly error message for demo account issues
     */
    private function getUserFriendlyErrorMessage(string $errorCode, string $errorMsg, bool $isDemoMode): string
    {
        // Common error codes and their meanings
        $errorMappings = [
            '10003' => 'کلید API نامعتبر است',
            '10004' => 'امضای API نامعتبر است',
            '10005' => 'مجوز دسترسی کافی نیست',
            '10006' => 'زمان درخواست منقضی شده است',
            '10007' => 'آدرس IP مجاز نیست',
            '10009' => 'کلید API غیرفعال است',
            '10016' => 'سرویس در دسترس نیست',
            '10018' => 'حساب کاربری محدود شده است',
            // Explicit mapping for positionIdx mismatch
            '10001' => 'شناسه موقعیت با حالت موقعیت مطابقت ندارد. لطفاً positionIdx صحیح را ارسال کنید یا حالت موقعیت (یک‌طرفه/دوطرفه) را بررسی کنید.',
        ];

        // Check for specific demo account issues
        if ($isDemoMode) {
            if (in_array($errorCode, ['10003', '10004', '10005'])) {
                return 'اطلاعات حساب دمو نامعتبر است. لطفاً از کلیدهای API تست‌نت Bybit استفاده کنید که از https://testnet.bybit.com ایجاد شده‌اند.';
            }
            
            if ($errorCode === '10009') {
                return 'کلید API حساب دمو غیرفعال است. لطفاً کلیدهای جدید از پنل تست‌نت Bybit ایجاد کنید.';
            }
            
            // Handle cases where mainnet API keys are used with testnet
            if (in_array($errorCode, ['10001', '10002', '10010'])) {
                return 'کلیدهای API حساب واقعی با تست‌نت سازگار نیستند. لطفاً کلیدهای API مخصوص تست‌نت را از https://testnet.bybit.com ایجاد کنید.';
            }
            
            if ($errorMsg === 'Unknown error' || empty($errorMsg) || $errorCode === 'N/A') {
                return 'خطا در اتصال به حساب دمو. احتمالاً کلیدهای API حساب واقعی با تست‌نت استفاده شده‌اند. لطفاً کلیدهای API مخصوص تست‌نت را از https://testnet.bybit.com ایجاد کنید.';
            }
        }

        // Return mapped error or original message
        if (isset($errorMappings[$errorCode])) {
            return $errorMappings[$errorCode];
        }

        // For unmapped errors, provide context
        if ($isDemoMode) {
            return "خطا در حساب دمو: {$errorMsg} (کد: {$errorCode})";
        } else {
            return "خطا در API Bybit: {$errorMsg} (کد: {$errorCode})";
        }
    }
}
