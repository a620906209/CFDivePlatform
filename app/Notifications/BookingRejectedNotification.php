<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingRejectedNotification extends Notification implements ShouldQueue
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
            'type'         => 'booking_rejected',
            'title'        => '預約申請未通過',
            'body'         => "你的《{$offer->title}》預約申請（時段：{$date}）未通過，請選擇其他時段",
            'action_url'   => config('app.frontend_url') . '/my-bookings',
            'related_id'   => $this->booking->id,
            'related_type' => 'booking',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $offer = $this->booking->schedule->divingOffer;
        $date  = $this->booking->schedule->scheduled_date->toDateString();
        $url   = config('app.frontend_url') . '/courses';

        return (new MailMessage)
            ->subject('你的預約申請未通過 — CFDivePlatform')
            ->greeting('通知')
            ->line("很抱歉，你的《{$offer->title}》預約申請（時段：{$date}）未通過審核。")
            ->action('瀏覽其他課程', $url)
            ->line('如有疑問，請聯繫課程教練。')
            ->salutation('CFDivePlatform 團隊');
    }
}
