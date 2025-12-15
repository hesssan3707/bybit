<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAccountSetting extends Model
{
    use HasFactory;

    protected $table = 'user_account_settings';

    protected $fillable = [
        'user_id',
        'key',
        'value',
        'type',
        'is_demo',
    ];

    protected $casts = [
        'is_demo' => 'boolean',
    ];

    /**
     * Get the user that owns the settings
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a specific setting value for a user
     */
    public static function getUserSetting($userId, $key, $default = null, $isDemo = false)
    {
        $setting = static::where('user_id', $userId)
                         ->where('key', $key)
                         ->where('is_demo', $isDemo)
                         ->first();

        if (!$setting) {
            return $default;
        }

        return static::castValue($setting->value, $setting->type);
    }

    /**
     * Set a setting value for a user
     */
    public static function setUserSetting($userId, $key, $value, $type = 'string', $isDemo = false)
    {
        // If value is null, remove the setting entirely
        if ($value === null) {
            return static::where('user_id', $userId)
                         ->where('key', $key)
                         ->where('is_demo', $isDemo)
                         ->delete();
        }

        return static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key, 'is_demo' => $isDemo],
            ['value' => $value, 'type' => $type]
        );
    }

    /**
     * Get all settings for a user as an associative array
     */
    public static function getUserSettings($userId, $isDemo = false)
    {
        $settings = static::where('user_id', $userId)
                          ->where('is_demo', $isDemo)
                          ->get();
        $result = [];

        foreach ($settings as $setting) {
            $result[$setting->key] = static::castValue($setting->value, $setting->type);
        }

        return $result;
    }

    /**
     * Cast value to appropriate type
     */
    protected static function castValue($value, $type)
    {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'decimal':
                return (float) $value;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Get default risk setting for user
     */
    public static function getDefaultRisk($userId, $isDemo = false)
    {
        return static::getUserSetting($userId, 'default_risk', null, $isDemo);
    }

    /**
     * Get default future order steps setting for user
     */
    public static function getDefaultFutureOrderSteps($userId, $isDemo = false)
    {
        return static::getUserSetting($userId, 'default_future_order_steps', null, $isDemo);
    }
    /**
     * Get default expiration time setting for user
     */
    public static function getDefaultExpirationTime($userId, $isDemo = false)
    {
        return static::getUserSetting($userId, 'default_expiration_time', null, $isDemo);
    }

    /**
     * Get TradingView default interval for user
     */
    public static function getTradingViewDefaultInterval($userId, $isDemo = false)
    {
        return static::getUserSetting($userId, 'tv_default_interval', '5', $isDemo);
    }

    /**
     * Set TradingView default interval for user
     */
    public static function setTradingViewDefaultInterval($userId, $interval, $isDemo = false)
    {
        return static::setUserSetting($userId, 'tv_default_interval', $interval, 'string', $isDemo);
    }


    /**
     * Set default risk with strict mode validation
     */
    public static function setDefaultRisk($userId, $risk, $isDemo = false)
    {
        // If risk is null, we're removing the default value, no validation needed
        if ($risk !== null) {
            $user = User::find($userId);

            // Validate risk percentage in strict mode
            if ($user && $user->future_strict_mode && $risk > 10) {
                throw new \InvalidArgumentException('در حالت سخت‌گیرانه نمی‌توانید ریسک بیش از ۱۰ درصد تنظیم کنید.');
            }
        }

        return static::setUserSetting($userId, 'default_risk', $risk, 'decimal', $isDemo);
    }

    /**
     * Set default expiration time
     */
    public static function setDefaultFutureOrderSteps($userId, $steps, $isDemo = false)
    {
        return static::setUserSetting($userId, 'default_future_order_steps', $steps, 'integer', $isDemo);
    }

    /**
     * Set default expiration time
     */
    public static function setDefaultExpirationTime($userId, $minutes, $isDemo = false)
    {
        return static::setUserSetting($userId, 'default_expiration_time', $minutes, 'integer', $isDemo);
    }

    /**
     * Get minimum RR ratio for strict mode (loss:profit minima, e.g., "3:1")
     */
    public static function getMinRrRatio($userId, $isDemo = false)
    {
        // Default to 3:1 (ضرر سه برابر سود)
        $val = static::getUserSetting($userId, 'min_rr_ratio', '3:1', $isDemo);
        // Normalize any previously stored reversed values to standard loss:profit minima
        $map = [
            '1:3' => '3:1',
            '1:2' => '2:1',
        ];
        if (isset($map[$val])) {
            $val = $map[$val];
        }
        return $val;
    }

    /**
     * Set minimum RR ratio for strict mode (allowed: 3:1, 2:1, 1:1, 1:2)
     */
    public static function setMinRrRatio($userId, $ratio, $isDemo = false)
    {
        $allowed = ['3:1', '2:1', '1:1', '1:2'];
        if (!in_array($ratio, $allowed, true)) {
            throw new \InvalidArgumentException('نسبت RR نامعتبر است. از گزینه‌های 3:1، 2:1، 1:1 یا 1:2 استفاده کنید.');
        }

        return static::setUserSetting($userId, 'min_rr_ratio', $ratio, 'string', $isDemo);
    }
}
