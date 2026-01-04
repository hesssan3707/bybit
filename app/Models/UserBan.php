<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserBan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_demo',
        'trade_id',
        'ban_type',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'is_demo' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function scopeActive($query)
    {
        return $query->where('ends_at', '>', Carbon::now());
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
        return $query->where('is_demo', $isDemo);
    }
}
