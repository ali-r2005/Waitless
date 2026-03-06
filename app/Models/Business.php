<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Queue;

class Business extends Model
{
    protected $fillable = [
        'name',
        'industry',
        'logo',
        'status',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function queues()
    {
        return $this->hasMany(Queue::class);
    }
}
