<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
     protected $fillable = ['name', 'description'];

    public function assistants(): BelongsToMany
    {
        return $this->belongsToMany(Assistant::class, 'assistant_permission')->withPivot('manager_id')->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AssistantActivity::class);
    }
}
