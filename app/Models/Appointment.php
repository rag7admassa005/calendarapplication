<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
     use HasFactory;

    protected $fillable = [
        'date', 'start_time', 'end_time', 'duration', 'status', 'user_id', 'manager_id', 'assistant_id'
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function manager() { return $this->belongsTo(User::class, 'manager_id'); }
    public function assistant() { return $this->belongsTo(User::class, 'assistant_id'); }
    public function invitedUsers() { return $this->belongsToMany(User::class, 'appointment_user'); }
    public function notes() { return $this->hasMany(AppointmentNote::class); }
    public function assistantActivities() { return $this->hasMany(AssistantActivity::class); }
    public function invitations() { return $this->hasMany(Invitation::class); }
}
