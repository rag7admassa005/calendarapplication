<?php

namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AppointmentRejected extends Notification
{
    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تم رفض طلب الموعد')
            ->line('نأسف، لقد تم رفض طلب الموعد من قبل المدير.');
    }
}
