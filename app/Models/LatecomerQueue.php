<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Queue;
use App\Models\User;
use App\Models\LatecomerQueueUser;

class LatecomerQueue extends Model
{
    protected $table = 'latecomer_queues';
    protected $fillable = ['queue_id'];

    public function queue()
    {
        return $this->belongsTo(Queue::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->using(LatecomerQueueUser::class);
    }
}
