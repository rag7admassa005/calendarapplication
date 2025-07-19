<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AppointmentInvitation extends Notification
{
    protected $appointment;
    protected $manager;

    public function __construct($appointment, $manager)
    {
        $this->appointment = $appointment;
        $this->manager = $manager;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('دعوة لحضور موعد')
            ->greeting('مرحباً ' . $notifiable->first_name)
            ->line('تمت دعوتك لحضور موعد من قبل المدير: ' . $this->manager->first_name)
            ->line('تاريخ ووقت الموعد: ' . $this->appointment->date . ' ' . $this->appointment->time)
            ->action('عرض الدعوة', url('/invitations')) // غير الرابط حسب واجهتك
            ->line('يرجى الرد على الدعوة من خلال النظام.');
    }
}
