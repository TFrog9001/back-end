<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Xác định kênh phát sóng.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Sử dụng đối tượng Channel
        return new Channel('staff-notifications');
    }

    /**
     * Tùy chỉnh tên sự kiện.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'NotificationSent';
    }
}
