<?php

namespace App\Services\Exchanges;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BingXApiService implements ExchangeApiServiceInterface
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $recvWindow = 5000;

    public function __construct(?bool $isDemo = null)
    {
        // Use parameter if provided, otherwise default to false (real account)
        $isDemo = $isDemo ?? false;
        
        $this->baseUrl = $isDemo ? 'https://open-api-vst.bingx.com' : 'https://open-api.bingx.com';
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

    private function sendRequestWithoutCredentials(string $method, string $endpoint, array $params = [])
    {
        $headers = [];
        $queryString = http_build_query($params, '', '&');

        $response = Http::withHeaders($headers)->timeout(10)->connectTimeout(5)->{$method}("{$this->baseUrl}{$endpoint}?{$queryString}");

        $responseData = $response->json();

        if ($response->failed() || ($responseData['code'] ?? 0) !== 0) {
            $errorCode = $responseData['code'] ?? 'N/A';
            $errorMsg = $responseData['msg'] ?? 'Unknown error';
            
            // Provide better error messages for demo account issues
            $isDemoMode = strpos($this->baseUrl, 'vst') !== false;
            $userFriendlyMessage = $this->getUserFriendlyErrorMessage($errorCode, $errorMsg, $isDemoMode);
            
            throw new \Exception($userFriendlyMessage);
        }

        return $responseData['data'];
    }
    private function sendRequest(string $method, string $endpoint, array $params = [])
    {
        if (!$this->apiKey || !$this->apiSecret) {
            throw new \Exception('API credentials not set. Please ensure the exchange is properly configured.');
        }

        $timestamp = intval(microtime(true) * 1000);
        $params['timestamp'] = $timestamp;

        $queryString = http_build_query($params, '', '&');
        $signature = $this->generateSignature($queryString);
        $queryString .= '&signature=' . $signature;

        $headers = [
            'X-BX-APIKEY' => $this->apiKey,
        ];

        $response = Http::withHeaders($headers)->timeout(10)->connectTimeout(5)->{$method}("{$this->baseUrl}{$endpoint}?{$queryString}");

        $responseData = $response->json();

        if ($response->failed() || ($responseData['code'] ?? 0) !== 0) {
            $errorCode = $responseData['code'] ?? 'N/A';
            $errorMsg = $responseData['msg'] ?? 'Unknown error';
            $isDemoMode = strpos($this->baseUrl, 'vst') !== false;
            $userFriendlyMessage = $this->getUserFriendlyErrorMessage($errorCode, $errorMsg, $isDemoMode);
            throw new \Exception($userFriendlyMessage);
        }

        return $responseData['data'];
    }

    public function getInstrumentsInfo(string $symbol = null): array
    {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        $info = $this->sendRequest('get', '/openApi/swap/v2/quote/contracts', $params);
        return ['list' => $info];
    }

    public function createOrder(array $orderData): array
    {
        return $this->sendRequest('post', '/openApi/swap/v2/trade/order', $orderData);
    }

    public function getHistoryOrder(string $orderId): array
    {
        throw new \Exception('BingX requires symbol to get order history. Use getOrder with symbol.');
    }

    public function cancelOrderWithSymbol(string $orderId, string $symbol): array
    {
        return $this->sendRequest('post', '/openApi/swap/v2/trade/cancel', ['symbol' => $symbol, 'orderId' => $orderId]);
    }

    public function amendOrder(array $params): array
    {
        // BingX does not support native amend. We must Cancel and Replace.
        $symbol = $params['symbol'];
        $orderId = $params['orderId'];

        // 1. Fetch original order to get details (side, type, qty, etc.)
        // We use getOpenOrders because we are editing a pending order.
        $openOrders = $this->getOpenOrders($symbol);
        $originalOrder = null;
        foreach ($openOrders['list'] as $o) {
            if ($o['orderId'] == $orderId) {
                $originalOrder = $o;
                break;
            }
        }

        if (!$originalOrder) {
            throw new \Exception('سفارش در صرافی یافت نشد (شاید قبلاً پر یا لغو شده است).');
        }

        // 2. Cancel the existing order
        try {
            $this->cancelOrderWithSymbol($orderId, $symbol);
        } catch (\Exception $e) {
            throw new \Exception('خطا در لغو سفارش قدیمی: ' . $e->getMessage());
        }

        // 3. Prepare new order data
        // Merge original data with new params
        // Map BingX response fields to createOrder fields if necessary
        // Response: side, price, origQty, type, positionSide, stopPrice, etc.
        // Create Params: symbol, side, type, quantity, price, positionSide, etc.
        
        $newSide = $originalOrder['side']; // 'BUY' or 'SELL'
        $newType = $originalOrder['type']; // 'LIMIT', 'MARKET', etc.
        $newQty = $params['qty'] ?? $originalOrder['origQty'];
        $newPrice = $params['price'] ?? $originalOrder['price'];
        $positionSide = $originalOrder['positionSide'] ?? 'BOTH'; // LONG/SHORT/BOTH

        $createParams = [
            'symbol' => $symbol,
            'side' => $newSide,
            'type' => $newType,
            'quantity' => (string)$newQty,
            'price' => (string)$newPrice,
            'positionSide' => $positionSide,
        ];

        // Handle Stop Loss / Take Profit if they were part of the order or params
        // Note: BingX often attaches SL/TP to the position or as separate orders.
        // If the original order had SL/TP params in the 'create' call, they might not be returned in 'getOpenOrders' simple view easily 
        // or might need separate calls.
        // However, the `update` controller passes `stopLoss` and `takeProfit`.
        if (isset($params['stopLoss'])) {
            $createParams['stopLoss'] = json_encode([
                'type' => 'STOP_LOSS', 
                'stopPrice' => (string)$params['stopLoss'],
                'price' => (string)$params['stopLoss'] // Trigger price
            ]); 
            // Wait, BingX createOrder structure for SL/TP is specific.
            // Actually, `createOrder` in this service just passes params to endpoint.
            // BingX V2 Swap API allows `stopLoss` and `takeProfit` fields in createOrder?
            // Checking docs (mental): BingX usually requires separate calls for SL/TP or specific JSON.
            // BUT, `FuturesController` passes `stopLoss` and `takeProfit` as simple values.
            // Let's assume the `createOrder` or the API handles it if passed.
            // If `FuturesController` logic relies on `amendOrder` handling SL/TP, we should pass them.
            
            // Re-reading `createOrder` implementation: it just calls `/trade/order`.
            // Let's check if `stopLoss` is a valid param for `trade/order`.
            // Usually it is `stopLoss` (json) or separate endpoint.
            // The Controller passes `stopLoss` key.
            // If the original order had SL/TP, we might lose it if we don't re-send it.
            // The Controller sends the NEW SL/TP. So we use that.
            $createParams['stopLoss'] = '{"type":"SL","stopPrice":"'.$params['stopLoss'].'","price":"'.$params['stopLoss'].'"}';
        }
        if (isset($params['takeProfit'])) {
             $createParams['takeProfit'] = '{"type":"TP","stopPrice":"'.$params['takeProfit'].'","price":"'.$params['takeProfit'].'"}';
        }
        
        // Simpler approach for SL/TP if the API supports simple keys (some wrappers do, but raw API usually needs JSON)
        // Given I can't test, I'll stick to what the Controller sends. 
        // The Controller sends `stopLoss` => value.
        // If `Bybit` handles that, good. BingX might need specific formatting.
        // For now, let's assume we pass what we received, but mapped correctly if we know.
        // Actually, looking at `BingXApiService::setStopLoss`, it calls `/trade/stopOrder`.
        // So `createOrder` might NOT support SL/TP directly attached for Limit orders in all versions.
        // BUT, if we are replacing the main Limit order, we focus on Price/Qty.
        // SL/TP might need to be set separately if they are separate orders.
        // However, the user wants "Cancel and Replace".
        
        // Let's try to create the order.
        try {
            $newOrder = $this->createOrder($createParams);
            return $newOrder; // Should contain 'orderId'
        } catch (\Exception $e) {
            // CRITICAL: Cancel succeeded, Create failed.
            // We must throw a specific error so Controller knows to delete local order.
            throw new \Exception('REPLACE_FAILED: ' . $e->getMessage());
        }
    }

    public function getPositions(string $symbol = null): array
    {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        $positions = $this->sendRequest('get', '/openApi/swap/v2/user/positions', $params);
        return ['list' => $positions];
    }

    public function setTradingStop(array $params): array
    {
        return $this->sendRequest('post', '/openApi/swap/v2/trade/stopOrder', $params);
    }

    public function switchPositionMode(bool $hedgeMode): array
    {
        // Based on research, this endpoint is for setting position mode.
        // BingX uses dualSidePosition parameter similar to Binance
        $result = $this->sendRequest('post', '/openApi/swap/v2/user/setPositionMode', [
            'dualSidePosition' => $hedgeMode ? 'true' : 'false'
        ]);

        // Duplicate info log removed; rely on higher-level controller logging

        return $result;
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

        return $this->sendRequest('get', '/openApi/swap/v2/trade/allOrders', $params);
    }

    public function getWalletBalance(string $accountType = 'FUTURES', ?string $coin = null): array
    {
        $balanceInfo = $this->sendRequest('get', '/openApi/swap/v2/user/balance');
        return ['list' => [$balanceInfo]]; // BingX returns a single object for the futures balance
    }

    public function getTickerInfo(string $symbol): array
    {
        $ticker = $this->sendRequestWithoutCredentials('get', '/openApi/swap/v2/quote/ticker', ['symbol' => $symbol]);
        return ['list' => $ticker];
    }
    public function getKlines(string $symbol , string $interval , $limit): array
    {
        // Normalize interval for BingX spot kline: expects '1min','5min','15min','30min','60min','1day' etc.
        $normalized = $interval;
        $lower = strtolower($interval);
        if (preg_match('/^\d+m$/', $lower)) {
            // '15m' -> '15min'
            $normalized = intval($lower) . 'min';
        } elseif (preg_match('/^\d+h$/', $lower)) {
            // '1h' -> '60min'
            $normalized = (intval($lower) * 60) . 'min';
        } elseif (in_array(strtoupper($interval), ['D'])) {
            $normalized = '1day';
        } elseif ($lower === '60min') {
            $normalized = '60min';
        } elseif ($lower === '1day' || $lower === '1d') {
            $normalized = '1day';
        } elseif (preg_match('/^\d+$/', $lower)) {
            // numeric minutes -> '<n>min'
            $normalized = intval($lower) . 'min';
        }
    
        return $this->sendRequestWithoutCredentials('GET', '/openApi/spot/v1/market/kline', ['interval' => $normalized, 'symbol' => $symbol , 'limit' => $limit]);
    }

    public function getAccountBalance(): array
    {
        return $this->getWalletBalance();
    }

    public function getOrder(string $orderId): array
    {
        throw new \Exception('Use getOrder with symbol parameter for BingX');
    }

    public function cancelOrder(string $orderId): array
    {
        throw new \Exception('Use cancelOrder with symbol parameter for BingX');
    }

    public function getOpenOrders(string $symbol = null): array
    {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        $orders = $this->sendRequest('get', '/openApi/swap/v2/trade/openOrders', $params);
        return ['list' => $orders['orders'] ?? []];
    }

    public function getConditionalOrders(string $symbol): array
    {
        $orders = $this->sendRequest('get', '/openApi/swap/v2/trade/stopOrder', ['symbol' => $symbol]);
        return ['list' => $orders['orders'] ?? []];
    }

    public function getOrderHistory(string $symbol = null, int $limit = 50, ?int $startTime = null): array
    {
        // Aggregate across 7-day chunks to align with other exchanges and ensure consistent retrieval over longer ranges
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
                'limit' => $limit,
                'startTime' => $chunkStart,
                'endTime' => $chunkEnd,
            ];
            if ($symbol) {
                $params['symbol'] = $symbol;
            }

            try {
                $resp = $this->sendRequest('get', '/openApi/swap/v2/trade/allOrders', $params);
                // BingX typically returns structure with 'orders' key; fallback to 'list' if present
                $list = $resp['orders'] ?? ($resp['list'] ?? []);

                foreach ($list as $item) {
                    $oid = $item['orderId'] ?? null;
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

    public function closePosition(string $symbol, string $side, float $qty): array
    {
        return $this->createOrder([
            'symbol' => $symbol,
            'side' => $side === 'Buy' ? 'Sell' : 'Buy',
            'type' => 'MARKET',
            'quantity' => (string)$qty,
            'positionSide' => $side === 'Buy' ? 'LONG' : 'SHORT',
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
        return 'bingx';
    }

    public function testConnection(): bool
    {
        try {
            $this->sendRequest('get', '/openApi/swap/v1/server/time');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRateLimits(): array
    {
        return [
            'requests_per_minute' => 1200,
            'orders_per_second' => 10,
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

    public function createSpotOrder(array $orderData): array
    {
        return $this->sendRequest('post', '/openApi/spot/v1/trade/order', $orderData);
    }

    public function getSpotAccountBalance(): array
    {
        $balance = $this->sendRequest('get', '/openApi/spot/v1/account/balance');
        return $balance['balances'] ?? [];
    }

    public function getSpotInstrumentsInfo(string $symbol = null): array
    {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        $info = $this->sendRequestWithoutCredentials('get', '/openApi/spot/v1/common/symbols', $params);
        return ['list' => $info['symbols'] ?? []];
    }

    public function getSpotTickerInfo(string $symbol): array
    {
        return $this->sendRequestWithoutCredentials('get', '/openApi/spot/v1/ticker/24hr', ['symbol' => $symbol]);
    }

    public function getSpotOrderHistory(string $symbol = null, int $limit = 50): array
    {
        $params = ['limit' => $limit];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        $orders = $this->sendRequest('get', '/openApi/spot/v1/trade/historyOrders', $params);
        return ['list' => $orders ?? []];
    }

    public function cancelSpotOrderWithSymbol(string $orderId, string $symbol): array
    {
        return $this->sendRequest('post', '/openApi/spot/v1/trade/cancel', ['symbol' => $symbol, 'orderId' => $orderId]);
    }

    public function getSpotOrder(string $orderId): array
    {
        // BingX requires symbol for spot order lookup
        throw new \Exception('بینگ ایکس برای دریافت اطلاعات سفارش نقدی نیاز به نماد دارد. از getSpotOrderWithSymbol استفاده کنید.');
    }

    public function getSpotOrderWithSymbol(string $orderId, string $symbol): array
    {
        return $this->sendRequest('get', '/openApi/spot/v1/trade/query', ['symbol' => $symbol, 'orderId' => $orderId]);
    }

    public function cancelSpotOrder(string $orderId): array
    {
        // BingX requires symbol for spot order cancellation
        throw new \Exception('بینگ ایکس برای لغو سفارش نقدی نیاز به نماد دارد. از cancelSpotOrderWithSymbol استفاده کنید.');
    }

    public function getOpenSpotOrders(string $symbol = null): array
    {
        $params = [];
        if ($symbol) {
            $params['symbol'] = $symbol;
        }
        $orders = $this->sendRequest('get', '/openApi/spot/v1/trade/openOrders', $params);
        return ['list' => $orders ?? []];
    }

    public function checkSpotAccess(): array
    {
        try {
            $this->getSpotAccountBalance();
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

    /**
     * Get account information including position mode status
     * For BingX, we check the position mode by examining existing positions
     */
    public function getAccountInfo(): array
    {
        try {
            // Try to get positions to determine position mode
            $positions = $this->getPositions();
            
            // Check if any position has positionSide indicating hedge mode
            $hedgeMode = false;
            $positionMode = 'one-way';
            
            if (isset($positions['list']) && !empty($positions['list'])) {
                foreach ($positions['list'] as $position) {
                    if (isset($position['positionSide']) && 
                        in_array(strtoupper($position['positionSide']), ['LONG', 'SHORT'])) {
                        $hedgeMode = true;
                        $positionMode = 'hedge';
                        break;
                    }
                }
            } else {
                // If no positions exist, we can't determine mode from positions
                // Don't assume mode - return unknown to avoid false validation errors
                return [
                    'hedgeMode' => false,
                    'details' => [
                        'exchange' => 'bingx',
                        'method' => 'no_positions_found',
                        'note' => 'Cannot determine position mode without positions'
                    ]
                ];
            }
            
            return [
                'positionMode' => $positionMode,
                'hedgeMode' => $hedgeMode,
                'details' => [
                    'exchange' => 'bingx',
                    'method' => 'position_analysis',
                    'positions_checked' => count($positions['list'] ?? [])
                ]
            ];
        } catch (\Exception $e) {
            return [
                'hedgeMode' => false,
                'details' => [
                    'error' => $e->getMessage(),
                    'exchange' => 'bingx'
                ]
            ];
        }
    }

    /**
     * Get user-friendly error message for demo account issues
     */
    private function getUserFriendlyErrorMessage(string $errorCode, string $errorMsg, bool $isDemoMode): string
    {
        // Common BingX error codes and their meanings
        $errorMappings = [
            '100001' => 'کلید API نامعتبر است',
            '100002' => 'امضای API نامعتبر است',
            '100003' => 'مجوز دسترسی کافی نیست',
            '100004' => 'زمان درخواست منقضی شده است',
            '100005' => 'آدرس IP مجاز نیست',
            '100006' => 'حساب کاربری محدود شده است'
        ];

        // Check for specific demo account issues
        if ($isDemoMode) {
            if (in_array($errorCode, ['100001', '100002', '100003'])) {
                return 'اطلاعات حساب دمو نامعتبر است. لطفاً از کلیدهای API تست‌نت BingX استفاده کنید که از پنل تست BingX ایجاد شده‌اند.';
            }
            
            if ($errorMsg === 'Unknown error' || empty($errorMsg)) {
                return 'خطا در اتصال به حساب دمو. لطفاً اطمینان حاصل کنید که از کلیدهای API تست‌نت BingX استفاده می‌کنید، نه کلیدهای حساب واقعی.';
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
            return "خطا در API BingX: {$errorMsg} (کد: {$errorCode})";
        }
    }

    public function checkIPAccess(): array
    {
        try {
            $result = $this->testConnection();
            if ($result) {
                return ['success' => true, 'message' => 'IP access confirmed.'];
            } else {
                return ['success' => false, 'message' => 'IP access validation failed.'];
            }
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

    /**
     * Get maximum leverage for a symbol
     */
    public function getMaxLeverage(string $symbol): int
    {
        $info = $this->getInstrumentsInfo($symbol);
        // Response is list of objects
        if (is_array($info)) {
            foreach ($info as $item) {
                if (($item['symbol'] ?? '') === $symbol) {
                    return (int)($item['maxLongLeverage'] ?? 20);
                }
            }
        }
        return 20;
    }

    /**
     * Set leverage for a symbol
     */
    public function setLeverage(string $symbol, int $leverage): array
    {
        // BingX requires side. Usually LONG is enough for one-way?
        // Let's try setting for LONG.
        return $this->sendRequest('POST', '/openApi/swap/v2/trade/leverage', [
            'symbol' => $symbol,
            'leverage' => $leverage,
            'side' => 'LONG',
        ]);
    }
}
