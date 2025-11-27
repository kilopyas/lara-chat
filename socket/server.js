const http = require("http");
const { Server } = require("socket.io");

const PORT = 3000;

const server = http.createServer();

// allow multiple origins via CORS_ORIGINS (comma-separated) or single via CORS_ORIGIN
const corsOrigins =
  (process.env.CORS_ORIGINS || process.env.CORS_ORIGIN || "http://localhost:8000,http://localhost")
    .split(",")
    .map((o) => o.trim())
    .filter(Boolean);

const io = new Server(server, {
  cors: {
    origin: corsOrigins,
    methods: ["GET", "POST"],
  },
});

// in-memory room store: roomId -> { name, participants: Map<socketId,userName>, updatedAt }
const rooms = new Map();

// helper: build a simple list of rooms for the lobby
function getRoomsList() {
  const list = [];
  for (const [roomId, room] of rooms.entries()) {
    list.push({
      roomId,
      name: room.name,
      participantsCount: room.participants.size,
      updatedAt: room.updatedAt,
    });
  }
  // sort by last activity, newest first
  list.sort((a, b) => (b.updatedAt || 0) - (a.updatedAt || 0));
  return list;
}

// helper: broadcast current rooms list to everyone
function broadcastRooms() {
  io.emit("rooms-list", getRoomsList());
}

io.on("connection", (socket) => {
  console.log("user connected:", socket.id);

  // lobby can request current list of rooms
  socket.on("get-rooms", () => {
    socket.emit("rooms-list", getRoomsList());
  });

  // join a chat room
  socket.on("join-room", ({ roomId, userName }) => {
    if (!roomId) roomId = "general";

    socket.join(roomId);
    socket.data.roomId = roomId;
    socket.data.userName = userName || "guest";

    // ensure room exists in our store
    let room = rooms.get(roomId);
    if (!room) {
      room = {
        name: roomId,
        participants: new Map(),
        updatedAt: Date.now(),
      };
      rooms.set(roomId, room);
    }

    // add / update participant
    room.participants.set(socket.id, socket.data.userName);
    room.updatedAt = Date.now();

    console.log(`${socket.id} joined room ${roomId} as ${socket.data.userName}`);

    // notify others in the room
    socket.to(roomId).emit("system-message", {
      message: `${socket.data.userName} joined the room`,
      userName: "system",
      time: new Date().toISOString(),
    });

    // update lobby
    broadcastRooms();
  });

  // receive chat messages from client
  socket.on("chat-message", ({ roomId, userName, message }) => {
    if (!message || !message.trim()) return;

    roomId = roomId || socket.data.roomId || "general";
    userName = userName || socket.data.userName || "guest";

    const payload = {
      roomId,
      userName,
      message: message.trim(),
      time: new Date().toISOString(),
      socketId: socket.id,
    };

    console.log("chat-message received:", payload);

    // update last activity for this room
    const room = rooms.get(roomId);
    if (room) {
      room.updatedAt = Date.now();
      broadcastRooms();
    }

    io.to(roomId).emit("chat-message", payload);
  });

  // typing indicator
  socket.on("typing", ({ roomId, userName, isTyping }) => {
    roomId = roomId || socket.data.roomId || "general";
    userName = userName || socket.data.userName || "guest";

    socket.to(roomId).emit("typing", {
      userName,
      isTyping: !!isTyping,
    });
  });

  // handle explicit leave (optional, if you emit 'leave-room' from client later)
  socket.on("leave-room", () => {
    const roomId = socket.data.roomId;
    const userName = socket.data.userName || "someone";

    if (!roomId) return;

    socket.leave(roomId);
    socket.data.roomId = null;

    const room = rooms.get(roomId);
    if (room) {
      room.participants.delete(socket.id);
      room.updatedAt = Date.now();

      if (room.participants.size === 0) {
        rooms.delete(roomId);
      }

      broadcastRooms();
    }

    socket.to(roomId).emit("system-message", {
      message: `${userName} left the room`,
      userName: "system",
      time: new Date().toISOString(),
    });
  });

  // handle disconnect
  socket.on("disconnect", () => {
    const roomId = socket.data.roomId;
    const userName = socket.data.userName || "someone";

    if (roomId) {
      const room = rooms.get(roomId);
      if (room) {
        room.participants.delete(socket.id);
        room.updatedAt = Date.now();

        if (room.participants.size === 0) {
          rooms.delete(roomId);
        }

        broadcastRooms();
      }

      socket.to(roomId).emit("system-message", {
        message: `${userName} left the room`,
        userName: "system",
        time: new Date().toISOString(),
      });
    }

    console.log("user disconnected:", socket.id);
  });
});

server.listen(PORT, () => {
  console.log(`socket.io server running on port ${PORT}`);
});
