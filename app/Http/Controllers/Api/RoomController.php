<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    public function index()
    {
        return Room::orderByDesc('last_active_at')
            ->get(['room_id', 'name', 'user_id', 'last_active_at', 'updated_at', 'created_at', 'password']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'room_id' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
            'password' => 'nullable|string|max:255',
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

        if ($request->has('password')) {
            $payload['password'] = $data['password'] ? Hash::make($data['password']) : null;
        }

        $room = Room::updateOrCreate(
            ['room_id' => $roomId],
            $payload
        );

        return response()->json($room, 201);
    }

    public function verify(Request $request, string $roomId)
    {
        $data = $request->validate([
            'password' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer',
        ]);

        $room = Room::where('room_id', $roomId)->first();
        if (!$room) {
            return response()->json(['ok' => false, 'message' => 'Room not found'], 404);
        }

        if ($room->user_id && $room->user_id === ($data['user_id'] ?? null)) {
            return response()->json(['ok' => true]);
        }

        if (!$room->password) {
            return response()->json(['ok' => true]);
        }

        $valid = $data['password'] && Hash::check($data['password'], $room->password);
        if (!$valid) {
            return response()->json(['ok' => false, 'message' => 'Invalid password'], 403);
        }

        return response()->json(['ok' => true]);
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
