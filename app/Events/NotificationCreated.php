<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public readonly int $userId) {}

    public function broadcastOn(): array
    {
        // 使用 Laravel 內建的 User private channel（channels.php 已有 auth）
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    // 不需要 payload，前端收到後直接呼叫 fetchUnreadCount()
    public function broadcastWith(): array
    {
        return [];
    }
}
