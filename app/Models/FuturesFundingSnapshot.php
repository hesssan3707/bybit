<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuturesFundingSnapshot extends Model
{
    use HasFactory;

    protected $table = 'futures_funding_snapshots';

    protected $fillable = [
        'exchange',
        'symbol',
        'funding_rate',
        'open_interest',
        'total_market_value',
        'metric_time',
    ];
}

