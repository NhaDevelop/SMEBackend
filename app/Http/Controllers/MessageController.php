<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $messages = Message::where('sender_id', $userId)
            ->orWhere('receiver_id', $userId)
            ->with(['sender:id,full_name', 'receiver:id,full_name'])
            ->latest()
            ->get();

        return $this->success($messages, 'Messages retrieved successfully');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'content' => 'required|string',
            'chat_id' => 'required|string'
        ]);

        $message = Message::create([
            'sender_id' => auth()->id(),
            ...$validated,
            'read' => false
        ]);

        return $this->success($message, 'Message sent successfully', 201);
    }
}
