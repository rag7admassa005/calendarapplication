<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentRequest extends Model
{
     use HasFactory;

    protected $fillable = [
        'user_id', 'manager_id', 'preferred_date', 'preferred_start_time',
        'preferred_end_time', 'reason', 'status', 'requested_at', 'reviewed_by'
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function manager() { return $this->belongsTo(User::class, 'manager_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
