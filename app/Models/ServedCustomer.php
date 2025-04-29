<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Queue;
use App\Models\User;

class ServedCustomer extends Model
{
    protected $fillable = ['queue_id', 'user_id', 'waiting_time'];

    public function queue()
    {
        return $this->belongsTo(Queue::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
}
