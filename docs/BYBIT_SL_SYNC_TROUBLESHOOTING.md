# Bybit Stop Loss Synchronization Troubleshooting

## Common Issues and Solutions

### 1. "Unknown error" on /v5/position/set-trading-stop

**Problem:** Bybit API returns "Code: N/A, Msg: Unknown error" when trying to set stop loss.

**Root Causes:**
- Missing required `positionIdx` parameter (Bybit API v5 requirement)
- Incorrect `tpslMode` for the position type
- Position doesn't exist or is already closed
- Invalid parameter combinations for Partial vs Full mode

**Solution:** The updated `FuturesSlTpSync` command now uses a multi-strategy approach:

1. **Strategy 1: Direct Modification** - Attempts to modify SL with proper parameters
2. **Strategy 2: Remove and Re-set** - Removes existing SL (set to 0) then sets new value
3. **Strategy 3: Alternative Mode** - Tries Partial mode if Full mode fails

### 2. Parameter Requirements by Mode

#### Full Mode (tpslMode: "Full")
- `category`: "linear" (required)
- `symbol`: Trading pair (required)
- `positionIdx`: Position index (required in API v5)
- `tpslMode`: "Full" (required)
- `stopLoss`: Target SL price or "0" to cancel
- `takeProfit`: Optional TP price
- `tpTriggerBy`: Optional trigger type
- `slTriggerBy`: Optional trigger type

#### Partial Mode (tpslMode: "Partial")
- All Full mode parameters plus:
- `slSize`: Stop loss size (required if setting SL)
- `tpSize`: Take profit size (required if setting TP)
- `slOrderType`: "Market" or "Limit" (default: "Market")
- `tpOrderType`: "Market" or "Limit" (default: "Market")
- **Important:** `tpSize` and `slSize` must be equal when both are specified

### 3. Position Index (positionIdx) Values

- `0`: One-way mode (most common)
- `1`: Hedge mode - Buy side
- `2`: Hedge mode - Sell side

The system automatically determines the correct `positionIdx` from position data.

### 4. Error Handling Strategies

The new implementation includes:

1. **Parameter Validation** - Checks all required parameters before API calls
2. **Retry Logic** - Multiple strategies if first attempt fails
3. **Detailed Logging** - Comprehensive error logging for debugging
4. **Graceful Degradation** - Falls back to alternative methods if primary fails

### 5. Common API Response Issues

**Empty retCode or retMsg:**
- Usually indicates network/connection issues
- The system will retry with alternative approaches

**"Position not found" errors:**
- Position may have been closed between detection and SL setting
- System will skip and continue with next position

**Rate limiting:**
- System includes delays between operations to avoid rate limits
- Implements exponential backoff for retry scenarios

### 6. Debugging Tips

1. **Check Position Data:**
   ```bash
   php artisan futures:sync-sltp --user=USER_ID
   ```

2. **Review Logs:**
   - Application logs contain detailed parameter information
   - Look for "BybitApiService setStopLossAdvanced" entries

3. **Verify Position Mode:**
   - Ensure user account is in correct position mode (One-way vs Hedge)
   - Check `positionIdx` values in position data

### 7. Recommended Testing

1. **Test with Single User:**
   ```bash
   php artisan futures:sync-sltp --user=6
   ```

2. **Monitor Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep "setStopLossAdvanced"
   ```

3. **Verify Results:**
   - Check if SL was actually set on exchange
   - Compare database SL with exchange SL values

## Implementation Notes

The enhanced system follows these principles:

1. **Multiple Fallback Strategies** - If one approach fails, others are attempted
2. **Comprehensive Validation** - All parameters are validated before API calls
3. **Detailed Logging** - Every operation is logged for troubleshooting
4. **Graceful Error Handling** - System continues processing other positions even if one fails
5. **API Compliance** - Follows Bybit API v5 specifications exactly

This multi-layered approach should resolve the persistent "Unknown error" issues and provide reliable stop loss synchronization.