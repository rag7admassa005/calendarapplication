<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistantActivity extends Model
{
     use HasFactory;

     protected $fillable = ['assistant_id', 'permission_id', 'appointment_id', 'executed_at'];

    public function assistant() { return $this->belongsTo(User::class, 'assistant_id'); }
    public function permission() { return $this->belongsTo(Permission::class); }
    public function appointment() { return $this->belongsTo(Appointment::class); }
}
