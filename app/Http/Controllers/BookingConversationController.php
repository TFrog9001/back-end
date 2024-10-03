<?php

namespace App\Http\Controllers;

use App\Models\BookingConversation;
use App\Models\BookingMessage;
use Illuminate\Http\Request;
use App\Events\BookingMessageSent;

class BookingConversationController extends Controller
{
    public function store(Request $request, $bookingId)
    {
        $conversation = BookingConversation::firstOrCreate([
            'booking_id' => $bookingId,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($conversation);
    }

    public function storeMessage(Request $request, BookingConversation $conversation)
    {

        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        if ($conversation->booking->user_id === auth()->id() || auth()->user()->isEmployee()) {
            $message = BookingMessage::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $request->user()->id,
                'message' => $request->message,
            ]);

            // Phát tin nhắn qua WebSockets
            broadcast(new BookingMessageSent($message))->toOthers();

            return response()->json($message);
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }

    public function getMessages(BookingConversation $conversation)
    {
        // Cho phép nhân viên hoặc chủ sở hữu booking xem tin nhắn
        if ($conversation->booking->user_id === auth()->id() || auth()->user()->isEmployee()) {
            return response()->json($conversation->messages()->with('sender')->get());
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }
}
