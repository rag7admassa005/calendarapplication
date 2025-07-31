<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'email_verified_at',
        'verification_code',
        'code_expires_at',
        'phone_number',
        'image',
        'address',
        'date_of_birth',
        'manager_id',
        'section_id',
        'job_id'
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

      public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function appointmentRequests(): HasMany
    {
        return $this->hasMany(AppointmentRequest::class);
    }

    public function appointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_user')->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_user_id');
    }

    public function assistant(): HasOne
    {
        return $this->hasOne(Assistant::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            // منع حذف المدير الأول (id = 1)
            if ($user->id === 1) {
                return false;
            }
        });
    }
}