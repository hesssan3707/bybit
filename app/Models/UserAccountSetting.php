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
    public static function getUserSetting($userId, $key, $default = null)
    {
        $setting = static::where('user_id', $userId)
                         ->where('key', $key)
                         ->first();

        if (!$setting) {
            return $default;
        }

        return static::castValue($setting->value, $setting->type);
    }

    /**
     * Set a setting value for a user
     */
    public static function setUserSetting($userId, $key, $value, $type = 'string')
    {
        // If value is null, remove the setting entirely
        if ($value === null) {
            return static::where('user_id', $userId)
                         ->where('key', $key)
                         ->delete();
        }

        return static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    /**
     * Get all settings for a user as an associative array
     */
    public static function getUserSettings($userId)
    {
        $settings = static::where('user_id', $userId)->get();
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
    public static function getDefaultRisk($userId)
    {
        return static::getUserSetting($userId, 'default_risk', null);
    }

    /**
     * Get default future order steps setting for user
     */
    public static function getDefaultFutureOrderSteps($userId)
    {
        return static::getUserSetting($userId, 'default_future_order_steps', null);
    }
    /**
     * Get default expiration time setting for user
     */
    public static function getDefaultExpirationTime($userId)
    {
        return static::getUserSetting($userId, 'default_expiration_time', null);
    }


    /**
     * Set default risk with strict mode validation
     */
    public static function setDefaultRisk($userId, $risk)
    {
        // If risk is null, we're removing the default value, no validation needed
        if ($risk !== null) {
            $user = User::find($userId);

            // Validate risk percentage in strict mode
            if ($user && $user->future_strict_mode && $risk > 10) {
                throw new \InvalidArgumentException('در حالت سخت‌گیرانه نمی‌توانید ریسک بیش از ۱۰ درصد تنظیم کنید.');
            }
        }

        return static::setUserSetting($userId, 'default_risk', $risk, 'decimal');
    }

    /**
     * Set default expiration time
     */
    public static function setDefaultFutureOrderSteps($userId, $steps)
    {
        return static::setUserSetting($userId, 'default_future_order_steps', $steps, 'integer');
    }

    /**
     * Set default expiration time
     */
    public static function setDefaultExpirationTime($userId, $minutes)
    {
        return static::setUserSetting($userId, 'default_expiration_time', $minutes, 'integer');
    }

    /**
     * Get minimum RR ratio for strict mode (e.g., "3:1")
     */
    public static function getMinRrRatio($userId)
    {
        return static::getUserSetting($userId, 'min_rr_ratio', '3:1');
    }

    /**
     * Set minimum RR ratio for strict mode (allowed: 3:1, 2:1, 1:1, 1:2)
     */
    public static function setMinRrRatio($userId, $ratio)
    {
        $allowed = ['3:1', '2:1', '1:1', '1:2'];
        if (!in_array($ratio, $allowed, true)) {
            throw new \InvalidArgumentException('نسبت RR نامعتبر است. از گزینه‌های 3:1، 2:1، 1:1 یا 1:2 استفاده کنید.');
        }

        return static::setUserSetting($userId, 'min_rr_ratio', $ratio, 'string');
    }
}
