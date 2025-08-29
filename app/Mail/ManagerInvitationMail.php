<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

   public $manager;

    public function __construct($manager)
    {
        $this->manager = $manager;
    }

  public function build()
{
    return $this->subject('You have been given an administrator account')
                ->view('emails.manager_invitationn')
                ->with([
                    'name' => $this->manager->first_name,
                    'code' => $this->manager->verification_code,
                ]);
}
}
