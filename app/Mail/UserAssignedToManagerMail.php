<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Manager;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserAssignedToManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $manager;

    public function __construct(User $user, Manager $manager)
    {
        $this->user = $user;
        $this->manager = $manager;
    }

    public function build()
    {
        return $this->subject('You have been assigned to a manager')
                    ->view('emails.user_assigned');
    }
}
