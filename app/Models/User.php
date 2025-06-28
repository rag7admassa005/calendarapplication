<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
      protected $fillable = [
        'first_name', 'last_name', 'role', 'email', 'password', 'email_verified_at',
        'verification_code', 'code_expires_at', 'phone_number', 'image', 'address',
        'date_of_birth', 'job_id', 'manager_id',
    ];

    public function job() { return $this->belongsTo(Job::class); }
    public function manager() { return $this->belongsTo(User::class, 'manager_id'); }
    public function managedAssistants() { return $this->hasMany(Assistant::class, 'manager_id'); }
    public function assistant() { return $this->hasOne(Assistant::class); }
    public function appointments() { return $this->hasMany(Appointment::class); }
    public function appointmentRequests() { return $this->hasMany(AppointmentRequest::class); }
    public function reviewedRequests() { return $this->hasMany(AppointmentRequest::class, 'reviewed_by'); }
    public function invitedAppointments() { return $this->belongsToMany(Appointment::class, 'appointment_user'); }
    public function permissions() {
        return $this->belongsToMany(Permission::class, 'assistant_permission', 'assistant_id', 'permission_id')
                    ->withPivot('manager_id')
                    ->withTimestamps();
    }
    public function sentInvitations() { return $this->hasMany(Invitation::class, 'inviter_id'); }
    public function receivedInvitations() { return $this->hasMany(Invitation::class, 'invitee_id'); }
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
    
}
