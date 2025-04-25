<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Queue;
class Staff extends Model
{
    protected $fillable = ['user_id','role_id'];

    protected $with = ['user','role'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function queues()
    {
        return $this->hasMany(Queue::class);
    }
}

