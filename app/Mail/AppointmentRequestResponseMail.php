<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentRequestResponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public $participant;
    public $status;

    public function __construct($participant, $status)
    {
        $this->participant = $participant;
        $this->status = $status;
    }

    public function build()
    {
        $subject = $this->status === 'accepted' ? 
            'Your Appointment Request Has Been Accepted' : 
            'Your Appointment Request Has Been Rejected';

        return $this->subject($subject)
                    ->view('emails.appointments_request_response')
                    ->with([
                        'participantName' => $this->participant->user->name,
                        'status' => $this->status,
                        'appointmentRequest' => $this->participant->appointmentRequest,
                    ]);
    }
}
