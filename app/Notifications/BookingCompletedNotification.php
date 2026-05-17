<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCompletedNotification extends Notification implements ShouldQueue
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

        return [
            'type'         => 'booking_completed',
            'title'        => '課程已完成，歡迎留下評價',
            'body'         => "你的《{$offer->title}》課程已完成，歡迎分享你的學習心得！",
            'action_url'   => config('app.frontend_url') . '/courses/' . $offer->id,
            'related_id'   => $this->booking->id,
            'related_type' => 'booking',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $offer = $this->booking->schedule->divingOffer;
        $url   = config('app.frontend_url') . '/courses/' . $offer->id;

        return (new MailMessage)
            ->subject('課程完成，歡迎留下評價 — CFDivePlatform')
            ->greeting('恭喜完成課程！')
            ->line("你的《{$offer->title}》課程已完成。")
            ->action('前往評價', $url)
            ->line('你的評價對其他學員非常有幫助，感謝你的分享！')
            ->salutation('CFDivePlatform 團隊');
    }
}
