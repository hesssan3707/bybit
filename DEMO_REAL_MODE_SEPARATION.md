# Demo/Real Mode Separation for User Account Settings

## Summary
Added an `is_demo` column to the `user_account_settings` table to properly separate demo and real account settings. This ensures that user preferences (risk, steps, expiration time, etc.) are independent for demo and real trading modes.

## Changes Made

### 1. Database Migration
- **File**: `database/migrations/2025_11_24_160000_add_is_demo_to_user_account_settings.php`
- Added `is_demo` boolean column (default: false)
- Updated unique constraint from `['user_id', 'key']` to `['user_id', 'key', 'is_demo']`
- Migration has been executed successfully

### 2. Model Updates
- **File**: `app/Models/UserAccountSetting.php`
- Added `is_demo` to `$fillable` array
- Added `is_demo` to `$casts` as boolean
- Updated all static methods to accept optional `$isDemo` parameter (defaults to `false`)
  - `getUserSetting($userId, $key, $default = null, $isDemo = false)`
  - `setUserSetting($userId, $key, $value, $type = 'string', $isDemo = false)`
  - `getUserSettings($userId, $isDemo = false)`
  - `getDefaultRisk($userId, $isDemo = false)`
  - `setDefaultRisk($userId, $risk, $isDemo = false)`
  - `getDefaultFutureOrderSteps($userId, $isDemo = false)`
  - `setDefaultFutureOrderSteps($userId, $steps, $isDemo = false)`
  - `getDefaultExpirationTime($userId, $isDemo = false)`
  - `setDefaultExpirationTime($userId, $minutes, $isDemo = false)`
  - `getMinRrRatio($userId, $isDemo = false)`
  - `setMinRrRatio($userId, $ratio, $isDemo = false)`

### 3. Controller Updates

#### FuturesController.php
Updated all UserAccountSetting method calls to pass `$isDemo` parameter based on current exchange mode:
- **create()**: Added logic to determine `$isDemo` from current exchange and pass to all getDefault* methods
- **edit()**: Updated to pass `$isDemo` based on `$order->is_demo`
- **store()**: 
  - Leverage caching now uses `is_demo` column instead of including in key
  - Key format: `leverage_{symbol}_{exchange_id}` (removed `_{demo/real}` suffix)
  - Added `where('is_demo', $isDemo)` to cache lookup
  - Fetches max leverage and sets it on exchange when cache is missing or expired
- **update()**: 
  - Added leverage optimization before updating orders
  - Checks cache and sets leverage to maximum if needed (3-day expiration)
  - Ensures demo/real mode separation for leverage settings
- **resend()**: 
  - Added leverage optimization before resending expired orders
  - Checks cache and sets leverage to maximum if needed (3-day expiration)
  - Ensures demo/real mode separation for leverage settings
- **chartData()**: Updated default_timeframe retrieval to use `$isDemo`

#### AccountSettingsController.php
- **index()**: Added logic to determine `$isDemo` from current exchange and pass to all get* methods
- **update()**: Added logic to pass `$isDemo` to all set* methods
- **getSettings()**: Updated API endpoint to return settings for current mode

#### SettingsController.php
- **activateFutureStrictMode()**: Updated to pass `$isDemo` when setting minimum RR ratio for strict mode activation

### 4. Leverage Caching Logic
- **File**: `app/Http/Controllers/FuturesController.php` (store method)
- Leverage cache key simplified: `leverage_{symbol}_{exchange_id}`
- Uses `is_demo` column for separation instead of key suffix
- Cache lookup now includes: `->where('is_demo', $isDemo)`
- Cache expires after 3 days
- Automatically fetches max leverage and sets it on exchange when cache is missing or expired

### 5. What Doesn't Need Modification

The following functionality **does NOT** require demo/real mode changes because it doesn't access UserAccountSettings:

- **Order Delete (`destroy()` method)**: Only cancels/deletes orders

Note: `update()` and `resend()` methods now **DO** include leverage optimization logic (added as of this implementation).

## Benefits

1. **Complete Separation**: Demo and real account settings are now completely independent
2. **User Flexibility**: Users can have different preferences for demo vs real trading
3. **Safe Testing**: Testing with different settings in demo mode won't affect real account preferences
4. **Leverage Isolation**: Leverage settings are cached separately for demo and real modes
5. **Backward Compatible**: Default `$isDemo = false` ensures existing code works without changes

## Database Structure

### user_account_settings table
```
- id
- user_id
- key
- value
- type
- is_demo (NEW)
- created_at
- updated_at

UNIQUE INDEX: (user_id, key, is_demo)
```

## Example Usage

### Before (Old Code)
```php
$defaultRisk = UserAccountSetting::getDefaultRisk($user->id);
```

### After (New Code)
```php
$currentExchange = $user->currentExchange ?? $user->defaultExchange;
$isDemo = $currentExchange ? (bool)$currentExchange->is_demo_active : false;
$defaultRisk = UserAccountSetting::getDefaultRisk($user->id, $isDemo);
```

## Testing Checklist

- [x] Migration executed successfully
- [ ] Verify demo account settings don't affect real account
- [ ] Verify real account settings don't affect demo account
- [ ] Test leverage caching for both modes
- [ ] Test account settings page for both modes
- [ ] Test futures order creation for both modes
- [ ] Test strict mode validations for both modes
