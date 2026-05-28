<?php

namespace App\Events;

use App\Models\BookingMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public BookingMessage $message) {}

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('booking.' . $this->message->booking_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->message->id,
            'sender_id'   => $this->message->sender_id,
            'sender_type' => $this->message->sender_type,
            'type'        => $this->message->type,
            'content'     => $this->message->content,
            'created_at'  => $this->message->created_at->toISOString(),
        ];
    }
}
