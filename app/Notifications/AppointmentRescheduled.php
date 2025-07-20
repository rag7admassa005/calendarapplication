<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AppointmentRescheduled extends Notification
{
    public $appointment;

    public function __construct($appointment)
    {
        $this->appointment = $appointment;
    }

    public function via($notifiable)
    {
        return ['mail']; // أو ['database', 'mail'] حسب الحاجة
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('تمت إعادة جدولة الموعد')
            ->line('لقد تمت إعادة جدولة الموعد.')
            ->line('التاريخ الجديد: ' . $this->appointment->date)
            ->line('من الساعة: ' . $this->appointment->start_time . ' إلى ' . $this->appointment->end_time);
    }
}
