# Ban System Debugging Guide

## What We've Done

### 1. Created `BanService` with Extensive Debug Logging
**File:** `app/Services/BanService.php`

This service now handles ALL ban creation logic with detailed logging at every step. The logs will show:
- Whether the order exists
- Exit price, TP, SL values
- Delta calculations (distance from TP/SL)
- Each condition check (closed_at, closed_by_user, deltas)
- Whether a ban already exists
- Whether a ban was created or why it wasn't

### 2. Refactored Both Lifecycle Managers
**Files:**
- `app/Console/Commands/FuturesLifecycleManager.php`
- `app/Console/Commands/DemoFuturesLifecycleManager.php`

Both now use the same `BanService`, ensuring consistent behavior.

### 3. Created Test Suite
**File:** `tests/Feature/BanDetectionTest.php`

Tests verify the ban logic works correctly under different scenarios.

## How to Debug Your Specific Issue

### Step 1: Find Your Trade ID
```sql
SELECT id, user_exchange_id, order_id, avg_exit_price, pnl, closed_at, closed_by_user, synchronized
FROM trades
WHERE user_exchange_id IN (SELECT id FROM user_exchanges WHERE user_id = YOUR_USER_ID)
ORDER BY closed_at DESC
LIMIT 5;
```

### Step 2: Check the Order Details
```sql
SELECT order_id, tp, sl, average_price, status, closed_at
FROM orders
WHERE order_id = 'YOUR_ORDER_ID';
```

### Step 3: Run Lifecycle with Logging
```bash
php artisan futures:lifecycle --user=YOUR_USER_ID
```

### Step 4: Check the Detailed Logs
```powershell
# View last 200 lines of log
Get-Content storage\logs\laravel.log -Tail 200

# Or filter for BanService messages
Get-Content storage\logs\laravel.log -Tail 500 | Select-String -Pattern "BanService"
```

## What the Logs Will Tell You

The `BanService` logs will show EXACTLY why a ban wasn't created:

```
[BanService] Checking exchange force close for trade 123
[BanService] Order exists: YES
[BanService] Avg exit price: 45000
[BanService] Trade 123 details:
  Exit Price: 45000
  TP: 50000 (Delta: 0.111)
  SL: 40000 (Delta: 0.111)
  Closed At: 2025-11-24 22:00:00
  Closed By User: 0
[BanService] Condition checks:
  Closed at not null: YES
  Not closed by user: YES
  Deltas not null: YES
  Deltas far enough (>0.2%): YES
[BanService] Active ban already exists: NO
[BanService] ✅ Created exchange_force_close ban for user 5
```

## Common Reasons Why Bans Aren't Created

### 1. `closed_by_user = 1`
**Symptom:** Log shows `Not closed by user: NO`

**Cause:** The trade has `closed_by_user` set to 1, meaning it was closed via the app.

**Check:**
```sql
SELECT closed_by_user FROM trades WHERE id = YOUR_TRADE_ID;
```

**Fix:** If this is wrong, update it:
```sql
UPDATE trades SET closed_by_user = 0 WHERE id = YOUR_TRADE_ID;
```

### 2. Exit Price Too Close to TP or SL
**Symptom:** Log shows `Deltas far enough (>0.2%): NO`

**Cause:** The exit price is within 0.2% of either TP or SL (meaning it legitimately hit TP/SL).

**Example:**
- TP: 50000, Exit: 49900 → Delta = 0.002 (0.2%) → NO BAN ✅ Correct
- TP: 50000, Exit: 45000 → Delta = 0.111 (11%) → BAN ✅ Correct

**Check:** Look at the delta values in the logs.

### 3. Order Missing
**Symptom:** Log shows `Order exists: NO`

**Cause:** The trade's `order_id` doesn't match any order in the database.

**Check:**
```sql
SELECT t.id, t.order_id, o.order_id as order_exists
FROM trades t
LEFT JOIN orders o ON t.order_id = o.order_id
WHERE t.id = YOUR_TRADE_ID;
```

### 4. No Exit Price
**Symptom:** Log shows `Avg exit price: NULL`

**Cause:** The trade doesn't have an `avg_exit_price` set.

**Check:**
```sql
SELECT avg_exit_price FROM trades WHERE id = YOUR_TRADE_ID;
```

### 5. Ban Already Exists
**Symptom:** Log shows `Active ban already exists: YES`

**Cause:** You already have an active `exchange_force_close` ban.

**Check:**
```sql
SELECT * FROM user_bans
WHERE user_id = YOUR_USER_ID
AND ban_type = 'exchange_force_close'
AND ends_at > NOW();
```

### 6. Trade Not Synchronized
**Symptom:** No logs at all for your trade

**Cause:** The ban logic only runs for trades with `synchronized = 0` (not yet processed).

**Check:**
```sql
SELECT synchronized FROM trades WHERE id = YOUR_TRADE_ID;
```

**Note:** Once a trade is synchronized (set to 1 or 2), the lifecycle won't process it again.

## Manual Testing

If you want to test the ban logic on an existing trade:

1. **Reset the trade's synchronized status:**
```sql
UPDATE trades SET synchronized = 0 WHERE id = YOUR_TRADE_ID;
```

2. **Delete any existing bans (if testing):**
```sql
DELETE FROM user_bans WHERE trade_id = YOUR_TRADE_ID;
```

3. **Run the lifecycle:**
```bash
php artisan futures:lifecycle --user=YOUR_USER_ID
```

4. **Check the logs and database:**
```sql
SELECT * FROM user_bans WHERE trade_id = YOUR_TRADE_ID;
```

## Running the Test Suite

To verify the logic works in isolation:

```bash
php artisan test tests/Feature/BanDetectionTest.php
```

All tests should pass, confirming the logic is correct.

## Next Steps

1. **Run the lifecycle** for your user
2. **Check the logs** to see the detailed output
3. **Share the log output** if you need help understanding why the ban wasn't created

The logs will tell us EXACTLY which condition failed!
