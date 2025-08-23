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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'activation_requested_at' => 'datetime',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
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

    public static function createExchangeRequest($userId, $exchangeName, $apiKey, $apiSecret, $reason = null)
    {
        return self::create([
            'user_id' => $userId,
            'exchange_name' => $exchangeName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'status' => 'pending',
            'activation_requested_at' => now(),
            'user_reason' => $reason,
        ]);
    }
}
