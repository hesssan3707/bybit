<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyExchangeRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'company_exchange_requests';

    protected $fillable = [
        'user_id',
        'exchange_name',
        'account_type', // 'live' or 'demo'
        'status', // 'pending', 'approved', 'rejected'
        'requested_at',
        'processed_at',
        'processed_by',
        'assigned_user_exchange_id',
        'admin_notes',
        'user_reason',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Relations
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedExchange()
    {
        return $this->belongsTo(UserExchange::class, 'assigned_user_exchange_id');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeForUser($query, $userId)
    {
        $user = User::find($userId);
        if ($user && $user->isWatcher()) {
            $userId = $user->parent_id;
        }
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to show only items visible to end-users:
     * - All non-rejected requests
     * - Rejected requests for up to 7 days from rejection (processed_at) or request time if processed_at is null
     */
    public function scopeVisibleToUser($query)
    {
        $threshold = now()->subDays(7);

        return $query->where(function ($q) use ($threshold) {
            $q->where('status', '<>', 'rejected')
              ->orWhere(function ($q2) use ($threshold) {
                  $q2->where('status', 'rejected')
                     ->where(function ($q3) use ($threshold) {
                         $q3->whereNotNull('processed_at')->where('processed_at', '>=', $threshold)
                            ->orWhere(function ($q4) use ($threshold) {
                                $q4->whereNull('processed_at')->where('requested_at', '>=', $threshold);
                            });
                     });
              });
        });
    }

    /**
     * Helpers
     */
    public function getExchangeConfigAttribute(): array
    {
        return UserExchange::$exchanges[$this->exchange_name] ?? [];
    }

    public function getExchangeDisplayNameAttribute(): string
    {
        return $this->exchange_config['display_name'] ?? ucfirst($this->exchange_name);
    }

    public function getExchangeColorAttribute(): string
    {
        return $this->exchange_config['color'] ?? '#4b6cb7';
    }

    /**
     * Static
     */
    public static function createRequest(int $userId, string $exchangeName, string $accountType, ?string $reason = null)
    {
        return self::create([
            'user_id' => $userId,
            'exchange_name' => $exchangeName,
            'account_type' => $accountType,
            'status' => 'pending',
            'requested_at' => now(),
            'user_reason' => $reason,
        ]);
    }
}