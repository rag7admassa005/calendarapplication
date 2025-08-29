<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentRescheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;
    public $rescheduledBy;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment, $rescheduledBy)
    {
        $this->appointment = $appointment;
        $this->rescheduledBy = $rescheduledBy;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Appointment Has Been Rescheduled')
                    ->view('emails.appointment_rescheduled')
                    ->with([
                        'appointment' => $this->appointment,
                        'rescheduledBy' => $this->rescheduledBy,
                    ]);
    }
}
