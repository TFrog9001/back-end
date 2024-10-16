<?php

namespace App\Events;

use App\Models\BookingMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageSent implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public $message;

    public function __construct(BookingMessage $message)
    {
        $this->message = $message;
    }

    // Kênh private cho từng booking
    public function broadcastOn()
    {
        return new PrivateChannel('booking.' . $this->message->booking_id);
    }

    public function broadcastWith()
    {   
        return [
            'message' => $this->message->message,
            'user_id' => $this->message->user_id,
            'booking_id' => $this->message->booking_id,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
            ],
            'create_at' => $this->message->created_at
        ];
    }
}
