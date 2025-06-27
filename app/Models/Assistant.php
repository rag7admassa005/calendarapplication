<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assistant extends Model
{ use HasFactory;


  
    protected $fillable = ['user_id', 'manager_id', 'active'];

    public function user() { return $this->belongsTo(User::class); }
    public function manager() { return $this->belongsTo(User::class, 'manager_id'); }
    public function permissions() {
        return $this->belongsToMany(Permission::class, 'assistant_permission')
                    ->withPivot('manager_id')
                    ->withTimestamps();
    }
    public function activities() { return $this->hasMany(AssistantActivity::class); }
}
