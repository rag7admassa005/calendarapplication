<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantActivity extends Model
{
    protected $table='assistant_activity';
    protected $fillable = ['assistant_id', 'permission_id', 'related_to_type',
        'related_to_id',
        'executed_at',];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

  
    public function relatedTo()
    {
        return $this->morphTo();
    }



}
