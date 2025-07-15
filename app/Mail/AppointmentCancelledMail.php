<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\AppointmentRequest;

class AppointmentCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $appointment;
    public $reason;

    public function __construct(User $user, AppointmentRequest $appointment, $reason)
    {
        $this->user = $user;
        $this->appointment = $appointment;
        $this->reason = $reason;
    }

    public function build()
    {
        return $this->subject('إلغاء موعد من المستخدم')
            ->view('emails.appointment_cancelled')
            ->with([
                'managerName' => optional($this->appointment->manager)->first_name,
                'userName'    => $this->user->first_name . ' ' . $this->user->last_name,
                'date'        => $this->appointment->preferred_date,
                'start'       => $this->appointment->preferred_start_time,
                'end'         => $this->appointment->preferred_end_time,
                'reason'      => $this->reason,
            ]);
    }
}