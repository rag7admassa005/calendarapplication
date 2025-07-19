<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AppointmentApprovedNotification extends Notification
{
    protected $appointment;

    public function __construct($appointment)
    {
        $this->appointment = $appointment;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تمت الموافقة على الموعد')
            ->greeting('مرحباً ' . $notifiable->first_name)
            ->line('تمت الموافقة على الموعد الذي دُعيت إليه.')
            ->line('التاريخ: ' . $this->appointment->date)
            ->line('الوقت: من ' . $this->appointment->start_time . ' إلى ' . $this->appointment->end_time)
            ->action('عرض تفاصيل الموعد', url('/appointments/' . $this->appointment->id))
            ->line('يرجى التأكد من الحضور في الوقت المحدد.');
    }
}