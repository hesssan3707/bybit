<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SpotOrder extends Model
{
    use HasFactory;

    protected $table = 'spot_orders';

    protected $fillable = [
        'user_exchange_id',
        'is_demo',
        'order_id',
        'order_link_id',
        'symbol',
        'base_coin',
        'quote_coin',
        'side',
        'order_type',
        'qty',
        'price',
        'executed_qty',
        'executed_price',
        'time_in_force',
        'status',
        'reject_reason',
        'commission',
        'commission_asset',
        'order_created_at',
        'order_updated_at',
        'filled_at',
        'cancelled_at',
        'raw_response',
    ];

    protected $casts = [
        'qty' => 'decimal:10',
        'price' => 'decimal:10',
        'executed_qty' => 'decimal:10',
        'executed_price' => 'decimal:10',
        'commission' => 'decimal:10',
        'order_created_at' => 'datetime',
        'order_updated_at' => 'datetime',
        'filled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'raw_response' => 'array',
    ];

    /**
     * Get the user exchange that owns the spot order
     */
    public function userExchange()
    {
        return $this->belongsTo(UserExchange::class);
    }

    /**
     * Get the user that owns the spot order (through user exchange)
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
        $user = User::find($userId);
        if ($user && $user->isInvestor()) {
            $userId = $user->parent_id;
        }

        return $query->whereHas('userExchange', function($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Get orders by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get orders by symbol
     */
    public function scopeBySymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    /**
     * Get orders by side (Buy/Sell)
     */
    public function scopeBySide($query, $side)
    {
        return $query->where('side', $side);
    }

    /**
     * Get recent orders (last 30 days)
     */
    public function scopeRecent($query)
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays(30));
    }

    /**
     * Get filled orders
     */
    public function scopeFilled($query)
    {
        return $query->where('status', 'Filled');
    }

    /**
     * Get pending orders (New, PartiallyFilled)
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['New', 'PartiallyFilled']);
    }

    /**
     * Check if order is filled
     */
    public function isFilled()
    {
        return $this->status === 'Filled';
    }

    /**
     * Check if order is pending
     */
    public function isPending()
    {
        return in_array($this->status, ['New', 'PartiallyFilled']);
    }

    /**
     * Check if order is cancelled
     */
    public function isCancelled()
    {
        return in_array($this->status, ['Cancelled', 'PartiallyFilledCanceled']);
    }

    /**
     * Get the total value of the order
     */
    public function getTotalValueAttribute()
    {
        if ($this->order_type === 'Market') {
            return $this->executed_qty * $this->executed_price;
        }
        return $this->qty * $this->price;
    }

    /**
     * Get the executed value
     */
    public function getExecutedValueAttribute()
    {
        return $this->executed_qty * $this->executed_price;
    }

    /**
     * Get profit/loss for filled orders
     */
    public function getProfitLossAttribute()
    {
        if (!$this->isFilled()) {
            return 0;
        }

        // This is a simple calculation - in reality you'd need to track buy/sell pairs
        // and consider fees for accurate P&L calculation
        return $this->executed_value - $this->commission;
    }

    /**
     * Format the order status for display
     */
    public function getStatusDisplayAttribute()
    {
        return match($this->status) {
            'New' => 'Pending',
            'PartiallyFilled' => 'Partially Filled',
            'Filled' => 'Filled',
            'Cancelled' => 'Cancelled',
            'Rejected' => 'Rejected',
            'PartiallyFilledCanceled' => 'Partially Filled & Cancelled',
            default => $this->status
        };
    }

    /**
     * Get the status color for UI display
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'New', 'PartiallyFilled' => 'warning',
            'Filled' => 'success',
            'Cancelled', 'PartiallyFilledCanceled' => 'secondary',
            'Rejected' => 'danger',
            default => 'info'
        };
    }

    /**
     * Get the side color for UI display
     */
    public function getSideColorAttribute()
    {
        return $this->side === 'Buy' ? 'success' : 'danger';
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
        if (auth()->check() && auth()->user()->isInvestor()) {
            return $query->where('is_demo', false);
        }
        return $query->where('is_demo', $isDemo);
    }
}
