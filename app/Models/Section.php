<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable=['name'];
        // العلاقات

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function managers()
    {
        return $this->hasMany(Manager::class);
    }

    public function jobs()
    {
        return $this->hasMany(Job::class);
    }
}
