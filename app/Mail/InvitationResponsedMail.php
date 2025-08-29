<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationResponsedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $userName;
    public $response;

    public function __construct($user, $invitation)
    {
        $this->userName = $user->name;
        $this->response = $invitation->status;
    }

    public function build()
    {
        return $this->subject('Invitation Response')
                    ->view('emails.invitation-response')
                    ->with([
                        'userName' => $this->userName,
                        'response' => $this->response,
                    ]);
    }
}
