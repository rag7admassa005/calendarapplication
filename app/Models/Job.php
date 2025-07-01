<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    protected $fillable = ['manager_id', 'title', 'description'];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

       protected static function boot()
{
    parent::boot();

    static::deleting(function ($job) {
        // هنا نفترض أن المدير الأول هو الذي رقم الـ id تبعه 1
        if ($job->id === 1) {
            // منع الحذف
            return false;
        }
    });
}
}
