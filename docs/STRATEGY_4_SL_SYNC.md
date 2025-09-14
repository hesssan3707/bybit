# Strategy 4 & 5: Optimized Stop Loss Synchronization

## Problem

The original stop loss synchronization command was experiencing persistent "Unknown error" issues when trying to modify stop loss levels using the Bybit `/v5/position/set-trading-stop` endpoint. The first three strategies consistently failed:

1. **Strategy 1**: Direct SL modification - Consistently failed with "Unknown error"
2. **Strategy 2**: Remove and re-set SL - Consistently failed with "Unknown error"
3. **Strategy 3**: Alternative tpslMode (Partial) - Consistently failed with "Unknown error"

**Additional Issue**: The working strategies (4 & 5) were unnecessarily canceling and recreating matching stop loss orders on every run, causing inefficiency and potential disruption.

## Root Cause Analysis

The issue was discovered to be threefold:
1. Bybit creates separate conditional orders when stop loss is set on a position
2. The `/v5/position/set-trading-stop` endpoint appears to have issues in certain market conditions
3. **Detection Logic Gap**: Conditional orders created by Strategy 5 weren't being properly recognized in subsequent runs

## Solutions: Optimized Strategy 4 & 5

### ✅ **Removed Non-Working Strategies**
Strategies 1, 2, and 3 have been **completely removed** as they consistently failed with "Unknown error" and provided no value.

### 🔧 **Enhanced Detection Logic**
Both strategies now include **smart detection** that:
1. **Recognizes conditional orders** created by Strategy 5 (via `orderLinkId` pattern `sl_*`)
2. **Checks price tolerance** before canceling (±0.001 precision)
3. **Skips unnecessary operations** when orders already match target price

### Strategy 4: Cancel Existing SL Orders and Reset

#### Approach
1. **Finds existing stop loss orders** using `/v5/order/realtime` with `orderFilter=StopOrder`
2. **Smart cancellation**: Only cancels orders that don't match target price
3. **Sets a new stop loss** at the position level once the old orders are cleared

### Strategy 5: Create Conditional Stop Loss Order

#### Approach
When Strategy 4 fails at the position-level API:
1. **Smart detection**: Checks if matching conditional order already exists
2. **Cancels only mismatched orders** (preserves correct ones)
3. **Creates new conditional order** only if needed
4. **Bypasses the problematic position-level API** entirely

#### Conditional Order Parameters

```php
$orderParams = [
    'category' => 'linear',
    'symbol' => $symbol,
    'side' => $positionSide === 'buy' ? 'Sell' : 'Buy', // Opposite side
    'orderType' => 'Market', // Market order when triggered
    'qty' => (string)$positionSize, // Full position size
    'triggerPrice' => (string)$targetSl, // Trigger at stop loss price
    'triggerBy' => 'LastPrice', // Trigger based on last price
    'triggerDirection' => $positionSide === 'buy' ? 2 : 1, // 2: falls, 1: rises
    'reduceOnly' => true, // Only reduce position
    'closeOnTrigger' => true, // Close position when triggered
    'positionIdx' => $positionIdx,
    'timeInForce' => 'GTC', // Good till canceled
    'orderLinkId' => 'sl_' . time() . '_' . rand(1000, 9999),
];
```

#### Key Benefits of Strategy 5

1. **Direct Order Creation**: Creates stop loss as a conditional order, not position modification
2. **Market Execution**: Uses Market orders for immediate execution when triggered
3. **Position Safety**: `reduceOnly` and `closeOnTrigger` ensure it only closes positions
4. **Reliable Triggering**: Uses `triggerBy: LastPrice` for consistent trigger mechanism
5. **Correct Direction**: `triggerDirection` ensures proper trigger logic (2: falls for Buy SL, 1: rises for Sell SL)

### Implementation Details

#### New API Method: `getConditionalOrders()`

Added to all exchange services:

```php
public function getConditionalOrders(string $symbol): array
{
    $params = [
        'category' => 'linear',
        'symbol' => $symbol,
        'orderFilter' => 'StopOrder', // Filter for conditional orders only
    ];
    return $this->sendRequest('GET', '/v5/order/realtime', $params);
}
```

#### Stop Loss Order Detection

Strategy 4 identifies stop loss orders by checking:

1. **stopLoss field**: Orders with `stopLoss` set and not equal to '0'
2. **reduceOnly + triggerPrice**: Conditional orders that reduce position size
3. **stopOrderType**: Orders with type 'Stop', 'StopLoss', or 'sl'

#### Cancellation Process

```php
foreach ($conditionalOrders as $order) {
    if ($isStopLossOrder) {
        $exchangeService->cancelOrderWithSymbol($order['orderId'], $symbol);
        usleep(200000); // 0.2 second delay between cancellations
    }
}
```

#### Final Reset

After canceling existing orders, sets new stop loss using the standard position modification:

```php
$params = [
    'category' => 'linear',
    'symbol' => $symbol,
    'stopLoss' => (string)$targetSl,
    'tpslMode' => 'Full',
    'positionIdx' => $positionIdx,
];
$exchangeService->setStopLossAdvanced($params);
```

### Execution Flow

**Optimized Flow (No unnecessary operations):**
```
Syncing stop loss for user 6 on bybit...
SL mismatch for user 6 on bybit, BNBUSDT (Side: buy). Exchange: 835.9, DB: 835. Resetting...
Strategy 4: Finding and canceling existing SL orders, then setting new SL...
  Found 1 conditional orders for BNBUSDT
  Found matching SL order at correct price (835.0), skipping cancellation
Strategy 4: Success - Existing SL orders canceled and new SL set
```

**When cancellation is needed:**
```
Strategy 5: Canceling existing SL orders and creating new conditional SL order...
  Canceling SL order: old-order-123 (price: 830.0)
  Canceled 1 stop loss orders
  Creating new conditional stop loss order at 835...
  Successfully created conditional SL order: new-sl-789
Strategy 5: Success - Conditional stop loss order created
```

**Smart Detection in Action:**
```
Strategy 5: Canceling existing SL orders and creating new conditional SL order...
  Found existing matching SL order at correct price (835.0)
Strategy 5: Success - Found existing matching SL order at correct price
```

### Benefits

1. **Maximum Efficiency**: Removes non-working strategies, focuses only on proven approaches
2. **Smart Detection**: Recognizes existing conditional orders and avoids unnecessary operations
3. **Price Tolerance**: Only acts when stop loss actually needs adjustment (±0.001 precision)
4. **Root Cause Resolution**: Addresses both order conflicts and API endpoint issues
5. **Reduced API Calls**: Skips cancellation/recreation cycles when orders already match
6. **Pattern Recognition**: Identifies Strategy 5 created orders via `orderLinkId` pattern
7. **Fallback Chain**: Strategy 5 runs when Strategy 4 fails, with intelligent pre-checks

### Testing

**Strategy 4 Test**: `testStopLossSyncWithStrategy4CancelAndReset()` verifies:
- Mocks the first 3 strategies to fail (simulating "Unknown error")
- Mocks `getConditionalOrders()` to return existing SL orders
- Verifies `cancelOrderWithSymbol()` is called for each SL order
- Confirms final `setStopLossAdvanced()` succeeds with correct parameters

**Strategy 5 Test**: `testStopLossSyncWithStrategy5ConditionalOrder()` verifies:
- Mocks all first 4 strategies to fail (including Strategy 4's final step)
- Verifies existing orders are canceled in both Strategy 4 and 5
- Confirms `createOrder()` is called with proper conditional order parameters
- Validates conditional order has correct trigger price, reduce-only, and close-on-trigger settings

### Usage

The optimized strategies are automatically integrated into the existing `FuturesSlTpSync` command:

```bash
php artisan futures:sync-sltp
php artisan futures:sync-sltp --user=6
```

**Execution Order (Optimized):**
1. ~~Strategy 1-3: Removed (consistently failed)~~ ❌
2. Strategy 4: Cancel mismatched orders + position-level reset ✅
3. Strategy 5: Cancel mismatched orders + create conditional order ✅

**Smart Behavior:**
- ⚡ **Skips operations** when stop loss already matches target
- 🔍 **Recognizes conditional orders** created by previous runs
- 🎯 **Only cancels orders** that don't match target price
- 🚀 **Reduces API calls** and improves efficiency

**When Strategy 5 Activates:**
- Strategy 4 fails at position-level API
- Strategy 5 checks for existing matching orders first
- Only creates new conditional order if no matching order exists

### Exchange Compatibility

- **Bybit**: Full implementation with conditional order support
- **Binance**: Placeholder implementation (returns empty list)
- **BingX**: Placeholder implementation (returns empty list)

Other exchanges can implement their own conditional order logic as needed.