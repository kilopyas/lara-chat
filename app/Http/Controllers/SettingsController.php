<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $rooms = Room::where('user_id', $request->user()->id)
            ->orderByDesc('last_active_at')
            ->orderByDesc('updated_at')
            ->get();

        error_log("\n before:" . print_r($rooms->toArray(), true), 3, "/var/www/html/storage/logs/laravel.log");
        return view('partials/settings', [
            'rooms' => $rooms,
        ]);
    }

    public function destroy(Request $request, string $roomId): RedirectResponse
    {
        $room = Room::where('room_id', $roomId)->first();

        if (!$room) {
            return redirect()->route('rooms.settings')->with('error', 'Room not found.');
        }

        if ($room->user_id !== $request->user()->id) {
            return redirect()->route('rooms.settings')->with('error', 'You can only delete rooms you own.');
        }

        $room->delete();

        return redirect()->route('rooms.settings')->with('success', 'Room deleted.');
    }
}
