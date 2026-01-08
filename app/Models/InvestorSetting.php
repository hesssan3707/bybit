<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestorSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'investment_limit',
        'is_trading_enabled',
        'allocation_percentage',
    ];

    protected $casts = [
        'is_trading_enabled' => 'boolean',
        'allocation_percentage' => 'integer',
        'investment_limit' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
