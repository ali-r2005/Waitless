<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Queue;
use App\Models\Business;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\LatecomerQueue;
use App\Models\QueueUser;
use App\Models\LatecomerQueueUser;
use App\Models\ServedCustomer;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'branch_id',
        'business_id',
        'password',
        'role'  
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function staff(){

    return $this->hasOne(Staff::class);
    }

    public function queues()
    {
        return $this->belongsToMany(Queue::class)->using(QueueUser::class);
    }

    public function latecomerQueues()
    {
        return $this->belongsToMany(LatecomerQueue::class)->using(LatecomerQueueUser::class);
    }

    public function servedCustomers()
    {
        return $this->hasMany(ServedCustomer::class);
    }

}
