<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\QueueUser;
use App\Models\ServedCustomer;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Queue extends Model
{
    use HasFactory;
    protected $fillable = [
        'business_id',
        'user_id',
        'name',
        'scheduled_date',
        'is_active',
        'is_paused',
        'start_time',
        'preferences'
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function servedCustomers()
    {
        return $this->hasMany(ServedCustomer::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->using(QueueUser::class)
            ->withPivot('status', 'ticket_number', 'served_at', 'start_serving_at', 'late_at', 'position');
    }
    
}