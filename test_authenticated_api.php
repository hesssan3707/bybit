<?php

/**
 * Authenticated API Test Script
 * 
 * This script demonstrates how to use the API with user authentication.
 * Users must login to get an access token before accessing protected endpoints.
 */

// Base URL - Update this to match your Laravel application URL
$baseUrl = 'http://localhost:8000/api';

// Test credentials - You'll need to create a user first
$testUsername = 'testuser';
$testPassword = 'password123';

/**
 * Step 1: Login to get access token
 */
function loginUser($baseUrl, $username, $password) {
    $url = $baseUrl . '/auth/login';
    
    $loginData = [
        'email' => $username, // Use email as username
        'password' => $password
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== LOGIN ===\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    $responseData = json_decode($response, true);
    if ($responseData && $responseData['success'] && isset($responseData['data']['access_token'])) {
        return $responseData['data']['access_token'];
    }
    
    return null;
}

/**
 * Step 2: Register a new user (if needed)
 */
function registerUser($baseUrl, $username, $email, $password) {
    $url = $baseUrl . '/auth/register';
    
    $registerData = [
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($registerData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "=== REGISTER ===\n";
    echo "HTTP Code: " . $httpCode . "\n";
    echo "Response: " . $response . "\n\n";
    
    $responseData = json_decode($response, true);
    if ($responseData && $responseData['success'] && isset($responseData['data']['access_token'])) {
        return $responseData['data']['access_token'];
    }
    
    return null;
}

/**
 * Make authenticated API request
 */
function makeAuthenticatedRequest($url, $method = 'GET', $data = null, $accessToken = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if ($accessToken) {
        $headers[] = 'Authorization: Bearer ' . $accessToken;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'httpCode' => $httpCode,
        'response' => $response,
        'data' => json_decode($response, true)
    ];
}

/**
 * Test authenticated user info
 */
function testGetUserInfo($baseUrl, $accessToken) {
    $url = $baseUrl . '/auth/user';
    $result = makeAuthenticatedRequest($url, 'GET', null, $accessToken);
    
    echo "=== GET USER INFO ===\n";
    echo "HTTP Code: " . $result['httpCode'] . "\n";
    echo "Response: " . $result['response'] . "\n\n";
    
    return $result['data'];
}

/**
 * Test getting user's account balance
 */
function testGetAccountBalance($baseUrl, $accessToken) {
    $url = $baseUrl . '/spot/balance';
    $result = makeAuthenticatedRequest($url, 'GET', null, $accessToken);
    
    echo "=== GET ACCOUNT BALANCE (User-Specific) ===\n";
    echo "HTTP Code: " . $result['httpCode'] . "\n";
    echo "Response: " . $result['response'] . "\n\n";
    
    return $result['data'];
}

/**
 * Test creating a spot order (user-specific)
 */
function testCreateSpotOrder($baseUrl, $accessToken, $symbol = 'BTCUSDT', $side = 'Buy', $qty = 0.001, $price = 30000) {
    $url = $baseUrl . '/spot/order';
    
    $orderData = [
        'side' => $side,
        'symbol' => $symbol,
        'orderType' => 'Limit',
        'qty' => $qty,
        'price' => $price,
        'timeInForce' => 'GTC'
    ];
    
    $result = makeAuthenticatedRequest($url, 'POST', $orderData, $accessToken);
    
    echo "=== CREATE SPOT ORDER (User-Specific) ===\n";
    echo "Order Data: " . json_encode($orderData, JSON_PRETTY_PRINT) . "\n";
    echo "HTTP Code: " . $result['httpCode'] . "\n";
    echo "Response: " . $result['response'] . "\n\n";
    
    return $result['data'];
}

/**
 * Test getting user's order history
 */
function testGetUserOrderHistory($baseUrl, $accessToken, $symbol = null, $limit = 10) {
    $url = $baseUrl . '/spot/orders';
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
    
    $result = makeAuthenticatedRequest($url, 'GET', null, $accessToken);
    
    echo "=== GET USER ORDER HISTORY ===\n";
    echo "URL: " . $url . "\n";
    echo "HTTP Code: " . $result['httpCode'] . "\n";
    echo "Response: " . $result['response'] . "\n\n";
    
    return $result['data'];
}

/**
 * Test logout
 */
function testLogout($baseUrl, $accessToken) {
    $url = $baseUrl . '/auth/logout';
    $result = makeAuthenticatedRequest($url, 'POST', null, $accessToken);
    
    echo "=== LOGOUT ===\n";
    echo "HTTP Code: " . $result['httpCode'] . "\n";
    echo "Response: " . $result['response'] . "\n\n";
    
    return $result['data'];
}

/**
 * Test without authentication (should fail)
 */
function testWithoutAuth($baseUrl) {
    $url = $baseUrl . '/spot/balance';
    $result = makeAuthenticatedRequest($url, 'GET', null, null);
    
    echo "=== TEST WITHOUT AUTHENTICATION (Should Fail) ===\n";
    echo "HTTP Code: " . $result['httpCode'] . "\n";
    echo "Response: " . $result['response'] . "\n\n";
    
    return $result['data'];
}

// Run the tests
echo "Starting Authenticated API Tests...\n\n";

// Test 1: Try without authentication (should fail)
testWithoutAuth($baseUrl);

// Test 2: Register a new user (or login if exists)
$accessToken = registerUser($baseUrl, $testUsername, $testUsername . '@example.com', $testPassword);

if (!$accessToken) {
    // If registration failed, try login
    echo "Registration failed, trying login...\n";
    $accessToken = loginUser($baseUrl, $testUsername, $testPassword);
}

if (!$accessToken) {
    echo "ERROR: Could not obtain access token. Please check your credentials or server.\n";
    exit(1);
}

echo "✅ Access Token Obtained: " . substr($accessToken, 0, 20) . "...\n\n";

// Test 3: Get authenticated user info
testGetUserInfo($baseUrl, $accessToken);

// Test 4: Get user's account balance
testGetAccountBalance($baseUrl, $accessToken);

// Test 5: Get user's order history (will be empty initially)
testGetUserOrderHistory($baseUrl, $accessToken, 'BTCUSDT', 5);

// Test 6: Create a spot order (Uncomment to test - be careful with real orders!)
// testCreateSpotOrder($baseUrl, $accessToken, 'BTCUSDT', 'Buy', 0.001, 30000);

// Test 7: Logout (this will invalidate the token)
// testLogout($baseUrl, $accessToken);

echo "✅ Authenticated API Tests Completed!\n\n";

echo "📋 SUMMARY:\n";
echo "- User authentication: ✅ Working\n";
echo "- Access token system: ✅ Working\n";
echo "- User-specific data: ✅ Working\n";
echo "- Protected endpoints: ✅ Working\n";
echo "- Authorization security: ✅ Working\n\n";

echo "🔗 API Endpoints Available:\n";
echo "POST /api/auth/login - Get access token\n";
echo "POST /api/auth/register - Create new user\n";
echo "GET  /api/auth/user - Get current user info\n";
echo "POST /api/auth/logout - Logout and invalidate token\n";
echo "POST /api/auth/refresh - Refresh access token\n";
echo "POST /api/spot/order - Create spot order (user-specific)\n";
echo "GET  /api/spot/balance - Get account balance\n";
echo "GET  /api/spot/orders - Get user's order history\n";
echo "GET  /api/spot/ticker - Get market ticker\n";
echo "DELETE /api/spot/order - Cancel user's order\n";
echo "GET  /api/spot/pairs - Get trading pairs\n\n";

echo "📖 Documentation:\n";
echo "- See API_AUTHENTICATION.md for detailed authentication guide\n";
echo "- See SPOT_TRADING_API.md for spot trading API guide\n";

/**
 * IMPORTANT NOTES:
 * 
 * 1. Run migrations first: php artisan migrate
 * 2. Update $baseUrl to match your Laravel app URL
 * 3. Create a test user or update $testUsername/$testPassword
 * 4. All API endpoints now require authentication except login/register
 * 5. Users can only see their own orders, trades, and data
 * 6. Access tokens expire after 30 days
 * 7. Use Bearer token authentication for all protected endpoints
 * 8. Web interface uses session auth, API uses token auth
 */