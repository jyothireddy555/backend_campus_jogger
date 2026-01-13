const express = require("express");
const http = require("http");
const { Server } = require("socket.io");
const cors = require("cors");

const app = express();
app.use(cors());

const server = http.createServer(app);

const io = new Server(server, {
  cors: {
    origin: "*",
  },
});

// Store active joggers in memory
const activeJoggers = {};

io.on("connection", (socket) => {
  console.log("User connected:", socket.id);

  // User starts jogging (REGISTER USER)
  socket.on("start_jog", (data) => {
    console.log("START_JOG:", data);

    activeJoggers[socket.id] = {
      name: data.name,
      lat: data.lat,
      lng: data.lng,
      socketId: socket.id,
    };

    io.emit("active_joggers", Object.values(activeJoggers));
  });

  // User sends LIVE location updates
  socket.on("update_location", (location) => {
    if (!activeJoggers[socket.id]) return;

    activeJoggers[socket.id].lat = location.lat;
    activeJoggers[socket.id].lng = location.lng;

    io.emit("active_joggers", Object.values(activeJoggers));
  });

  // User stops jogging
  socket.on("stop_jog", () => {
    delete activeJoggers[socket.id];
    io.emit("active_joggers", Object.values(activeJoggers));
  });

  socket.on("disconnect", () => {
    delete activeJoggers[socket.id];
    io.emit("active_joggers", Object.values(activeJoggers));
    console.log("User disconnected:", socket.id);
  });
});

server.listen(3000, () => {
  console.log("Live jog server running on port 3000");
});

