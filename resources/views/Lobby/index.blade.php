@extends('partials.layout.layout')

@section('title', 'Chat Lobby')

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
    header span {
        font-size: 13px;
        opacity: 0.8;
    }
    .container {
        padding: 16px 18px;
        max-width: 900px;
        margin: 0 auto;
    }
    .row {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
    }
    .left, .right {
        flex: 1 1 280px;
    }
    .card {
        background: var(--card);
        border-radius: 10px;
        border: 1px solid var(--border);
        padding: 12px 14px;
        margin-bottom: 12px;
    }
    .card h2 {
        margin: 0 0 8px;
        font-size: 15px;
    }
    .card p {
        margin: 4px 0;
        font-size: 13px;
        opacity: 0.9;
    }
    .rooms-list {
        max-height: 60vh;
        overflow-y: auto;
    }
    .room-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 10px;
        border-radius: 8px;
        border: 1px solid var(--border);
        margin-bottom: 8px;
        background: var(--card);
    }
    .room-item .title {
        font-size: 14px;
        font-weight: 600;
    }
    .room-item .meta {
        font-size: 12px;
        opacity: 0.75;
    }
    button {
        border: none;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-primary {
        background: var(--primary);
        color: #022c22;
    }
    .btn-secondary {
        background: #1f2937;
        color: var(--text);
    }
    input[type="text"], input[type="password"] {
        width: 100%;
        border-radius: 8px;
        border: 1px solid var(--border);
        padding: 7px 9px;
        background: var(--card);
        color: var(--text);
        font-size: 14px;
    }
</style>
@endpush

@section('content')
    <header>
        <div>
            <h1>Chat Lobby</h1>
            <span>See active rooms and join or create one</span>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 13px; opacity: 0.85;">{{ auth()->user()->name }}</span>
            <a href="{{ route('rooms.settings') }}"
               title="Room settings"
               style="width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; background: #1f2937; color: var(--text); text-decoration: none; font-size: 16px;">
                ⚙
            </a>
            <span id="connectionStatus">Connecting…</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-secondary">Logout</button>
            </form>
        </div>
    </header>

    <div class="container">
        <div class="row">
            <div class="left">
                <div class="card">
                    <h2>Create or Join a Room</h2>
                    <p>Type a room name to join or create it. Add a password to make it private (optional).</p>

                    <input id="roomNameInput" type="text" placeholder="Room name">
                    <input id="roomPasswordInput" type="password" placeholder="Password (optional)" style="margin-top: 8px;">

                    <div style="margin-top: 8px; display: flex; gap: 8px;">
                        <button id="createJoinBtn" class="btn-primary">Create / Join</button>
                        <button id="refreshBtn" class="btn-secondary">Refresh</button>
                    </div>
                </div>
            </div>

            <div class="right">
                <div class="card">
                    <h2>Active Rooms</h2>
                    <div id="roomsList" class="rooms-list"></div>
                    <div id="roomsEmpty" style="opacity: .8; font-size: 13px; display: none;">
                        No active rooms yet.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

    @push('scripts')
    {{-- socket.io client + global helper --}}
    @include('partials.socket-config')
    <script src="{{ env('SOCKET_URL', 'http://localhost:3000') }}/socket.io/socket.io.js"></script>
    @php($socketJsVersion = @filemtime(public_path('js/socket.js')))
    <script src="{{ asset('js/socket.js') }}?v={{ $socketJsVersion }}"></script>

    <script>
        const USER_ID = @json(auth()->id());
        const roomsListEl = document.getElementById('roomsList');
        const roomsEmptyEl = document.getElementById('roomsEmpty');
        const roomNameInput = document.getElementById('roomNameInput');
        const roomPasswordInput = document.getElementById('roomPasswordInput');
        const connectionStatus = document.getElementById('connectionStatus');
        const createJoinBtn = document.getElementById('createJoinBtn');
    const refreshBtn = document.getElementById('refreshBtn');

    // render rooms list
    // here
    function updateRoomsList(rooms) {
        roomsListEl.innerHTML = '';

        if (!rooms || rooms.length === 0) {
            roomsEmptyEl.style.display = 'block';
            return;
        }

        roomsEmptyEl.style.display = 'none';

        rooms.forEach(room => {
            const div = document.createElement('div');
            div.className = 'room-item';
            const isProtected = !!room.hasPassword;
            const isOwner = room.ownerId && room.ownerId === USER_ID;

            const info = document.createElement('div');
            info.innerHTML = `
                <div class="title">${room.name} ${isProtected ? '🔒' : ''}</div>
                <div class="meta">${room.participantsCount} participant(s) • ${isProtected ? 'Password protected' : 'Public'}</div>
            `;

            const joinBtn = document.createElement('button');
            joinBtn.className = 'btn-secondary';
            joinBtn.textContent = 'Join';
            joinBtn.onclick = async () => {
                let passwordToUse = '';
                if (isProtected && !isOwner) {
                    passwordToUse = prompt('This room is password protected. Enter password to join:') || '';
                }

                if (isProtected && !isOwner && !passwordToUse.trim()) {
                    alert('Password is required to join this room.');
                    return;
                }

                if (isProtected && !isOwner) {
                    try {
                        const verifyRes = await fetch(`/api/rooms/${encodeURIComponent(room.roomId)}/verify`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                password: passwordToUse,
                                user_id: USER_ID,
                            }),
                        });

                        const verifyData = await verifyRes.json();
                        if (!verifyRes.ok || !verifyData.ok) {
                            alert(verifyData.message || 'Invalid password.');
                            return;
                        }
                    } catch (err) {
                        console.error(err);
                        alert('Could not verify the password. Please try again.');
                        return;
                    }
                }

                if (passwordToUse) {
                    sessionStorage.setItem(`roomPassword:${room.roomId}`, passwordToUse);
                }

                window.location.href = '/chat/' + encodeURIComponent(room.roomId);
            };

            div.appendChild(info);
            div.appendChild(joinBtn);
            roomsListEl.appendChild(div);
        });
    }

    // hook socket events via chatSocket
        chatSocket.onConnect(() => {
            connectionStatus.textContent = 'Connected';
            connectionStatus.style.color = '#22c55e';
            chatSocket.requestRooms();
            console.log('lobby on connect');
        });

        chatSocket.onDisconnect(() => {
            connectionStatus.textContent = 'Disconnected';
            connectionStatus.style.color = '#f97316';
        });

        chatSocket.onRoomsList((rooms) => {
            updateRoomsList(rooms);
        });

        // buttons
        createJoinBtn.onclick = async () => {
            const name = roomNameInput.value.trim();
            const password = roomPasswordInput.value;
            if (!name) return;

            try {
                const res = await fetch('/api/rooms', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        name: name,
                        user_id: USER_ID,
                        password: password || null,
                    }),
                });

                if (!res.ok) {
                    throw new Error('Failed to create/join room');
                }

                const resData = await res.json();
                const roomId = resData.room_id || resData.roomId;
                if (roomId) {
                    window.location.href = '/chat/' + encodeURIComponent(roomId);
                }
            } catch (err) {
                console.error(err);
                alert('Could not create or join the room. Please try again.');
            }
        };

        refreshBtn.onclick = () => {
            chatSocket.requestRooms();
        };
    </script>
@endpush
