<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentNote extends Model
{
     use HasFactory;

    protected $fillable = ['appointment_id', 'author_id', 'notes'];

    public function appointment() { return $this->belongsTo(Appointment::class); }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
}