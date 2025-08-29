<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;
    public $rejectedBy;

    public function __construct($appointment, $rejectedBy)
    {
        $this->appointment = $appointment;
        $this->rejectedBy = $rejectedBy; // Manager or Assistant
    }

    public function build()
    {
        return $this->subject('Appointment Cancelled')
                    ->markdown('emails.appointment_cancelled');
    }
}
