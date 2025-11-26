<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat Lobby</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #020617;
            color: #e5e7eb;
        }
        header {
            padding: 12px 18px;
            background: #0b1120;
            border-bottom: 1px solid #1f2937;
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
            background: #020617;
            border-radius: 10px;
            border: 1px solid #1f2937;
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
            border: 1px solid #1f2937;
            margin-bottom: 8px;
            background: #020617;
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
            background: #22c55e;
            color: #022c22;
        }
        .btn-secondary {
            background: #1f2937;
            color: #e5e7eb;
        }
        input[type="text"] {
            width: 100%;
            border-radius: 8px;
            border: 1px solid #1f2937;
            padding: 7px 9px;
            background: #020617;
            color: #e5e7eb;
            font-size: 14px;
        }
    </style>
</head>
<body>
<header>
    <div>
        <h1>Chat Lobby</h1>
        <span>See active rooms and join or create one</span>
    </div>
    <span id="connectionStatus">Connecting…</span>
</header>

<div class="container">
    <div class="row">
        <div class="left">
            <div class="card">
                <h2>Create or Join a Room</h2>
                <p>Type a room name to join or create it.</p>

                <input id="roomNameInput" type="text" placeholder="Room name">

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

{{-- socket.io client + global helper --}}
<script src="/socket.io/socket.io.js"></script>
<script src="/js/socket.js"></script>

<script>
    const roomsListEl = document.getElementById('roomsList');
    const roomsEmptyEl = document.getElementById('roomsEmpty');
    const roomNameInput = document.getElementById('roomNameInput');
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

            const info = document.createElement('div');
            info.innerHTML = `
                <div class="title">${room.name}</div>
                <div class="meta">${room.participantsCount} participant(s)</div>
            `;

            const joinBtn = document.createElement('button');
            joinBtn.className = 'btn-secondary';
            joinBtn.textContent = 'Join';
            joinBtn.onclick = () => {
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
    createJoinBtn.onclick = () => {
        const room = roomNameInput.value.trim();
        if (!room) return;
        window.location.href = '/chat/' + encodeURIComponent(room);
    };

    refreshBtn.onclick = () => {
        chatSocket.requestRooms();
    };
</script>
</body>
</html>
