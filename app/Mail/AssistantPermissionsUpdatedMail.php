<?php 
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssistantPermissionsUpdatedMail extends Mailable
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
    return $this->subject('Your Permissions have been updated')
                ->view('emails.assistant_permissions_updated')
                ->with([
                    'assistant' => $this->assistant,
                    'permissions' => $this->permissions,
                ]);
}

}
