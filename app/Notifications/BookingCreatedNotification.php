<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $offer = $this->booking->schedule->divingOffer;
        $date  = $this->booking->schedule->scheduled_date->toDateString();

        return [
            'type'         => 'booking_created',
            'title'        => '你有新的預約申請',
            'body'         => "學員申請了《{$offer->title}》的預約（時段：{$date}）",
            'action_url'   => config('app.frontend_url') . '/coach/bookings',
            'related_id'   => $this->booking->id,
            'related_type' => 'booking',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $offer = $this->booking->schedule->divingOffer;
        $date  = $this->booking->schedule->scheduled_date->toDateString();
        $url   = config('app.frontend_url') . '/coach/bookings';

        return (new MailMessage)
            ->subject('你有新的預約申請 — CFDivePlatform')
            ->greeting('你好！')
            ->line("學員申請了《{$offer->title}》的預約（時段：{$date}）。")
            ->action('查看預約', $url)
            ->line('請盡快確認或拒絕此預約申請。')
            ->salutation('CFDivePlatform 團隊');
    }
}
