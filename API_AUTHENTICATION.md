# API Authentication Documentation

## Overview

The application now supports user-based authentication with access tokens. Each user can only access their own data (orders, spot orders, trades). All API endpoints require authentication except for login and registration.

## Authentication Flow

### 1. User Registration (Optional)
**POST** `/api/auth/register`

Create a new user account.

```json
{
    "email": "user@example.com", 
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "message": "User registered successfully",
    "data": {
        "access_token": "your-access-token-here",
        "token_type": "Bearer",
        "expires_at": "2025-09-22T15:30:00.000000Z",
        "user": {
            "id": 1,
            "username": "user123",
            "email": "user@example.com"
        }
    }
}
```

### 2. User Login
**POST** `/api/auth/login`

Authenticate with username and password to get access token.

```json
{
    "email": "user@example.com",
    "password": "password123",
    "exchange_name": "bybit"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "access_token": "your-access-token-here",
        "token_type": "Bearer",
        "expires_at": "2025-09-22T15:30:00.000000Z",
        "user": {
            "id": 1,
            "username": "user123",
            "email": "user@example.com"
        }
    }
}
```

### 3. Using Access Token

Include the access token in all subsequent API requests:

**Header:** `Authorization: Bearer your-access-token-here`

Or as query parameter: `?access_token=your-access-token-here`

## Protected Endpoints

All endpoints now require authentication and return user-specific data:

### Authenticated User Info
**GET** `/api/auth/user`
```
Authorization: Bearer your-access-token-here
```

### Token Refresh
**POST** `/api/auth/refresh`
```
Authorization: Bearer your-access-token-here
```

### Logout
**POST** `/api/auth/logout`
```
Authorization: Bearer your-access-token-here
```

### Spot Trading (All User-Specific)
- **POST** `/api/spot/order` - Create spot order (saved to user's account)
- **GET** `/api/spot/balance` - Get user's account balance
- **GET** `/api/spot/orders` - Get user's spot order history
- **GET** `/api/spot/ticker` - Get market ticker (public data)
- **DELETE** `/api/spot/order` - Cancel user's spot order
- **GET** `/api/spot/pairs` - Get trading pairs (public data)

### Futures Trading
- **POST** `/api/store` - Create futures order (saved to user's account)

## Usage Examples

### 1. Complete Authentication Flow

```bash
# 1. Login to get access token
curl -X POST http://your-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password123",
    "exchange_name": "bybit"
  }'

# Response will include access_token: "abc123token"

# 2. Use token for authenticated requests
curl -X GET http://your-domain.com/api/spot/orders \
  -H "Authorization: Bearer abc123token"

# 3. Create spot order with authentication
curl -X POST http://your-domain.com/api/spot/order \
  -H "Authorization: Bearer abc123token" \
  -H "Content-Type: application/json" \
  -d '{
    "side": "Buy",
    "symbol": "BTCUSDT",
    "orderType": "Limit",
    "qty": 0.001,
    "price": 45000.50
  }'
```

### 2. Error Responses

**Invalid Token:**
```json
{
    "success": false,
    "message": "Invalid or expired access token"
}
```

**Missing Token:**
```json
{
    "success": false,
    "message": "Access token is required"
}
```

**Invalid Credentials:**
```json
{
    "success": false,
    "message": "Invalid credentials"
}
```

## Database Changes

### User-Specific Data
All tables now include `user_id` foreign keys:
- `orders` table - futures trading orders
- `spot_orders` table - spot trading orders  
- `trades` table - completed trades

### API Token Management
Users table includes:
- `api_token` - hashed access token
- `api_token_expires_at` - token expiration timestamp

## Security Features

1. **Token Expiration**: Tokens expire after 30 days
2. **Secure Hashing**: Tokens are hashed before database storage
3. **User Isolation**: Users can only access their own data
4. **Token Revocation**: Logout invalidates the token
5. **Bearer Token**: Industry standard authentication method

## Token Management

### Token Lifecycle
- **Creation**: Login/register generates new token
- **Usage**: Include in Authorization header
- **Refresh**: Get new token before expiration
- **Revocation**: Logout removes token

### Token Expiration
- Default: 30 days from creation
- Auto-refresh available via `/api/auth/refresh`
- Check expiration in token response

## Migration Required

Before using the new authentication system:

```bash
php artisan migrate
```

This will:
1. Add `user_id` foreign keys to existing tables
2. Add API token fields to users table
3. Set up proper relationships

## Backward Compatibility

- Web interface continues to use session authentication
- All existing functionality preserved
- API now requires token authentication
- Database structure updated for user isolation

## Testing

Use the provided `test_spot_api.php` script with authentication:

```php
// Add authentication header to all requests
$headers = [
    'Authorization: Bearer your-access-token-here',
    'Content-Type: application/json'
];
```