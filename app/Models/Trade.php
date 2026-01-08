<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trade extends Model
{
    use HasFactory;

    protected $table = 'trades';

    protected $fillable = [
        'user_exchange_id',
        'is_demo',
        'symbol',
        'side',
        'order_type',
        'leverage',
        'qty',
        'avg_entry_price',
        'avg_exit_price',
        'pnl',
        'order_id',
        'closed_at',
        'synchronized',
    ];

    protected $casts = [
        'qty' => 'decimal:10',
        'avg_entry_price' => 'decimal:10',
        'avg_exit_price' => 'decimal:10',
        'pnl' => 'decimal:10',
        'closed_at' => 'datetime',
        'synchronized' => 'integer',
    ];

    /**
     * Get the user exchange that owns the trade
     */
    public function userExchange()
    {
        return $this->belongsTo(UserExchange::class);
    }

    /**
     * Get the user that owns the trade (through user exchange)
     */
    public function user()
    {
        return $this->hasOneThrough(User::class, UserExchange::class, 'id', 'id', 'user_exchange_id', 'user_id');
    }

    /**
     * Get the order associated with this trade
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'order_id');
    }

    /**
     * Get the bans associated with this trade
     */
    public function bans()
    {
        return $this->hasMany(UserBan::class, 'trade_id');
    }

    /**
     * Scope a query to only include trades for a specific user exchange
     */
    public function scopeForUserExchange($query, $userExchangeId)
    {
        return $query->where('user_exchange_id', $userExchangeId);
    }

    /**
     * Scope a query to only include trades for a specific user
     */
    public function scopeForUser($query, $userId)
    {
        $user = User::find($userId);
        if ($user && $user->isInvestor()) {
            $userId = $user->parent_id;
        }

        return $query->whereHas('userExchange', function($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Scope a query to only include profitable trades
     */
    public function scopeProfitable($query)
    {
        return $query->where('pnl', '>', 0);
    }

    /**
     * Scope a query to only include losing trades
     */
    public function scopeLosing($query)
    {
        return $query->where('pnl', '<', 0);
    }

    /**
     * Scope a query to only include recent trades
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope a query to only include demo account trades
     */
    public function scopeDemo($query)
    {
        return $query->where('is_demo', true);
    }

    /**
     * Scope a query to only include real account trades
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
        if (auth()->check() && auth()->user()->isInvestor()) {
            return $query->where('is_demo', false);
        }
        return $query->where('is_demo', $isDemo);
    }
}
