<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user_name;
    public $code;

    public function __construct($user_name, $code)
    {
        $this->user_name = $user_name;
        $this->code = $code;
    }

    public function build()
    {
        return $this->subject("Your Verification Code")
                    ->view("verification_codee")
                    ->with([
                        "user_name" => $this->user_name,
                        "code" => $this->code
                    ]);
    }
}
