<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Appointment extends Model
{
     protected $fillable = [
        'title', 'description', 'date', 'start_time', 'end_time', 'duration', 'status', 'manager_id', 'assistant_id'
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'appointment_user')->withTimestamps();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(AppointmentNote::class);
    }

   public function invitations(): MorphMany
{
    return $this->morphMany(Invitation::class, 'related_to');
}

public function assistantActivities()
    {
        return $this->morphMany(AssistantActivity::class, 'relatedTo');
    }

// المراجِع (مدير أو مساعد) - morph
    public function reviewedBy()
    {
        return $this->morphTo();
    }

}