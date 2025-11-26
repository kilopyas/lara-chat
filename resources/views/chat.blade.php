<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat – Room {{ $roomId }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #111827;
            color: #e5e7eb;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        header {
            padding: 10px 16px;
            background: #020617;
            border-bottom: 1px solid #1f2937;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header span {
            font-size: 14px;
            opacity: 0.8;
        }
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 12px 16px;
            overflow: hidden;
        }
        #messages {
            flex: 1;
            overflow-y: auto;
            padding-right: 4px;
        }
        .message {
            margin-bottom: 8px;
            max-width: 80%;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 14px;
        }
        .message.me {
            margin-left: auto;
            background: #2563eb;
        }
        .message.other {
            margin-right: auto;
            background: #374151;
        }
        .message .meta {
            font-size: 11px;
            opacity: 0.7;
            margin-bottom: 2px;
        }
        .system {
            text-align: center;
            font-size: 12px;
            opacity: 0.7;
            margin: 4px 0;
        }
        .input-area {
            border-top: 1px solid #1f2937;
            padding: 8px 12px;
            display: flex;
            gap: 8px;
            align-items: center;
            background: #020617;
        }
        .input-area input[type="text"] {
            width: 120px;
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid #374151;
            background: #020617;
            color: #e5e7eb;
            font-size: 13px;
        }
        .input-area textarea {
            flex: 1;
            resize: none;
            min-height: 40px;
            max-height: 80px;
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid #374151;
            background: #020617;
            color: #e5e7eb;
            font-size: 14px;
        }
        .input-area button {
            padding: 8px 14px;
            border-radius: 6px;
            border: none;
            background: #22c55e;
            color: #022c22;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
        }
        .input-area button:disabled {
            opacity: 0.5;
            cursor: default;
        }
        #typingIndicator {
            font-size: 12px;
            opacity: 0.7;
            min-height: 16px;
            margin-top: 2px;
        }
    </style>
</head>
<body>
<header>
    <div>
        <strong>Room: {{ $roomId }}</strong>
        <div style="font-size: 12px; opacity: 0.8;">Simple Socket.IO Chat</div>
    </div>
    <span id="connectionStatus">Connecting…</span>
</header>

<div class="chat-container">
    <div id="messages"></div>
    <div id="typingIndicator"></div>
</div>

<div class="input-area">
    <input id="userName" type="text" placeholder="Your name" value="User{{ rand(100,999) }}">
    <textarea id="messageInput" placeholder="Type a message..."></textarea>
    <button id="sendBtn" disabled>Send</button>
</div>

{{-- Socket.IO client (served via nginx proxy to /socket.io/) --}}
<script src="/socket.io/socket.io.js"></script>
<script>
    const ROOM_ID = @json($roomId);
    const userNameInput = document.getElementById('userName');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    const messagesEl = document.getElementById('messages');
    const typingIndicator = document.getElementById('typingIndicator');
    const connectionStatus = document.getElementById('connectionStatus');

    let socket = null;
    let typingTimeout = null;
    let mySocketId = null;

    function addMessage({ userName, message, time, socketId }, isSystem = false) {
        if (isSystem) {
            const div = document.createElement('div');
            div.className = 'system';
            div.textContent = message;
            messagesEl.appendChild(div);
        } else {
            const div = document.createElement('div');
            const isMe = socketId && socketId === mySocketId;
            div.className = 'message ' + (isMe ? 'me' : 'other');

            const meta = document.createElement('div');
            meta.className = 'meta';
            const t = time ? new Date(time) : new Date();
            meta.textContent = `${userName || 'Unknown'} • ${t.toLocaleTimeString()}`;

            const body = document.createElement('div');
            body.textContent = message;

            div.appendChild(meta);
            div.appendChild(body);
            messagesEl.appendChild(div);
        }

        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function connectSocket() {
        socket = io(); // same-origin, nginx will proxy to socket_server

        socket.on('connect', () => {
            mySocketId = socket.id;
            connectionStatus.textContent = 'Connected';
            connectionStatus.style.color = '#22c55e';
            sendBtn.disabled = false;

            socket.emit('join-room', {
                roomId: ROOM_ID,
                userName: userNameInput.value || 'Guest'
            });
        });

        socket.on('disconnect', () => {
            connectionStatus.textContent = 'Disconnected';
            connectionStatus.style.color = '#f97316';
            sendBtn.disabled = true;
        });

        socket.on('chat-message', (data) => {
            console.log('view chat-message');
            addMessage(data, false);
        });

        socket.on('system-message', (data) => {
            addMessage(data, true);
        });

        socket.on('typing', ({ userName, isTyping }) => {
            if (isTyping) {
                typingIndicator.textContent = `${userName} is typing...`;
            } else {
                typingIndicator.textContent = '';
            }
        });
    }

    function sendMessage() {
        const message = messageInput.value.trim();
        const userName = userNameInput.value.trim() || 'Guest';
        if (!message || !socket || !socket.connected) return;

        console.log('TEST sendMessage', socket.connected);
        socket.emit('chat-message', {
            roomId: ROOM_ID,
            userName,
            message,
        });

        messageInput.value = '';
        socket.emit('typing', { roomId: ROOM_ID, userName, isTyping: false });
    }

    messageInput.addEventListener('keydown', (e) => {
        console.log('test');
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
            return;
        }

        const userName = userNameInput.value.trim() || 'Guest';
        if (socket && socket.connected) {
            socket.emit('typing', { roomId: ROOM_ID, userName, isTyping: true });
            if (typingTimeout) clearTimeout(typingTimeout);
            typingTimeout = setTimeout(() => {
                socket.emit('typing', { roomId: ROOM_ID, userName, isTyping: false });
            }, 1000);
        }
    });

    sendBtn.addEventListener('click', sendMessage);

    // start
    connectSocket();
</script>
</body>
</html>
