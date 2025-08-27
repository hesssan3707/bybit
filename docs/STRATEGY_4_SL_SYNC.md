# Strategy 4: Cancel Existing SL Orders and Reset

## Problem

The original stop loss synchronization command was experiencing persistent "Unknown error" issues when trying to modify stop loss levels using the Bybit `/v5/position/set-trading-stop` endpoint. All three existing strategies were failing:

1. **Strategy 1**: Direct SL modification - Failed with "Unknown error"
2. **Strategy 2**: Remove and re-set SL - Failed with "Unknown error"
3. **Strategy 3**: Alternative tpslMode (Partial) - Failed with "Unknown error"

## Root Cause Analysis

The issue was that Bybit creates separate conditional orders when stop loss is set on a position. These orders exist independently and can sometimes conflict with position-level stop loss modifications, especially when there are existing stop loss orders that weren't properly cleared.

## Solution: Strategy 4

### Approach

Instead of trying to modify the stop loss at the position level, Strategy 4:

1. **Finds existing stop loss orders** using the `/v5/order/realtime` endpoint with `orderFilter=StopOrder`
2. **Cancels these orders individually** using their order IDs via `/v5/order/cancel`
3. **Sets a new stop loss** at the position level once the old orders are cleared

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

```
Strategy 4: Finding and canceling existing SL orders, then setting new SL...
  Found 1 conditional orders for BNBUSDT
  Canceling SL order: sl-order-abc123
  Canceled 1 stop loss orders
  Setting new stop loss to 835...
Strategy 4: Success - Existing SL orders canceled and new SL set
```

### Benefits

1. **Reliability**: Addresses the root cause by clearing conflicting orders
2. **Transparency**: Shows exactly which orders are being canceled
3. **Compatibility**: Works with Bybit's order management system as intended
4. **Fallback**: Only runs after other strategies fail, preserving existing functionality

### Testing

A comprehensive test case `testStopLossSyncWithStrategy4CancelAndReset()` verifies:

- Mocks the first 3 strategies to fail (simulating "Unknown error")
- Mocks `getConditionalOrders()` to return existing SL orders
- Verifies `cancelOrderWithSymbol()` is called for each SL order
- Confirms final `setStopLossAdvanced()` succeeds with correct parameters

### Usage

The strategy is automatically integrated into the existing `FuturesStopLossSync` command:

```bash
php artisan futures:sync-sl
php artisan futures:sync-sl --user=6
```

Strategy 4 will only execute if Strategies 1-3 fail, ensuring backward compatibility while providing a robust fallback for problematic cases.

### Exchange Compatibility

- **Bybit**: Full implementation with conditional order support
- **Binance**: Placeholder implementation (returns empty list)
- **BingX**: Placeholder implementation (returns empty list)

Other exchanges can implement their own conditional order logic as needed.