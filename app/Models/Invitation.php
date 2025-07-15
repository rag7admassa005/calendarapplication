<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invitation extends Model
{
      protected $fillable = [
        'appointment_id', 'invited_user_id', 'invited_by_id', 'invited_by_type',
        'status', 'sent_at', 'responded_at'
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class,'appointment_id');
    }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

    public function invitedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
