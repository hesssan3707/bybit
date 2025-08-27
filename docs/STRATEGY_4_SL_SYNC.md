# Strategy 4 & 5: Enhanced Stop Loss Synchronization

## Problem

The original stop loss synchronization command was experiencing persistent "Unknown error" issues when trying to modify stop loss levels using the Bybit `/v5/position/set-trading-stop` endpoint. All existing strategies were failing:

1. **Strategy 1**: Direct SL modification - Failed with "Unknown error"
2. **Strategy 2**: Remove and re-set SL - Failed with "Unknown error"
3. **Strategy 3**: Alternative tpslMode (Partial) - Failed with "Unknown error"
4. **Strategy 4**: Cancel existing SL orders, then set new SL - Cancellation succeeded, but setting new SL still failed

## Root Cause Analysis

The issue was discovered to be twofold:
1. Bybit creates separate conditional orders when stop loss is set on a position
2. The `/v5/position/set-trading-stop` endpoint itself appears to have issues in certain market conditions

## Solutions: Strategy 4 & 5

### Strategy 4: Cancel Existing SL Orders and Reset

#### Approach
1. **Finds existing stop loss orders** using `/v5/order/realtime` with `orderFilter=StopOrder`
2. **Cancels these orders individually** using their order IDs via `/v5/order/cancel`
3. **Sets a new stop loss** at the position level once the old orders are cleared

### Strategy 5: Create Conditional Stop Loss Order

#### Approach
When Strategy 4 successfully cancels existing orders but fails to set new SL via position API:
1. **Cancels existing stop loss orders** (same as Strategy 4)
2. **Creates a new conditional order** directly using `/v5/order/create` with `triggerPrice`
3. **Bypasses the problematic position-level API** entirely

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

**Strategy 4 Flow:**
```
Strategy 4: Finding and canceling existing SL orders, then setting new SL...
  Found 1 conditional orders for BNBUSDT
  Canceling SL order: sl-order-abc123
  Canceled 1 stop loss orders
  Setting new stop loss to 835...
Strategy 4: Success - Existing SL orders canceled and new SL set
```

**Strategy 5 Flow (when Strategy 4 fails at final step):**
```
Strategy 5: Canceling existing SL orders and creating new conditional SL order...
  Canceled 1 stop loss orders
  Creating new conditional stop loss order at 835...
  Successfully created conditional SL order: new-sl-789
Strategy 5: Success - Conditional stop loss order created
```

### Benefits

1. **Maximum Reliability**: Two complementary approaches for different failure scenarios
2. **Root Cause Resolution**: Addresses both order conflicts and API endpoint issues
3. **Transparency**: Shows exactly which orders are being canceled and created
4. **Fallback Chain**: Strategy 5 only runs when Strategy 4's cancellation succeeds but reset fails
5. **API Compatibility**: Works with both position-level and order-level Bybit APIs

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

Both strategies are automatically integrated into the existing `FuturesStopLossSync` command:

```bash
php artisan futures:sync-sl
php artisan futures:sync-sl --user=6
```

**Execution Order:**
1. Strategy 1-3: Original approaches (direct modification, remove/reset, partial mode)
2. Strategy 4: Cancel existing orders + position-level reset
3. Strategy 5: Cancel existing orders + create conditional order

Strategies 4 and 5 only execute if previous strategies fail, ensuring backward compatibility while providing robust fallbacks for problematic cases.

**When Strategy 5 Activates:**
- All first 3 strategies fail with "Unknown error"
- Strategy 4 successfully cancels existing orders
- Strategy 4 fails when trying to set new stop loss via position API
- Strategy 5 then creates a conditional order directly

### Exchange Compatibility

- **Bybit**: Full implementation with conditional order support
- **Binance**: Placeholder implementation (returns empty list)
- **BingX**: Placeholder implementation (returns empty list)

Other exchanges can implement their own conditional order logic as needed.