<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantPermission extends Model
{
     protected $table = 'assistant_permission';
    protected $fillable = ['assistant_id', 'permission_id', 'manager_id'];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }
}
