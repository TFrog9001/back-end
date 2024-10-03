<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'booking_id' => 'required|integer',
        ]);

        $message = $request->message;
        $bookingId = $request->booking_id;

        broadcast(new MessageSent($message, $bookingId))->toOthers();

        return response()->json(['status' => 'Message Sent!']);
    }

    public function sendMessage(Request $request)
    {
        $user = auth()->user();
        $message = $request->input('message');

        broadcast(new MessageSent($message, $user))->toOthers();

        return response()->json(['status' => 'Message Sent!']);
    }
}
