<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentRequestParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_request_id',
        'user_id',
        'status',
    ];

    public function appointmentRequest()
    {
        return $this->belongsTo(AppointmentRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
