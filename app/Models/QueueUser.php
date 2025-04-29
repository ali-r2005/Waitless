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
        'ticket_number',
        'served_at',
        'late_at',
        'position'
    ];
    
}
