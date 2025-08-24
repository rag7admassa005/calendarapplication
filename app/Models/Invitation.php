<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invitation extends Model
{
    protected $fillable = [
        'invited_user_id',
        //'related_to_type',
        //'related_to_id',
        'invited_by_type',
        'invited_by_id',
        'title',
        'description',
        'date',
        'time',
        'duration',
        'status',
        'sent_at',
        
    ];

    // public function relatedTo(): MorphTo
    // {
    //     return $this->morphTo();
    // }

    public function invitedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_user_id');
    }

   public function inviter()
    {
        return $this->morphTo(null, 'invited_by_type', 'invited_by_id');
    }
}