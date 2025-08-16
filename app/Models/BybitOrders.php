<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BybitOrders extends Model
{
    use HasFactory;
    protected $table = "bybit_orders";
    protected $guarded = [];
    protected $fillable = [
        'order_id','symbol','entry_price','tp','sl','steps',
        'expire_minutes','status','closed_at','side','amount','entry_low','entry_high'
    ];
}
