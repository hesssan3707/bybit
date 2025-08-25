<?php

/**
 * Spot Trading API Test Examples
 * 
 * This file contains examples of how to test the spot trading API endpoints
 * using PHP cURL. You can run these examples to test the functionality.
 */

// Base URL - Update this to match your Laravel application URL
$baseUrl = 'http://localhost:8000/api/spot';

/**
 * Example 1: Get Account Balance
 */
function testGetAccountBalance($baseUrl) {
    $url = $baseUrl . '/balance';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== GET ACCOUNT BALANCE ===\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    return json_decode($response, true);
}

/**
 * Example 2: Get Trading Pairs
 */
function testGetTradingPairs($baseUrl) {
    $url = $baseUrl . '/pairs';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== GET TRADING PAIRS ===\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    return json_decode($response, true);
}

/**
 * Example 3: Get Ticker Information
 */
function testGetTickerInfo($baseUrl, $symbol = 'BTCUSDT') {
    $url = $baseUrl . '/ticker?symbol=' . urlencode($symbol);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== GET TICKER INFO ({$symbol}) ===\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    return json_decode($response, true);
}

/**
 * Example 4: Create Limit Buy Order
 */
function testCreateLimitOrder($baseUrl, $symbol = 'BTCUSDT', $side = 'Buy', $qty = 0.001, $price = 30000) {
    $url = $baseUrl . '/order';
    
    $orderData = [
        'side' => $side,
        'symbol' => $symbol,
        'orderType' => 'Limit',
        'qty' => $qty,
        'price' => $price,
        'timeInForce' => 'GTC'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== CREATE LIMIT ORDER ===\n";
    echo "Order Data: " . json_encode($orderData, JSON_PRETTY_PRINT) . "\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    return json_decode($response, true);
}

/**
 * Example 5: Create Market Order
 */
function testCreateMarketOrder($baseUrl, $symbol = 'BTCUSDT', $side = 'Buy', $qty = 0.001) {
    $url = $baseUrl . '/order';
    
    $orderData = [
        'side' => $side,
        'symbol' => $symbol,
        'orderType' => 'Market',
        'qty' => $qty
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== CREATE MARKET ORDER ===\n";
    echo "Order Data: " . json_encode($orderData, JSON_PRETTY_PRINT) . "\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    return json_decode($response, true);
}

/**
 * Example 6: Get Order History
 */
function testGetOrderHistory($baseUrl, $symbol = null, $limit = 10) {
    $url = $baseUrl . '/orders';
    $params = [];
    
    if ($symbol) {
        $params['symbol'] = $symbol;
    }
    if ($limit) {
        $params['limit'] = $limit;
    }
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== GET ORDER HISTORY ===\n";
    echo "URL: " . $url . "\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    return json_decode($response, true);
}

/**
 * Example 7: Cancel Order (you'll need a real order ID)
 */
function testCancelOrder($baseUrl, $orderId, $symbol) {
    $url = $baseUrl . '/order';
    
    $cancelData = [
        'orderId' => $orderId,
        'symbol' => $symbol
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cancelData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== CANCEL ORDER ===\n";
    echo "Cancel Data: " . json_encode($cancelData, JSON_PRETTY_PRINT) . "\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    return json_decode($response, true);
}

// Run the tests
echo "Starting Spot Trading API Tests...\n\n";

// Test 1: Get Account Balance
testGetAccountBalance($baseUrl);

// Test 2: Get Trading Pairs
testGetTradingPairs($baseUrl);

// Test 3: Get Ticker Info
testGetTickerInfo($baseUrl, 'BTCUSDT');

// Test 4: Get Order History
testGetOrderHistory($baseUrl, 'BTCUSDT', 5);

// Test 5: Create Limit Order (Uncomment to test - be careful with real orders!)
// testCreateLimitOrder($baseUrl, 'BTCUSDT', 'Buy', 0.001, 30000);

// Test 6: Create Market Order (Uncomment to test - be careful with real orders!)
// testCreateMarketOrder($baseUrl, 'BTCUSDT', 'Buy', 0.001);

// Test 7: Cancel Order (Uncomment and provide real order ID to test)
// testCancelOrder($baseUrl, 'your-order-id', 'BTCUSDT');

echo "API Tests Completed!\n";

/**
 * IMPORTANT NOTES:
 * 
 * 1. Make sure your Laravel application is running (php artisan serve)
 * 2. Update the $baseUrl variable to match your application URL
 * 3. Ensure your .env file has valid Bybit API credentials
 * 4. Use BYBIT_TESTNET=true for testing with testnet
 * 5. Be careful with order creation tests - they will place real orders!
 * 6. The limit order example uses a low price (30000) to avoid accidental execution
 * 7. Always test on testnet first before using mainnet
 */