<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmedNotification extends Notification implements ShouldQueue
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
            'type'         => 'booking_confirmed',
            'title'        => '預約已確認',
            'body'         => "你的《{$offer->title}》課程預約已由教練確認（時段：{$date}）",
            'action_url'   => config('app.frontend_url') . '/my-bookings',
            'related_id'   => $this->booking->id,
            'related_type' => 'booking',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $offer = $this->booking->schedule->divingOffer;
        $date  = $this->booking->schedule->scheduled_date->toDateString();
        $url   = config('app.frontend_url') . '/my-bookings';

        return (new MailMessage)
            ->subject('你的預約已確認 — CFDivePlatform')
            ->greeting('好消息！')
            ->line("你的《{$offer->title}》課程預約已由教練確認（時段：{$date}）。")
            ->action('查看預約', $url)
            ->line('請準時出席，如需取消請至少提前 24 小時操作。')
            ->salutation('CFDivePlatform 團隊');
    }
}
