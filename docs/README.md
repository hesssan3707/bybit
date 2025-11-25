# Ban System - Complete Implementation Summary

## Overview

This document provides a comprehensive summary of the ban system implementation for the futures trading platform. The ban system prevents users from placing new orders when certain risk management conditions are met.

## Table of Contents

1. [Ban Types](#ban-types)
2. [Implementation Architecture](#implementation-architecture)
3. [Timing Rules](#timing-rules)
4. [Persian Error Messages](#persian-error-messages)
5. [Related Documentation](#related-documentation)

## Ban Types

### 1. Single Loss Ban
- **Trigger:** User closes a trade with a loss
- **Duration:** 1 hour from trade closing time
- **Timing Requirement:** Loss must be within the last hour

### 2. Double Loss Ban
- **Trigger:** Two consecutive losing trades
- **Duration:** 24 hours from second trade's closing time
- **Timing Requirements:**
  - Both trades must be consecutive (last 2 trades)
  - Both trades must be within last 24 hours

### 3. Exchange Force Close Ban
- **Trigger:** Trade closed on exchange (not by user)
- **Duration:** 72 hours (3 days) from trade closing time
- **Conditions:**
  - `closed_by_user != 1`
  - Exit price is far from both TP and SL (>0.2% distance)
  - Trade has `closed_at` timestamp

## Implementation Architecture

### Services

#### BanService (`app/Services/BanService.php`)
Central service that handles all ban logic:

```php
class BanService
{
    // Main methods
    processTradeBans(Trade $trade): void
    checkAndCreateHistoricalBans(int $userId, bool $isDemo): void
    getPersianBanMessage(UserBan $ban): string
    
    // Private methods
    checkExchangeForceCloseBan(...)
    checkSingleLossBan(...)
    checkDoubleLossBan(...)
}
```

**Key Features:**
- All timing checks (1 hour, 24 hours)
- Bans start from trade closing time (not current time)
- Prevents duplicate bans
- Extensive logging for debugging

### Controllers

#### FuturesController (`app/Http/Controllers/FuturesController.php`)
Handles ban checking in order creation flow:

```php
public function create()
{
    // 1. Quick check for existing ban (fast path)
    $activeBan = UserBan::active()->...->first();
    
    // 2. If no ban, proactively check and create bans
    if (!$activeBan) {
        $banService->checkAndCreateHistoricalBans($userId, $isDemo);
        // Check again after creating bans
        $activeBan = UserBan::active()->...->first();
    }
    
    // 3. Generate Persian message if banned
    if ($activeBan) {
        $banMessage = BanService::getPersianBanMessage($activeBan);
    }
}
```

### Lifecycle Commands

Both `FuturesLifecycleManager` and `DemoFuturesLifecycleManager` have **separate** processes for syncing and ban detection:

```php
private function syncLifecycleForExchange(...)
{
    // 1. Sync orders (unchanged)
    $this->syncExchangeOrderToDatabase(...);
    
    // 2. Sync PnL records (unchanged)
    $this->syncPnlRecords(...);
    
    // 3. Verify closed trades synchronization (unchanged)
    $this->verifyClosedTradesSynchronization(...);
    
    // 4. Check for bans (SEPARATE, INDEPENDENT)
    $this->checkRecentTradesForBans($userExchange);
}

private function checkRecentTradesForBans(UserExchange $userExchange)
{
    // Get all trades closed within last 5 minutes (any sync status)
    $recentlyClosedTrades = Trade::where('closed_at', '>=', now()->subMinutes(5))->get();
    
    // Process each for ban detection
    foreach ($recentlyClosedTrades as $trade) {
        $banService->processTradeBans($trade);
    }
}
```

**Key Principle:** Trade synchronization and ban detection are **completely separate**. Sync only processes `synchronized = 0`, while ban detection processes all recently closed trades.

## Timing Rules

### Ban Start/End Times

All bans start from the **trade's closing time**, not from detection time:

```php
$closedAt = \Carbon\Carbon::parse($trade->closed_at);

UserBan::create([
    'starts_at' => $closedAt,  // From trade close time
    'ends_at' => $closedAt->copy()->addHours(X),  // Duration from close time
]);
```

### Timing Checks

#### Single Loss
```php
$hoursSinceClosed = now()->diffInHours($lastTrade->closed_at);
if ($hoursSinceClosed < 1) {
    // Create ban
}
```

#### Double Loss
```php
// Both trades must be within 24 hours
$firstWithin24h = now()->diffInHours($lastTrade->closed_at) < 24;
$secondWithin24h = now()->diffInHours($secondTrade->closed_at) < 24;

if ($firstWithin24h && $secondWithin24h) {
    // Create ban
}
```

### Active Ban Check

```php
// Scope on UserBan model
public function scopeActive($query)
{
    return $query->where('ends_at', '>', Carbon::now());
}
```

This ensures expired bans are not considered active.

## Persian Error Messages

### Message Generation

```php
public static function getPersianBanMessage(UserBan $ban): string
{
    $reasonMap = [
        'single_loss' => 'ضرر در یک معامله',
        'double_loss' => 'دو ضرر متوالی',
        'exchange_force_close' => 'بستن اجباری سفارش توسط صرافی',
    ];
    
    $reason = $reasonMap[$ban->ban_type];
    
    // Calculate remaining time
    $days = floor($remainingSeconds / 86400);
    $hours = floor(($remainingSeconds % 86400) / 3600);
    $minutes = floor(($remainingSeconds % 3600) / 60);
    
    // Build time string
    $timeParts = [];
    if ($days > 0) $timeParts[] = $days . ' روز';
    if ($hours > 0) $timeParts[] = $hours . ' ساعت';
    if ($minutes > 0 || empty($timeParts)) $timeParts[] = $minutes . ' دقیقه';
    
    $timeRemaining = implode(' و ', $timeParts);
    
    return "به دلیل {$reason}، شما تا {$timeRemaining} دیگر امکان ثبت سفارش جدید را ندارید.";
}
```

### Example Messages

- "به دلیل ضرر در یک معامله، شما تا 45 دقیقه دیگر امکان ثبت سفارش جدید را ندارید."
- "به دلیل دو ضرر متوالی، شما تا 23 ساعت و 15 دقیقه دیگر امکان ثبت سفارش جدید را ندارید."
- "به دلیل بستن اجباری سفارش توسط صرافی، شما تا 2 روز و 12 ساعت دیگر امکان ثبت سفارش جدید را ندارید."

## Detection Flow Diagram

```
User Action
    ↓
┌─────────────────────────────────────┐
│ 1. User Opens Order Creation Page   │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ 2. Check for Existing Active Ban    │
│    (Quick query: active() scope)    │
└─────────────────────────────────────┘
    ↓
Yes │           │ No
    ↓           ↓
Show Ban   ┌──────────────────────────────┐
Message    │ 3. Proactive Ban Detection   │
           │    - Fetch last 2 trades     │
           │    - Check timing rules      │
           │    - Create bans if needed   │
           └──────────────────────────────┘
                    ↓
           ┌──────────────────────────────┐
           │ 4. Check Again for Active Ban│
           └──────────────────────────────┘
                    ↓
              Yes │    │ No
                  ↓    ↓
            Show Ban  Allow Order
            Message   Submission


Lifecycle Process (Every Minute)
    ↓
┌─────────────────────────────────────┐
│ 1. Sync Orders (synchronized = 0)   │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ 2. Sync PnL Records                 │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ 3. Verify Closed Trades Sync        │
│    (Only synchronized = 0)           │
└─────────────────────────────────────┘
    ↓
┌─────────────────────────────────────┐
│ 4. Check Bans (SEPARATE PROCESS)    │
│    - Get trades closed < 5 min       │
│    - Any sync status                 │
│    - Process through BanService      │
└─────────────────────────────────────┘
```

## Related Documentation

- [Ban Debugging Guide](./BAN_DEBUGGING_GUIDE.md) - How to debug ban creation issues
- [Ban System Fixes](./BAN_SYSTEM_FIXES.md) - History of bug fixes
- [Ban Optimization](./BAN_OPTIMIZATION_FINAL.md) - Performance optimizations
- [Ban Time Fix](./BAN_TIME_FIX.md) - Fix for start/end time calculation
- [Lifecycle Ban Detection](./LIFECYCLE_BAN_DETECTION.md) - Detailed lifecycle implementation

## Testing

### Manual Testing

1. **Single Loss Ban:**
   - Place and close a trade with loss
   - Try to place new order immediately
   - Should see: "به دلیل ضرر در یک معامله، شما تا 1 ساعت..."

2. **Double Loss Ban:**
   - Close two consecutive losing trades
   - Try to place new order
   - Should see: "به دلیل دو ضرر متوالی، شما تا 24 ساعت..."

3. **Exchange Force Close Ban:**
   - Manually close trade on exchange
   - Wait for lifecycle to detect
   - Try to place new order
   - Should see: "به دلیل بستن اجباری..."

### Unit Tests

See `tests/Feature/BanDetectionTest.php` for comprehensive test coverage.

## Database Schema

### user_bans Table

```sql
CREATE TABLE user_bans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    is_demo BOOLEAN NOT NULL,
    trade_id BIGINT UNSIGNED,
    ban_type VARCHAR(255) NOT NULL,  -- 'single_loss', 'double_loss', 'exchange_force_close'
    starts_at TIMESTAMP NOT NULL,
    ends_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX (user_id, is_demo, ends_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (trade_id) REFERENCES trades(id) ON DELETE SET NULL
);
```

## Configuration

No environment variables needed. All configuration is hardcoded:
- Single loss: 1 hour
- Double loss: 24 hours  
- Exchange force close: 72 hours
- TP/SL distance threshold: 0.2%
- Recently closed window: 5 minutes

## Performance Considerations

1. **Early Exit:** Check existing ban first before running detection
2. **Limited Queries:** Only fetch last 2 trades for detection
3. **Indexed Queries:** Database indexes on `user_id`, `is_demo`, `ends_at`
4. **Separate Processes:** Sync and ban detection don't interfere
5. **5-Minute Window:** Recent trades processed multiple times but checks prevent duplicates

## Security

- Bans are enforced both in UI and backend (store method)
- Persian messages don't expose sensitive data
- Ban records tied to specific trades for audit trail
- Separate bans for demo and real accounts

---

Last Updated: 2025-11-25
Version: 2.0 (Separated Sync/Ban Detection)
