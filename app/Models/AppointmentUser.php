<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentUser extends Model
{
    use HasFactory;

    protected $table = 'appointment_user';
    protected $fillable = ['appointment_id', 'user_id'];
}
