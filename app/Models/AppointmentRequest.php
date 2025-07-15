<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppointmentRequest extends Model
{
  protected $fillable = [
    'user_id', 'manager_id', 'preferred_date', 'preferred_start_time',
    'preferred_end_time', 'preferred_duration', 'reason', 'status', 'requested_at',
    'reviewed_by_type', 'reviewed_by_id',  // <- أضفهم هنا
];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    public function reviewedBy(): MorphTo
    {
        return $this->morphTo();
    }
    public function invitations(): MorphMany
{
    return $this->morphMany(Invitation::class, 'related_to');
}

}
