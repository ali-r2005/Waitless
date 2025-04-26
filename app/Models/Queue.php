<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\QueueUser;
use App\Models\LatecomerQueue;

class Queue extends Model
{
    protected $fillable = [
        'branch_id',
        'staff_id',
        'name',
        'scheduled_date',
        'is_active',
        'start_time',
        'preferences'
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->using(QueueUser::class);
    }

    public function latecomerQueues()
    {
        return $this->hasOne(LatecomerQueue::class);
    }

    
}