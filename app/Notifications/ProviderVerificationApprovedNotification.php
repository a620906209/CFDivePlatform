<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProviderVerificationApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'verification_approved',
            'title'        => '審核通過',
            'body'         => '恭喜！你的教練資格已通過平台審核，課程現在會公開曝光並可接受預約。',
            'action_url'   => config('app.frontend_url') . '/coach/dashboard',
            'related_id'   => $notifiable->id,
            'related_type' => 'provider_verification',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('教練資格審核通過 — CFDivePlatform')
            ->greeting('恭喜！')
            ->line('你的教練資格已通過平台審核。')
            ->line('你的課程現在會公開曝光，並可開始接受會員預約。')
            ->action('前往教練後台', config('app.frontend_url') . '/coach/dashboard');
    }
}
