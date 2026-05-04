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

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute()
    {
        if (!$this->logo) {
            return asset('images/default_logo.png');
        }
        
        // If it's the default logo path or doesn't start with images/ (legacy), handle accordingy
        if ($this->logo === 'images/default_logo.png') {
            return asset($this->logo);
        }

        return asset('storage/' . $this->logo);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function queues()
    {
        return $this->hasMany(Queue::class);
    }
}
