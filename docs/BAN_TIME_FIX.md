# Ban Start/End Times - Critical Fix

## Issue
Bans were starting from current time (`now()`) instead of from the trade's closing time (`$trade->closed_at`).

## Correct Behavior

| Ban Type | Start Time | End Time | Duration |
|----------|------------|----------|----------|
| **Single Loss** | Trade closing time | Start + 1 hour | 1 hour |
| **Double Loss** | Second trade's closing time | Start + 24 hours | 24 hours |
| **Exchange Force Close** | Trade closing time | Start + 72 hours | 72 hours (3 days) |

## Changes Made

### 1. Exchange Force Close Ban
**Before:**
```php
'starts_at' => now(),
'ends_at' => now()->addDays(3),
```

**After:**
```php
$closedAt = \Carbon\Carbon::parse($trade->closed_at);
'starts_at' => $closedAt,
'ends_at' => $closedAt->copy()->addHours(72),
```

### 2. Single Loss Ban
**Before:**
```php
'starts_at' => now(),
'ends_at' => now()->addHours(1),
```

**After:**
```php
$closedAt = \Carbon\Carbon::parse($trade->closed_at);
'starts_at' => $closedAt,
'ends_at' => $closedAt->copy()->addHours(1),
```

### 3. Double Loss Ban
**Before:**
```php
'starts_at' => now(),
'ends_at' => now()->addDays(1),
```

**After:**
```php
// Ban starts from the second (most recent) trade's closing time
$closedAt = \Carbon\Carbon::parse($trade->closed_at);
'starts_at' => $closedAt,
'ends_at' => $closedAt->copy()->addHours(24),
```

## Why This Matters

### Scenario: User closes trade 30 minutes ago

**Before (Wrong):**
- Trade closed: 18:00
- Ban detected at: 18:30
- Ban starts: 18:30 → Ends: 19:30 (1 hour from detection)
- **User got extra 30 minutes**

**After (Correct):**
- Trade closed: 18:00
- Ban detected at: 18:30
- Ban starts: 18:00 → Ends: 19:00 (1 hour from close)
- **User only has 30 minutes left**

### Scenario: Old trade detected

**Before (Wrong):**
- Trade closed: 3 days ago
- Ban detected now
- Ban starts: now → Ends: now + 1 hour
- **User gets full ban even though time passed**

**After (Correct):**
- Trade closed: 3 days ago
- Ban detected now
- Ban starts: 3 days ago → Ends: 3 days ago + 1 hour
- **Ban already expired!**
- `UserBan::active()` won't match it

## Impact on `active()` Scope

The `active()` scope checks `ends_at > now()`:

```php
public function scopeActive($query)
{
    return $query->where('ends_at', '>', Carbon::now());
}
```

This means:
- ✅ If ban already expired based on trade close time, it won't be "active"
- ✅ Remaining time calculations are now accurate
- ✅ Users can't exploit delayed processing to extend their bans

## Testing Examples

### Example 1: Recent trade
```
Trade closed at: 2025-11-25 18:00:00
Detection time: 2025-11-25 18:15:00
Current time: 2025-11-25 18:20:00

Single Loss Ban:
  starts_at: 2025-11-25 18:00:00
  ends_at:   2025-11-25 19:00:00
  Remaining: 40 minutes ✅
```

### Example 2: Old trade
```
Trade closed at: 2025-11-25 10:00:00
Detection time: 2025-11-25 18:00:00
Current time: 2025-11-25 18:00:00

Single Loss Ban:
  starts_at: 2025-11-25 10:00:00
  ends_at:   2025-11-25 11:00:00
  Ban expired ✅ (not active)
```

### Example 3: Double loss
```
Trade 1 closed: 2025-11-25 17:00:00
Trade 2 closed: 2025-11-25 18:00:00 (2nd loss)
Detection time: 2025-11-25 18:05:00
Current time: 2025-11-25 18:10:00

Double Loss Ban:
  starts_at: 2025-11-25 18:00:00 (from 2nd trade)
  ends_at:   2025-11-26 18:00:00 (24h later)
  Remaining: ~23h 50min ✅
```

## Files Modified

✅ `app/Services/BanService.php`
- Updated `checkExchangeForceCloseBan()`
- Updated `checkSingleLossBan()`
- Updated `checkDoubleLossBan()`

All three methods now use `$trade->closed_at` as the ban start time.
