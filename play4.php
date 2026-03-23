<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sunset Beach Racer</title>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      user-select: none;
    }

    html, body {
      width: 100%;
      height: 100%;
      overflow: hidden;
      font-family: "Tahoma", sans-serif;
      background: #0b0f16;
    }

    canvas {
      display: block;
      width: 100vw;
      height: 100vh;
    }

    .hud {
      position: fixed;
      top: 18px;
      left: 18px;
      z-index: 20;
      color: #fff;
      background: rgba(8, 12, 20, 0.38);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 16px;
      padding: 14px 18px;
      min-width: 240px;
      box-shadow: 0 10px 40px rgba(0,0,0,.28);
    }

    .hud h1 {
      font-size: 18px;
      margin-bottom: 8px;
      letter-spacing: 0.5px;
    }

    .hud p {
      font-size: 14px;
      line-height: 1.8;
      color: rgba(255,255,255,0.92);
    }

    .tip {
      position: fixed;
      right: 18px;
      bottom: 18px;
      z-index: 20;
      color: #fff;
      background: rgba(8, 12, 20, 0.35);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 16px;
      padding: 12px 16px;
      font-size: 14px;
      line-height: 1.6;
      box-shadow: 0 10px 40px rgba(0,0,0,.28);
    }

    .minimap-wrap {
      position: fixed;
      top: 18px;
      right: 18px;
      z-index: 20;
      width: 220px;
      height: 220px;
      background: rgba(8, 12, 20, 0.42);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 18px;
      box-shadow: 0 10px 40px rgba(0,0,0,.28);
      padding: 10px;
    }

    .minimap-title {
      position: absolute;
      top: 10px;
      left: 14px;
      color: #fff;
      font-size: 13px;
      opacity: 0.92;
      z-index: 2;
    }

    #minimap {
      width: 100%;
      height: 100%;
      display: block;
    }

    .message {
      position: fixed;
      inset: 0;
      z-index: 30;
      display: none;
      align-items: center;
      justify-content: center;
      background: rgba(0,0,0,0.38);
    }

    .message-box {
      min-width: 320px;
      max-width: 90vw;
      text-align: center;
      color: #fff;
      background: rgba(10, 14, 22, 0.72);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.14);
      border-radius: 20px;
      padding: 28px 24px;
      box-shadow: 0 16px 60px rgba(0,0,0,.38);
    }

    .message-box h2 {
      font-size: 32px;
      margin-bottom: 10px;
    }

    .message-box p {
      font-size: 16px;
      opacity: 0.92;
      line-height: 1.8;
    }
  </style>
</head>
<body>
  <div class="hud">
    <h1>Sunset Beach Racer</h1>
    <p>ความเร็ว: <span id="speed">0</span> km/h</p>
    <p>ระยะทางรวม: <span id="distance">0</span> m</p>
    <p>รอบ: <span id="lap">0</span> / 3</p>
    <p>เป้าหมาย: ผ่านเส้นชัย 3 รอบ</p>
    <p>เวลาเย็น: <span id="timeOfDay">18:12</span></p>
  </div>

  <div class="minimap-wrap">
    <div class="minimap-title">แผนที่สนาม</div>
    <canvas id="minimap" width="220" height="220"></canvas>
  </div>

  <div class="tip">
    W / ↑ = เร่ง<br>
    S / ↓ = เบรก<br>
    A / ← = เลี้ยวซ้าย<br>
    D / → = เลี้ยวขวา
  </div>

  <div class="message" id="winMessage">
    <div class="message-box">
      <h2>ชนะแล้ว</h2>
      <p>คุณขับผ่านเส้นชัยครบ 3 รอบเรียบร้อยแล้ว</p>
      <p>กด F5 เพื่อเล่นใหม่</p>
    </div>
  </div>

  <canvas id="game"></canvas>

  <script>
    const canvas = document.getElementById("game");
    const ctx = canvas.getContext("2d");

    const minimap = document.getElementById("minimap");
    const mctx = minimap.getContext("2d");

    const speedEl = document.getElementById("speed");
    const distanceEl = document.getElementById("distance");
    const lapEl = document.getElementById("lap");
    const timeEl = document.getElementById("timeOfDay");
    const winMessage = document.getElementById("winMessage");

    function resizeCanvas() {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    }
    resizeCanvas();
    window.addEventListener("resize", resizeCanvas);

    const keys = {};
    window.addEventListener("keydown", (e) => { keys[e.key.toLowerCase()] = true; });
    window.addEventListener("keyup", (e) => { keys[e.key.toLowerCase()] = false; });

    const game = {
      roadWidth: 2800,           // กว้างขึ้น เล่นง่ายขึ้น
      segmentLength: 220,
      drawDistance: 260,
      cameraDepth: 0.82,
      playerX: 0,
      position: 0,
      prevPosition: 0,
      speed: 0,
      maxSpeed: 250,             // ลดความเร็วสูงสุดให้ง่ายขึ้น
      accel: 0.34,               // เร่งได้ง่ายขึ้น
      brake: 0.55,
      decel: 0.16,
      centrifugal: 0.00055,      // ลดแรงเหวี่ยงจากโค้ง
      lapDistance: 0,
      totalDistance: 0,
      time: 0,
      sunShift: 0,
      laps: 0,
      lapsToWin: 3,
      finished: false,
      startFinishSegment: 0
    };

    const road = [];
    const opponents = [];

    function lerp(a, b, t) {
      return a + (b - a) * t;
    }

    function clamp(v, min, max) {
      return Math.max(min, Math.min(max, v));
    }

    function easeInOut(a) {
      return (-Math.cos(a * Math.PI) / 2) + 0.5;
    }

    function addSegment(curve = 0, y = 0) {
      const n = road.length;
      road.push({
        index: n,
        p1: { world: { x: 0, y: 0, z: n * game.segmentLength }, screen: {}, scale: 0 },
        p2: { world: { x: 0, y: y, z: (n + 1) * game.segmentLength }, screen: {}, scale: 0 },
        curve,
        sprites: []
      });
    }

    function addRoad(enter, hold, leave, curve, hill = 0) {
      const startY = road.length > 0 ? road[road.length - 1].p2.world.y : 0;
      const endY = startY + Math.floor(hill) * game.segmentLength;
      const total = enter + hold + leave;

      for (let n = 0; n < enter; n++) {
        addSegment(lerp(0, curve, easeInOut(n / enter)), lerp(startY, endY, n / total));
      }
      for (let n = 0; n < hold; n++) {
        addSegment(curve, lerp(startY, endY, (enter + n) / total));
      }
      for (let n = 0; n < leave; n++) {
        addSegment(lerp(curve, 0, easeInOut(n / leave)), lerp(startY, endY, (enter + hold + n) / total));
      }
    }

    function buildTrack() {
      // ปรับให้โค้งนุ่มลง เล่นง่ายขึ้น
      addRoad(30, 120, 30, 0.0, 0);
      addRoad(30, 90, 30, 0.35, 0);
      addRoad(25, 80, 25, -0.30, 0);
      addRoad(25, 80, 25, 0.18, 0);
      addRoad(30, 100, 30, 0.42, 0);
      addRoad(30, 100, 30, -0.38, 0);
      addRoad(30, 110, 30, 0.0, 0);
      addRoad(20, 70, 20, 0.22, 0);
      addRoad(20, 70, 20, -0.24, 0);
      addRoad(25, 100, 25, 0.0, 0);
    }

    function resetSprites() {
      for (let i = 0; i < road.length; i++) {
        if (Math.random() < 0.18) {
          road[i].sprites.push({ side: -1, type: "palm", offset: 1.8 + Math.random() * 1.8, size: 1 + Math.random() * 0.8 });
        }
        if (Math.random() < 0.15) {
          road[i].sprites.push({ side: 1, type: "palm", offset: 1.9 + Math.random() * 1.7, size: 1 + Math.random() * 1.0 });
        }
        if (Math.random() < 0.05) {
          road[i].sprites.push({ side: 1, type: "rock", offset: 1.3 + Math.random() * 1.2, size: 0.8 + Math.random() * 0.6 });
        }
      }
    }

    function createOpponents() {
      for (let i = 0; i < 8; i++) {
        opponents.push({
          z: (i + 2) * 3400,
          offset: (Math.random() * 1.4 - 0.7),
          speed: 95 + Math.random() * 65,
          color: [
            "#ff4343", "#00d0ff", "#ffd447", "#ffffff",
            "#8c7bff", "#57ff9d", "#ff7bc7"
          ][Math.floor(Math.random() * 7)]
        });
      }
    }

    buildTrack();
    resetSprites();
    createOpponents();

    const trackLength = () => road.length * game.segmentLength;

    function findSegment(z) {
      return road[Math.floor(z / game.segmentLength) % road.length];
    }

    function project(point, cameraX, cameraY, cameraZ, cameraDepth, width, height, roadWidth) {
      const dz = point.world.z - cameraZ;
      const dx = point.world.x - cameraX;
      const dy = point.world.y - cameraY;

      point.scale = cameraDepth / dz;
      point.screen.x = Math.round((width / 2) + (point.scale * dx * width / 2));
      point.screen.y = Math.round((height / 2) - (point.scale * dy * height / 2));
      point.screen.w = Math.round((point.scale * roadWidth * width / 2));
    }

    function drawPolygon(x1, y1, x2, y2, x3, y3, x4, y4, color) {
      ctx.fillStyle = color;
      ctx.beginPath();
      ctx.moveTo(x1, y1);
      ctx.lineTo(x2, y2);
      ctx.lineTo(x3, y3);
      ctx.lineTo(x4, y4);
      ctx.closePath();
      ctx.fill();
    }

    function drawSky() {
      const w = canvas.width;
      const h = canvas.height;

      const sky = ctx.createLinearGradient(0, 0, 0, h);
      sky.addColorStop(0, "#0a1330");
      sky.addColorStop(0.18, "#243a78");
      sky.addColorStop(0.42, "#ff8a57");
      sky.addColorStop(0.64, "#ffb77e");
      sky.addColorStop(0.82, "#ffd8a8");
      sky.addColorStop(1, "#ffe8c8");
      ctx.fillStyle = sky;
      ctx.fillRect(0, 0, w, h);

      const sunX = w * 0.75;
      const sunY = h * 0.26 + Math.sin(game.sunShift) * 6;
      const sunR = Math.min(w, h) * 0.08;

      const glow = ctx.createRadialGradient(sunX, sunY, 0, sunX, sunY, sunR * 2.6);
      glow.addColorStop(0, "rgba(255,236,184,0.95)");
      glow.addColorStop(0.35, "rgba(255,180,100,0.55)");
      glow.addColorStop(1, "rgba(255,120,50,0)");
      ctx.fillStyle = glow;
      ctx.beginPath();
      ctx.arc(sunX, sunY, sunR * 2.6, 0, Math.PI * 2);
      ctx.fill();

      const core = ctx.createRadialGradient(sunX, sunY, 0, sunX, sunY, sunR);
      core.addColorStop(0, "#fff8dd");
      core.addColorStop(0.45, "#ffd08a");
      core.addColorStop(1, "#ff9f50");
      ctx.fillStyle = core;
      ctx.beginPath();
      ctx.arc(sunX, sunY, sunR, 0, Math.PI * 2);
      ctx.fill();

      for (let i = 0; i < 7; i++) {
        const cloudX = (w * 0.1) + i * (w * 0.13) + Math.sin(game.time * 0.00015 + i) * 20;
        const cloudY = h * (0.1 + (i % 3) * 0.06);
        drawCloud(cloudX, cloudY, 50 + (i % 3) * 18, "rgba(255,255,255,0.10)");
      }
    }

    function drawCloud(x, y, size, color) {
      ctx.fillStyle = color;
      ctx.beginPath();
      ctx.arc(x, y, size * 0.55, 0, Math.PI * 2);
      ctx.arc(x + size * 0.45, y + 5, size * 0.42, 0, Math.PI * 2);
      ctx.arc(x - size * 0.4, y + 12, size * 0.36, 0, Math.PI * 2);
      ctx.arc(x + size * 0.1, y + 16, size * 0.48, 0, Math.PI * 2);
      ctx.fill();
    }

    function drawSeaAndBeach(horizonY) {
      const w = canvas.width;
      const h = canvas.height;

      const seaTop = horizonY - 8;
      const seaBottom = horizonY + h * 0.14;

      const seaGrad = ctx.createLinearGradient(0, seaTop, 0, seaBottom);
      seaGrad.addColorStop(0, "#ffb78a");
      seaGrad.addColorStop(0.12, "#ff9b67");
      seaGrad.addColorStop(0.4, "#2d7ea5");
      seaGrad.addColorStop(1, "#114e73");
      ctx.fillStyle = seaGrad;
      ctx.fillRect(0, seaTop, w, seaBottom - seaTop);

      ctx.fillStyle = "rgba(255,220,170,0.18)";
      for (let i = 0; i < 18; i++) {
        const yy = seaTop + i * 7;
        const amp = 18 + i;
        ctx.fillRect(
          w * 0.55 + Math.sin(game.time * 0.001 + i) * 18 - amp,
          yy,
          amp * 2,
          1.5
        );
      }

      const sandGrad = ctx.createLinearGradient(0, seaBottom, 0, h);
      sandGrad.addColorStop(0, "#e1b27d");
      sandGrad.addColorStop(0.5, "#cf9866");
      sandGrad.addColorStop(1, "#8f5f39");
      ctx.fillStyle = sandGrad;
      ctx.fillRect(0, seaBottom, w, h - seaBottom);
    }

    function drawPalm(x, y, scale, flip = 1, alpha = 1) {
      ctx.save();
      ctx.translate(x, y);
      ctx.scale(scale * flip, scale);
      ctx.globalAlpha = alpha;

      ctx.strokeStyle = "rgba(20,12,10,0.95)";
      ctx.lineWidth = 9;
      ctx.lineCap = "round";
      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.quadraticCurveTo(14, -50, 4, -110);
      ctx.stroke();

      for (let i = 0; i < 6; i++) {
        const angle = -1.8 + i * 0.34;
        ctx.strokeStyle = "rgba(20,30,18,0.98)";
        ctx.lineWidth = 5;
        ctx.beginPath();
        ctx.moveTo(4, -108);
        ctx.quadraticCurveTo(
          Math.cos(angle) * 22,
          -126 + Math.sin(angle) * 8,
          Math.cos(angle) * 55,
          -110 + Math.sin(angle) * 15
        );
        ctx.stroke();
      }

      ctx.restore();
    }

    function drawMountainLayer(horizonY) {
      const w = canvas.width;
      const h = canvas.height;

      ctx.fillStyle = "rgba(40,30,55,0.55)";
      ctx.beginPath();
      ctx.moveTo(0, horizonY + 20);
      for (let x = 0; x <= w; x += 80) {
        const y = horizonY + 15 - Math.sin(x * 0.01) * 14 - Math.cos(x * 0.02) * 10;
        ctx.lineTo(x, y);
      }
      ctx.lineTo(w, h);
      ctx.lineTo(0, h);
      ctx.closePath();
      ctx.fill();
    }

    function drawBackground(horizonY) {
      drawSky();
      drawSeaAndBeach(horizonY);
      drawMountainLayer(horizonY);

      for (let i = 0; i < 8; i++) {
        const px = i * (canvas.width / 7) + Math.sin(game.time * 0.0002 + i) * 15;
        drawPalm(px, horizonY + 110 + (i % 2) * 18, 0.9 + (i % 3) * 0.25, i % 2 === 0 ? 1 : -1, 0.75);
      }
    }

    function renderRoad() {
      const baseSegment = findSegment(game.position);
      const baseIndex = baseSegment.index;
      const cameraHeight = 920;
      const cameraZ = game.position - (game.cameraDepth * game.segmentLength);
      let x = 0;
      let dx = -(baseSegment.curve * (game.position % game.segmentLength) / game.segmentLength);
      let maxY = canvas.height;

      const horizonY = canvas.height * 0.42;
      drawBackground(horizonY);

      for (let n = 0; n < game.drawDistance; n++) {
        const segment = road[(baseIndex + n) % road.length];
        segment.looped = segment.index < baseIndex;
        segment.fog = n / game.drawDistance;

        project(
          segment.p1,
          (game.playerX * game.roadWidth) - x,
          cameraHeight,
          cameraZ - (segment.looped ? road.length * game.segmentLength : 0),
          game.cameraDepth,
          canvas.width,
          canvas.height,
          game.roadWidth
        );

        project(
          segment.p2,
          (game.playerX * game.roadWidth) - x - dx,
          cameraHeight,
          cameraZ - (segment.looped ? road.length * game.segmentLength : 0),
          game.cameraDepth,
          canvas.width,
          canvas.height,
          game.roadWidth
        );

        x += dx;
        dx += segment.curve;

        if (segment.p1.screen.y >= maxY || segment.p2.screen.y >= maxY || segment.p2.screen.y >= segment.p1.screen.y) {
          continue;
        }

        const isStartFinish = segment.index === game.startFinishSegment;

        const grassColor = n % 2 ? "#a36b43" : "#b6784d";
        const rumbleColor = n % 2 ? "#f8e1d0" : "#d74444";
        const laneColor = "rgba(255,255,255,0.78)";
        const roadColor = n % 2 ? "#373d43" : "#31373d";
        const shoulderColor = n % 2 ? "#d8b181" : "#c89c69";

        drawPolygon(
          0, segment.p2.screen.y,
          canvas.width, segment.p2.screen.y,
          canvas.width, segment.p1.screen.y,
          0, segment.p1.screen.y,
          grassColor
        );

        drawPolygon(
          segment.p1.screen.x - segment.p1.screen.w * 1.25, segment.p1.screen.y,
          segment.p1.screen.x - segment.p1.screen.w, segment.p1.screen.y,
          segment.p2.screen.x - segment.p2.screen.w, segment.p2.screen.y,
          segment.p2.screen.x - segment.p2.screen.w * 1.25, segment.p2.screen.y,
          shoulderColor
        );

        drawPolygon(
          segment.p1.screen.x + segment.p1.screen.w, segment.p1.screen.y,
          segment.p1.screen.x + segment.p1.screen.w * 1.25, segment.p1.screen.y,
          segment.p2.screen.x + segment.p2.screen.w * 1.25, segment.p2.screen.y,
          segment.p2.screen.x + segment.p2.screen.w, segment.p2.screen.y,
          shoulderColor
        );

        drawPolygon(
          segment.p1.screen.x - segment.p1.screen.w, segment.p1.screen.y,
          segment.p1.screen.x - segment.p1.screen.w * 0.94, segment.p1.screen.y,
          segment.p2.screen.x - segment.p2.screen.w * 0.94, segment.p2.screen.y,
          segment.p2.screen.x - segment.p2.screen.w, segment.p2.screen.y,
          rumbleColor
        );

        drawPolygon(
          segment.p1.screen.x + segment.p1.screen.w * 0.94, segment.p1.screen.y,
          segment.p1.screen.x + segment.p1.screen.w, segment.p1.screen.y,
          segment.p2.screen.x + segment.p2.screen.w, segment.p2.screen.y,
          segment.p2.screen.x + segment.p2.screen.w * 0.94, segment.p2.screen.y,
          rumbleColor
        );

        drawPolygon(
          segment.p1.screen.x - segment.p1.screen.w * 0.94, segment.p1.screen.y,
          segment.p1.screen.x + segment.p1.screen.w * 0.94, segment.p1.screen.y,
          segment.p2.screen.x + segment.p2.screen.w * 0.94, segment.p2.screen.y,
          segment.p2.screen.x - segment.p2.screen.w * 0.94, segment.p2.screen.y,
          roadColor
        );

        if (isStartFinish) {
          drawStartFinishLine(segment);
          drawStartFinishBanner(segment);
        }

        const laneW1 = segment.p1.screen.w * 1.88 / 6;
        const laneW2 = segment.p2.screen.w * 1.88 / 6;

        for (let lane = 1; lane <= 2; lane++) {
          const lanex1 = segment.p1.screen.x - segment.p1.screen.w * 0.94 + lane * laneW1 * 2;
          const lanex2 = segment.p2.screen.x - segment.p2.screen.w * 0.94 + lane * laneW2 * 2;

          drawPolygon(
            lanex1 - 2, segment.p1.screen.y,
            lanex1 + 2, segment.p1.screen.y,
            lanex2 + 2, segment.p2.screen.y,
            lanex2 - 2, segment.p2.screen.y,
            laneColor
          );
        }

        renderRoadsideSprites(segment);
        maxY = segment.p2.screen.y;
      }

      renderOpponents(baseIndex);
      drawPlayerCar();
      drawLapHint();
    }

    function drawStartFinishLine(segment) {
      const left1 = segment.p1.screen.x - segment.p1.screen.w * 0.92;
      const right1 = segment.p1.screen.x + segment.p1.screen.w * 0.92;
      const left2 = segment.p2.screen.x - segment.p2.screen.w * 0.92;
      const right2 = segment.p2.screen.x + segment.p2.screen.w * 0.92;

      const pieces = 12;
      for (let i = 0; i < pieces; i++) {
        const t1 = i / pieces;
        const t2 = (i + 1) / pieces;
        const xa1 = lerp(left1, right1, t1);
        const xb1 = lerp(left1, right1, t2);
        const xa2 = lerp(left2, right2, t1);
        const xb2 = lerp(left2, right2, t2);

        drawPolygon(
          xa1, segment.p1.screen.y,
          xb1, segment.p1.screen.y,
          xb2, segment.p2.screen.y,
          xa2, segment.p2.screen.y,
          i % 2 === 0 ? "#ffffff" : "#111111"
        );
      }
    }

    function drawStartFinishBanner(segment) {
      const topY = segment.p1.screen.y - 120;
      const leftPoleX = segment.p1.screen.x - segment.p1.screen.w * 0.82;
      const rightPoleX = segment.p1.screen.x + segment.p1.screen.w * 0.82;

      ctx.strokeStyle = "#1e2026";
      ctx.lineWidth = 8;
      ctx.beginPath();
      ctx.moveTo(leftPoleX, segment.p1.screen.y);
      ctx.lineTo(leftPoleX, topY);
      ctx.moveTo(rightPoleX, segment.p1.screen.y);
      ctx.lineTo(rightPoleX, topY);
      ctx.stroke();

      ctx.fillStyle = "rgba(20,24,30,0.95)";
      roundRect(leftPoleX, topY - 24, rightPoleX - leftPoleX, 28, 8);
      ctx.fill();

      ctx.fillStyle = "#ffffff";
      ctx.font = "bold 16px Tahoma";
      ctx.textAlign = "center";
      ctx.fillText("START / FINISH", (leftPoleX + rightPoleX) / 2, topY - 5);
      ctx.textAlign = "left";
    }

    function drawLapHint() {
      if (game.finished) return;
      ctx.save();
      ctx.fillStyle = "rgba(255,255,255,0.9)";
      ctx.font = "bold 20px Tahoma";
      ctx.textAlign = "center";
      ctx.fillText(`รอบ ${game.laps} / ${game.lapsToWin}`, canvas.width / 2, 46);
      ctx.restore();
    }

    function renderRoadsideSprites(segment) {
      const p = segment.p2;
      for (const s of segment.sprites) {
        const scale = p.scale * 220 * s.size;
        const x = p.screen.x + p.screen.w * s.offset * s.side;
        const y = p.screen.y;

        if (scale < 2) continue;

        if (s.type === "palm") {
          drawPalmShadow(x, y, scale * 0.006, s.side);
          drawPalm(x, y, scale * 0.006, s.side, 0.96);
        } else if (s.type === "rock") {
          drawRock(x, y, scale * 0.006);
        }
      }
    }

    function drawPalmShadow(x, y, scale, flip = 1) {
      ctx.save();
      ctx.translate(x + 18 * flip, y + 4);
      ctx.scale(scale * flip * 1.35, scale * 0.4);
      ctx.fillStyle = "rgba(0,0,0,0.18)";
      ctx.beginPath();
      ctx.ellipse(0, 0, 90, 22, -0.2, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }

    function drawRock(x, y, scale) {
      ctx.save();
      ctx.translate(x, y);
      ctx.scale(scale, scale);
      ctx.fillStyle = "#66564b";
      ctx.beginPath();
      ctx.moveTo(-18, 0);
      ctx.quadraticCurveTo(-26, -12, -10, -18);
      ctx.quadraticCurveTo(8, -24, 20, -10);
      ctx.quadraticCurveTo(24, -2, 16, 4);
      ctx.quadraticCurveTo(0, 12, -18, 0);
      ctx.fill();
      ctx.restore();
    }

    function renderOpponents(baseIndex) {
      for (const car of opponents) {
        let dz = car.z - game.position;
        while (dz < 0) dz += trackLength();

        if (dz > game.drawDistance * game.segmentLength) continue;

        const segment = findSegment(car.z);
        const percent = (car.z % game.segmentLength) / game.segmentLength;
        const p1 = segment.p1.screen;
        const p2 = segment.p2.screen;
        const scale = lerp(segment.p1.scale, segment.p2.scale, percent);

        const roadCenter = lerp(p1.x, p2.x, percent);
        const roadHalf = lerp(segment.p1.screen.w, segment.p2.screen.w, percent) * 0.82;
        const x = roadCenter + roadHalf * car.offset;
        const y = lerp(p1.y, p2.y, percent);

        drawOpponentCar(x, y, scale, car.color);
      }
    }

    function drawOpponentCar(x, y, scale, color) {
      const s = Math.max(0.12, scale * 1.15);
      const w = 120 * s;
      const h = 190 * s;

      ctx.save();
      ctx.translate(x, y);

      ctx.fillStyle = "rgba(0,0,0,0.25)";
      ctx.beginPath();
      ctx.ellipse(0, 18 * s, 55 * s, 22 * s, 0, 0, Math.PI * 2);
      ctx.fill();

      const body = ctx.createLinearGradient(-w * 0.5, 0, w * 0.5, h);
      body.addColorStop(0, lightenColor(color, 0.28));
      body.addColorStop(0.5, color);
      body.addColorStop(1, darkenColor(color, 0.35));
      ctx.fillStyle = body;

      roundRect(-w * 0.44, -h * 0.42, w * 0.88, h * 0.96, 18 * s);
      ctx.fill();

      ctx.fillStyle = "rgba(255,255,255,0.17)";
      roundRect(-w * 0.29, -h * 0.28, w * 0.58, h * 0.22, 12 * s);
      ctx.fill();

      ctx.fillStyle = "#14171d";
      roundRect(-w * 0.34, h * 0.12, w * 0.68, h * 0.18, 10 * s);
      ctx.fill();

      ctx.fillStyle = "#0e0f13";
      ctx.fillRect(-w * 0.46, -h * 0.26, 18 * s, 46 * s);
      ctx.fillRect(w * 0.31, -h * 0.26, 18 * s, 46 * s);
      ctx.fillRect(-w * 0.46, h * 0.16, 18 * s, 46 * s);
      ctx.fillRect(w * 0.31, h * 0.16, 18 * s, 46 * s);

      ctx.fillStyle = "#ffe9a9";
      ctx.fillRect(-w * 0.2, -h * 0.34, w * 0.13, 9 * s);
      ctx.fillRect(w * 0.07, -h * 0.34, w * 0.13, 9 * s);

      ctx.fillStyle = "#ff5050";
      ctx.fillRect(-w * 0.2, h * 0.41, w * 0.13, 10 * s);
      ctx.fillRect(w * 0.07, h * 0.41, w * 0.13, 10 * s);

      ctx.restore();
    }

    function drawPlayerCar() {
      const x = canvas.width / 2 + (canvas.width * 0.18) * game.playerX;
      const y = canvas.height * 0.82;

      ctx.save();
      ctx.translate(x, y);

      const steer = clamp((keys["a"] || keys["arrowleft"] ? -1 : 0) + (keys["d"] || keys["arrowright"] ? 1 : 0), -1, 1);
      ctx.rotate(steer * 0.02);

      const shadowGrad = ctx.createRadialGradient(0, 44, 30, 0, 44, 120);
      shadowGrad.addColorStop(0, "rgba(0,0,0,0.38)");
      shadowGrad.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = shadowGrad;
      ctx.beginPath();
      ctx.ellipse(0, 46, 122, 40, 0, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = "rgba(255,180,80,0.12)";
      ctx.beginPath();
      ctx.moveTo(-120, 65);
      ctx.lineTo(-18, -65);
      ctx.lineTo(18, -65);
      ctx.lineTo(120, 65);
      ctx.closePath();
      ctx.fill();

      const carBody = ctx.createLinearGradient(-120, -60, 120, 140);
      carBody.addColorStop(0, "#ffffff");
      carBody.addColorStop(0.22, "#d7dce5");
      carBody.addColorStop(0.55, "#7b808c");
      carBody.addColorStop(1, "#2d323b");
      ctx.fillStyle = carBody;

      roundRect(-86, -104, 172, 238, 34);
      ctx.fill();

      const centerGlow = ctx.createLinearGradient(0, -95, 0, 115);
      centerGlow.addColorStop(0, "rgba(255,140,90,0.62)");
      centerGlow.addColorStop(0.3, "rgba(255,90,70,0.22)");
      centerGlow.addColorStop(1, "rgba(255,255,255,0)");
      ctx.fillStyle = centerGlow;
      roundRect(-18, -96, 36, 205, 16);
      ctx.fill();

      ctx.fillStyle = "#11161d";
      roundRect(-58, -48, 116, 86, 18);
      ctx.fill();

      const glass = ctx.createLinearGradient(-40, -48, 40, 38);
      glass.addColorStop(0, "rgba(255,255,255,0.75)");
      glass.addColorStop(0.18, "rgba(180,230,255,0.45)");
      glass.addColorStop(1, "rgba(15,20,28,0.95)");
      ctx.fillStyle = glass;
      roundRect(-48, -40, 96, 70, 14);
      ctx.fill();

      ctx.fillStyle = "#050607";
      roundRect(-96, -72, 18, 55, 8);
      roundRect(78, -72, 18, 55, 8);
      roundRect(-96, 52, 18, 55, 8);
      roundRect(78, 52, 18, 55, 8);
      ctx.fill();

      const rimGrad = ctx.createRadialGradient(0, 0, 2, 0, 0, 12);
      rimGrad.addColorStop(0, "#c9ccd3");
      rimGrad.addColorStop(1, "#4f555e");
      ctx.fillStyle = rimGrad;
      drawWheelRim(-87, -46, 12);
      drawWheelRim(87, -46, 12);
      drawWheelRim(-87, 78, 12);
      drawWheelRim(87, 78, 12);

      ctx.fillStyle = "#ffe19a";
      roundRect(-42, -95, 26, 10, 4);
      roundRect(16, -95, 26, 10, 4);
      ctx.fill();

      ctx.fillStyle = "#ff493f";
      roundRect(-44, 118, 28, 11, 4);
      roundRect(16, 118, 28, 11, 4);
      ctx.fill();

      ctx.fillStyle = "rgba(255,255,255,0.22)";
      roundRect(-62, -90, 32, 18, 8);
      ctx.fill();

      ctx.restore();
    }

    function drawWheelRim(x, y, r) {
      ctx.beginPath();
      ctx.arc(x, y, r, 0, Math.PI * 2);
      ctx.fill();
    }

    function roundRect(x, y, w, h, r) {
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.arcTo(x + w, y, x + w, y + h, r);
      ctx.arcTo(x + w, y + h, x, y + h, r);
      ctx.arcTo(x, y + h, x, y, r);
      ctx.arcTo(x, y, x + w, y, r);
      ctx.closePath();
    }

    function lightenColor(hex, amt) {
      const c = hexToRgb(hex);
      const r = Math.round(c.r + (255 - c.r) * amt);
      const g = Math.round(c.g + (255 - c.g) * amt);
      const b = Math.round(c.b + (255 - c.b) * amt);
      return `rgb(${r},${g},${b})`;
    }

    function darkenColor(hex, amt) {
      const c = hexToRgb(hex);
      const r = Math.round(c.r * (1 - amt));
      const g = Math.round(c.g * (1 - amt));
      const b = Math.round(c.b * (1 - amt));
      return `rgb(${r},${g},${b})`;
    }

    function hexToRgb(hex) {
      const clean = hex.replace("#", "");
      const bigint = parseInt(clean, 16);
      return {
        r: (bigint >> 16) & 255,
        g: (bigint >> 8) & 255,
        b: bigint & 255
      };
    }

    function update(dt) {
      if (game.finished) {
        speedEl.textContent = 0;
        return;
      }

      const accelerating = keys["w"] || keys["arrowup"];
      const braking = keys["s"] || keys["arrowdown"];
      const left = keys["a"] || keys["arrowleft"];
      const right = keys["d"] || keys["arrowright"];

      game.prevPosition = game.position;

      if (accelerating) {
        game.speed += game.accel * 100 * dt;
      } else if (braking) {
        game.speed -= game.brake * 100 * dt;
      } else {
        game.speed -= game.decel * 100 * dt;
      }

      game.speed = clamp(game.speed, 0, game.maxSpeed);

      const speedPercent = game.speed / game.maxSpeed;
      const playerTurnSpeed = 2.9 * dt * (0.8 + speedPercent * 0.5);

      if (left) game.playerX -= playerTurnSpeed;
      if (right) game.playerX += playerTurnSpeed;

      const playerSegment = findSegment(game.position + game.cameraDepth * game.segmentLength);

      game.playerX -= playerSegment.curve * game.centrifugal * game.speed * 0.30;

      // ช่วยดึงรถกลับเข้ากลางอัตโนมัตินิดหน่อย ถ้าไม่ได้กดเลี้ยว
      if (!left && !right) {
        game.playerX *= (1 - 1.7 * dt);
      }

      // ออกนอกถนนมากไปจะช้าลง แต่ยังคุมรถได้
      if (Math.abs(game.playerX) > 1.02) {
        game.speed -= 40 * dt;
      }

      game.playerX = clamp(game.playerX, -1.18, 1.18);

      const move = game.speed * 40 * dt;
      game.position += move;
      game.lapDistance += move;
      game.totalDistance += move;
      game.time += dt * 1000;
      game.sunShift += dt * 0.45;

      const totalTrackLength = trackLength();

      if (game.position >= totalTrackLength) {
        game.position -= totalTrackLength;
      }

      // นับรอบตอนข้ามเส้นชัย
      if (game.prevPosition > game.position) {
        game.laps++;
        if (game.laps >= game.lapsToWin) {
          game.laps = game.lapsToWin;
          game.finished = true;
          game.speed = 0;
          winMessage.style.display = "flex";
        }
      }

      for (const car of opponents) {
        car.z += car.speed * 28 * dt;
        while (car.z >= totalTrackLength) car.z -= totalTrackLength;

        const dzRaw = car.z - game.position;
        let wrapped = dzRaw;
        if (wrapped < -totalTrackLength / 2) wrapped += totalTrackLength;
        if (wrapped > totalTrackLength / 2) wrapped -= totalTrackLength;

        // หลบผู้เล่นแบบนุ่ม ๆ
        if (Math.abs(wrapped) < 700 && Math.abs(car.offset - game.playerX) < 0.22) {
          if (wrapped > 0) {
            if (car.offset >= game.playerX) car.offset += 0.28 * dt;
            else car.offset -= 0.28 * dt;
          }
        }

        car.offset += Math.sin(game.time * 0.0004 + car.z * 0.001) * 0.002;
        car.offset = clamp(car.offset, -0.78, 0.78);
      }

      speedEl.textContent = Math.round(game.speed);
      distanceEl.textContent = Math.round(game.totalDistance);
      lapEl.textContent = game.laps;
      timeEl.textContent = "18:12";
    }

    function drawMiniMap() {
      const w = minimap.width;
      const h = minimap.height;
      const cx = w / 2;
      const cy = h / 2 + 8;

      mctx.clearRect(0, 0, w, h);

      mctx.fillStyle = "rgba(255,255,255,0.04)";
      mctx.beginPath();
      mctx.roundRect?.(8, 8, w - 16, h - 16, 18);
      mctx.fill();

      // วงสนาม
      mctx.strokeStyle = "rgba(255,255,255,0.18)";
      mctx.lineWidth = 22;
      mctx.beginPath();
      mctx.ellipse(cx, cy, 68, 48, -0.25, 0, Math.PI * 2);
      mctx.stroke();

      mctx.strokeStyle = "rgba(255,190,120,0.9)";
      mctx.lineWidth = 3;
      mctx.beginPath();
      mctx.ellipse(cx, cy, 68, 48, -0.25, 0, Math.PI * 2);
      mctx.stroke();

      // เส้น start/finish
      const finishA = -Math.PI / 2;
      const fx1 = cx + Math.cos(finishA - 0.25) * 68;
      const fy1 = cy + Math.sin(finishA - 0.25) * 48;
      const fx2 = cx + Math.cos(finishA - 0.25) * 48;
      const fy2 = cy + Math.sin(finishA - 0.25) * 34;

      mctx.strokeStyle = "#ffffff";
      mctx.lineWidth = 4;
      mctx.beginPath();
      mctx.moveTo(fx1, fy1);
      mctx.lineTo(fx2, fy2);
      mctx.stroke();

      // รถผู้เล่น
      drawMapDot(game.position / trackLength(), "#61f2ff", 6);

      // คู่แข่ง
      for (const car of opponents) {
        drawMapDot(car.z / trackLength(), car.color, 4);
      }

      function drawMapDot(progress, color, r) {
        const ang = progress * Math.PI * 2 - Math.PI / 2;
        const px = cx + Math.cos(ang) * 68;
        const py = cy + Math.sin(ang) * 48;

        mctx.fillStyle = color;
        mctx.beginPath();
        mctx.arc(px, py, r, 0, Math.PI * 2);
        mctx.fill();

        mctx.fillStyle = "rgba(255,255,255,0.35)";
        mctx.beginPath();
        mctx.arc(px - r * 0.25, py - r * 0.25, r * 0.35, 0, Math.PI * 2);
        mctx.fill();
      }

      mctx.fillStyle = "#ffffff";
      mctx.font = "12px Tahoma";
      mctx.textAlign = "center";
      mctx.fillText(`รอบ ${game.laps}/${game.lapsToWin}`, cx, h - 16);
      mctx.textAlign = "left";
    }

    let last = performance.now();
    function frame(now) {
      const dt = Math.min(0.033, (now - last) / 1000);
      last = now;

      update(dt);
      renderRoad();
      drawMiniMap();

      requestAnimationFrame(frame);
    }

    requestAnimationFrame(frame);
  </script>
</body>
</html>