const http = require("http");
const { Server } = require("socket.io");

const PORT = 3000;

const server = http.createServer();

const io = new Server(server, {
  cors: {
    origin: process.env.CORS_ORIGIN || "http://localhost",
    methods: ["GET", "POST"],
  },
});

io.on("connection", (socket) => {
  console.log("user connected:", socket.id);

  socket.on("join-room", ({ roomId, userName }) => {
    if (!roomId) roomId = "general";

    socket.join(roomId);
    socket.data.roomId = roomId;
    socket.data.userName = userName || "guest";

    console.log(`${socket.id} joined room ${roomId} as ${socket.data.userName}`);

    socket.to(roomId).emit("system-message", {
      message: `${socket.data.userName} joined the room`,
      userName: "system",
      time: new Date().toISOString(),
    });
  });

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

    console.log("chat-message received:", payload); // debug

    io.to(roomId).emit("chat-message", payload);
  });

  socket.on("typing", ({ roomId, userName, isTyping }) => {
    roomId = roomId || socket.data.roomId || "general";
    userName = userName || socket.data.userName || "guest";

    socket.to(roomId).emit("typing", {
      userName,
      isTyping: !!isTyping,
    });
  });

  socket.on("disconnect", () => {
    const roomId = socket.data.roomId;
    const userName = socket.data.userName || "someone";

    if (roomId) {
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
