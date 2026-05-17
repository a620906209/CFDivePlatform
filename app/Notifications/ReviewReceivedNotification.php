<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReviewReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly Review $review) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $offer = $this->review->divingOffer;

        return [
            'type'         => 'review_received',
            'title'        => '你收到了一則新評價',
            'body'         => "《{$offer->title}》收到了 {$this->review->rating} 星評價",
            'action_url'   => config('app.frontend_url') . '/coach/reviews',
            'related_id'   => $this->review->id,
            'related_type' => 'review',
        ];
    }
}
