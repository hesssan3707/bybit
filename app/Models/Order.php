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
        'user_exchange_id', 'order_id', 'order_link_id', 'symbol', 'entry_price', 'tp', 'sl', 'steps',
        'expire_minutes', 'status', 'closed_at', 'side', 'amount', 'entry_low', 'entry_high'
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'entry_price' => 'decimal:10',
        'tp' => 'decimal:10',
        'sl' => 'decimal:10',
        'amount' => 'decimal:10',
        'entry_low' => 'decimal:10',
        'entry_high' => 'decimal:10',
    ];

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
}
