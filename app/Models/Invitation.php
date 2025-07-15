<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invitation extends Model
{
    protected $fillable = [
        'invited_user_id',
        'related_to_type',
        'related_to_id',
        'invited_by_type',
        'invited_by_id',
        'status',
        'sent_at',
        'responded_at',
    ];

    public function relatedTo(): MorphTo
    {
        return $this->morphTo();
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