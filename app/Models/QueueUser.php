<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class QueueUser extends Pivot
{
    protected $table = 'queue_user';

    public $incrementing = true;
    protected $primaryKey = 'id';

    protected $fillable = [
        'queue_id',
        'user_id',
        'status',
        'ticket_number',
        'served_at',
        'late_at',
        'position',
        'estimated_waiting_time',
    ];

    public function queue()
    {
        return $this->belongsTo(Queue::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
