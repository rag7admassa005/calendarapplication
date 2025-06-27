<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssistantPermission extends Model
{
      use HasFactory;

    protected $table = 'assistant_permission';
    protected $fillable = ['assistant_id', 'permission_id', 'manager_id'];
}