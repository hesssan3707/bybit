# Multi-Exchange Trading Platform Setup

## Files Renamed and Restructured

### Controllers
- `BybitController.php` → `FuturesController.php` (handles futures/perpetual trading)
- `SpotTradingController.php` → (kept same name, handles spot trading)
- `WalletBalanceController.php` → (handles wallet balance and mobile balance pages)

### Console Commands
- `BybitEnforceOrders.php` → `FuturesOrderEnforcer.php` 
- `BybitLifecycle.php` → `FuturesLifecycleManager.php`
- `SyncStopLoss.php` → `FuturesSlTpSync.php`

### Command Signatures
- `bybit:enforce` → `futures:enforce`
- `bybit:lifecycle` → `futures:lifecycle` 
- `bybit:sync-sl` → `futures:sync-sltp`

### Tests
- `BybitControllerTest.php` → `FuturesControllerTest.php`

### Navigation Updates
- "Perpetual Trading" → "Futures Trading" (معاملات آتی)
- Added mobile balance page
- Reorganized dropdown menus

## Database Reset Instructions

**IMPORTANT: This will delete all existing data!**

### 1. Drop and Recreate Database
```bash
# Connect to MySQL and drop database
mysql -u root -p
DROP DATABASE IF EXISTS your_database_name;
CRETE DATABASE your_database_name;
exit;
```

### 2. Run Fresh Migration
```bash
cd c:\wamp64\www\bybit

# Clear all Laravel caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# Run the new individual migrations
php artisan migrate:fresh

# Seed the database with admin and demo users
php artisan db:seed
```

### 3. Migration Structure
The database now uses individual migrations for better maintainability:

1. **2014_10_12_000000_create_users_table.php** - Core user authentication table
2. **2019_12_14_000001_create_personal_access_tokens_table.php** - Laravel Sanctum tokens
3. **2024_01_01_000001_create_user_exchanges_table.php** - Multi-exchange support
4. **2024_01_01_000002_add_foreign_keys_to_users_table.php** - User table constraints
5. **2024_01_01_000003_create_orders_table.php** - Futures trading orders
6. **2024_01_01_000004_create_spot_orders_table.php** - Spot trading orders
7. **2024_01_01_000005_create_trades_table.php** - P&L history tracking

Each migration can be rolled back individually if needed:
```bash
# Rollback specific migrations
php artisan migrate:rollback --step=1

# Rollback to specific batch
php artisan migrate:rollback --batch=2
```

### 3. Default Users Created
After seeding, you'll have:

**Admin User:**
- Email: `admin@trading.local`
- Password: `admin123`
- Can approve/reject exchange requests
- Can manage all users

**Demo User:**
- Email: `demo@trading.local` 
- Password: `demo123`
- Has a pre-approved Bybit exchange (with fake API credentials)
- Can test all trading features

## New Command Usage

### Futures Commands (Multi-User Support)
```bash
# Run lifecycle management for all users
php artisan futures:lifecycle

# Run for specific user
php artisan futures:lifecycle --user=2

# Enforce order consistency for all users  
php artisan futures:enforce

# Sync stop loss for all users
php artisan futures:sync-sltp
```

### Schedule Setup (Cron)
Update your cron entry:
```bash
* * * * * cd /path/to/project && php artisan futures:lifecycle >> /dev/null 2>&1
*/5 * * * * cd /path/to/project && php artisan futures:enforce >> /dev/null 2>&1  
*/10 * * * * cd /path/to/project && php artisan futures:sync-sltp >> /dev/null 2>&1
```

## Key Features Now Available

### 1. Multi-Exchange Support
- Users can have multiple exchanges (Bybit, Binance, BingX)
- Admin approval required for new exchanges
- Easy switching between exchanges

### 2. Mobile Balance Page
- Accessible at `/mobile/balance`
- Shows spot and futures balances separately
- Handles exchange-specific limitations gracefully

### 3. Exchange-Agnostic Trading
- All controllers now use `ExchangeFactory::createForUser()`
- Automatic fallbacks when exchanges don't support features
- Consistent error handling across exchanges

### 4. Improved Navigation
- Futures trading dropdown (order history, PnL, new order)
- Spot trading dropdown (orders, balances, new order)
- Mobile-friendly navigation

### 5. Better Security
- All trading pages check for active exchange
- Redirect to profile if no exchange is configured
- User-specific data isolation

## Testing the Setup

1. **Login as admin**: Test user and exchange management
2. **Login as demo user**: Test trading features with fake exchange
3. **Register new user**: Test the approval workflow
4. **Mobile interface**: Test balance page and navigation

## Production Notes

- Update `.env` with real database credentials
- Set up proper email configuration for notifications
- Replace demo API credentials with real ones
- Configure proper SSL certificates
- Set up background job queues for better performance

## Troubleshooting

If you encounter issues:
```bash
# Clear everything and restart
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Check for errors
php artisan tinker
# Test: App\Models\User::first()
```