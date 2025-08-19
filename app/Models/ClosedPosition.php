<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClosedPosition extends Model
{
    use HasFactory;

    protected $table = 'closed_positions';

    protected $fillable = [
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
    ];
}
