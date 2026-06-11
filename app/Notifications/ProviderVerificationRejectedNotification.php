<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProviderVerificationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'verification_rejected',
            'title'        => '審核未通過',
            'body'         => "你的教練資格審核未通過。原因：{$this->reason}",
            'action_url'   => config('app.frontend_url') . '/coach/verification',
            'related_id'   => $notifiable->id,
            'related_type' => 'provider_verification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('教練資格審核結果 — CFDivePlatform')
            ->greeting('審核結果通知')
            ->line('很抱歉，你的教練資格審核未通過。')
            ->line("原因：{$this->reason}")
            ->line('你可以補正資料與證照後重新送審。')
            ->action('重新送審', config('app.frontend_url') . '/coach/verification');
    }
}
