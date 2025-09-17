<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;

class UserExchange extends Model
{
    use HasFactory;

    protected $table = 'user_exchanges';

    protected $fillable = [
        'user_id',
        'exchange_name',
        'api_key',
        'api_secret',
        'demo_api_key',
        'demo_api_secret',
        'is_demo_active',
        'is_active',
        'is_default',
        'status',
        'activation_requested_at',
        'activated_at',
        'activated_by',
        'deactivated_at',
        'deactivated_by',
        'admin_notes',
        'user_reason',
        'validation_results',
        'last_validation_at',
        'spot_access',
        'futures_access',
        'position_mode',
        'ip_access',
        'validation_message',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_demo_active' => 'boolean',
        'activation_requested_at' => 'datetime',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'last_validation_at' => 'datetime',
        'validation_results' => 'array',
        'spot_access' => 'boolean',
        'futures_access' => 'boolean',
        'ip_access' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
        'demo_api_key',
        'demo_api_secret',
    ];

    // Exchange configurations
    public static $exchanges = [
        'bybit' => [
            'name' => 'Bybit',
            'display_name' => 'Bybit Exchange',
            'short_name' => 'Bybit',
            'color' => '#f7931a',
            'color_rgb' => '247, 147, 26',
            'logo' => '/images/exchanges/bybit.png',
            'api_url' => 'https://api.bybit.com',
            'website' => 'https://www.bybit.com',
            'docs_url' => 'https://bybit-exchange.github.io/docs',
            'supported_features' => ['spot', 'futures', 'options'],
            'api_rate_limit' => 120, // requests per minute
            'min_order_size' => 0.001,
            'supported_symbols' => ['BTCUSDT', 'ETHUSDT', 'ADAUSDT', 'DOTUSDT'],
            'description' => 'Global cryptocurrency exchange with advanced trading features',
        ],
        'binance' => [
            'name' => 'Binance',
            'display_name' => 'Binance Exchange',
            'short_name' => 'Binance',
            'color' => '#f0b90b',
            'color_rgb' => '240, 185, 11',
            'logo' => '/images/exchanges/binance.png',
            'api_url' => 'https://api.binance.com',
            'website' => 'https://www.binance.com',
            'docs_url' => 'https://binance-docs.github.io/apidocs',
            'supported_features' => ['spot', 'futures', 'margin'],
            'api_rate_limit' => 100, // requests per minute
            'min_order_size' => 0.001,
            'supported_symbols' => ['BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'ADAUSDT'],
            'description' => 'Leading global cryptocurrency exchange with high liquidity',
        ],
        'bingx' => [
            'name' => 'BingX',
            'display_name' => 'BingX Exchange',
            'short_name' => 'BingX',
            'color' => '#00d4aa',
            'color_rgb' => '0, 212, 170',
            'logo' => '/images/exchanges/bingx.png',
            'api_url' => 'https://open-api.bingx.com',
            'website' => 'https://bingx.com',
            'docs_url' => 'https://bingx-api.github.io/docs',
            'supported_features' => ['spot', 'futures'],
            'api_rate_limit' => 80, // requests per minute
            'min_order_size' => 0.001,
            'supported_symbols' => ['BTCUSDT', 'ETHUSDT', 'BINGUSDT'],
            'description' => 'Advanced cryptocurrency trading platform with copy trading features',
        ],
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function deactivatedBy()
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function spotOrders()
    {
        return $this->hasMany(SpotOrder::class);
    }

    public function trades()
    {
        return $this->hasMany(Trade::class);
    }

    /**
     * Mutators and Accessors
     */
    public function setApiKeyAttribute($value)
    {
        $this->attributes['api_key'] = Crypt::encryptString($value);
    }

    public function getApiKeyAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setApiSecretAttribute($value)
    {
        $this->attributes['api_secret'] = Crypt::encryptString($value);
    }

    public function getApiSecretAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setDemoApiKeyAttribute($value)
    {
        $this->attributes['demo_api_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getDemoApiKeyAttribute($value)
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function setDemoApiSecretAttribute($value)
    {
        $this->attributes['demo_api_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getDemoApiSecretAttribute($value)
    {
        if (!$value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getMaskedApiKeyAttribute()
    {
        $key = $this->getApiKeyAttribute($this->attributes['api_key']);
        if (!$key) return 'N/A';

        return substr($key, 0, 8) . '...' . substr($key, -4);
    }

    public function getExchangeConfigAttribute()
    {
        return self::$exchanges[$this->exchange_name] ?? null;
    }

    public function getExchangeDisplayNameAttribute()
    {
        return $this->exchange_config['name'] ?? ucfirst($this->exchange_name);
    }

    public function getExchangeColorAttribute()
    {
        return $this->exchange_config['color'] ?? '#007bff';
    }

    public function getExchangeColorRgbAttribute()
    {
        return $this->exchange_config['color_rgb'] ?? '0, 123, 255';
    }

    public function getExchangeLogoAttribute()
    {
        return $this->exchange_config['logo'] ?? '/images/exchanges/default.png';
    }

    public function getApiUrlAttribute()
    {
        return $this->exchange_config['api_url'] ?? '';
    }

    public function getExchangeWebsiteAttribute()
    {
        return $this->exchange_config['website'] ?? '';
    }

    public function getExchangeDocsUrlAttribute()
    {
        return $this->exchange_config['docs_url'] ?? '';
    }

    public function getExchangeDescriptionAttribute()
    {
        return $this->exchange_config['description'] ?? '';
    }

    public function getExchangeSupportedFeaturesAttribute()
    {
        return $this->exchange_config['supported_features'] ?? [];
    }

    public function getExchangeApiRateLimitAttribute()
    {
        return $this->exchange_config['api_rate_limit'] ?? 60;
    }

    public function getExchangeMinOrderSizeAttribute()
    {
        return $this->exchange_config['min_order_size'] ?? 0.001;
    }

    public function getExchangeSupportedSymbolsAttribute()
    {
        return $this->exchange_config['supported_symbols'] ?? [];
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByExchange($query, $exchangeName)
    {
        return $query->where('exchange_name', $exchangeName);
    }

    /**
     * Methods
     */
    public function activate($adminId, $notes = null)
    {
        // If this is being set as default, unset other defaults for this user
        if ($this->is_default) {
            self::where('user_id', $this->user_id)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);
        }

        // If this is the user's first exchange, make it default
        if (!self::where('user_id', $this->user_id)->where('is_active', true)->exists()) {
            $this->is_default = true;
        }

        return $this->update([
            'is_active' => true,
            'status' => 'approved',
            'activated_at' => now(),
            'activated_by' => $adminId,
            'admin_notes' => $notes,
        ]);
    }

    public function deactivate($adminId, $notes = null)
    {
        $wasDefault = $this->is_default;

        $result = $this->update([
            'is_active' => false,
            'is_default' => false,
            'status' => 'suspended',
            'deactivated_at' => now(),
            'deactivated_by' => $adminId,
            'admin_notes' => $notes,
        ]);

        // If this was the default exchange, set another active exchange as default
        if ($wasDefault) {
            $nextExchange = self::where('user_id', $this->user_id)
                ->where('is_active', true)
                ->where('id', '!=', $this->id)
                ->first();

            if ($nextExchange) {
                $nextExchange->update(['is_default' => true]);
            }
        }

        return $result;
    }

    public function makeDefault()
    {
        if (!$this->is_active) {
            return false;
        }

        // Unset other defaults for this user
        self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        return $this->update(['is_default' => true]);
    }

    public function requestActivation($reason = null)
    {
        return $this->update([
            'status' => 'pending',
            'activation_requested_at' => now(),
            'user_reason' => $reason,
        ]);
    }

    public function reject($adminId, $notes = null)
    {
        return $this->update([
            'status' => 'rejected',
            'admin_notes' => $notes,
            'activated_by' => $adminId,
            'activated_at' => now(),
        ]);
    }

    /**
     * Static Methods
     */
    public static function getAvailableExchanges()
    {
        return self::$exchanges;
    }

    public static function createExchangeRequest($userId, $exchangeName, $apiKey, $apiSecret, $reason = null, $demoApiKey = null, $demoApiSecret = null)
    {
        return self::create([
            'user_id' => $userId,
            'exchange_name' => $exchangeName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'demo_api_key' => $demoApiKey,
            'demo_api_secret' => $demoApiSecret,
            'status' => 'pending',
            'activation_requested_at' => now(),
            'user_reason' => $reason,
        ]);
    }

    /**
     * Update validation results for this exchange
     */
    public function updateValidationResults(array $validationData)
    {
        $spotAccess = $validationData['spot']['success'] ?? null;
        $futuresAccess = $validationData['futures']['success'] ?? null;
        $ipAccess = $validationData['ip']['success'] ?? null;

        // Special handling for Bybit UNIFIED accounts
        // If either spot or futures validation succeeds for Bybit, both should be marked as successful
        // since UNIFIED account provides access to both spot and futures trading
        if ($this->exchange_name === 'bybit') {
            // Check if either validation indicates UNIFIED account access
            $hasUnifiedAccess = false;

            // Check spot validation for UNIFIED account indicators
            if (isset($validationData['spot']['details']['account_type']) &&
                $validationData['spot']['details']['account_type'] === 'UNIFIED') {
                $hasUnifiedAccess = true;
            }

            // Check futures validation for UNIFIED account indicators
            if (isset($validationData['futures']['details']['account_type']) &&
                $validationData['futures']['details']['account_type'] === 'UNIFIED') {
                $hasUnifiedAccess = true;
            }

            // Check for successful spot access (which indicates UNIFIED access)
            if ($spotAccess === true) {
                $hasUnifiedAccess = true;
            }

            // Check for successful futures access (which indicates UNIFIED access)
            if ($futuresAccess === true) {
                $hasUnifiedAccess = true;
            }

            // If UNIFIED access is detected, set both spot and futures to true
            if ($hasUnifiedAccess) {
                $spotAccess = true;
                $futuresAccess = true;
            }
        }

        return $this->update([
            'validation_results' => $validationData,
            'last_validation_at' => now(),
            'spot_access' => $spotAccess,
            'futures_access' => $futuresAccess,
            'ip_access' => $ipAccess,
            'validation_message' => $this->generateValidationMessage($validationData),
        ]);
    }

    /**
     * Generate a human-readable validation message
     */
    private function generateValidationMessage(array $validationData)
    {
        $messages = [];

        if (!($validationData['ip']['success'] ?? true)) {
            $messages[] = 'آدرس IP مسدود است';
        }

        if (!($validationData['spot']['success'] ?? true)) {
            if (($validationData['spot']['details']['error_type'] ?? '') === 'not_supported') {
                $messages[] = 'معاملات اسپات پشتیبانی نمی‌شود';
            } else {
                $messages[] = 'دسترسی به معاملات اسپات ندارد';
            }
        }

        if (!($validationData['futures']['success'] ?? true)) {
            if (($validationData['futures']['details']['error_type'] ?? '') === 'not_supported') {
                $messages[] = 'معاملات آتی پشتیبانی نمی‌شود';
            } else {
                $messages[] = 'دسترسی به معاملات آتی ندارد';
            }
        }

        if (empty($messages)) {
            return 'تمام دسترسی‌ها تأیید شده';
        }

        return implode(' | ', $messages);
    }

    /**
     * Check if validation results are recent (within last 24 hours)
     */
    public function hasRecentValidation()
    {
        return $this->last_validation_at &&
               $this->last_validation_at->isAfter(now()->subHours(24));
    }

    /**
     * Get validation status summary (returns string for view compatibility)
     */
    public function getValidationSummary()
    {
        if (!$this->last_validation_at) {
            return 'هنوز اعتبارسنجی نشده';
        }

        if (!$this->ip_access) {
            return 'آدرس IP مسدود';
        }

        $hasAnyAccess = $this->spot_access || $this->futures_access;

        if (!$hasAnyAccess) {
            return 'هیچ دسترسی معاملاتی ندارد';
        }

        if ($this->spot_access && $this->futures_access) {
            return 'دسترسی کامل';
        }

        $limitedType = $this->spot_access ? 'فقط اسپات' : 'فقط آتی';
        return "دسترسی محدود ({$limitedType})";
    }

    /**
     * Get detailed validation status with icon and class (for admin panel)
     */
    public function getValidationDetails()
    {
        if (!$this->last_validation_at) {
            return [
                'status' => 'not_validated',
                'message' => 'هنوز اعتبارسنجی نشده',
                'icon' => '⚠️',
                'class' => 'warning'
            ];
        }

        if (!$this->ip_access) {
            return [
                'status' => 'ip_blocked',
                'message' => 'آدرس IP مسدود',
                'icon' => '🚫',
                'class' => 'danger'
            ];
        }

        $hasAnyAccess = $this->spot_access || $this->futures_access;

        if (!$hasAnyAccess) {
            return [
                'status' => 'no_access',
                'message' => 'هیچ دسترسی معاملاتی ندارد',
                'icon' => '❌',
                'class' => 'danger'
            ];
        }

        if ($this->spot_access && $this->futures_access) {
            return [
                'status' => 'full_access',
                'message' => 'دسترسی کامل',
                'icon' => '✅',
                'class' => 'success'
            ];
        }

        $limitedType = $this->spot_access ? 'فقط اسپات' : 'فقط آتی';
        return [
            'status' => 'limited_access',
            'message' => "دسترسی محدود ({$limitedType})",
            'icon' => '⚠️',
            'class' => 'warning'
        ];
    }

    /**
     * Check if user can access spot trading
     */
    public function canAccessSpot()
    {
        return $this->is_active && $this->ip_access && $this->spot_access;
    }

    /**
     * Check if user can access futures trading
     */
    public function canAccessFutures()
    {
        return $this->is_active && $this->ip_access && $this->futures_access;
    }

    /**
     * Get current active API credentials (demo or real)
     */
    public function getCurrentApiCredentials()
    {
        if ($this->is_demo_active) {
            return [
                'api_key' => $this->demo_api_key,
                'api_secret' => $this->demo_api_secret,
                'is_demo' => true
            ];
        }

        return [
            'api_key' => $this->api_key,
            'api_secret' => $this->api_secret,
            'is_demo' => false
        ];
    }

    /**
     * Check if demo credentials are available
     */
    public function hasDemoCredentials()
    {
        return !empty($this->demo_api_key) && !empty($this->demo_api_secret);
    }

    /**
     * Check if real credentials are available
     */
    public function hasRealCredentials()
    {
        return !empty($this->api_key) && !empty($this->api_secret);
    }

    /**
     * Switch to demo mode
     */
    public function switchToDemo()
    {
        if (!$this->hasDemoCredentials()) {
            throw new \Exception('Demo credentials are not configured for this exchange.');
        }
        
        $this->update(['is_demo_active' => true]);
    }

    /**
     * Switch to real mode
     */
    public function switchToReal()
    {
        if (!$this->hasRealCredentials()) {
            throw new \Exception('Real credentials are not configured for this exchange.');
        }
        
        $this->update(['is_demo_active' => false]);
    }
}
