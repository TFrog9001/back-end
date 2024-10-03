<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $bookingId;

    public function __construct($message, $bookingId)
    {
        $this->message = $message;
        $this->bookingId = $bookingId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('booking.' . $this->bookingId);
    }
}
