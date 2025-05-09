<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Queue;

class Branch extends Model
{
    protected $fillable = ['business_id','name','address','parent_id'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    
    public function parent()
    {
        return $this->belongsTo(Branch::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Branch::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    public function queues()
    {
        return $this->hasMany(Queue::class);
    }
}
