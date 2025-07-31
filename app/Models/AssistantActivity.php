<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantActivity extends Model
{
    protected $table='assistant_activity';
    protected $fillable = ['assistant_id', 'permission_id', 'appointment_request_id', 'executed_at'];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(Assistant::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

   public function appointmentRequest()
{
    return $this->belongsTo(AppointmentRequest::class, 'appointment_request_id');
}

}
