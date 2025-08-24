<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;


class Assistant extends Authenticatable implements JWTSubject
{
    
       use Notifiable;
      protected $fillable = ['user_id', 'manager_id'];


      // App\Models\Assistant.php
public function user()
{
    return $this->belongsTo(User::class);
}


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
   

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'assistant_permission')->withPivot('manager_id')->withTimestamps();
    }

    public function mypermissions(): BelongsToMany
{
    return $this->belongsToMany(Permission::class, 'assistant_permission')
                ->withTimestamps();
}

    public function activities(): HasMany
    {
        return $this->hasMany(AssistantActivity::class);
    }

    public function sentInvitations(): MorphMany
    {
        return $this->morphMany(Invitation::class, 'invited_by');
    }
}
