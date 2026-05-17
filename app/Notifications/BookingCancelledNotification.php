<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $cancelledBy,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $offer = $this->booking->schedule->divingOffer;
        $date  = $this->booking->schedule->scheduled_date->toDateString();

        if ($this->cancelledBy === 'member') {
            return [
                'type'         => 'booking_cancelled',
                'title'        => '學員取消了預約',
                'body'         => "學員已取消《{$offer->title}》的預約（時段：{$date}）",
                'action_url'   => config('app.frontend_url') . '/coach/bookings',
                'related_id'   => $this->booking->id,
                'related_type' => 'booking',
            ];
        }

        return [
            'type'         => 'booking_cancelled',
            'title'        => '教練取消了你的預約',
            'body'         => "教練已取消你的《{$offer->title}》預約（時段：{$date}），如有疑問請聯繫教練",
            'action_url'   => config('app.frontend_url') . '/my-bookings',
            'related_id'   => $this->booking->id,
            'related_type' => 'booking',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $offer = $this->booking->schedule->divingOffer;
        $date  = $this->booking->schedule->scheduled_date->toDateString();

        if ($this->cancelledBy === 'member') {
            return (new MailMessage)
                ->subject('預約已取消 — CFDivePlatform')
                ->greeting('通知')
                ->line("學員已取消《{$offer->title}》的預約（時段：{$date}）。")
                ->action('查看所有預約', config('app.frontend_url') . '/coach/bookings')
                ->salutation('CFDivePlatform 團隊');
        }

        return (new MailMessage)
            ->subject('你的預約已取消 — CFDivePlatform')
            ->greeting('通知')
            ->line("教練已取消你的《{$offer->title}》預約（時段：{$date}）。如有疑問，請聯繫課程教練。")
            ->action('查看預約', config('app.frontend_url') . '/my-bookings')
            ->salutation('CFDivePlatform 團隊');
    }
}
