<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssistantPermissionsAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $assistant;
    public $permissions;

    public function __construct($assistant, $permissions)
    {
        $this->assistant = $assistant;
        $this->permissions = $permissions;
    }

    public function build()
    {
        return $this->subject('You have been assigned as an assistant')
                    ->view('emails.assistant_permissions_assigned');
    }
}
