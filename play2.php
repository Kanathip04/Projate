<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เกมเครื่องบินยิงศัตรู - เวอร์ชันสมจริง</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Tahoma", sans-serif;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background:
                radial-gradient(circle at top, rgba(183,140,60,0.15), transparent 25%),
                radial-gradient(circle at bottom, rgba(120,90,30,0.18), transparent 30%),
                linear-gradient(180deg, #050608 0%, #0c0f14 40%, #141922 100%);
            overflow-x: hidden;
            padding: 18px 14px 24px;
        }

        .game-wrapper {
            width: 100%;
            max-width: 920px;
            text-align: center;
        }

        .title {
            font-size: clamp(30px, 4vw, 42px);
            font-weight: 900;
            margin-bottom: 8px;
            color: #fff8dc;
            text-shadow:
                0 0 8px rgba(255, 215, 120, 0.35),
                0 0 20px rgba(255, 180, 50, 0.18);
            letter-spacing: 1px;
        }

        .subtitle {
            font-size: clamp(14px, 2vw, 18px);
            margin-bottom: 14px;
            color: #f3e8c8;
            opacity: 0.95;
        }

        .hud-top {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .hud-box {
            min-width: 135px;
            padding: 10px 14px;
            border-radius: 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255, 215, 140, 0.25);
            box-shadow: 0 6px 18px rgba(0,0,0,0.35);
            backdrop-filter: blur(5px);
            font-weight: bold;
            color: #ffe6a7;
            font-size: 18px;
        }

        .canvas-frame {
            position: relative;
            display: inline-block;
            width: min(92vw, 560px);
            padding: 12px;
            border-radius: 26px;
            background: linear-gradient(145deg, rgba(191,155,86,0.22), rgba(78,55,15,0.18));
            box-shadow:
                0 0 0 2px rgba(212,174,96,0.28),
                0 20px 60px rgba(0,0,0,0.45),
                inset 0 0 30px rgba(255, 210, 100, 0.06);
        }

        canvas {
            display: block;
            width: 100%;
            height: auto;
            aspect-ratio: 10 / 14;
            background:
                radial-gradient(circle at center, rgba(255,255,255,0.02), transparent 60%),
                linear-gradient(180deg, #0b0d12 0%, #121722 45%, #0b0d12 100%);
            border: 4px solid #c9a257;
            border-radius: 20px;
            box-shadow:
                0 0 22px rgba(225, 179, 84, 0.18),
                inset 0 0 40px rgba(255,255,255,0.03);
        }

        .boss-bar-wrap {
            margin: 12px auto 0;
            width: min(92vw, 560px);
            display: none;
        }

        .boss-name {
            text-align: left;
            margin-bottom: 6px;
            color: #ffd88d;
            font-weight: bold;
        }

        .boss-bar-bg {
            width: 100%;
            height: 16px;
            background: rgba(255,255,255,0.08);
            border-radius: 999px;
            overflow: hidden;
            border: 1px solid rgba(255,215,120,0.25);
        }

        .boss-bar-fill {
            height: 100%;
            width: 100%;
            background: linear-gradient(90deg, #ff5a5a, #ff9f43, #ffd166);
            border-radius: 999px;
            transition: width 0.15s linear;
            box-shadow: 0 0 12px rgba(255,120,80,0.4);
        }

        .controls {
            margin-top: 14px;
            color: #ddd1b0;
            font-size: 14px;
            line-height: 1.7;
        }

        .btn-back {
            display: inline-block;
            margin-top: 14px;
            padding: 11px 22px;
            border-radius: 999px;
            text-decoration: none;
            color: #fff8e1;
            font-weight: bold;
            background: linear-gradient(135deg, #c89b4d, #8d6420);
            box-shadow: 0 8px 24px rgba(0,0,0,0.35);
            transition: 0.25s ease;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(0,0,0,0.45);
        }

        @media (max-height: 850px) {
            .title {
                margin-bottom: 4px;
            }

            .subtitle {
                margin-bottom: 10px;
            }

            .hud-top {
                margin-bottom: 10px;
            }

            .hud-box {
                padding: 8px 12px;
                font-size: 16px;
            }

            .controls {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="game-wrapper">
        <div class="title">✈ เกมเครื่องบินยิงศัตรู</div>
        <div class="subtitle">บังคับด้วยเมาส์ คลิกซ้ายเพื่อยิง เอาตัวรอดและสู้กับบอสลายไทย</div>

        <div class="hud-top">
            <div class="hud-box">คะแนน: <span id="score">0</span></div>
            <div class="hud-box">เลเวล: <span id="level">1</span></div>
            <div class="hud-box">พลังชีวิต: <span id="playerHp">100</span></div>
        </div>

        <div class="canvas-frame">
            <canvas id="gameCanvas"></canvas>
        </div>

        <div class="boss-bar-wrap" id="bossBarWrap">
            <div class="boss-name">บอส: อสุรลายกนก</div>
            <div class="boss-bar-bg">
                <div class="boss-bar-fill" id="bossBarFill"></div>
            </div>
        </div>

        <div class="controls">
            ยิงศัตรูเพื่อเก็บคะแนน เมื่อคะแนนถึงกำหนดจะเข้าสู่ด่านบอส<br>
            บอสถูกออกแบบให้มีลวดลายไทยโทนทอง แดง และส้ม ให้ความรู้สึกขลังและเด่นขึ้น
        </div>

        <a href="game.php" class="btn-back">← กลับ</a>
    </div>

    <script>
        const canvas = document.getElementById("gameCanvas");
        const ctx = canvas.getContext("2d");

        const GAME_WIDTH = 500;
        const GAME_HEIGHT = 700;

        function resizeCanvas() {
            canvas.width = GAME_WIDTH;
            canvas.height = GAME_HEIGHT;
        }

        resizeCanvas();
        window.addEventListener("resize", resizeCanvas);

        const scoreEl = document.getElementById("score");
        const levelEl = document.getElementById("level");
        const playerHpEl = document.getElementById("playerHp");
        const bossBarWrap = document.getElementById("bossBarWrap");
        const bossBarFill = document.getElementById("bossBarFill");

        let score = 0;
        let level = 1;
        let gameOver = false;
        let bossMode = false;
        let stars = [];
        let particles = [];
        let bullets = [];
        let enemyBullets = [];
        let enemies = [];
        let mouseX = GAME_WIDTH / 2;
        let mouseY = GAME_HEIGHT - 90;
        let frame = 0;

        const player = {
            x: GAME_WIDTH / 2,
            y: GAME_HEIGHT - 90,
            w: 46,
            h: 62,
            hp: 100,
            shootCooldown: 0
        };

        let boss = null;

        function createStars() {
            stars = [];
            for (let i = 0; i < 90; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    r: Math.random() * 2,
                    speed: 0.4 + Math.random() * 1.6,
                    alpha: 0.2 + Math.random() * 0.8
                });
            }
        }

        function drawStars() {
            for (const s of stars) {
                s.y += s.speed;
                if (s.y > canvas.height) {
                    s.y = -5;
                    s.x = Math.random() * canvas.width;
                }
                ctx.beginPath();
                ctx.fillStyle = `rgba(255,255,255,${s.alpha})`;
                ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        function drawPlayer() {
            const x = player.x;
            const y = player.y;

            ctx.save();
            ctx.translate(x, y);

            ctx.shadowColor = "rgba(255, 215, 100, 0.4)";
            ctx.shadowBlur = 18;

            ctx.fillStyle = "#7b3fc6";
            ctx.beginPath();
            ctx.moveTo(-20, 8);
            ctx.lineTo(-6, -8);
            ctx.lineTo(-6, 18);
            ctx.closePath();
            ctx.fill();

            ctx.beginPath();
            ctx.moveTo(20, 8);
            ctx.lineTo(6, -8);
            ctx.lineTo(6, 18);
            ctx.closePath();
            ctx.fill();

            const grad = ctx.createLinearGradient(0, -32, 0, 32);
            grad.addColorStop(0, "#ffe08a");
            grad.addColorStop(0.5, "#b8913f");
            grad.addColorStop(1, "#6b5323");

            ctx.fillStyle = grad;
            ctx.beginPath();
            ctx.moveTo(0, -34);
            ctx.lineTo(12, 18);
            ctx.lineTo(0, 12);
            ctx.lineTo(-12, 18);
            ctx.closePath();
            ctx.fill();

            ctx.fillStyle = "#9fe7ff";
            ctx.beginPath();
            ctx.ellipse(0, -8, 5, 10, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = "#f0c14f";
            ctx.fillRect(-4, 18, 8, 16);

            ctx.fillStyle = "rgba(255, 150, 50, 0.7)";
            ctx.beginPath();
            ctx.ellipse(0, 38, 6, 14, 0, 0, Math.PI * 2);
            ctx.fill();

            ctx.restore();
        }

        function shoot() {
            bullets.push({
                x: player.x,
                y: player.y - 28,
                w: 6,
                h: 22,
                speed: 10,
                damage: 10
            });
        }

        function drawBullets() {
            for (let i = bullets.length - 1; i >= 0; i--) {
                const b = bullets[i];
                b.y -= b.speed;

                ctx.save();
                ctx.shadowColor = "yellow";
                ctx.shadowBlur = 12;
                ctx.fillStyle = "#fff200";
                ctx.fillRect(b.x - b.w / 2, b.y, b.w, b.h);
                ctx.restore();

                if (b.y < -30) bullets.splice(i, 1);
            }
        }

        function drawEnemyBullets() {
            for (let i = enemyBullets.length - 1; i >= 0; i--) {
                const b = enemyBullets[i];
                b.x += b.vx;
                b.y += b.vy;

                ctx.save();
                ctx.shadowColor = "rgba(255,80,80,0.9)";
                ctx.shadowBlur = 10;
                ctx.fillStyle = "#ff6040";
                ctx.beginPath();
                ctx.arc(b.x, b.y, b.r, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();

                if (b.y > canvas.height + 30 || b.x < -30 || b.x > canvas.width + 30) {
                    enemyBullets.splice(i, 1);
                    continue;
                }

                if (rectCircleCollide(player, b)) {
                    player.hp -= b.damage;
                    updateUI();
                    createExplosion(b.x, b.y, "#ff9f43", 8);
                    enemyBullets.splice(i, 1);

                    if (player.hp <= 0) {
                        player.hp = 0;
                        gameOver = true;
                    }
                }
            }
        }

        function spawnEnemy() {
            if (bossMode) return;

            const typeRand = Math.random();
            let type = "normal";

            if (typeRand > 0.8) type = "fast";
            if (typeRand > 0.93) type = "tank";

            const enemy = {
                type,
                x: 40 + Math.random() * (canvas.width - 80),
                y: -40,
                size: type === "tank" ? 28 : type === "fast" ? 18 : 22,
                speed: type === "tank" ? 1.3 : type === "fast" ? 3.2 : 2,
                hp: type === "tank" ? 35 : type === "fast" ? 10 : 18,
                color: type === "tank" ? "#b03a2e" : type === "fast" ? "#ff7675" : "#e74c3c",
                shootRate: type === "tank" ? 110 : 180
            };

            enemies.push(enemy);
        }

        function drawEnemies() {
            for (let i = enemies.length - 1; i >= 0; i--) {
                const e = enemies[i];
                e.y += e.speed;

                ctx.save();
                ctx.translate(e.x, e.y);

                ctx.shadowColor = "rgba(255,100,70,0.35)";
                ctx.shadowBlur = 14;

                if (e.type === "fast") {
                    ctx.fillStyle = "#ff6b6b";
                    ctx.beginPath();
                    ctx.moveTo(0, -e.size);
                    ctx.lineTo(e.size * 0.8, e.size);
                    ctx.lineTo(0, e.size * 0.5);
                    ctx.lineTo(-e.size * 0.8, e.size);
                    ctx.closePath();
                    ctx.fill();
                } else if (e.type === "tank") {
                    ctx.fillStyle = "#9b2c2c";
                    ctx.beginPath();
                    ctx.roundRect(-e.size, -e.size, e.size * 2, e.size * 2.2, 8);
                    ctx.fill();

                    ctx.fillStyle = "#ffd166";
                    ctx.fillRect(-6, -8, 12, 16);
                } else {
                    ctx.fillStyle = "#e74c3c";
                    ctx.beginPath();
                    ctx.arc(0, 0, e.size, 0, Math.PI * 2);
                    ctx.fill();

                    ctx.fillStyle = "#ffd166";
                    ctx.beginPath();
                    ctx.arc(0, 0, e.size * 0.35, 0, Math.PI * 2);
                    ctx.fill();
                }

                ctx.restore();

                if (frame % e.shootRate === 0 && e.y > 40) {
                    const angle = Math.atan2(player.y - e.y, player.x - e.x);
                    enemyBullets.push({
                        x: e.x,
                        y: e.y,
                        vx: Math.cos(angle) * 3,
                        vy: Math.sin(angle) * 3,
                        r: e.type === "tank" ? 6 : 4,
                        damage: e.type === "tank" ? 10 : 6
                    });
                }

                if (e.y > canvas.height + 50) {
                    enemies.splice(i, 1);
                    player.hp -= 8;
                    updateUI();
                    if (player.hp <= 0) {
                        player.hp = 0;
                        gameOver = true;
                    }
                    continue;
                }

                for (let j = bullets.length - 1; j >= 0; j--) {
                    const b = bullets[j];
                    if (
                        b.x > e.x - e.size &&
                        b.x < e.x + e.size &&
                        b.y > e.y - e.size &&
                        b.y < e.y + e.size
                    ) {
                        e.hp -= b.damage;
                        bullets.splice(j, 1);
                        createExplosion(b.x, b.y, "#ffd166", 5);

                        if (e.hp <= 0) {
                            score += e.type === "tank" ? 25 : e.type === "fast" ? 15 : 10;
                            createExplosion(e.x, e.y, "#ff7043", 16);
                            enemies.splice(i, 1);
                            updateUI();
                        }
                        break;
                    }
                }
            }
        }

        function spawnBoss() {
            bossMode = true;
            bossBarWrap.style.display = "block";

            boss = {
                x: canvas.width / 2,
                y: 120,
                w: 180,
                h: 180,
                hp: 500,
                maxHp: 500,
                dir: 1,
                angle: 0,
                shootCooldown: 0,
                phase: 1
            };
        }

        function drawBoss() {
            if (!boss) return;

            boss.x += 2.2 * boss.dir;
            if (boss.x > canvas.width - 110 || boss.x < 110) boss.dir *= -1;
            boss.angle += 0.03;

            if (boss.hp < boss.maxHp * 0.55) boss.phase = 2;
            if (boss.hp < boss.maxHp * 0.25) boss.phase = 3;

            ctx.save();
            ctx.translate(boss.x, boss.y);
            ctx.rotate(Math.sin(boss.angle) * 0.08);

            ctx.shadowColor = "rgba(255,180,70,0.55)";
            ctx.shadowBlur = 24;

            const gold = ctx.createRadialGradient(0, 0, 20, 0, 0, 100);
            gold.addColorStop(0, "#fff1b0");
            gold.addColorStop(0.35, "#f6c453");
            gold.addColorStop(0.7, "#c9891f");
            gold.addColorStop(1, "#6b3f00");

            ctx.fillStyle = gold;
            drawThaiBossShape();

            ctx.fillStyle = "#8e1b1b";
            ctx.beginPath();
            ctx.arc(0, 0, 34, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = "#ffdf7a";
            ctx.beginPath();
            ctx.arc(0, 0, 14, 0, Math.PI * 2);
            ctx.fill();

            ctx.rotate(-boss.angle * 1.8);
            ctx.strokeStyle = "rgba(255, 220, 120, 0.9)";
            ctx.lineWidth = 3;

            for (let i = 0; i < 8; i++) {
                ctx.rotate(Math.PI / 4);
                ctx.beginPath();
                ctx.moveTo(0, -25);
                ctx.quadraticCurveTo(12, -58, 0, -88);
                ctx.quadraticCurveTo(-12, -58, 0, -25);
                ctx.stroke();
            }

            ctx.restore();

            boss.shootCooldown--;
            if (boss.shootCooldown <= 0) {
                if (boss.phase === 1) {
                    shootBossCircle(8, 2.2);
                    boss.shootCooldown = 90;
                } else if (boss.phase === 2) {
                    shootBossCircle(12, 2.8);
                    shootBossAim(2.8);
                    boss.shootCooldown = 70;
                } else {
                    shootBossCircle(16, 3.2);
                    shootBossAim(3.5);
                    shootBossSpiral();
                    boss.shootCooldown = 50;
                }
            }

            for (let i = bullets.length - 1; i >= 0; i--) {
                const b = bullets[i];
                if (
                    b.x > boss.x - 85 &&
                    b.x < boss.x + 85 &&
                    b.y > boss.y - 85 &&
                    b.y < boss.y + 85
                ) {
                    boss.hp -= b.damage;
                    bullets.splice(i, 1);
                    createExplosion(b.x, b.y, "#ffe082", 6);
                    updateBossBar();

                    if (boss.hp <= 0) {
                        boss.hp = 0;
                        score += 500;
                        updateUI();
                        createExplosion(boss.x, boss.y, "#ffb347", 45);
                        boss = null;
                        bossMode = false;
                        bossBarWrap.style.display = "none";
                        level++;
                        updateUI();
                    }
                }
            }
        }

        function drawThaiBossShape() {
            ctx.beginPath();

            ctx.moveTo(0, -95);
            ctx.quadraticCurveTo(28, -58, 20, -12);
            ctx.quadraticCurveTo(58, -42, 92, -18);
            ctx.quadraticCurveTo(65, 5, 42, 12);
            ctx.quadraticCurveTo(82, 40, 70, 82);
            ctx.quadraticCurveTo(28, 64, 14, 32);

            ctx.quadraticCurveTo(8, 76, 0, 98);
            ctx.quadraticCurveTo(-8, 76, -14, 32);

            ctx.quadraticCurveTo(-28, 64, -70, 82);
            ctx.quadraticCurveTo(-82, 40, -42, 12);
            ctx.quadraticCurveTo(-65, 5, -92, -18);
            ctx.quadraticCurveTo(-58, -42, -20, -12);
            ctx.quadraticCurveTo(-28, -58, 0, -95);

            ctx.closePath();
            ctx.fill();

            ctx.strokeStyle = "rgba(120, 40, 0, 0.55)";
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(0, -70);
            ctx.quadraticCurveTo(12, -30, 0, 18);
            ctx.quadraticCurveTo(-12, -30, 0, -70);
            ctx.stroke();
        }

        function shootBossCircle(count, speed) {
            for (let i = 0; i < count; i++) {
                const angle = (Math.PI * 2 / count) * i + boss.angle;
                enemyBullets.push({
                    x: boss.x,
                    y: boss.y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    r: 5,
                    damage: 8
                });
            }
        }

        function shootBossAim(speed) {
            const angle = Math.atan2(player.y - boss.y, player.x - boss.x);
            enemyBullets.push({
                x: boss.x,
                y: boss.y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                r: 7,
                damage: 12
            });
        }

        function shootBossSpiral() {
            for (let i = 0; i < 2; i++) {
                const angle = boss.angle + i * Math.PI;
                enemyBullets.push({
                    x: boss.x,
                    y: boss.y,
                    vx: Math.cos(angle) * 4,
                    vy: Math.sin(angle) * 4,
                    r: 4,
                    damage: 10
                });
            }
        }

        function createExplosion(x, y, color, amount) {
            for (let i = 0; i < amount; i++) {
                particles.push({
                    x,
                    y,
                    vx: (Math.random() - 0.5) * 5,
                    vy: (Math.random() - 0.5) * 5,
                    size: 2 + Math.random() * 4,
                    life: 20 + Math.random() * 20,
                    color
                });
            }
        }

        function drawParticles() {
            for (let i = particles.length - 1; i >= 0; i--) {
                const p = particles[i];
                p.x += p.vx;
                p.y += p.vy;
                p.life--;

                ctx.save();
                ctx.globalAlpha = p.life / 40;
                ctx.fillStyle = p.color;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();

                if (p.life <= 0) particles.splice(i, 1);
            }
        }

        function rectCircleCollide(rect, circle) {
            const distX = Math.abs(circle.x - rect.x);
            const distY = Math.abs(circle.y - rect.y);
            return distX <= rect.w / 2 + circle.r && distY <= rect.h / 2 + circle.r;
        }

        function updateUI() {
            scoreEl.textContent = score;
            levelEl.textContent = level;
            playerHpEl.textContent = player.hp;
        }

        function updateBossBar() {
            if (!boss) return;
            const percent = (boss.hp / boss.maxHp) * 100;
            bossBarFill.style.width = percent + "%";
        }

        canvas.addEventListener("mousemove", (e) => {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;

            mouseX = (e.clientX - rect.left) * scaleX;
            mouseY = (e.clientY - rect.top) * scaleY;

            player.x = Math.max(30, Math.min(canvas.width - 30, mouseX));
            player.y = Math.max(80, Math.min(canvas.height - 60, mouseY));
        });

        canvas.addEventListener("click", () => {
            if (!gameOver && player.shootCooldown <= 0) {
                shoot();
                player.shootCooldown = 10;
            }
        });

        function drawVignette() {
            const grad = ctx.createRadialGradient(
                canvas.width / 2,
                canvas.height / 2,
                120,
                canvas.width / 2,
                canvas.height / 2,
                420
            );
            grad.addColorStop(0, "rgba(255,255,255,0)");
            grad.addColorStop(1, "rgba(0,0,0,0.45)");
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }

        function drawGameOver() {
            ctx.save();
            ctx.fillStyle = "rgba(0,0,0,0.62)";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.fillStyle = "#fff0d0";
            ctx.textAlign = "center";
            ctx.font = "bold 40px Tahoma";
            ctx.fillText("GAME OVER", canvas.width / 2, canvas.height / 2 - 30);

            ctx.font = "bold 22px Tahoma";
            ctx.fillStyle = "#ffd166";
            ctx.fillText("คะแนนรวม: " + score, canvas.width / 2, canvas.height / 2 + 15);

            ctx.font = "18px Tahoma";
            ctx.fillStyle = "#ffffff";
            ctx.fillText("รีเฟรชหน้าเพื่อเล่นใหม่", canvas.width / 2, canvas.height / 2 + 55);
            ctx.restore();
        }

        function gameLoop() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            frame++;

            drawStars();
            drawParticles();

            if (!gameOver) {
                if (player.shootCooldown > 0) player.shootCooldown--;

                if (!bossMode && frame % 40 === 0) spawnEnemy();

                if (!bossMode && score >= 180) {
                    enemies = [];
                    enemyBullets = [];
                    spawnBoss();
                    updateBossBar();
                }

                drawEnemies();
                drawBullets();
                drawEnemyBullets();
                drawPlayer();

                if (bossMode) {
                    drawBoss();
                }

                drawVignette();
            } else {
                drawEnemies();
                drawBullets();
                drawEnemyBullets();
                drawPlayer();
                drawParticles();
                drawVignette();
                drawGameOver();
            }

            requestAnimationFrame(gameLoop);
        }

        createStars();
        updateUI();
        gameLoop();
    </script>
</body>
</html>