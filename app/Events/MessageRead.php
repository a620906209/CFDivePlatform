<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $bookingId,
        public string $readerType,
        public int $lastReadMessageId,
    ) {}

    public function broadcastAs(): string
    {
        return 'MessageRead';
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('booking.' . $this->bookingId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'reader_type'           => $this->readerType,
            'last_read_message_id'  => $this->lastReadMessageId,
        ];
    }
}
