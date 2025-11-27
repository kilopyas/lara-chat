<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    public function index()
    {
        return Room::orderByDesc('last_active_at')->get(['room_id', 'name', 'last_active_at', 'updated_at', 'created_at']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if (empty($data['user_id']) && $request->user()) {
            $data['user_id'] = $request->user()->id;
        }

        $roomId = $data['room_id'] ?? Str::uuid()->toString();

        $payload = [
            'name' => $data['name'] ?? $roomId,
            'last_active_at' => Carbon::now(),
        ];

        if (!empty($data['user_id'])) {
            $payload['user_id'] = $data['user_id'];
        }

        $room = Room::updateOrCreate(
            ['room_id' => $roomId],
            $payload
        );

        return response()->json($room, 201);
    }

    public function destroy(string $roomId)
    {
        $room = Room::where('room_id', $roomId)->first();
        if (!$room) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $room->delete();

        return response()->json(['message' => 'deleted']);
    }
}
