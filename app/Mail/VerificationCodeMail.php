<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $user_name;
    public $code;
    public function __construct($user_name,$code)
    {
        $this->user_name=$user_name;
        $this->code=$code;
    }

    public function build()
    {
        return $this->subject("Your Verification Code")
                    ->view("verification_code")
                    ->with([
                        "user_name" => $this->user_name,
                        "code" => $this->code
                    ]);
    }
}
