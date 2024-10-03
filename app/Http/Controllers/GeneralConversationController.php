<?php
namespace App\Http\Controllers;

use App\Models\GeneralConversation;
use App\Models\GeneralMessage;
use Illuminate\Http\Request;
use App\Events\GeneralMessageSent;

class GeneralConversationController extends Controller
{
    public function store(Request $request)
    {
        $conversation = GeneralConversation::firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return response()->json($conversation);
    }

    public function storeMessage(Request $request, GeneralConversation $conversation)
    {
        $message = GeneralMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'message' => $request->message,
        ]);

        // Phát tin nhắn qua WebSockets
        broadcast(new GeneralMessageSent($message))->toOthers();

        return response()->json($message);
    }

    public function getMessages(GeneralConversation $conversation)
    {
        return response()->json($conversation->messages()->with('sender')->get());
    }
}
