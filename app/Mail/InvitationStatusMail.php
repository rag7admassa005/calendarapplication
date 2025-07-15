<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $appointment;
    public $status;

    public function __construct($user, $appointment, $status)
    {
        $this->user = $user;
        $this->appointment = $appointment;
        $this->status = $status;
    }

    public function build()
    {
        return $this->subject('تحديث على حالة دعوة الاجتماع')
            ->view('emails.invitation_status')
            ->with([
                'userName' => $this->user->first_name . ' ' . $this->user->last_name,
                'date'     => $this->appointment->preferred_date,
                'start'    => $this->appointment->preferred_start_time,
                'end'      => $this->appointment->preferred_end_time,
                'statusText' => $this->status === 'approved' ? 'قبول' : 'رفض',
            ]);
    }
}