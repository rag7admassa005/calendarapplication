<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Assistant extends Model
{
      protected $fillable = ['user_id', 'manager_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'assistant_permission')->withPivot('manager_id')->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AssistantActivity::class);
    }

    public function sentInvitations(): MorphMany
    {
        return $this->morphMany(Invitation::class, 'invited_by');
    }
}
