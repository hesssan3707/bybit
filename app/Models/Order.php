<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = "orders";
    protected $guarded = [];
    protected $fillable = [
        'user_exchange_id', 'is_demo', 'order_id', 'order_link_id', 'symbol', 'entry_price', 'tp', 'sl', 'steps',
        'expire_minutes', 'status', 'closed_at', 'filled_at', 'side', 'amount','filled_quantity','balance_at_creation',
        'initial_risk_percent', 'entry_low', 'entry_high','cancel_price'
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'filled_at' => 'datetime',
        'entry_price' => 'decimal:10',
        'tp' => 'decimal:10',
        'sl' => 'decimal:10',
        'amount' => 'decimal:10',
        'entry_low' => 'decimal:10',
        'entry_high' => 'decimal:10',
        'filled_quantity' => 'decimal:8',
        'average_price' => 'decimal:4',
        'initial_risk_percent' => 'decimal:2',
    ];

    public function candleData()
    {
        return $this->hasOne(\App\Models\OrderCandleData::class);
    }

    /**
     * Get the user exchange that owns the order
     */
    public function userExchange()
    {
        return $this->belongsTo(UserExchange::class);
    }

    /**
     * Get the user that owns the order (through user exchange)
     */
    public function user()
    {
        return $this->hasOneThrough(User::class, UserExchange::class, 'id', 'id', 'user_exchange_id', 'user_id');
    }

    /**
     * Get the trade associated with this order
     */
    public function trade()
    {
        return $this->hasOne(Trade::class, 'order_id', 'order_id');
    }

    /**
     * Scope a query to only include orders for a specific user exchange
     */
    public function scopeForUserExchange($query, $userExchangeId)
    {
        return $query->where('user_exchange_id', $userExchangeId);
    }

    /**
     * Scope a query to only include orders for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('userExchange', function($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Scope a query to only include orders with a specific status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include recent orders
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope a query to only include demo account orders
     */
    public function scopeDemo($query)
    {
        return $query->where('is_demo', true);
    }

    /**
     * Scope a query to only include real account orders
     */
    public function scopeReal($query)
    {
        return $query->where('is_demo', false);
    }

    /**
     * Scope a query to filter by account type (demo/real)
     */
    public function scopeAccountType($query, $isDemo)
    {
        return $query->where('is_demo', $isDemo);
    }
}
