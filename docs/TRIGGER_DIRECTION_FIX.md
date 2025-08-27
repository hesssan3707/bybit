# TriggerDirection Fix for Strategy 5

## Issue Identified

Strategy 5 was failing with error:
```
TriggerDirection invalid
Code: 10001
```

## Root Cause

When creating conditional orders in Bybit API v5, the `triggerDirection` parameter is **required** for conditional orders. This parameter specifies when the order should be triggered:

- `triggerDirection: 1` - Triggered when market price **rises** to triggerPrice
- `triggerDirection: 2` - Triggered when market price **falls** to triggerPrice

## Fix Applied

Added the `triggerDirection` parameter to conditional order creation:

```php
$orderParams = [
    // ... other parameters ...
    'triggerDirection' => $positionSide === 'buy' ? 2 : 1,
    // ... other parameters ...
];
```

### Logic Explanation

- **Buy Position Stop Loss**: `triggerDirection: 2` (price falls)
  - When you have a Buy position, stop loss triggers when price falls below your trigger price
  - Example: Buy at 900, stop loss at 835 → trigger when price falls to 835

- **Sell Position Stop Loss**: `triggerDirection: 1` (price rises)  
  - When you have a Sell position, stop loss triggers when price rises above your trigger price
  - Example: Sell at 800, stop loss at 835 → trigger when price rises to 835

## Expected Result

With this fix, Strategy 5 should now successfully create conditional stop loss orders:

```
Strategy 5: Canceling existing SL orders and creating new conditional SL order...
  Canceled 0 stop loss orders  // Already canceled by Strategy 4
  Creating new conditional stop loss order at 835...
  Successfully created conditional SL order: abc123-new-order-id
Strategy 5: Success - Conditional stop loss order created
```

## Validation

The order will be created with these parameters for your BNBUSDT Buy position:
```json
{
  "category": "linear",
  "symbol": "BNBUSDT",
  "side": "Sell",
  "orderType": "Market", 
  "qty": "0.01",
  "triggerPrice": "835",
  "triggerBy": "LastPrice",
  "triggerDirection": 2,  // ✅ This was missing!
  "reduceOnly": true,
  "closeOnTrigger": true,
  "positionIdx": 0,
  "timeInForce": "GTC"
}
```

This conditional order will automatically execute a Market Sell order when BNBUSDT price falls to 835, effectively closing your Buy position as a stop loss.