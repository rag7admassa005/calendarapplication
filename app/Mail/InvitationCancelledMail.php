<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
class InvitationCancelledMail extends Mailable
{
    public $user;
    public $invitation;
    public $appointment;

    public function __construct($user, $invitation, $appointment)
    {
        $this->user = $user;
        $this->invitation = $invitation;
        $this->appointment = $appointment;
    }

    public function build()
    {
        return $this->subject('تم إلغاء الاستجابة للدعوة')
                    ->view('emails.invitation-cancelled')
                    ->with([
                        'userName' => $this->user->name,
                        'appointmentTitle' => $this->appointment->title
                    ]);
    }
}