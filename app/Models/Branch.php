<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['business_id','name','address'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
    public function staff()
    {
        return $this->hasMany(Staff::class);
    }
}
