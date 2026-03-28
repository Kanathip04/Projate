<?php
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$games = [];
$sql = "SELECT * FROM games WHERE is_active = 1 ORDER BY sort_order ASC, id ASC";
$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $games[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เมนูเกม</title>

<style>
body{
    font-family:'Segoe UI', Tahoma, sans-serif;
    background:#f3f3f3;
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:100vh;
    margin:0;
}

.game-container{
    width:560px;
    background:#fff;
    padding:40px;
    border-radius:18px;
    box-shadow:0 20px 50px rgba(0,0,0,.12);
    text-align:center;
}

.game-container h1{
    margin-bottom:30px;
    font-size:48px;
    font-weight:800;
}

.game-list{
    display:flex;
    flex-direction:column;
    gap:18px;
}

.game-card{
    display:flex;
    align-items:center;
    gap:16px;
    text-decoration:none;
    background:#6f8f10;
    color:#fff;
    padding:14px;
    border-radius:14px;
    transition:.2s;
}

.game-card:hover{
    background:#5d780d;
    transform:translateY(-2px);
}

.game-image{
    width:120px;
    height:70px;
    border-radius:10px;
    object-fit:cover;
    background:#dcdcdc;
    flex-shrink:0;
}

.game-title{
    font-size:22px;
    font-weight:700;
    text-align:left;
}

.no-image{
    width:120px;
    height:70px;
    border-radius:10px;
    background:#dcdcdc;
    color:#666;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:14px;
    flex-shrink:0;
}

.no-game{
    padding:20px;
    color:#666;
    font-size:18px;
}

.back-btn{
    position:absolute;
    top:30px;
    left:30px;
    background:#6f8f10;
    color:white;
    padding:12px 22px;
    border-radius:10px;
    text-decoration:none;
    font-weight:700;
    box-shadow:0 5px 15px rgba(0,0,0,0.15);
    transition:0.2s;
}

.back-btn:hover{
    background:#5d780d;
    transform:translateY(-2px);
}

</style>
</head>
<body>

<a href="index.php" class="back-btn">← กลับหน้าหลัก</a>

<div class="game-container">
    <h1>เลือกเกม</h1>

    <div class="game-list">
        <?php if (!empty($games)): ?>
            <?php foreach ($games as $game): ?>
                <a href="<?php echo htmlspecialchars($game['game_url']); ?>" class="game-card">
                    <?php if (!empty($game['cover_image'])): ?>
                        <img src="<?php echo htmlspecialchars($game['cover_image']); ?>" alt="<?php echo htmlspecialchars($game['title']); ?>" class="game-image">
                    <?php else: ?>
                        <div class="no-image">ไม่มีรูป</div>
                    <?php endif; ?>

                    <div class="game-title">
                        <?php echo htmlspecialchars($game['title']); ?>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-game">ยังไม่มีเกมให้แสดง</div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>