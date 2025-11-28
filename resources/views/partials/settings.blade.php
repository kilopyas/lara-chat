@extends('partials.layout.layout')

@section('title', 'Room Settings')

@push('head')
<style>
    header {
        padding: 12px 18px;
        background: var(--panel);
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    header h1 {
        margin: 0;
        font-size: 18px;
    }
    .container {
        padding: 16px 18px;
        max-width: 800px;
        margin: 0 auto;
    }
    .card {
        background: var(--card);
        border-radius: 10px;
        border: 1px solid var(--border);
        padding: 12px 14px;
    }
    .room {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-bottom: 8px;
    }
    .room .meta {
        font-size: 12px;
        opacity: 0.75;
    }
    button {
        border: none;
        border-radius: 6px;
        padding: 7px 10px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-secondary {
        background: #1f2937;
        color: var(--text);
    }
    .btn-danger {
        background: #ef4444;
        color: #fff;
    }
    .flash {
        padding: 10px 12px;
        border-radius: 8px;
        margin-bottom: 10px;
        font-size: 14px;
    }
    .flash.success {
        background: #065f46;
        color: #d1fae5;
    }
    .flash.error {
        background: #7f1d1d;
        color: #fee2e2;
    }
</style>
@endpush

@section('content')
    <header>
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="{{ route('lobby') }}"
               style="padding: 6px 10px; background: #1f2937; border-radius: 6px; text-decoration: none; color: var(--text); font-size: 13px;">
                ← Back
            </a>
            <h1>Settings</h1>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 13px; opacity: 0.85;">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-secondary">Logout</button>
            </form>
        </div>
    </header>

    <div class="container">
        <div class="card">
            <h2 style="margin: 0 0 10px;">Your Rooms</h2>

            @if (session('success'))
                <div class="flash success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="flash error">{{ session('error') }}</div>
            @endif

            @if ($rooms->isEmpty())
                <p style="opacity: .8;">You do not own any rooms yet.</p>
            @else
                @foreach ($rooms as $room)
                    <?php error_log("\n room:" . print_r($room->last_active_at, true), 3, "/var/www/html/storage/logs/laravel.log"); ?>
                    <div class="room">
                        <div>
                            <div style="font-weight: 600;">{{ $room->name }} ({{ $room->room_id }})</div>
                            <div class="meta">
                                Last active: {{ optional($room->last_active_at)->diffForHumans() ?? 'n/a' }}
                                <br>
                                {{ $room->has_password ? 'Password protected' : 'Public room' }}
                            </div>
                        </div>
                        <form method="POST" action="{{ route('rooms.settings.destroy', $room->room_id) }}" onsubmit="return confirm('Delete this room? This cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-danger">Delete</button>
                        </form>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @if (session('deleted_room_id'))
    {{-- socket.io client + global helper --}}
    @include('partials.socket-config')
    <script src="{{ env('SOCKET_URL', 'http://localhost:3000') }}/socket.io/socket.io.js"></script>
    @php($socketJsVersion = @filemtime(public_path('js/socket.js')))
    <script src="{{ asset('js/socket.js') }}?v={{ $socketJsVersion }}"></script>
        <script>
            chatSocket.performDeleteRoom("{{ session('deleted_room_id') }}");
        </script>
    @endif
@endpush
