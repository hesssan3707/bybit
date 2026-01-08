<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_demo',
        'name',
        'started_at',
        'ended_at',
        'is_default',
        'is_active',
        'metrics_all',
        'metrics_buy',
        'metrics_sell',
        'exchange_metrics',
    ];

    protected $casts = [
        'is_demo' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metrics_all' => 'array',
        'metrics_buy' => 'array',
        'metrics_sell' => 'array',
        'exchange_metrics' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, int $userId)
    {
        $user = User::find($userId);
        if ($user && $user->isInvestor()) {
            $userId = $user->parent_id;
        }
        return $query->where('user_id', $userId);
    }

    public function scopeAccountType($query, bool $isDemo)
    {
        if (auth()->check() && auth()->user()->isInvestor()) {
            return $query->where('is_demo', false);
        }
        return $query->where('is_demo', $isDemo);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function includesDate(Carbon $date): bool
    {
        if ($this->ended_at) {
            return $date->betweenIncluded($this->started_at, $this->ended_at);
        }
        return $date->gte($this->started_at);
    }
}
