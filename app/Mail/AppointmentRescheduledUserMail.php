<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentRescheduledUserMail extends Mailable
{    use Queueable, SerializesModels;

    public $appointment;
    public $rescheduledBy;

    /**
     * Create a new message instance.
     */
    public function __construct($rescheduledBy, $appointment)
    {
        $this->rescheduledBy = $rescheduledBy;
        $this->appointment = $appointment;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Appointment Rescheduled')
                    ->view('emails.appointments_rescheduled_user')
                    ->with([
                        'appointment' => $this->appointment,
                        'rescheduledBy' => $this->rescheduledBy,
                    ]);
    }
}
