<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AppointmentRequestUsersMail extends Mailable
{

    use Queueable, SerializesModels;

    public $appointmentRequest;
    public $participantName;

    public function __construct($appointmentRequest, $participantName)
    {
        $this->appointmentRequest = $appointmentRequest;
        $this->participantName = $participantName;
    }

    public function build()
    {
        return $this->subject('You have been sent a request to an appointment')
                    ->view('emails.appointments_requests')
                    ->with([
                        'participantName' => $this->participantName,
                        'appointment' => $this->appointmentRequest
                    ]);
    }
}
