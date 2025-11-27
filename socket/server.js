const http = require("http");
const { Server } = require("socket.io");
const axios = require("axios").default;

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

const api = axios.create({
  // default to the compose service name so it works inside Docker; override with API_BASE if running locally
  baseURL: process.env.API_BASE || "http://web",
  timeout: 5000,
});

// in-memory room store: roomId -> { name, participants: Map<socketId,userName>, updatedAt }
const rooms = new Map();

async function upsertRoom(roomId, name) {
  await api.post("/api/rooms", { room_id: roomId, name: name || roomId });
}

async function recordMessage(roomId, userName, message) {
  await api.post("/api/messages", {
    room_id: roomId,
    user_name: userName || "guest",
    message,
  });
}

async function deleteRoom(roomId) {
  try {
    await api.delete(`/api/rooms/${encodeURIComponent(roomId)}`);
  } catch (err) {
    console.error("failed to delete room", err?.message || err);
  }
}

async function fetchRecentMessages(roomId, limit = 50) {
  const { data } = await api.get(`/api/rooms/${encodeURIComponent(roomId)}/messages`, {
    params: { limit },
  });
  return data || [];
}

async function fetchRoomsFromDb() {
  const { data } = await api.get("/api/rooms");
  return data || [];
}

async function buildRoomsList() {
  const dbRooms = await fetchRoomsFromDb();

  if (!dbRooms || dbRooms.length === 0) {
      return [];
  }

  const list = dbRooms.map((r) => {
    const roomId = r.room_id || r.roomId;
    const lastActive = r.last_active_at || r.lastActiveAt || r.updated_at || r.updatedAt || r.created_at || r.createdAt;
    return {
      roomId,
      name: r.name || roomId,
      participantsCount: rooms.get(roomId)?.participants.size || 0,
      updatedAt: lastActive ? new Date(lastActive).getTime() : 0,
    };
  });

  // // include active rooms not yet saved (edge cases)
  // for (const [roomId, room] of rooms.entries()) {
  //   if (!list.find((r) => r.roomId === roomId)) {
  //     list.push({
  //       roomId,
  //       name: room.name || roomId,
  //       participantsCount: room.participants.size,
  //       updatedAt: room.updatedAt || Date.now(),
  //     });
  //   }
  // }

  list.sort((a, b) => (b.updatedAt || 0) - (a.updatedAt || 0));
  return list;
}

async function broadcastRooms() {
  try {
    const list = await buildRoomsList();
    io.emit("rooms-list", list);
  } catch (err) {
    console.error("failed to broadcast rooms", err);
  }
}

io.on("connection", (socket) => {
  console.log("user connected:", socket.id);

  // lobby can request current list of rooms
  socket.on("get-rooms", async () => {
    try {
      socket.emit("rooms-list", await buildRoomsList());
    } catch (err) {
      console.error("failed to get rooms", err);
    }
  });

  // join a chat room
  socket.on("join-room", async ({ roomId, userName, roomName }) => {
    try {
      if (!roomId) roomId = "general";
      const safeUserName = userName || "guest";

      // await upsertRoom(roomId, safeUserName);

      socket.join(roomId);
      socket.data.roomId = roomId;
      socket.data.userName = safeUserName;
      socket.data.roomName = roomName || roomId;

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

      console.log(`${socket.id} joined room ${roomId} as ${socket.data.userName} - Room name: ${socket.data.roomName}`);

      // notify others in the room
      socket.to(roomId).emit("system-message", {
        message: `${socket.data.userName} joined the room`,
        userName: "system",
        time: new Date().toISOString(),
      });

      // send recent history to the new user
      try {
        const history = await fetchRecentMessages(roomId, 50);
        socket.emit("chat-history", history);
      } catch (err) {
        console.error("failed to load history", err);
      }

      // update lobby
      broadcastRooms();
    } catch (err) {
      console.error("failed to join room", err);
      socket.emit("system-message", {
        message: "Could not join room",
        userName: "system",
        time: new Date().toISOString(),
      });
    }
  });

  // receive chat messages from client
  socket.on("chat-message", async ({ roomId, userName, message }) => {
    if (!message || !message.trim()) return;

    try {
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

      try {
        await recordMessage(roomId, userName, message);
      } catch (err) {
        console.error("failed to persist message", err);
      }
    } catch (err) {
      console.error("failed to handle chat message", err);
    }
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

    console.log('[TEST] DISCONNECTED SOCKET DATA:', socket.data);
    if (roomId) {
      const room = rooms.get(roomId);
      if (room) {
        room.participants.delete(socket.id);
        room.updatedAt = Date.now();

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
