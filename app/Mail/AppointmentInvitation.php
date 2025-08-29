<?php

namespace App\Mail;

use App\Models\Invitation;
use App\Models\Manager;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;
    public $manager;

    /**
     * Create a new message instance.
     */
    public function __construct(Invitation $invitation, Manager $manager)
    {
        $this->invitation = $invitation;
        $this->manager = $manager;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('You Have Been Invited to an Appointment')
                    ->view('emails.appointments_invitation')
                    ->with([
                        'invitation' => $this->invitation,
                        'manager'    => $this->manager,
                    ]);
    }
}
