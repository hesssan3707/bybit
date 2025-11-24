# Log Optimization - Quick Guide

## Changes Made

### ✅ Removed Info Logs
- Removed all `$this->info()` calls for ban creation
- Only error logs remain (for actual failures)
- This will significantly reduce log file growth

### ✅ Improved Deduplication
**Old settings:**
- Level: `warning` (only deduplicated warnings and errors)
- TTL: `300` seconds (5 minutes)

**New settings:**
- Level: `info` (deduplicates info, warnings, and errors)
- TTL: `3600` seconds (1 hour)

**What this means:**
- If the same log message appears multiple times within 1 hour, only the first occurrence will be logged
- This dramatically reduces duplicate log entries
- You can override these in `.env` file:
  ```env
  LOG_DEDUP_LEVEL=info
  LOG_DEDUP_TTL=3600
  ```

## Clean Up Existing Large Log File

### Option 1: Use the Cleanup Script (Recommended)
```powershell
.\cleanup-logs.ps1
```

This interactive script gives you 3 options:
1. Archive old logs and start fresh
2. Keep only last 1000 lines
3. Delete completely

### Option 2: Manual Cleanup

**Archive current log:**
```powershell
Move-Item storage\logs\laravel.log storage\logs\laravel.log.old
New-Item storage\logs\laravel.log -ItemType File
```

**Keep only last 1000 lines:**
```powershell
Get-Content storage\logs\laravel.log -Tail 1000 | Set-Content storage\logs\laravel.log.new
Move-Item storage\logs\laravel.log.new storage\logs\laravel.log -Force
```

**Delete completely:**
```powershell
Remove-Item storage\logs\laravel.log
```

## Prevent Future Bloat

### 1. Configure Log Rotation
Add to `config/logging.php` in your channel configuration:
```php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 7, // Keep only 7 days of logs
],
```

### 2. Adjust Deduplication (Optional)
If logs are still too verbose, increase the TTL in `.env`:
```env
# Deduplicate for 2 hours instead of 1
LOG_DEDUP_TTL=7200

# Or even 24 hours
LOG_DEDUP_TTL=86400
```

### 3. Use Different Log Levels
In `.env`, set minimum log level:
```env
# Only log warnings and errors (not info or debug)
LOG_LEVEL=warning
```

## What Logs Remain

**You will still see:**
- ✅ Critical errors (database, API failures, etc.)
- ✅ Ban creation errors (with full stack trace)
- ✅ Important warnings

**You will NOT see:**
- ❌ Info messages about successful ban creation
- ❌ Duplicate identical messages within 1 hour
- ❌ Debug messages (if LOG_LEVEL=warning)

## Testing

After cleanup, run the lifecycle:
```bash
php artisan futures:lifecycle
```

Then check the log file size:
```powershell
(Get-Item storage\logs\laravel.log).Length / 1MB
# Should show size in MB
```

## Files Modified

1. ✅ `app/Console/Commands/FuturesLifecycleManager.php` - Removed info logs
2. ✅ `app/Console/Commands/DemoFuturesLifecycleManager.php` - Removed info logs
3. ✅ `app/Logging/DedupTap.php` - Improved deduplication settings

## Summary

**Before:**
- Info logs on every ban creation
- Duplicates logged every 5 minutes
- Log file grows very fast

**After:**
- No info logs (only errors)
- Duplicates suppressed for 1 hour
- Log file grows much slower
- Error logging still fully functional
