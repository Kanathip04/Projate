<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jump Runner - เวอร์ชันสมจริงขึ้น</title>
    <style>
        *{
            box-sizing:border-box;
        }

        body{
            margin:0;
            min-height:100vh;
            font-family:'Tahoma', sans-serif;
            color:#fff;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:24px;
            background:
                radial-gradient(circle at 15% 20%, rgba(255,210,120,0.14), transparent 18%),
                radial-gradient(circle at 85% 10%, rgba(120,200,255,0.10), transparent 22%),
                linear-gradient(180deg, #0c1016 0%, #111722 40%, #0a0e14 100%);
        }

        .wrapper{
            width:100%;
            max-width:900px;
        }

        .header{
            text-align:center;
            margin-bottom:18px;
        }

        .header h1{
            margin:0 0 8px;
            font-size:52px;
            line-height:1.1;
            font-weight:900;
            letter-spacing:.4px;
            text-shadow:0 6px 18px rgba(0,0,0,.35);
        }

        .header p{
            margin:0;
            color:rgba(255,255,255,.82);
            font-size:18px;
        }

        .game-shell{
            background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
            border:1px solid rgba(255,255,255,.08);
            border-radius:28px;
            padding:22px;
            box-shadow:
                0 30px 60px rgba(0,0,0,.35),
                inset 0 1px 0 rgba(255,255,255,.06);
            backdrop-filter: blur(8px);
        }

        .hud{
            display:grid;
            grid-template-columns: repeat(3, 1fr);
            gap:14px;
            margin-bottom:18px;
        }

        .card{
            background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.025));
            border:1px solid rgba(255,255,255,.07);
            border-radius:18px;
            padding:14px 16px;
            box-shadow:0 10px 24px rgba(0,0,0,.18);
            text-align:center;
        }

        .card .label{
            color:rgba(255,255,255,.65);
            font-size:13px;
            margin-bottom:6px;
        }

        .card .value{
            font-size:30px;
            font-weight:900;
            color:#fff;
        }

        .card .value.small{
            font-size:22px;
        }

        .canvas-wrap{
            position:relative;
        }

        canvas{
            display:block;
            width:100%;
            max-width:100%;
            margin:0 auto;
            background:#cfeeff;
            border-radius:24px;
            border:4px solid #7b9e1a;
            box-shadow:
                0 20px 40px rgba(0,0,0,.28),
                inset 0 0 0 1px rgba(255,255,255,.45);
        }

        .controls{
            display:flex;
            justify-content:center;
            gap:14px;
            flex-wrap:wrap;
            margin-top:18px;
        }

        .btn{
            border:none;
            border-radius:16px;
            padding:14px 24px;
            font-size:18px;
            font-weight:800;
            cursor:pointer;
            transition:.2s ease;
            text-decoration:none;
            display:inline-block;
        }

        .btn-primary{
            color:#fff;
            background:linear-gradient(180deg, #8db91b, #6f9410);
            box-shadow:0 12px 22px rgba(111,148,16,.28);
        }

        .btn-primary:hover{
            transform:translateY(-2px);
            filter:brightness(1.05);
        }

        .btn-secondary{
            color:#fff;
            background:linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
            border:1px solid rgba(255,255,255,.08);
        }

        .btn-secondary:hover{
            transform:translateY(-2px);
            background:linear-gradient(180deg, rgba(255,255,255,.16), rgba(255,255,255,.08));
        }

        .tip{
            margin-top:16px;
            text-align:center;
            color:rgba(255,255,255,.78);
            font-size:15px;
        }

        .game-over-panel{
            position:absolute;
            inset:0;
            display:none;
            justify-content:center;
            align-items:center;
            background:rgba(5,8,12,.34);
            border-radius:24px;
        }

        .game-over-panel.show{
            display:flex;
        }

        .game-over-box{
            width:min(90%, 420px);
            background:linear-gradient(180deg, rgba(22,28,36,.95), rgba(16,22,28,.96));
            border:1px solid rgba(255,255,255,.08);
            border-radius:22px;
            padding:24px;
            box-shadow:0 25px 40px rgba(0,0,0,.35);
            text-align:center;
        }

        .game-over-box h2{
            margin:0 0 10px;
            font-size:34px;
        }

        .game-over-box p{
            margin:6px 0;
            color:rgba(255,255,255,.82);
            font-size:17px;
        }

        @media (max-width: 700px){
            .header h1{
                font-size:38px;
            }

            .hud{
                grid-template-columns:1fr;
            }

            .card .value{
                font-size:26px;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>🎮 เกมกระโดด</h1>
            <p>เวอร์ชันภาพสวยขึ้น มีมิติขึ้น และสมจริงกว่าเดิม</p>
        </div>

        <div class="game-shell">
            <div class="hud">
                <div class="card">
                    <div class="label">คะแนน</div>
                    <div class="value" id="score">0</div>
                </div>
                <div class="card">
                    <div class="label">คะแนนสูงสุด</div>
                    <div class="value" id="bestScore">0</div>
                </div>
                <div class="card">
                    <div class="label">สถานะ</div>
                    <div class="value small" id="statusText">พร้อมเล่น</div>
                </div>
            </div>

            <div class="canvas-wrap">
                <canvas id="gameCanvas" width="760" height="360"></canvas>

                <div class="game-over-panel" id="gameOverPanel">
                    <div class="game-over-box">
                        <h2>Game Over</h2>
                        <p id="finalScoreText">คะแนน: 0</p>
                        <p>กดเริ่มใหม่เพื่อเล่นอีกครั้ง</p>
                    </div>
                </div>
            </div>

            <div class="controls">
                <button class="btn btn-primary" onclick="restartGame()">เริ่มใหม่</button>
                <a href="game.php" class="btn btn-secondary">กลับ</a>
            </div>

            <div class="tip">
                กด <b>Space</b>, <b>Arrow Up</b>, คลิกเมาส์ หรือแตะหน้าจอเพื่อกระโดด
            </div>
        </div>
    </div>

    <script>
        const canvas = document.getElementById("gameCanvas");
        const ctx = canvas.getContext("2d");

        const scoreEl = document.getElementById("score");
        const bestScoreEl = document.getElementById("bestScore");
        const statusTextEl = document.getElementById("statusText");
        const gameOverPanel = document.getElementById("gameOverPanel");
        const finalScoreText = document.getElementById("finalScoreText");

        const groundY = 295;
        let bestScore = Number(localStorage.getItem("jump_best_score_real") || 0);
        bestScoreEl.textContent = bestScore;

        let player;
        let obstacles;
        let particles;
        let score;
        let speed;
        let gravity;
        let jumpForce;
        let gameOver;
        let spawnTimer;
        let frame;
        let clouds;
        let birds;

        function initGame() {
            player = {
                x: 95,
                y: groundY - 44,
                w: 44,
                h: 44,
                vy: 0,
                onGround: true,
                legTick: 0
            };

            obstacles = [];
            particles = [];
            clouds = [
                { x: 90, y: 62, size: 1.0, speed: 0.35 },
                { x: 300, y: 88, size: 1.25, speed: 0.28 },
                { x: 560, y: 56, size: 0.92, speed: 0.4 }
            ];
            birds = [
                { x: 640, y: 80, speed: 1.1 },
                { x: 820, y: 110, speed: 0.95 }
            ];

            score = 0;
            speed = 5.8;
            gravity = 0.58;
            jumpForce = -12.2;
            gameOver = false;
            spawnTimer = 0;
            frame = 0;

            scoreEl.textContent = score;
            statusTextEl.textContent = "กำลังเล่น";
            gameOverPanel.classList.remove("show");
        }

        function restartGame() {
            initGame();
        }

        function jump() {
            if (gameOver) return;
            if (player.onGround) {
                player.vy = jumpForce;
                player.onGround = false;

                for (let i = 0; i < 12; i++) {
                    particles.push({
                        x: player.x + 10 + Math.random() * 20,
                        y: groundY - 1,
                        r: 2 + Math.random() * 4,
                        vx: -2 + Math.random() * 2.2,
                        vy: -1.2 - Math.random() * 2.4,
                        alpha: 0.9,
                        color: Math.random() > 0.5 ? "#c1a27a" : "#9e845f"
                    });
                }
            }
        }

        function createObstacle() {
            const types = [
                { w: 18, h: 34, color1: "#d35400", color2: "#a63f00" },
                { w: 24, h: 54, color1: "#e74c3c", color2: "#b93a2d" },
                { w: 16, h: 28, color1: "#c0392b", color2: "#8e2b20" },
                { w: 30, h: 38, color1: "#f39c12", color2: "#c57f0f" }
            ];

            const t = types[Math.floor(Math.random() * types.length)];

            obstacles.push({
                x: canvas.width + 30,
                y: groundY - t.h,
                w: t.w,
                h: t.h,
                color1: t.color1,
                color2: t.color2,
                passed: false
            });
        }

        function isColliding(a, b) {
            return (
                a.x < b.x + b.w &&
                a.x + a.w > b.x &&
                a.y < b.y + b.h &&
                a.y + a.h > b.y
            );
        }

        function updatePlayer() {
            player.y += player.vy;
            player.vy += gravity;
            player.legTick += 0.22;

            if (player.y + player.h >= groundY) {
                player.y = groundY - player.h;
                player.vy = 0;
                player.onGround = true;
            }
        }

        function updateObstacles() {
            spawnTimer++;

            const spawnGap = Math.max(72, 118 - Math.floor(score / 7));
            if (spawnTimer > spawnGap) {
                createObstacle();
                spawnTimer = 0;
            }

            for (let i = obstacles.length - 1; i >= 0; i--) {
                const ob = obstacles[i];
                ob.x -= speed;

                if (!ob.passed && ob.x + ob.w < player.x) {
                    ob.passed = true;
                    score++;
                    scoreEl.textContent = score;

                    if (score > bestScore) {
                        bestScore = score;
                        localStorage.setItem("jump_best_score_real", bestScore);
                        bestScoreEl.textContent = bestScore;
                    }

                    if (score % 5 === 0) {
                        speed += 0.28;
                    }
                }

                if (ob.x + ob.w < -30) {
                    obstacles.splice(i, 1);
                    continue;
                }

                if (isColliding(player, ob)) {
                    gameOver = true;
                    statusTextEl.textContent = "เกมจบ";
                    finalScoreText.textContent = "คะแนน: " + score;
                    gameOverPanel.classList.add("show");
                }
            }
        }

        function updateParticles() {
            for (let i = particles.length - 1; i >= 0; i--) {
                const p = particles[i];
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.09;
                p.alpha -= 0.025;

                if (p.alpha <= 0) {
                    particles.splice(i, 1);
                }
            }
        }

        function updateSkyObjects() {
            clouds.forEach(c => {
                c.x -= c.speed;
                if (c.x < -140) {
                    c.x = canvas.width + Math.random() * 120;
                    c.y = 35 + Math.random() * 75;
                    c.size = 0.85 + Math.random() * 0.6;
                }
            });

            birds.forEach(b => {
                b.x -= b.speed;
                if (b.x < -40) {
                    b.x = canvas.width + Math.random() * 180;
                    b.y = 60 + Math.random() * 70;
                }
            });
        }

        function drawRoundedRect(x, y, w, h, r) {
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
        }

        function drawBackground() {
            const sky = ctx.createLinearGradient(0, 0, 0, canvas.height);
            sky.addColorStop(0, "#81d4fa");
            sky.addColorStop(0.42, "#c9efff");
            sky.addColorStop(0.72, "#eef9ff");
            sky.addColorStop(1, "#f8f6ef");
            ctx.fillStyle = sky;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const sunGlow = ctx.createRadialGradient(610, 70, 12, 610, 70, 78);
            sunGlow.addColorStop(0, "rgba(255,230,150,0.95)");
            sunGlow.addColorStop(0.35, "rgba(255,223,130,0.55)");
            sunGlow.addColorStop(1, "rgba(255,223,130,0)");
            ctx.fillStyle = sunGlow;
            ctx.beginPath();
            ctx.arc(610, 70, 78, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = "#ffd66b";
            ctx.beginPath();
            ctx.arc(610, 70, 28, 0, Math.PI * 2);
            ctx.fill();

            clouds.forEach(c => drawCloud(c.x, c.y, c.size));

            birds.forEach(b => drawBird(b.x, b.y));

            drawMountainsLayer("#b8d1b0", 205, [
                { x: -10, w: 200, h: 58 },
                { x: 120, w: 220, h: 82 },
                { x: 305, w: 250, h: 76 },
                { x: 530, w: 180, h: 54 }
            ]);

            drawMountainsLayer("#9db991", 235, [
                { x: -30, w: 220, h: 60 },
                { x: 150, w: 180, h: 90 },
                { x: 310, w: 220, h: 72 },
                { x: 520, w: 220, h: 84 }
            ]);

            const groundGrad = ctx.createLinearGradient(0, groundY - 10, 0, canvas.height);
            groundGrad.addColorStop(0, "#93c552");
            groundGrad.addColorStop(0.15, "#7dac3d");
            groundGrad.addColorStop(0.151, "#70553d");
            groundGrad.addColorStop(1, "#5d4635");
            ctx.fillStyle = groundGrad;
            ctx.fillRect(0, groundY - 10, canvas.width, canvas.height - groundY + 10);

            ctx.fillStyle = "#4d392c";
            ctx.fillRect(0, groundY + 18, canvas.width, 4);

            for (let i = 0; i < canvas.width; i += 28) {
                ctx.fillStyle = i % 56 === 0 ? "rgba(255,255,255,0.07)" : "rgba(0,0,0,0.05)";
                ctx.fillRect(i, groundY + 26, 16, 2);
            }
        }

        function drawCloud(x, y, s) {
            ctx.save();
            ctx.translate(x, y);
            ctx.scale(s, s);
            ctx.fillStyle = "rgba(255,255,255,0.88)";
            ctx.beginPath();
            ctx.arc(0, 18, 18, 0, Math.PI * 2);
            ctx.arc(18, 10, 22, 0, Math.PI * 2);
            ctx.arc(42, 18, 17, 0, Math.PI * 2);
            ctx.arc(24, 22, 18, 0, Math.PI * 2);
            ctx.closePath();
            ctx.fill();
            ctx.restore();
        }

        function drawBird(x, y) {
            ctx.strokeStyle = "rgba(60,70,80,0.5)";
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(x, y, 8, Math.PI, 2 * Math.PI);
            ctx.stroke();
            ctx.beginPath();
            ctx.arc(x + 14, y, 8, Math.PI, 2 * Math.PI);
            ctx.stroke();
        }

        function drawMountainsLayer(color, baseY, mountains) {
            ctx.fillStyle = color;
            mountains.forEach(m => {
                ctx.beginPath();
                ctx.moveTo(m.x, baseY);
                ctx.lineTo(m.x + m.w / 2, baseY - m.h);
                ctx.lineTo(m.x + m.w, baseY);
                ctx.closePath();
                ctx.fill();
            });
        }

        function drawPlayer() {
            const x = player.x;
            const y = player.y;
            const w = player.w;
            const h = player.h;

            ctx.save();

            const shadowScale = player.onGround ? 1 : 0.72;
            const shadowWidth = 34 * shadowScale;
            ctx.fillStyle = "rgba(0,0,0,0.18)";
            ctx.beginPath();
            ctx.ellipse(x + w / 2, groundY + 4, shadowWidth, 7, 0, 0, Math.PI * 2);
            ctx.fill();

            const bounce = Math.sin(player.legTick) * 2;

            const bodyGrad = ctx.createLinearGradient(x, y, x, y + h);
            bodyGrad.addColorStop(0, "#7fbc2b");
            bodyGrad.addColorStop(1, "#557d12");
            ctx.fillStyle = bodyGrad;
            drawRoundedRect(x + 8, y + 10, 26, 28, 12);
            ctx.fill();

            ctx.fillStyle = "#f0d6b5";
            ctx.beginPath();
            ctx.arc(x + 21, y + 11, 10, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = "#2f241b";
            ctx.beginPath();
            ctx.arc(x + 18, y + 9, 8, Math.PI, 2 * Math.PI);
            ctx.fill();

            ctx.fillStyle = "#fff";
            ctx.beginPath();
            ctx.arc(x + 18, y + 11, 2.2, 0, Math.PI * 2);
            ctx.arc(x + 24, y + 11, 2.2, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = "#1f1f1f";
            ctx.beginPath();
            ctx.arc(x + 18, y + 11, 1, 0, Math.PI * 2);
            ctx.arc(x + 24, y + 11, 1, 0, Math.PI * 2);
            ctx.fill();

            ctx.strokeStyle = "#2b2b2b";
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(x + 21, y + 14);
            ctx.lineTo(x + 21, y + 18);
            ctx.stroke();

            ctx.strokeStyle = "#4c351d";
            ctx.lineWidth = 4;
            ctx.lineCap = "round";

            ctx.beginPath();
            ctx.moveTo(x + 13, y + 20);
            ctx.lineTo(x + 6, y + 26 + bounce * 0.2);
            ctx.stroke();

            ctx.beginPath();
            ctx.moveTo(x + 29, y + 20);
            ctx.lineTo(x + 36, y + 26 - bounce * 0.2);
            ctx.stroke();

            const legSwing = Math.sin(player.legTick) * 5;

            ctx.beginPath();
            ctx.moveTo(x + 17, y + 37);
            ctx.lineTo(x + 14, y + 48 + legSwing * 0.35);
            ctx.stroke();

            ctx.beginPath();
            ctx.moveTo(x + 25, y + 37);
            ctx.lineTo(x + 28, y + 48 - legSwing * 0.35);
            ctx.stroke();

            ctx.strokeStyle = "#ffffff";
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(x + 14, y + 17);
            ctx.lineTo(x + 17, y + 17);
            ctx.stroke();

            ctx.restore();
        }

        function drawObstacle(ob) {
            ctx.save();

            ctx.fillStyle = "rgba(0,0,0,0.18)";
            ctx.beginPath();
            ctx.ellipse(ob.x + ob.w / 2, groundY + 4, ob.w * 0.55, 6, 0, 0, Math.PI * 2);
            ctx.fill();

            const grad = ctx.createLinearGradient(ob.x, ob.y, ob.x, ob.y + ob.h);
            grad.addColorStop(0, ob.color1);
            grad.addColorStop(1, ob.color2);
            ctx.fillStyle = grad;
            drawRoundedRect(ob.x, ob.y, ob.w, ob.h, 5);
            ctx.fill();

            ctx.fillStyle = "rgba(255,255,255,0.20)";
            ctx.fillRect(ob.x + 3, ob.y + 4, Math.max(3, ob.w * 0.22), ob.h - 8);

            ctx.fillStyle = "rgba(255,255,255,0.08)";
            ctx.fillRect(ob.x, ob.y + ob.h - 5, ob.w, 5);

            ctx.restore();
        }

        function drawParticles() {
            particles.forEach(p => {
                ctx.globalAlpha = p.alpha;
                ctx.fillStyle = p.color;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fill();
                ctx.globalAlpha = 1;
            });
        }

        function animate() {
            frame++;

            drawBackground();
            updateSkyObjects();
            updateParticles();

            if (!gameOver) {
                updatePlayer();
                updateObstacles();
            }

            drawParticles();

            obstacles.forEach(drawObstacle);
            drawPlayer();

            requestAnimationFrame(animate);
        }

        document.addEventListener("keydown", (e) => {
            if (e.code === "Space" || e.code === "ArrowUp") {
                e.preventDefault();
                jump();
            }
        });

        canvas.addEventListener("mousedown", jump);
        canvas.addEventListener("touchstart", (e) => {
            e.preventDefault();
            jump();
        }, { passive: false });

        initGame();
        animate();
    </script>
</body>
</html>