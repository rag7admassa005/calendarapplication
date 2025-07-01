<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

use Illuminate\Foundation\Auth\User as Authenticatable;
class Manager extends  Authenticatable implements JWTSubject
{
      public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    protected $fillable = ['email', 'password', 'department','email_verified_at','verification_code','code_expires_at','must_change_password'];

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function appointmentRequests(): HasMany
    {
        return $this->hasMany(AppointmentRequest::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function sentInvitations(): MorphMany
    {
        return $this->morphMany(Invitation::class, 'invited_by');
    }

    public function assistantPermissions(): HasMany
    {
        return $this->hasMany(AssistantPermission::class);
    }
public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
public function assistants(): HasMany
    {
        return $this->hasMany(Assistant::class);
    }

    protected static function boot()
{
    parent::boot();

    static::deleting(function ($manager) {
        // هنا نفترض أن المدير الأول هو الذي رقم الـ id تبعه 1
        if ($manager->id === 1) {
            // منع الحذف
            return false;
        }
    });
}


}
