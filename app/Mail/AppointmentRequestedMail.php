<?php

namespace App\Mail;

use App\Models\User;
use App\Models\AppointmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $appointment;

    public function __construct(User $user, AppointmentRequest $appointment)
    {
        $this->user = $user;
        $this->appointment = $appointment;
    }

    public function build()
    {
        return $this->subject('New Appointment Request from ' . $this->user->first_name)
                    ->view('emails.appointment_requested');
    }
}