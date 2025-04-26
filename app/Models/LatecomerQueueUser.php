<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class LatecomerQueueUser extends Pivot
{
    protected $table = 'latecomer_queue_user';

    protected $fillable = [
        'latecomer_queue_id',
        'user_id'
    ];
} 