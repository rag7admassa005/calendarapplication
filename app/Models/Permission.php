<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
      use HasFactory;
  protected $fillable = ['name', 'description'];

    public function assistants() {
        return $this->belongsToMany(User::class, 'assistant_permission', 'permission_id', 'assistant_id')
                    ->withPivot('manager_id')
                    ->withTimestamps();
    }
}
