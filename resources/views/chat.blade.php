<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat – Room {{ $roomId }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #111827;
            color: #e5e7eb;
        }
        header {
            padding: 10px 16px;
            background: #020617;
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
        .chat-container {
            padding: 12px 16px;
            height: calc(100vh - 120px);
            display: flex;
            flex-direction: column;
        }
        #messages {
            flex: 1;
            overflow-y: auto;
            border: 1px solid #1f2937;
            border-radius: 8px;
            padding: 10px;
            background: #020617;
        }
        .msg {
            margin-bottom: 6px;
            font-size: 14px;
        }
        .msg.me {
            color: #22c55e;
        }
        .msg.other {
            color: #60a5fa;
        }
        .msg.system {
            color: #9ca3af;
            font-size: 12px;
            text-align: center;
        }
        .input-area {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .input-area input[type="text"] {
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid #374151;
            background: #020617;
            color: #e5e7eb;
            font-size: 14px;
        }
        #userName {
            width: 140px;
        }
        #messageInput {
            flex: 1;
        }
        button {
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        #sendBtn {
            background: #22c55e;
            color: #022c22;
        }
        #typingIndicator {
            margin-top: 4px;
            font-size: 12px;
            opacity: 0.75;
            min-height: 16px;
        }
    </style>
</head>
<body>
<header>
    <div style="display: flex; align-items: center; gap: 12px;">
        <a href="/" 
           style="padding: 6px 10px; background: #1f2937; border-radius: 6px; text-decoration: none; color: #e5e7eb; font-size: 13px;">
            ← Back
        </a>

        <div>
            <h1>Room: {{ $roomId }}</h1>
            <span>Simple Socket.IO Chat</span>
        </div>
    </div>

    <span id="connectionStatus">Connecting…</span>
</header>


<div class="chat-container">
    <div id="messages"></div>
    <div id="typingIndicator"></div>

    <div class="input-area">
        <input id="userName" type="text" value="User{{ rand(100,999) }}" placeholder="Your name">
        <input id="messageInput" type="text" placeholder="Type a message...">
        <button id="sendBtn" disabled>Send</button>
    </div>
</div>

{{-- socket.io client + global helper --}}
<script src="/socket.io/socket.io.js"></script>
<script src="/js/socket.js"></script>

<script>
    const ROOM_ID = @json($roomId);

    const messagesEl = document.getElementById('messages');
    const typingIndicator = document.getElementById('typingIndicator');
    const connectionStatus = document.getElementById('connectionStatus');
    const userNameInput = document.getElementById('userName');
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');

    let myId = null;
    let typingTimeout = null;

    // add message to ui
    // here
    function addMessage(text, type = 'other') {
        const div = document.createElement('div');
        div.className = 'msg ' + type;
        div.textContent = text;
        messagesEl.appendChild(div);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // wire socket events via chatSocket
    chatSocket.onConnect((socketId) => {
        myId = socketId;
        connectionStatus.textContent = 'Connected';
        connectionStatus.style.color = '#22c55e';
        sendBtn.disabled = false;

        chatSocket.joinRoom(ROOM_ID, userNameInput.value || 'Guest');
    });

    chatSocket.onDisconnect(() => {
        connectionStatus.textContent = 'Disconnected';
        connectionStatus.style.color = '#f97316';
        sendBtn.disabled = true;
    });

    chatSocket.onSystemMessage((data) => {
        addMessage(`[System] ${data.message}`, 'system');
    });

    chatSocket.onChatMessage((data) => {
        const isMe = data.socketId === myId;
        const prefix = isMe ? '(Me)' : `(${data.userName})`;
        addMessage(`${prefix} ${data.message}`, isMe ? 'me' : 'other');
    });

    chatSocket.onTyping(({ userName, isTyping }) => {
        if (isTyping) {
            typingIndicator.textContent = `${userName} is typing...`;
        } else {
            typingIndicator.textContent = '';
        }
    });

    // send message
    function sendMessage() {
        const message = messageInput.value.trim();
        if (!message) return;

        const userName = userNameInput.value || 'Guest';
        chatSocket.sendMessage(ROOM_ID, userName, message);
        messageInput.value = '';
        chatSocket.sendTyping(ROOM_ID, userName, false);
    }

    sendBtn.addEventListener('click', sendMessage);

    messageInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            sendMessage();
            return;
        }

        const userName = userNameInput.value || 'Guest';
        chatSocket.sendTyping(ROOM_ID, userName, true);

        if (typingTimeout) clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            chatSocket.sendTyping(ROOM_ID, userName, false);
        }, 1000);
    });
</script>
</body>
</html>
