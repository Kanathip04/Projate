<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>WARZONE STRIKE 2D</title>
  <style>
    *{
      box-sizing:border-box;
      margin:0;
      padding:0;
    }

    body{
      font-family:"Tahoma", sans-serif;
      background:
        radial-gradient(circle at top, rgba(255,120,50,0.08), transparent 25%),
        radial-gradient(circle at bottom, rgba(255,0,0,0.08), transparent 35%),
        linear-gradient(180deg, #050608 0%, #0e1218 50%, #1b1e24 100%);
      overflow:hidden;
      color:#fff;
    }

    .top-actions{
      position:fixed;
      top:18px;
      left:18px;
      right:18px;
      z-index:50;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      pointer-events:none;
    }

    .top-left,
    .top-right{
      display:flex;
      gap:10px;
      pointer-events:auto;
    }

    .nav-btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      min-width:150px;
      padding:12px 20px;
      border-radius:12px;
      text-decoration:none;
      font-weight:700;
      color:#fff;
      background:linear-gradient(135deg, #6f8f10, #88a91a);
      box-shadow:0 8px 24px rgba(0,0,0,.28);
      border:1px solid rgba(255,255,255,.12);
      transition:.2s;
    }

    .nav-btn:hover{
      transform:translateY(-2px);
      filter:brightness(1.05);
    }

    .reset-btn{
      border:none;
      cursor:pointer;
      font-family:inherit;
    }

    .game-shell{
      width:100%;
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:18px;
    }

    .game-wrap{
      width:1200px;
      max-width:100%;
      position:relative;
      margin-top:38px;
    }

    canvas{
      width:100%;
      height:auto;
      display:block;
      border:3px solid #6d5b35;
      border-radius:18px;
      background:#111;
      box-shadow:
        0 25px 60px rgba(0, 0, 0, 0.55),
        inset 0 0 60px rgba(255, 140, 50, 0.04);
    }

    .hud-top{
      position:absolute;
      top:16px;
      left:18px;
      right:18px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      pointer-events:none;
      z-index:5;
    }

    .player-box{
      width:36%;
    }

    .player-name{
      font-size:14px;
      letter-spacing:1.2px;
      margin-bottom:6px;
      text-shadow:0 2px 8px rgba(0,0,0,.5);
    }

    .hp-bar{
      height:22px;
      background:rgba(255,255,255,.08);
      border:2px solid rgba(255,255,255,.18);
      border-radius:999px;
      overflow:hidden;
      box-shadow:inset 0 0 12px rgba(0,0,0,.45);
    }

    .hp-fill{
      height:100%;
      width:100%;
      background:linear-gradient(90deg, #43b84a, #c9dd4a, #ff7333);
      transition:width .12s linear;
      box-shadow:inset 0 0 10px rgba(255,255,255,.15);
    }

    .center-box{
      width:160px;
      text-align:center;
    }

    .timer{
      font-size:38px;
      font-weight:bold;
      color:#f5e6b6;
      text-shadow:0 4px 12px rgba(0,0,0,.65);
    }

    .round-text{
      font-size:13px;
      color:#ddd;
      letter-spacing:2px;
    }

    .info-panel{
      margin-top:14px;
      background:rgba(10, 10, 10, 0.62);
      border:1px solid rgba(255,255,255,.08);
      padding:12px 16px;
      border-radius:14px;
      color:#d7d7d7;
      line-height:1.8;
      box-shadow:0 8px 30px rgba(0,0,0,.35);
    }

    .info-panel b{
      color:#fff1bf;
    }

    .overlay{
      position:absolute;
      inset:0;
      display:flex;
      align-items:center;
      justify-content:center;
      z-index:10;
    }

    .overlay-box{
      text-align:center;
      padding:28px 36px;
      border-radius:18px;
      background:rgba(10,10,10,.55);
      border:1px solid rgba(255,255,255,.12);
      backdrop-filter:blur(4px);
      box-shadow:0 10px 40px rgba(0,0,0,.45);
    }

    .overlay-box h1{
      font-size:48px;
      margin-bottom:10px;
      color:#ffe8b0;
      text-shadow:0 4px 14px rgba(0,0,0,.6);
    }

    .overlay-box p{
      font-size:18px;
      color:#ddd;
      margin-bottom:14px;
    }

    .start-btn{
      border:none;
      cursor:pointer;
      font-family:inherit;
      padding:12px 24px;
      border-radius:12px;
      font-weight:700;
      color:#fff;
      background:linear-gradient(135deg, #b76a24, #db8c36);
      box-shadow:0 8px 22px rgba(0,0,0,.28);
      transition:.2s;
    }

    .start-btn:hover{
      transform:translateY(-2px);
      filter:brightness(1.05);
    }

    @media (max-width: 768px){
      .timer{ font-size:28px; }
      .overlay-box h1{ font-size:34px; }
      .info-panel{ font-size:14px; }
      .nav-btn{ min-width:unset; padding:10px 14px; font-size:14px; }
      .top-actions{ top:12px; left:12px; right:12px; }
      .game-wrap{ margin-top:56px; }
    }
  </style>
</head>
<body>

  <div class="top-actions">
    <div class="top-left">
      <a href="game.php" class="nav-btn">← กลับหน้าเกม</a>
    </div>
    <div class="top-right">
      <button class="nav-btn reset-btn" id="topResetBtn">เริ่มใหม่</button>
    </div>
  </div>

  <div class="game-shell">
    <div class="game-wrap">
      <div class="hud-top">
        <div class="player-box">
          <div class="player-name">PLAYER 1 — COMMANDER REX</div>
          <div class="hp-bar"><div class="hp-fill" id="p1hp"></div></div>
        </div>

        <div class="center-box">
          <div class="timer" id="timer">90</div>
          <div class="round-text">WARZONE STRIKE</div>
        </div>

        <div class="player-box">
          <div class="player-name" style="text-align:right;">ENEMY — IRON WRAITH</div>
          <div class="hp-bar"><div class="hp-fill" id="p2hp"></div></div>
        </div>
      </div>

      <canvas id="game" width="1200" height="650"></canvas>

      <div class="overlay" id="overlay">
        <div class="overlay-box">
          <h1>WARZONE STRIKE 2D</h1>
          <p>กดเริ่มเกม หรือกดปุ่มคีย์บอร์ดเพื่อเริ่มเล่น</p>
          <button class="start-btn" id="startBtn">เริ่มเกม</button>
        </div>
      </div>

      <div class="info-panel">
        <b>ปุ่มควบคุม:</b>
        A = ซ้าย, D = ขวา, W = กระโดด, J = ต่อย, K = เตะ, L = ป้องกัน, U = ท่าพิเศษ, R = เริ่มใหม่
      </div>
    </div>
  </div>

  <script>
    const canvas = document.getElementById("game");
    const ctx = canvas.getContext("2d");

    const p1hpEl = document.getElementById("p1hp");
    const p2hpEl = document.getElementById("p2hp");
    const timerEl = document.getElementById("timer");
    const overlay = document.getElementById("overlay");
    const startBtn = document.getElementById("startBtn");
    const topResetBtn = document.getElementById("topResetBtn");

    const WIDTH = canvas.width;
    const HEIGHT = canvas.height;
    const GROUND_Y = 560;

    const keys = Object.create(null);

    const GAME_SETTINGS = {
      startTime: 90,
      maxDelta: 33,
      playerPunchDamage: 8,
      playerKickDamage: 12,
      playerSpecialDamage: 18,
      enemyPunchDamage: 4,
      enemyKickDamage: 7,
      enemySpecialDamage: 11
    };

    let gameOver = false;
    let gameStarted = false;
    let roundTime = GAME_SETTINGS.startTime;
    let timerAccumulator = 0;
    let lastTime = null;
    let screenShake = 0;
    let smokeParticles = [];
    let impactParticles = [];

    function startGame() {
      gameStarted = true;
      overlay.style.display = "none";
    }

    startBtn.addEventListener("click", startGame);
    topResetBtn.addEventListener("click", resetGame);

    document.addEventListener("keydown", (e) => {
      const key = e.key.toLowerCase();
      keys[key] = true;

      if (["a", "d", "w", "j", "k", "l", "u", "r"].includes(key)) {
        e.preventDefault();
      }

      if (!gameStarted && key !== "r") {
        startGame();
      }

      if (key === "r") {
        resetGame();
      }
    });

    document.addEventListener("keyup", (e) => {
      keys[e.key.toLowerCase()] = false;
    });

    class Fighter {
      constructor(options) {
        this.name = options.name;
        this.startX = options.x;
        this.x = options.x;
        this.y = GROUND_Y;
        this.w = 68;
        this.h = 150;
        this.color = options.color;
        this.trim = options.trim;
        this.direction = options.direction;
        this.isPlayer = options.isPlayer || false;

        this.vx = 0;
        this.vy = 0;
        this.speed = options.speed;
        this.jumpForce = options.jumpForce;
        this.gravity = 0.9;

        this.health = 100;
        this.maxHealth = 100;
        this.blocking = false;
        this.attacking = false;
        this.attackType = "";
        this.attackTimer = 0;
        this.attackHitDone = false;
        this.hitCooldown = 0;
        this.onGround = true;
        this.dead = false;
        this.aiTimer = 0;
        this.specialCooldown = 0;
        this.flashTimer = 0;
        this.stun = 0;
      }

      reset() {
        this.x = this.startX;
        this.y = GROUND_Y;
        this.vx = 0;
        this.vy = 0;
        this.health = 100;
        this.blocking = false;
        this.attacking = false;
        this.attackType = "";
        this.attackTimer = 0;
        this.attackHitDone = false;
        this.hitCooldown = 0;
        this.onGround = true;
        this.dead = false;
        this.aiTimer = 0;
        this.specialCooldown = 0;
        this.flashTimer = 0;
        this.stun = 0;
      }

      get centerX() {
        return this.x + this.w / 2;
      }

      get hurtBox() {
        return { x: this.x, y: this.y - this.h, w: this.w, h: this.h };
      }

      get attackBox() {
        if (!this.attacking) return null;

        let range = 0;
        let width = 0;
        let y = this.y - this.h + 22;
        let h = 58;

        if (this.attackType === "punch") {
          range = 18;
          width = 48;
          y += 26;
          h = 30;
        } else if (this.attackType === "kick") {
          range = 24;
          width = 62;
          y += 66;
          h = 30;
        } else if (this.attackType === "special") {
          range = 34;
          width = 100;
          y += 12;
          h = 92;
        }

        const x = this.direction === 1
          ? this.x + this.w + range
          : this.x - width - range;

        return { x, y, w: width, h };
      }

      moveLeft() {
        if (this.stun > 0 || this.attacking || this.dead) return;
        this.vx = -this.speed;
        this.direction = -1;
      }

      moveRight() {
        if (this.stun > 0 || this.attacking || this.dead) return;
        this.vx = this.speed;
        this.direction = 1;
      }

      jump() {
        if (!this.onGround || this.stun > 0 || this.dead) return;
        this.vy = -this.jumpForce;
        this.onGround = false;
      }

      attack(type) {
        if (this.attacking || this.dead || this.stun > 0) return;
        if (type === "special" && this.specialCooldown > 0) return;

        this.attacking = true;
        this.attackType = type;
        this.attackHitDone = false;

        if (type === "punch") this.attackTimer = 14;
        if (type === "kick") this.attackTimer = 18;
        if (type === "special") {
          this.attackTimer = 28;
          this.specialCooldown = 180;
        }
      }

      block(state) {
        if (this.dead || this.attacking || this.stun > 0) {
          this.blocking = false;
          return;
        }
        this.blocking = state;
      }

      takeHit(damage, attackerX, isSpecial = false) {
        if (this.hitCooldown > 0 || this.dead) return;

        if (this.blocking) {
          damage *= isSpecial ? 0.45 : 0.3;
        }

        this.health -= damage;
        if (this.health < 0) this.health = 0;

        this.hitCooldown = 14;
        this.flashTimer = 7;
        this.stun = isSpecial ? 16 : 9;

        const knock = isSpecial ? 8 : 4;
        this.vx = attackerX < this.x ? knock : -knock;
        this.vy = isSpecial ? -5 : -2;

        screenShake = Math.max(screenShake, isSpecial ? 8 : 4);
        createImpact(this.centerX, this.y - this.h / 2, isSpecial ? 14 : 8);

        if (this.health <= 0) {
          this.dead = true;
          gameOver = true;
        }
      }

      update() {
        if (this.dead) return;

        if (this.hitCooldown > 0) this.hitCooldown--;
        if (this.specialCooldown > 0) this.specialCooldown--;
        if (this.flashTimer > 0) this.flashTimer--;
        if (this.stun > 0) this.stun--;

        if (!this.attacking && this.stun <= 0) {
          this.vx *= 0.82;
          if (Math.abs(this.vx) < 0.12) this.vx = 0;
        }

        if (this.attacking) {
          this.vx *= 0.55;
          this.attackTimer--;
          if (this.attackTimer <= 0) {
            this.attacking = false;
            this.attackType = "";
            this.attackHitDone = false;
          }
        }

        this.vy += this.gravity;
        this.x += this.vx;
        this.y += this.vy;

        if (this.y >= GROUND_Y) {
          this.y = GROUND_Y;
          this.vy = 0;
          this.onGround = true;
        }

        if (this.x < 40) this.x = 40;
        if (this.x + this.w > WIDTH - 40) this.x = WIDTH - 40 - this.w;
      }

      draw() {
        ctx.save();
        ctx.globalAlpha = 0.28;
        ctx.fillStyle = "#000";
        ctx.beginPath();
        ctx.ellipse(this.centerX, GROUND_Y + 8, 42, 10, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        ctx.save();

        if (this.flashTimer > 0) {
          ctx.shadowColor = "rgba(255,80,80,.9)";
          ctx.shadowBlur = 22;
        }

        ctx.fillStyle = "#2f2f34";
        ctx.fillRect(this.x + 15, this.y - 54, 14, 54);
        ctx.fillRect(this.x + 39, this.y - 54, 14, 54);

        ctx.fillStyle = "#111";
        ctx.fillRect(this.x + 10, this.y - 8, 24, 8);
        ctx.fillRect(this.x + 37, this.y - 8, 24, 8);

        const torsoGradient = ctx.createLinearGradient(this.x, this.y - 120, this.x, this.y);
        torsoGradient.addColorStop(0, this.color);
        torsoGradient.addColorStop(1, "#2a2f34");
        ctx.fillStyle = torsoGradient;
        roundRect(ctx, this.x + 6, this.y - 136, 56, 82, 10, true);

        ctx.fillStyle = this.trim;
        ctx.fillRect(this.x + 12, this.y - 126, 44, 8);
        ctx.fillRect(this.x + 24, this.y - 98, 20, 6);

        ctx.fillStyle = "#393f45";
        const armOffset = this.attacking ? (this.direction * (this.attackType === "special" ? 22 : 12)) : 0;
        ctx.fillRect(this.x - 2 + Math.max(0, armOffset), this.y - 125, 16, 54);
        ctx.fillRect(this.x + 54 + Math.min(0, armOffset), this.y - 125, 16, 54);

        ctx.fillStyle = "#161616";
        ctx.fillRect(this.x - 4 + Math.max(0, armOffset), this.y - 78, 20, 12);
        ctx.fillRect(this.x + 52 + Math.min(0, armOffset), this.y - 78, 20, 12);

        ctx.fillStyle = "#c89a7c";
        ctx.beginPath();
        ctx.arc(this.x + 34, this.y - 154, 20, 0, Math.PI * 2);
        ctx.fill();

        ctx.fillStyle = "#20252c";
        ctx.fillRect(this.x + 17, this.y - 168, 34, 12);
        ctx.fillRect(this.x + 16, this.y - 155, 36, 10);

        ctx.fillStyle = "#d05b43";
        ctx.fillRect(this.x + 22, this.y - 151, 24, 4);

        ctx.strokeStyle = "rgba(255,255,255,.12)";
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(this.x + 34, this.y - 134);
        ctx.lineTo(this.x + 34, this.y - 58);
        ctx.stroke();

        if (this.blocking) {
          ctx.strokeStyle = "rgba(120,180,255,.9)";
          ctx.lineWidth = 3;
          ctx.beginPath();
          ctx.arc(this.x + 34, this.y - 95, 48, 0, Math.PI * 2);
          ctx.stroke();
        }

        ctx.restore();
      }
    }

    function roundRect(ctx, x, y, w, h, r, fill = false, stroke = false) {
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.lineTo(x + w - r, y);
      ctx.quadraticCurveTo(x + w, y, x + w, y + r);
      ctx.lineTo(x + w, y + h - r);
      ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
      ctx.lineTo(x + r, y + h);
      ctx.quadraticCurveTo(x, y + h, x, y + h - r);
      ctx.lineTo(x, y + r);
      ctx.quadraticCurveTo(x, y, x + r, y);
      ctx.closePath();
      if (fill) ctx.fill();
      if (stroke) ctx.stroke();
    }

    function rectsIntersect(a, b) {
      return (
        a &&
        b &&
        a.x < b.x + b.w &&
        a.x + a.w > b.x &&
        a.y < b.y + b.h &&
        a.y + a.h > b.y
      );
    }

    function createSmoke() {
      if (smokeParticles.length < 40 && Math.random() < 0.18) {
        smokeParticles.push({
          x: Math.random() * WIDTH,
          y: GROUND_Y - 40 - Math.random() * 220,
          r: 14 + Math.random() * 32,
          vx: 0.08 + Math.random() * 0.35,
          vy: -0.08 - Math.random() * 0.18,
          alpha: 0.06 + Math.random() * 0.05
        });
      }
    }

    function createImpact(x, y, count) {
      for (let i = 0; i < count; i++) {
        impactParticles.push({
          x,
          y,
          vx: (Math.random() - 0.5) * 8,
          vy: (Math.random() - 0.5) * 8,
          life: 18 + Math.random() * 10,
          size: 2 + Math.random() * 4,
          alpha: 0.8
        });
      }
    }

    function drawBackground() {
      const sky = ctx.createLinearGradient(0, 0, 0, HEIGHT);
      sky.addColorStop(0, "#0b0d11");
      sky.addColorStop(0.35, "#151922");
      sky.addColorStop(0.75, "#2a241f");
      sky.addColorStop(1, "#171512");
      ctx.fillStyle = sky;
      ctx.fillRect(0, 0, WIDTH, HEIGHT);

      const haze = ctx.createRadialGradient(930, 120, 20, 930, 120, 180);
      haze.addColorStop(0, "rgba(255,190,120,.16)");
      haze.addColorStop(1, "rgba(255,190,120,0)");
      ctx.fillStyle = haze;
      ctx.fillRect(0, 0, WIDTH, HEIGHT);

      ctx.fillStyle = "#10141a";
      for (let i = 0; i < 14; i++) {
        const x = i * 90;
        const h = 120 + (i % 4) * 35;
        ctx.fillRect(x, GROUND_Y - h, 55 + (i % 3) * 15, h);
      }

      ctx.fillStyle = "#1b2028";
      ctx.fillRect(100, 250, 130, 310);
      ctx.fillRect(255, 300, 90, 260);
      ctx.fillRect(820, 220, 140, 340);
      ctx.fillRect(980, 290, 120, 270);

      ctx.fillStyle = "#242a33";
      ctx.beginPath();
      ctx.moveTo(100, 250);
      ctx.lineTo(130, 210);
      ctx.lineTo(160, 250);
      ctx.closePath();
      ctx.fill();

      ctx.beginPath();
      ctx.moveTo(900, 220);
      ctx.lineTo(940, 180);
      ctx.lineTo(980, 220);
      ctx.closePath();
      ctx.fill();

      const fireGlow = ctx.createRadialGradient(310, 500, 10, 310, 500, 120);
      fireGlow.addColorStop(0, "rgba(255,140,50,.32)");
      fireGlow.addColorStop(1, "rgba(255,140,50,0)");
      ctx.fillStyle = fireGlow;
      ctx.fillRect(200, 380, 240, 220);

      drawFire(290, 525, 1.1);
      drawFire(735, 530, 0.9);
      drawFire(1030, 535, 0.8);

      const groundGrad = ctx.createLinearGradient(0, GROUND_Y, 0, HEIGHT);
      groundGrad.addColorStop(0, "#3d352d");
      groundGrad.addColorStop(0.4, "#26211d");
      groundGrad.addColorStop(1, "#161514");
      ctx.fillStyle = groundGrad;
      ctx.fillRect(0, GROUND_Y, WIDTH, HEIGHT - GROUND_Y);

      ctx.strokeStyle = "rgba(255,255,255,.08)";
      ctx.lineWidth = 2;
      for (let i = 0; i < 18; i++) {
        const x = 40 + i * 65;
        ctx.beginPath();
        ctx.moveTo(x, GROUND_Y + 10);
        ctx.lineTo(x + 18, GROUND_Y + 18);
        ctx.lineTo(x - 8, GROUND_Y + 34);
        ctx.stroke();
      }

      ctx.fillStyle = "#4f463d";
      for (let i = 0; i < 30; i++) {
        const x = (i * 41) % WIDTH;
        const y = GROUND_Y + 15 + (i % 4) * 10;
        ctx.fillRect(x, y, 8 + (i % 3) * 5, 4 + (i % 2) * 3);
      }

      smokeParticles.forEach(p => {
        ctx.save();
        ctx.globalAlpha = p.alpha;
        ctx.fillStyle = "#b7b2a8";
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
      });
    }

    function drawFire(x, y, s = 1) {
      ctx.save();
      ctx.translate(x, y);
      ctx.scale(s, s);

      ctx.beginPath();
      ctx.fillStyle = "rgba(255, 90, 20, 0.6)";
      ctx.moveTo(0, 0);
      ctx.quadraticCurveTo(10, -30, 0, -50);
      ctx.quadraticCurveTo(-10, -30, 0, 0);
      ctx.fill();

      ctx.beginPath();
      ctx.fillStyle = "rgba(255, 170, 60, 0.7)";
      ctx.moveTo(8, 0);
      ctx.quadraticCurveTo(18, -22, 8, -40);
      ctx.quadraticCurveTo(-2, -22, 8, 0);
      ctx.fill();

      ctx.restore();
    }

    function updateParticles() {
      createSmoke();

      smokeParticles.forEach(p => {
        p.x += p.vx;
        p.y += p.vy;
        p.r += 0.02;
        if (p.x > WIDTH + 50 || p.y < -60) {
          p.x = -40;
          p.y = GROUND_Y - 40 - Math.random() * 180;
        }
      });

      impactParticles = impactParticles.filter(p => p.life > 0);
      impactParticles.forEach(p => {
        p.x += p.vx;
        p.y += p.vy;
        p.life--;
        p.alpha *= 0.94;
      });
    }

    function drawImpactParticles() {
      impactParticles.forEach(p => {
        ctx.save();
        ctx.globalAlpha = p.alpha;
        ctx.fillStyle = "#ffd18b";
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
      });
    }

    const player = new Fighter({
      name: "Commander Rex",
      x: 220,
      color: "#596a42",
      trim: "#e6c67f",
      direction: 1,
      speed: 5.4,
      jumpForce: 18,
      isPlayer: true
    });

    const enemy = new Fighter({
      name: "Iron Wraith",
      x: 900,
      color: "#5e3d3d",
      trim: "#c84533",
      direction: -1,
      speed: 3.3,
      jumpForce: 16
    });

    function resetGame() {
      player.reset();
      enemy.reset();

      impactParticles = [];
      smokeParticles = [];
      screenShake = 0;
      gameOver = false;
      gameStarted = true;
      roundTime = GAME_SETTINGS.startTime;
      timerAccumulator = 0;
      overlay.style.display = "none";
      updateHUD();
    }

    function updateHUD() {
      p1hpEl.style.width = `${Math.max(0, player.health)}%`;
      p2hpEl.style.width = `${Math.max(0, enemy.health)}%`;
      timerEl.textContent = roundTime;
    }

    function handlePlayerInput() {
      if (gameOver || !gameStarted) return;

      player.block(!!keys["l"]);

      if (keys["a"] && !keys["d"]) player.moveLeft();
      if (keys["d"] && !keys["a"]) player.moveRight();

      if (keys["w"]) {
        player.jump();
        keys["w"] = false;
      }

      if (keys["j"]) {
        player.attack("punch");
        keys["j"] = false;
      }

      if (keys["k"]) {
        player.attack("kick");
        keys["k"] = false;
      }

      if (keys["u"]) {
        player.attack("special");
        keys["u"] = false;
      }
    }

    function handleEnemyAI() {
      if (gameOver || !gameStarted || enemy.dead) return;

      const dist = player.centerX - enemy.centerX;
      const absDist = Math.abs(dist);
      enemy.direction = dist > 0 ? 1 : -1;

      enemy.aiTimer--;
      enemy.blocking = false;

      if (enemy.stun > 0) return;

      if (!enemy.attacking) {
        if (absDist > 150) {
          enemy.vx = dist > 0 ? enemy.speed * 0.60 : -enemy.speed * 0.60;
        } else if (absDist > 100) {
          enemy.vx = dist > 0 ? enemy.speed * 0.36 : -enemy.speed * 0.36;
        } else {
          enemy.vx *= 0.65;
        }

        if (absDist < 95 && Math.random() < 0.015) {
          enemy.blocking = true;
        }

        if (enemy.aiTimer <= 0) {
          if (absDist < 88) {
            const roll = Math.random();
            if (roll < 0.55) enemy.attack("punch");
            else if (roll < 0.88) enemy.attack("kick");
            else enemy.attack("special");

            enemy.aiTimer = 45 + Math.random() * 30;
          } else if (absDist < 180 && Math.random() < 0.01) {
            enemy.jump();
            enemy.aiTimer = 30;
          } else {
            enemy.aiTimer = 14;
          }
        }
      }
    }

    function getDamage(attacker) {
      if (attacker === player) {
        if (attacker.attackType === "punch") return { damage: GAME_SETTINGS.playerPunchDamage, isSpecial: false };
        if (attacker.attackType === "kick") return { damage: GAME_SETTINGS.playerKickDamage, isSpecial: false };
        if (attacker.attackType === "special") return { damage: GAME_SETTINGS.playerSpecialDamage, isSpecial: true };
      } else {
        if (attacker.attackType === "punch") return { damage: GAME_SETTINGS.enemyPunchDamage, isSpecial: false };
        if (attacker.attackType === "kick") return { damage: GAME_SETTINGS.enemyKickDamage, isSpecial: false };
        if (attacker.attackType === "special") return { damage: GAME_SETTINGS.enemySpecialDamage, isSpecial: true };
      }
      return { damage: 0, isSpecial: false };
    }

    function resolveHits(attacker, defender) {
      if (!attacker.attacking || attacker.attackHitDone) return;

      const atk = attacker.attackBox;
      const hurt = defender.hurtBox;

      if (rectsIntersect(atk, hurt) && defender.hitCooldown <= 0) {
        const result = getDamage(attacker);
        defender.takeHit(result.damage, attacker.x, result.isSpecial);
        attacker.attackHitDone = true;
      }
    }

    function drawAttackEffect(fighter) {
      if (!fighter.attacking) return;
      const box = fighter.attackBox;
      if (!box) return;

      ctx.save();
      if (fighter.attackType === "punch") {
        ctx.fillStyle = "rgba(255,255,255,.08)";
      } else if (fighter.attackType === "kick") {
        ctx.fillStyle = "rgba(255,210,120,.10)";
      } else {
        ctx.fillStyle = "rgba(255,80,40,.14)";
      }
      ctx.fillRect(box.x, box.y, box.w, box.h);

      if (fighter.attackType === "special") {
        ctx.strokeStyle = "rgba(255,160,70,.35)";
        ctx.lineWidth = 4;
        ctx.strokeRect(box.x, box.y, box.w, box.h);
      }
      ctx.restore();
    }

    function endRoundText() {
      if (!gameOver && roundTime > 0) return null;
      if (player.health > enemy.health) return "YOU WIN";
      if (enemy.health > player.health) return "YOU LOSE";
      return "DRAW";
    }

    function drawGameOver() {
      const result = endRoundText();
      if (!result) return;

      ctx.save();
      ctx.fillStyle = "rgba(0,0,0,.5)";
      ctx.fillRect(0, 0, WIDTH, HEIGHT);

      ctx.fillStyle = "#fff0c7";
      ctx.font = "bold 64px Tahoma";
      ctx.textAlign = "center";
      ctx.fillText(result, WIDTH / 2, HEIGHT / 2 - 20);

      ctx.fillStyle = "#dddddd";
      ctx.font = "24px Tahoma";
      ctx.fillText("กด R หรือปุ่มเริ่มใหม่ด้านบน", WIDTH / 2, HEIGHT / 2 + 35);
      ctx.restore();
    }

    function updateTimer(delta) {
      if (!gameStarted || gameOver) return;

      timerAccumulator += delta;
      while (timerAccumulator >= 1000) {
        roundTime--;
        timerAccumulator -= 1000;

        if (roundTime <= 0) {
          roundTime = 0;
          gameOver = true;
          break;
        }
      }
    }

    function gameLoop(timestamp) {
      if (lastTime === null) lastTime = timestamp;
      let delta = timestamp - lastTime;
      lastTime = timestamp;

      if (delta > GAME_SETTINGS.maxDelta) {
        delta = GAME_SETTINGS.maxDelta;
      }

      updateTimer(delta);
      updateParticles();

      handlePlayerInput();
      handleEnemyAI();

      player.update();
      enemy.update();

      resolveHits(player, enemy);
      resolveHits(enemy, player);

      updateHUD();

      if (screenShake > 0) {
        screenShake *= 0.86;
        if (screenShake < 0.15) screenShake = 0;
      }

      ctx.save();
      const shakeX = (Math.random() - 0.5) * screenShake;
      const shakeY = (Math.random() - 0.5) * screenShake;
      ctx.translate(shakeX, shakeY);

      drawBackground();
      drawAttackEffect(player);
      drawAttackEffect(enemy);
      player.draw();
      enemy.draw();
      drawImpactParticles();

      ctx.restore();

      drawGameOver();

      requestAnimationFrame(gameLoop);
    }

    updateHUD();
    requestAnimationFrame(gameLoop);
  </script>
</body>
</html>