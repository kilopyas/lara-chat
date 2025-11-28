@extends('partials.layout.layout')

@section('title', "Chat – Room {$roomName}")

@push('head')
<style>
    header {
        padding: 10px 16px;
        background: var(--card);
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
    .chat-container {
        padding: 12px 16px;
        height: calc(100vh - 120px);
        display: flex;
        flex-direction: column;
    }
    #messages {
        flex: 1;
        overflow-y: auto;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 10px;
        background: var(--card);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .msg {
        display: flex;
        font-size: 14px;
    }
    .msg .bubble {
        max-width: 75%;
        padding: 8px 10px;
        border-radius: 10px;
        line-height: 1.35;
        border: 1px solid var(--border);
    }
    .msg.me { justify-content: flex-end; }
    .msg.me .bubble {
        background: var(--primary);
        color: #022c22;
        border-color: var(--primary);
    }
    .msg.other { justify-content: flex-start; }
    .msg.other .bubble {
        background: #1f2937;
        color: var(--text);
    }
    .msg.system {
        justify-content: center;
        color: var(--muted);
        font-size: 12px;
    }
    .msg.system .bubble {
        background: transparent;
        border: none;
        padding: 0;
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
        background: var(--card);
        color: var(--text);
        font-size: 14px;
    }
    #userName { width: 140px; }
    #messageInput { flex: 1; }
    button {
        border: none;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
    }
    #sendBtn {
        background: var(--primary);
        color: #022c22;
    }
    #typingIndicator {
        margin-top: 4px;
        font-size: 12px;
        opacity: 0.75;
        min-height: 16px;
    }
</style>
@endpush

@section('content')
    <header>
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="/"
               style="padding: 6px 10px; background: #1f2937; border-radius: 6px; text-decoration: none; color: var(--text); font-size: 13px;">
                ← Back
            </a>

            <div>
                <h1>Room: {{ $roomName }}</h1>
                <span>Simple Socket.IO Chat</span>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 13px; opacity: 0.85;">{{ auth()->user()->name }}</span>
            <span id="connectionStatus">Connecting…</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-secondary">Logout</button>
            </form>
        </div>
    </header>


    <div class="chat-container">
        <div id="messages"></div>
        <div id="typingIndicator"></div>

        <div class="input-area">
            <input id="userName" type="text" value="{{ auth()->user()->name }}" readonly>
            <input id="messageInput" type="text" placeholder="Type a message...">
            <button id="sendBtn" disabled>Send</button>
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
        const ROOM_ID = @json($roomId);
        const ROOM_NAME = @json($roomName);
        const USER_ID = @json(auth()->id());
        const PASSWORD_KEY = `roomPassword:${ROOM_ID}`;
        
        let roomPassword = sessionStorage.getItem(PASSWORD_KEY) || null;
        if (roomPassword) {
            sessionStorage.removeItem(PASSWORD_KEY);
        }

        const messagesEl = document.getElementById('messages');
        const typingIndicator = document.getElementById('typingIndicator');
        const connectionStatus = document.getElementById('connectionStatus');
        const userNameInput = document.getElementById('userName');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');

        let myId = null;
        let typingTimeout = null;
        let earliestMessage = null;
        let loadingOlder = false;
        let hasMore = true;

        // add message to ui
        function addMessage(msg, { prepend = false, autoScroll = true } = {}) {
            const userName = msg.userName || 'Guest';
            const isSystem = msg.type === 'system';
            const isMe = isSystem ? false : !!msg.isMe;
            const wrap = document.createElement('div');
            wrap.className = 'msg ' + (isSystem ? 'system' : (isMe ? 'me' : 'other'));

            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            const prefix = isSystem ? '[System]' : (isMe ? '(Me)' : `(${userName})`);
            bubble.textContent = isSystem ? `${prefix} ${msg.message}` : `${prefix} ${msg.message}`;
            wrap.appendChild(bubble);

            if (prepend) {
                messagesEl.insertBefore(wrap, messagesEl.firstChild);
            } else {
                messagesEl.appendChild(wrap);
            }

            if (autoScroll && !prepend) {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
        }

        // wire socket events via chatSocket
        chatSocket.onConnect((socketId) => {
            myId = socketId;
            connectionStatus.textContent = 'Connected';
            connectionStatus.style.color = '#22c55e';
            sendBtn.disabled = false;

            console.log('ni join room');
            chatSocket.joinRoom(ROOM_ID, userNameInput.value || 'Guest', ROOM_NAME, USER_ID, roomPassword);
        });

        chatSocket.onDisconnect(() => {
            connectionStatus.textContent = 'Disconnected';
            connectionStatus.style.color = '#f97316';
            sendBtn.disabled = true;
        });

        chatSocket.onSystemMessage((data) => {
            addMessage({ message: data.message, type: 'system' });
        });

        chatSocket.onChatHistory((history) => {
            (history || []).forEach((msg) => {
                const isMe = msg.userId ? msg.userId === USER_ID : (msg.userName || '').toLowerCase() === (userNameInput.value || '').toLowerCase();
                addMessage({ ...msg, isMe, type: 'chat' }, { autoScroll: false });
            });

            if (history && history.length) {
                earliestMessage = history[0].id || earliestMessage;
            }

            messagesEl.scrollTop = messagesEl.scrollHeight;
        });

        chatSocket.onChatMessage((data) => {
            const isMe = data.userId === USER_ID;
            addMessage({ userName: data.userName, message: data.message, isMe, type: 'chat' });
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
            chatSocket.sendMessage(ROOM_ID, userName, message, USER_ID);
            messageInput.value = '';
            chatSocket.sendTyping(ROOM_ID, userName, false);
        }

        // btn
        sendBtn.addEventListener('click', sendMessage);

        // enter
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

        messagesEl.addEventListener('scroll', () => {
            if (messagesEl.scrollTop <= 0) {
                loadOlderMessages();
            }
        });

        async function loadOlderMessages() {
            if (loadingOlder || !hasMore || !earliestMessage) return;
            loadingOlder = true;

            const prevHeight = messagesEl.scrollHeight;
            const prevTop = messagesEl.scrollTop;

            try {
                const res = await fetch(`/api/rooms/${ROOM_ID}/messages?limit=50&before=${encodeURIComponent(earliestMessage)}`);
                if (!res.ok) throw new Error('Failed to fetch older messages');

                const data = await res.json();
                if (!Array.isArray(data) || data.length === 0) {
                    hasMore = false;
                    return;
                }

                earliestMessage = data[0].id || earliestMessage;
                data.reverse().forEach((msg) => {
                    const isMe = msg.userId ? msg.userId === USER_ID : (msg.userName || '').toLowerCase() === (userNameInput.value || '').toLowerCase();
                    addMessage({ ...msg, isMe, type: 'chat' }, { prepend: true, autoScroll: false });
                });

                const newHeight = messagesEl.scrollHeight;
                messagesEl.scrollTop = newHeight - prevHeight + prevTop;
            } catch (err) {
                console.error(err);
            } finally {
                loadingOlder = false;
            }
        }
    </script>
@endpush
