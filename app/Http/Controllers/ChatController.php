<?php

namespace App\Http\Controllers;

use App\Models\BookingMessage;
use Illuminate\Http\Request;
use App\Events\MessageSent;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    // Lấy danh sách tin nhắn theo booking ID
    public function getMessages($bookingId)
    {
        $messages = BookingMessage::where('booking_id', $bookingId)->with('user')->get();
        return response()->json($messages);
    }

    // Gửi tin nhắn
    public function sendMessage(Request $request, $bookingId)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $message = BookingMessage::create([
            'message' => $request->message,
            'user_id' => Auth::id(),
            'booking_id' => $bookingId,
        ]);

        // Phát sự kiện qua WebSocket
        broadcast(new MessageSent($message->load('user')))->toOthers();

        return response()->json(['message' => 'Message sent successfully!', 'data' => $message]);
    }
}
