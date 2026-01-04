<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'api_token',
        'api_token_expires_at',
        'current_exchange_id',
        'email_verified_at',
        'is_active',
        'activation_token',
        'password_reset_token',
        'password_reset_expires_at',
        'activated_at',
        'activated_by',
        'role',
        'future_strict_mode',
        'future_strict_mode_activated_at',
        'selected_market',
        'parent_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
        'activation_token',
        'password_reset_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'api_token_expires_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password_reset_expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'is_active' => 'boolean',
        'future_strict_mode' => 'boolean',
        'future_strict_mode_activated_at' => 'datetime',
    ];

    /**
     * Get the user's futures orders
     */
    public function orders()
    {
        $localKey = $this->isInvestor() ? 'parent_id' : 'id';
        return $this->hasManyThrough(Order::class, UserExchange::class, 'user_id', 'user_exchange_id', $localKey, 'id');
    }

    /**
     * Get the user's spot orders
     */
    public function spotOrders()
    {
        $localKey = $this->isInvestor() ? 'parent_id' : 'id';
        return $this->hasManyThrough(SpotOrder::class, UserExchange::class, 'user_id', 'user_exchange_id', $localKey, 'id');
    }

    /**
     * Get the user's trades
     */
    public function trades()
    {
        $localKey = $this->isInvestor() ? 'parent_id' : 'id';
        return $this->hasManyThrough(Trade::class, UserExchange::class, 'user_id', 'user_exchange_id', $localKey, 'id');
    }

    /**
     * Get the parent user (if this user is a watcher)
     */
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function investors()
    {
        return $this->hasMany(User::class, 'parent_id')->where('role', 'investor');
    }

    public function watchers()
    {
        return $this->hasMany(User::class, 'parent_id')->where('role', 'investor');
    }

    public function isInvestor()
    {
        return $this->role === 'investor';
    }

    public function isWatcher()
    {
        return $this->isInvestor();
    }

    public function getAccountOwner()
    {
        return $this->isInvestor() ? $this->parent : $this;
    }

    public function getRealEmailAttribute()
    {
        if ($this->isInvestor()) {
            $email = (string) $this->email;
            $email = preg_replace('/^investor\s*-\s*/i', '', $email);
            $email = preg_replace('/^watcher\s*-\s*/i', '', $email);
            return $email;
        }
        return $this->email;
    }

    /**
     * Get the user's periods
     */
    public function periods()
    {
        $localKey = $this->isInvestor() ? 'parent_id' : 'id';
        return $this->hasMany(UserPeriod::class, 'user_id', $localKey);
    }

    /**
     * Get the user's bans
     */
    public function bans()
    {
        $localKey = $this->isInvestor() ? 'parent_id' : 'id';
        return $this->hasMany(UserBan::class, 'user_id', $localKey);
    }

    /**
     * Get the user's exchange accounts
     */
    public function exchanges()
    {
        $localKey = $this->isInvestor() ? 'parent_id' : 'id';
        return $this->hasMany(UserExchange::class, 'user_id', $localKey);
    }

    /**
     * Get the user's active exchange accounts
     */
    public function activeExchanges()
    {
        $localKey = $this->isInvestor() ? 'parent_id' : 'id';
        return $this->hasMany(UserExchange::class, 'user_id', $localKey)
            ->active();
    }

    /**
     * Get the user's account settings
     */
    public function accountSettings()
    {
        $localKey = $this->isInvestor() ? 'parent_id' : 'id';
        return $this->hasOne(UserAccountSetting::class, 'user_id', $localKey);
    }

    /**
     * Get the user's default/current exchange
     */
    public function defaultExchange()
    {
        $localKey = $this->isInvestor() ? 'parent_id' : 'id';
        return $this->hasOne(UserExchange::class, 'user_id', $localKey)
            ->where('is_default', true)
            ->where('is_active', true)
            ->where('status', 'approved');
    }

    /**
     * Get the user's current API exchange (for API token context)
     */
    public function currentExchange()
    {
        return $this->belongsTo(UserExchange::class, 'current_exchange_id');
    }

    /**
     * Get active exchange by name
     */
    public function getExchange($exchangeName)
    {
        return $this->exchanges()->where('exchange_name', $exchangeName)->where('is_active', true)->first();
    }

    /**
     * Check if the API token is valid and not expired
     */
    public function isApiTokenValid()
    {
        return $this->api_token &&
               $this->api_token_expires_at &&
               $this->api_token_expires_at->isFuture();
    }

    /**
     * Find user by API token
     */
    public static function findByToken($token)
    {
        if (!$token) {
            return null;
        }

        $hashedToken = hash('sha256', $token);

        return self::where('api_token', $hashedToken)
                  ->where('api_token_expires_at', '>', Carbon::now())
                  ->first();
    }

    /**
     * Generate a new API token
     */
    public function generateApiToken()
    {
        $token = \Illuminate\Support\Str::random(80);
        $expiresAt = Carbon::now()->addDays(30);

        $this->update([
            'api_token' => hash('sha256', $token),
            'api_token_expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Revoke the API token
     */
    public function revokeApiToken()
    {
        $this->update([
            'api_token' => null,
            'api_token_expires_at' => null,
        ]);
    }

    /**
     * Find user by email (since email is username)
     */
    public static function findByEmail($email)
    {
        $input = trim((string)$email);
        if ($input === '') {
            return null;
        }

        if (preg_match('/^watcher\s*-\s*/i', $input)) {
            $normalized = preg_replace('/^watcher\s*-\s*/i', 'watcher-', $input);
            $normalized = strtolower($normalized);
            $raw = preg_replace('/^watcher-/', '', $normalized);

            return self::whereIn('email', [
                $normalized,
                'Watcher-' . $raw,
                'Watcher - ' . $raw,
            ])->first();
        }

        return self::where('email', $input)->first();
    }

    /**
     * Check if user account is active
     */
    public function isActive()
    {
        return $this->is_active;
    }

    /**
     * Check if user can login (active and email verified)
     */
    public function canLogin()
    {
        // For testing purposes, allow login without email verification
        // In production, uncomment the email verification requirement
        return $this->is_active; // && $this->hasVerifiedEmail();
    }

    /**
     * Generate email verification token
     */
    public function generateEmailVerificationToken()
    {
        $token = \Illuminate\Support\Str::random(60);
        $this->update([
            'activation_token' => $token, // Reuse activation_token for email verification
        ]);
        return $token;
    }

    /**
     * Verify email using token
     */
    public function verifyEmail($token)
    {
        if ($this->activation_token === $token) {
            $this->update([
                'email_verified_at' => Carbon::now(),
                'activation_token' => null,
            ]);
            return true;
        }
        return false;
    }

    /**
     * Check if email is verified
     */
    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Generate activation token
     */
    public function generateActivationToken()
    {
        $token = \Illuminate\Support\Str::random(60);
        $this->update([
            'activation_token' => $token,
        ]);
        return $token;
    }

    /**
     * Activate user account
     */
    public function activate($activatedBy = null)
    {
        $this->update([
            'is_active' => true,
            'activated_at' => Carbon::now(),
            'activated_by' => $activatedBy,
            'activation_token' => null,
        ]);
    }

    /**
     * Generate password reset token
     */
    public function generatePasswordResetToken()
    {
        $token = \Illuminate\Support\Str::random(60);
        $expiresAt = Carbon::now()->addHours(1); // 1 hour expiry

        $this->update([
            'password_reset_token' => $token,
            'password_reset_expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Verify password reset token
     */
    public function verifyPasswordResetToken($token)
    {
        return $this->password_reset_token === $token &&
               $this->password_reset_expires_at &&
               $this->password_reset_expires_at->isFuture();
    }

    /**
     * Reset password using token
     */
    public function resetPassword($newPassword)
    {
        $this->update([
            'password' => $newPassword,
            'password_reset_token' => null,
            'password_reset_expires_at' => null,
        ]);
    }

    /**
     * Find user by password reset token
     */
    public static function findByPasswordResetToken($token)
    {
        return self::where('password_reset_token', $token)
                   ->where('password_reset_expires_at', '>', Carbon::now())
                   ->first();
    }

    /**
     * Find user by activation token
     */
    public static function findByActivationToken($token)
    {
        return self::where('activation_token', $token)->first();
    }

    /**
     * Get user who activated this account
     */
    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * Get users activated by this user
     */
    public function activatedUsers()
    {
        return $this->hasMany(User::class, 'activated_by');
    }

    /**
     * Scope for active users only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for inactive users only
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Exchange-related methods
     */

    /**
     * Check if user has any active exchanges
     */
    public function hasActiveExchanges()
    {
        return $this->activeExchanges()->exists();
    }

    /**
     * Get user's current/default exchange
     */
    public function getCurrentExchange()
    {
        if ($this->isInvestor() && $this->current_exchange_id) {
            return $this->currentExchange;
        }
        return $this->defaultExchange;
    }

    /**
     * Switch to a different exchange (make it default)
     */
    public function switchToExchange($exchangeName)
    {
        $exchange = $this->getExchange($exchangeName);

        if (!$exchange) {
            throw new \Exception("Exchange {$exchangeName} not found or not active");
        }

        $result = $exchange->makeDefault();

        // Switch to hedge mode after making exchange default
        try {
            $exchangeService = \App\Services\Exchanges\ExchangeFactory::createForUserExchange($exchange);
            $exchangeService->switchPositionMode(true);
            // Duplicate info log removed; controller-level logging covers this state change
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to activate hedge mode during model exchange switch', [
                'user_id' => $this->id,
                'exchange_id' => $exchange->id,
                'exchange_name' => $exchange->exchange_name,
                'error' => $e->getMessage()
            ]);
            // Continue with exchange switch even if hedge mode fails
        }

        return $result;
    }

    /**
     * Request activation for a new exchange
     */
    public function requestExchangeActivation($exchangeName, $apiKey, $apiSecret, $reason = null)
    {
        // Check if already exists
        $existing = $this->exchanges()->where('exchange_name', $exchangeName)->first();

        if ($existing) {
            if ($existing->is_active) {
                throw new \Exception("Exchange {$exchangeName} is already active");
            }
            if ($existing->status === 'pending') {
                throw new \Exception("Exchange {$exchangeName} activation is already pending");
            }
            // Update existing inactive exchange
            return $existing->update([
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
                'status' => 'pending',
                'activation_requested_at' => now(),
                'user_reason' => $reason,
            ]);
        }

        return UserExchange::createExchangeRequest($this->id, $exchangeName, $apiKey, $apiSecret, $reason);
    }

    /**
     * Check if user has permission to access admin features
     */
    public function isAdmin() : bool
    {
        return $this->role === 'admin';
    }

    /**
     * Set user role to admin
     */
    public function makeAdmin()
    {
        $this->update(['role' => 'admin']);
    }

    /**
     * Remove admin role from user
     */
    public function removeAdmin()
    {
        $this->update(['role' => 'user']);
    }

    /**
     * Scope for admin users only
     */
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }
}
