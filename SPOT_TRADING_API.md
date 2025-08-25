# Bybit Spot Trading API Documentation

This document describes the new spot trading API endpoints that have been added to the Laravel application.

## Base URL
All API endpoints are prefixed with `/api/spot/`

## Authentication
The API uses the same Bybit API credentials configured in your `.env` file:
- `BYBIT_API_KEY`
- `BYBIT_API_SECRET`
- `BYBIT_TESTNET` (true/false)

## Endpoints

### 1. Create Spot Order
**POST** `/api/spot/order`

Creates a new spot trading order on Bybit.

#### Request Body
```json
{
    "side": "Buy",              // Required: "Buy" or "Sell"
    "symbol": "BTCUSDT",        // Required: Trading pair (e.g., BTCUSDT, ETHUSDT)
    "orderType": "Limit",       // Required: "Market" or "Limit"
    "qty": 0.001,              // Required: Order quantity (minimum 0.00000001)
    "price": 45000.50,         // Required for Limit orders: Order price
    "timeInForce": "GTC"       // Optional: "GTC", "IOC", or "FOK" (default: GTC)
}
```

#### Response Examples

**Success Response:**
```json
{
    "success": true,
    "message": "Spot order created successfully",
    "data": {
        "orderId": "1234567890",
        "orderLinkId": "uuid-generated-id",
        "symbol": "BTCUSDT",
        "side": "Buy",
        "orderType": "Limit",
        "qty": 0.001,
        "price": 45000.50
    }
}
```

**Error Response (Validation):**
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "price": ["Price is required for Limit orders"]
    }
}
```

#### cURL Example
```bash
curl -X POST http://your-domain.com/api/spot/order \
  -H "Content-Type: application/json" \
  -d '{
    "side": "Buy",
    "symbol": "BTCUSDT",
    "orderType": "Limit",
    "qty": 0.001,
    "price": 45000.50
  }'
```

### 2. Get Account Balance
**GET** `/api/spot/balance`

Retrieves account balance separated by each currency.

#### Response Example
```json
{
    "success": true,
    "message": "Account balance retrieved successfully",
    "data": {
        "accountType": "SPOT",
        "totalEquity": 1000.50,
        "totalWalletBalance": 1000.50,
        "totalMarginBalance": 0,
        "totalAvailableBalance": 1000.50,
        "currencies": [
            {
                "currency": "BTC",
                "walletBalance": 0.5,
                "transferBalance": 0.5,
                "bonus": 0,
                "usdValue": 22500.25
            },
            {
                "currency": "USDT",
                "walletBalance": 1000.50,
                "transferBalance": 1000.50,
                "bonus": 0,
                "usdValue": 1000.50
            }
        ]
    }
}
```

#### cURL Example
```bash
curl -X GET http://your-domain.com/api/spot/balance
```

### 3. Get Order History
**GET** `/api/spot/orders`

Retrieves spot order history with optional filtering.

#### Query Parameters
- `symbol` (optional): Filter by trading pair (e.g., BTCUSDT)
- `limit` (optional): Number of orders to return (1-50, default: 20)

#### Response Example
```json
{
    "success": true,
    "message": "Order history retrieved successfully",
    "data": {
        "list": [
            {
                "orderId": "1234567890",
                "orderLinkId": "uuid-generated-id",
                "symbol": "BTCUSDT",
                "side": "Buy",
                "orderType": "Limit",
                "qty": "0.001",
                "price": "45000.50",
                "orderStatus": "Filled",
                "createdTime": "1692789123456",
                "updatedTime": "1692789123456"
            }
        ]
    }
}
```

#### cURL Example
```bash
curl -X GET "http://your-domain.com/api/spot/orders?symbol=BTCUSDT&limit=10"
```

### 4. Get Ticker Information
**GET** `/api/spot/ticker`

Gets current market ticker information for a trading pair.

#### Query Parameters
- `symbol` (required): Trading pair (e.g., BTCUSDT)

#### Response Example
```json
{
    "success": true,
    "message": "Ticker information retrieved successfully",
    "data": {
        "list": [
            {
                "symbol": "BTCUSDT",
                "lastPrice": "45000.50",
                "prevPrice24h": "44500.00",
                "price24hPcnt": "0.0112",
                "highPrice24h": "45200.00",
                "lowPrice24h": "44300.00",
                "volume24h": "1234.567"
            }
        ]
    }
}
```

#### cURL Example
```bash
curl -X GET "http://your-domain.com/api/spot/ticker?symbol=BTCUSDT"
```

### 5. Cancel Order
**DELETE** `/api/spot/order`

Cancels an existing spot order.

#### Request Body
```json
{
    "orderId": "1234567890",    // Required: Order ID to cancel
    "symbol": "BTCUSDT"        // Required: Trading pair
}
```

#### Response Example
```json
{
    "success": true,
    "message": "Order cancelled successfully",
    "data": {
        "orderId": "1234567890",
        "orderLinkId": "uuid-generated-id"
    }
}
```

#### cURL Example
```bash
curl -X DELETE http://your-domain.com/api/spot/order \
  -H "Content-Type: application/json" \
  -d '{
    "orderId": "1234567890",
    "symbol": "BTCUSDT"
  }'
```

### 6. Get Trading Pairs
**GET** `/api/spot/pairs`

Retrieves all available spot trading pairs and their specifications.

#### Response Example
```json
{
    "success": true,
    "message": "Trading pairs retrieved successfully",
    "data": [
        {
            "symbol": "BTCUSDT",
            "baseCoin": "BTC",
            "quoteCoin": "USDT",
            "status": "Trading",
            "minOrderQty": "0.00001",
            "maxOrderQty": "500",
            "qtyStep": "0.00001",
            "tickSize": "0.01"
        },
        {
            "symbol": "ETHUSDT",
            "baseCoin": "ETH",
            "quoteCoin": "USDT",
            "status": "Trading",
            "minOrderQty": "0.0001",
            "maxOrderQty": "5000",
            "qtyStep": "0.0001",
            "tickSize": "0.01"
        }
    ]
}
```

#### cURL Example
```bash
curl -X GET http://your-domain.com/api/spot/pairs
```

## Error Handling

All endpoints follow a consistent error response format:

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        // Validation errors (if applicable)
    }
}
```

### Common HTTP Status Codes
- `200` - Success
- `400` - Bad Request (validation errors)
- `422` - Unprocessable Entity (validation failed)
- `500` - Internal Server Error

## Order Types

### Market Orders
- Execute immediately at the current market price
- No `price` parameter required
- Example:
```json
{
    "side": "Buy",
    "symbol": "BTCUSDT",
    "orderType": "Market",
    "qty": 0.001
}
```

### Limit Orders
- Execute only when the market reaches the specified price
- `price` parameter is required
- Example:
```json
{
    "side": "Buy",
    "symbol": "BTCUSDT",
    "orderType": "Limit",
    "qty": 0.001,
    "price": 45000.50
}
```

## Time in Force Options

- **GTC (Good Till Cancelled)**: Order remains active until filled or manually cancelled
- **IOC (Immediate or Cancel)**: Fill immediately, cancel any unfilled portion
- **FOK (Fill or Kill)**: Fill completely or cancel the entire order

## Implementation Notes

1. **Precision Handling**: The API automatically rounds quantities and prices to the correct precision based on the trading pair specifications.

2. **Validation**: All inputs are validated before sending to Bybit API to prevent errors.

3. **Error Logging**: All API errors are logged for debugging purposes.

4. **UUID Generation**: Each order gets a unique `orderLinkId` for tracking.

5. **Rate Limiting**: Follow Bybit's rate limiting guidelines to avoid API restrictions.

## Testing

You can test the endpoints using tools like:
- cURL (examples provided above)
- Postman
- Any HTTP client library

Make sure to configure your Bybit API credentials in the `.env` file before testing.