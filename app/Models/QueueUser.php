<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class QueueUser extends Pivot
{
    protected $table = 'queue_user';

    protected $fillable = [
        'queue_id',
        'user_id',
        'status',
        'joined_at',
        'notified_at',
        'served_at'
    ];
    
}
