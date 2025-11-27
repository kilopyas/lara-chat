<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\User;

class MessageController extends Controller
{
    public function index(Request $request, string $roomId)
    {
        $limit = (int) $request->query('limit', 50);
        $limit = $limit > 0 ? min($limit, 200) : 50;

        $messages = Message::where('room_id', $roomId)
            ->orderBy('created_at')
            ->limit($limit)
            ->get(['room_id', 'user_name as userName', 'user_id as userId', 'message', 'created_at as time']);

        return $messages;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'required|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
            'message' => 'required|string',
        ]);

        $user = User::find($data['user_id']);
        $userName = $user?->name ?? 'guest';

        // update last active
        Room::updateOrCreate(
            ['room_id' => $data['room_id']],
            [
                'last_active_at' => Carbon::now(),
            ]
        );

        // add message
        $message = Message::create([
            'room_id' => $data['room_id'],
            'user_name' => $userName,
            'user_id' => $data['user_id'],
            'message' => $data['message'],
        ]);

        return response()->json($message, 201);
    }
}
