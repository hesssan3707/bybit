<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    use HasFactory;

    protected $fillable = [
        'market',
        'timeframe',
        'price',
        'timestamp',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];
}
