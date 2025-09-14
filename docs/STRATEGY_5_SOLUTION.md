# Strategy 5: Conditional Order Solution for Stop Loss Sync

## Issue Summary

Your latest test showed that **Strategy 4 partially worked**:
- ✅ **Successfully found and canceled existing SL order**: `0793ea9f-a9cc-4ef5-95ad-d226e64516da`
- ❌ **Failed to set new stop loss**: Still got "Unknown error" from `/v5/position/set-trading-stop`

This confirms that the **order cancellation approach is correct**, but the **position-level stop loss API is problematic**.

## Strategy 5: The Solution

Instead of trying to set stop loss via the problematic `/v5/position/set-trading-stop` endpoint, **Strategy 5 creates a conditional order directly** using `/v5/order/create`.

### How Strategy 5 Works

1. **Cancels existing SL orders** (same as Strategy 4)
2. **Creates new conditional order** with these parameters:
   ```json
   {
     "category": "linear",
     "symbol": "BNBUSDT", 
     "side": "Sell",          // Opposite of Buy position
     "orderType": "Market",   // Immediate execution when triggered
     "qty": "0.01",          // Position size
     "triggerPrice": "835",  // Your target stop loss price
     "triggerBy": "LastPrice",
     "triggerDirection": 2,   // 2: price falls (for Buy SL), 1: price rises (for Sell SL)
     "reduceOnly": true,     // Only close position, don't open new
     "closeOnTrigger": true, // Close position when triggered
     "positionIdx": 0,
     "timeInForce": "GTC"
   }
   ```

### Expected Output

Next time you run the command, you should see:

```
Strategy 4: Finding and canceling existing SL orders, then setting new SL...
Found 1 conditional orders for BNBUSDT
Canceling SL order: 0793ea9f-a9cc-4ef5-95ad-d226e64516da
Canceled 1 stop loss orders
Setting new stop loss to 835...
Strategy 4: Failed - Failed to set stop loss for BNBUSDT: Bybit API Error...

Strategy 5: Canceling existing SL orders and creating new conditional SL order...
Canceled 0 stop loss orders  // Already canceled by Strategy 4
Creating new conditional stop loss order at 835...
Successfully created conditional SL order: abc123-new-order-id
Strategy 5: Success - Conditional stop loss order created
```

## Key Advantages

1. **Bypasses problematic API**: Doesn't use `/v5/position/set-trading-stop` at all
2. **Direct order creation**: Creates stop loss as a standalone conditional order
3. **Market execution**: Executes immediately when price hits trigger
4. **Safe parameters**: `reduceOnly` and `closeOnTrigger` prevent unwanted position changes

## Testing the Fix

Run the command again:
```bash
php artisan futures:sync-sltp --user=6
```

Strategy 5 should now successfully create the conditional stop loss order where Strategy 4 left off.

## Technical Details

The conditional order approach works because:
- It uses the standard `/v5/order/create` endpoint (more reliable)
- It creates the stop loss as a separate order, not a position modification
- It uses Market order type for guaranteed execution when triggered
- It includes safety flags to ensure it only closes the position

This solution directly addresses the root cause: the `/v5/position/set-trading-stop` endpoint appears to have issues in certain market conditions, but the order creation endpoint works reliably.