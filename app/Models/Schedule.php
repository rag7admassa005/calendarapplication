<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
     protected $fillable = [
        'manager_id', 'day_of_week', 'start_time', 'end_time',
        'is_available', 'repeat_for_weeks', 'meeting_duration_1', 'meeting_duration_2'
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
}
}