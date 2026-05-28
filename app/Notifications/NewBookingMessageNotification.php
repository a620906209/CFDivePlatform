<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\User;
use Illuminate\Notifications\Notification;

// 不走 Queue：通知需要在 HTTP response 前同步寫進 DB，
// 讓後續的 NotificationCreated broadcast 能立刻讓前端拉到正確的 unread count。
class NewBookingMessageNotification extends Notification
{
    public function __construct(
        public readonly BookingMessage $message,
        public readonly Booking $booking,
        public readonly User $sender,
    ) {}

    public function via(object $notifiable): array
    {
        // 聊天訊息通知只寫進 DB，不寄信（太頻繁）
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        // 顯示名稱：教練加前綴，學員直接用名字
        $senderLabel = $this->sender->role === 'provider'
            ? "教練 {$this->sender->name}"
            : $this->sender->name;

        $preview = $this->message->type === 'image'
            ? '傳送了一張圖片'
            : mb_strimwidth($this->message->content, 0, 50, '…');

        // 依收件方角色決定跳轉路徑
        $actionUrl = $notifiable->role === 'provider'
            ? config('app.frontend_url') . '/coach/bookings'
            : config('app.frontend_url') . '/my-bookings';

        return [
            'type'         => 'new_message',
            'title'        => "{$senderLabel} 傳來新訊息",
            'body'         => $preview,
            'action_url'   => $actionUrl,
            'related_id'   => $this->booking->id,
            'related_type' => 'booking',
        ];
    }
}
