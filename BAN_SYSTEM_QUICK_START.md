# Ban System - Quick Start Guide

## What Was Fixed

✅ **Fixed:** Logging system error preventing errors from being logged  
✅ **Fixed:** Incorrect Carbon method calls (`addHour()` → `addHours()`, `addDay()` → `addDays()`)  
✅ **Fixed:** Silent exception handling - now errors are logged with full stack traces  
✅ **Added:** Success logging when bans are created  
✅ **Added:** `bans()` relationship to Trade model for easier debugging

## How to Test

### Option 1: Run Lifecycle Command Manually

Run the lifecycle command for a specific user to trigger ban creation:

```bash
php artisan futures:lifecycle --user=1
```

Or for all users:

```bash
php artisan futures:lifecycle
```

### Option 2: Monitor Real-Time Logs

In one terminal, watch the logs:

```bash
# Windows PowerShell
Get-Content storage\logs\laravel.log -Wait -Tail 50 | Select-String "\[Ban\]"

# Or just tail all logs
Get-Content storage\logs\laravel.log -Wait -Tail 100
```

In another terminal, run the lifecycle:

```bash
php artisan futures:lifecycle
```

You should now see messages like:
```
[Ban] کاربر 5 به دلیل ضرر در یک معامله برای 1 ساعت محروم شد
[Ban] کاربر 3 به دلیل دو ضرر متوالی برای 24 ساعت محروم شد
[Ban] کاربر 7 به دلیل بسته شدن اجباری توسط صرافی برای 3 روز محروم شد
```

If there are errors:
```
[Ban] خطا در ایجاد محرومیت: <error message>
[Ban] Stack trace: <full stack trace>
```

### Option 3: Query Database Directly

Check for recent bans:

```sql
-- See all bans created in last 24 hours
SELECT 
    id,
    user_id,
    is_demo,
    ban_type,
    starts_at,
    ends_at,
    TIMESTAMPDIFF(HOUR, starts_at, ends_at) as duration_hours,
    created_at
FROM user_bans 
WHERE created_at > NOW() - INTERVAL 24 HOUR
ORDER BY created_at DESC;

-- Count bans by type
SELECT 
    ban_type,
    is_demo,
    COUNT(*) as count,
    AVG(TIMESTAMPDIFF(HOUR, starts_at, ends_at)) as avg_hours
FROM user_bans
GROUP BY ban_type, is_demo;
```

## Ban Types Reference

| Ban Type | Trigger |Duration | Notes |
|----------|---------|---------|-------|
| `manual_close` | User manually closes order | Variable | Already working ✅ |
| `single_loss` | Trade closes with loss | 1 hour | Now fixed ✅ |
| `double_loss` | Two consecutive losses in 24h | 24 hours | Now fixed ✅ |
| `exchange_force_close` | Exchange force-closes position | 3 days | Now fixed ✅ |

## Troubleshooting

### No Bans Being Created

1. **Check if lifecycle is running:**
   ```bash
   php artisan futures:lifecycle --user=1
   ```
   
2. **Check for errors in logs:**
   ```bash
   tail -n 200 storage/logs/laravel.log | grep -i error
   ```

3. **Verify there are closed losing trades:**
   ```sql
   SELECT COUNT(*) FROM trades 
   WHERE closed_at IS NOT NULL 
   AND pnl < 0 
   AND synchronized = 1;
   ```

4. **Check if bans already exist (won't create duplicates):**
   ```sql
   SELECT * FROM user_bans WHERE trade_id IN (
       SELECT id FROM trades WHERE closed_at IS NOT NULL AND pnl < 0 ORDER BY closed_at DESC LIMIT 10
   );
   ```

### Bans Have Wrong Duration

If bans are being created with wrong durations, check the database:

```sql
SELECT 
    ban_type,
    TIMESTAMPDIFF(HOUR, starts_at, ends_at) as hours,
    starts_at,
    ends_at
FROM user_bans
ORDER BY created_at DESC
LIMIT 10;
```

Expected durations:
- `single_loss`: 1 hour
- `double_loss`: 24 hours  
- `exchange_force_close`: 72 hours

## Scheduling (Optional)

If you want the lifecycle to run automatically, add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Run every 5 minutes
    $schedule->command('futures:lifecycle')->everyFiveMinutes();
    
    // OR run every minute for faster ban creation
    $schedule->command('futures:lifecycle')->everyMinute();
}
```

Then ensure the scheduler is running:

```bash
# Add to crontab (Linux) or Task Scheduler (Windows)
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

**Note:** On shared hosting, cron jobs might not work reliably. Consider running manually or using a webhook/external cron service.

## Files Modified

1. `app/Logging/DedupTap.php` - Fixed logging type error
2. `app/Console/Commands/FuturesLifecycleManager.php` - Fixed ban creation (Real)
3. `app/Console/Commands/DemoFuturesLifecycleManager.php` - Fixed ban creation (Demo)
4. `app/Models/Trade.php` - Added bans() relationship

## Key Points to Remember

- ✅ Bans are created in `verifyClosedTradesSynchronization()` method
- ✅ Only runs for trades with `synchronized = 0` (not yet verified with exchange)
- ✅ Bans are NOT created via jobs (they're created directly in the lifecycle)
- ✅ Duplicate bans are prevented by checking for existing bans before creation
- ✅ All exceptions are now logged with full details for debugging
