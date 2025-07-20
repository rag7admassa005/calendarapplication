<?php
namespace App\Mail;

use App\Models\AppointmentRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GroupAppointmentRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $appointmentRequest;
    public $user;

    public function __construct(AppointmentRequest $appointmentRequest, User $user)
    {
        $this->appointmentRequest = $appointmentRequest;
        $this->user = $user;
    }
public function build()
{
    return $this->subject('طلب موعد جماعي جديد')
                ->view('emails.group_appointment_requested') 
                ->with([
                    'user' => $this->user,
                    'appointmentRequest' => $this->appointmentRequest,
                ]);
}

}
