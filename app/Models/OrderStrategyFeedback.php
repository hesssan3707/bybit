<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStrategyFeedback extends Model
{
    protected $table = 'order_strategy_feedbacks';
    protected $fillable = [
        'order_id',
        'user_id',
        'answer',
    ];
}
