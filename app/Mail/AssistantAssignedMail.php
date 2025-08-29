<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssistantAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $manager;

    public function __construct($user, $manager)
    {
        $this->user = $user;
        $this->manager = $manager;
    }

    public function build()
    {
        return $this->subject('You have been assigned as an assistant')
            ->view('emails.assistant_assigned');
    }
}
