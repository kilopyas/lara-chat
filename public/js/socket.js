// global socket.io connection (explicit URL so it works when Laravel serves from a different port)
const SOCKET_URL = window.SOCKET_URL || 'http://localhost:3000';
window.socket = io(SOCKET_URL, {
    // you can add options here later if needed
});

// simple helper api for lobby and chat
window.chatSocket = {
    // connection events
    onConnect(callback) {
        // here
        socket.on('connect', () => {
            callback(socket.id);
        });
    },

    onDisconnect(callback) {
        socket.on('disconnect', callback);
    },

    // lobby-related
    requestRooms() {
        console.log('socket.js requestRooms');
        socket.emit('get-rooms');
    },

    onRoomsList(callback) {
        socket.on('rooms-list', (rooms) => {
            callback(rooms || []);
        });
    },

    // room join
    joinRoom(roomId, userName) {
        socket.emit('join-room', {
            roomId,
            userName,
        });
    },

    // chat messages
    sendMessage(roomId, userName, message) {
        socket.emit('chat-message', {
            roomId,
            userName,
            message,
        });
    },

    onChatMessage(callback) {
        socket.on('chat-message', (data) => {
            callback(data);
        });
    },

    // system messages (join/leave)
    onSystemMessage(callback) {
        socket.on('system-message', (data) => {
            callback(data);
        });
    },

    // typing indicator
    sendTyping(roomId, userName, isTyping) {
        socket.emit('typing', {
            roomId,
            userName,
            isTyping,
        });
    },

    onTyping(callback) {
        socket.on('typing', (data) => {
            callback(data);
        });
    }
};

// socket.on('connect', () => {
//     console.log('socket connected:', socket.id);
// });

// socket.on('disconnect', () => {
//     console.log('socket disconnected');
// });
