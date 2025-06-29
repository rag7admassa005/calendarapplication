<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppointmentNote extends Model
{
       protected $fillable = ['appointment_id', 'notes'];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }
}
