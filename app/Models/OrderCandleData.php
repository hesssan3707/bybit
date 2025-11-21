<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderCandleData extends Model
{
    use HasFactory;

    protected $table = 'order_candle_data';

    protected $fillable = [
        'order_id', 'exchange', 'symbol',
        'entry_price', 'exit_price', 'entry_time', 'exit_time',
        'candles_m1', 'candles_m5', 'candles_m15', 'candles_h1', 'candles_h4',
    ];

    protected $casts = [
        'entry_price' => 'decimal:8',
        'exit_price' => 'decimal:8',
        'entry_time' => 'datetime',
        'exit_time' => 'datetime',
        'candles_m1' => 'array',
        'candles_m5' => 'array',
        'candles_m15' => 'array',
        'candles_h1' => 'array',
        'candles_h4' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}