<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\QueueUser;
use App\Models\LatecomerQueue;
use App\Models\ServedCustomer;

class Queue extends Model
{
    protected $fillable = [
        'branch_id',
        'staff_id',
        'name',
        'scheduled_date',
        'is_active',
        'auto_activate',
        'is_paused',
        'start_time',
        'preferences'
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function servedCustomers()
    {
        return $this->hasMany(ServedCustomer::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(QueueUser::class)
            ->withPivot('status', 'ticket_number', 'served_at', 'late_at', 'position');
    }

    public function latecomerQueue()
    {
        return $this->hasOne(LatecomerQueue::class);
    }

    
}