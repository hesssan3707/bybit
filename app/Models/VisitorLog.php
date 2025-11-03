<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorLog extends Model
{
    protected $table = 'visitor_logs';

    protected $fillable = [
        'ip',
        'user_id',
        'event_type',
        'route',
        'referrer',
        'user_agent',
        'occurred_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}