<?php
// ลองทั้ง 2 password
$passwords = ["Kanathip04", ""];
$conn = null;
$usedPass = "";
foreach ($passwords as $p) {
    $c = @new mysqli("localhost", "root", $p, "backoffice_db");
    if (!$c->connect_error) { $conn = $c; $usedPass = $p; break; }
}

if (!$conn) { die("เชื่อมต่อ MySQL ไม่ได้เลย — ลอง password ทั้งสองแบบแล้ว"); }

$conn->set_charset("utf8mb4");
echo "<pre>";
echo "Connected with password: '" . ($usedPass === "" ? "(empty)" : $usedPass) . "'\n\n";

// 1. Check avatar column
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($res && $res->num_rows > 0) {
    echo "avatar column: EXISTS ✓\n";
} else {
    echo "avatar column: MISSING — กำลัง ADD...\n";
    $ok = $conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
    echo $ok ? "ADD COLUMN สำเร็จ ✓\n" : "ADD COLUMN ล้มเหลว: " . $conn->error . "\n";
}

// 2. All columns
$res2 = $conn->query("SHOW COLUMNS FROM users");
echo "\n=== users columns ===\n";
while ($r = $res2->fetch_assoc()) echo $r['Field'] . " (" . $r['Type'] . ")\n";

// 3. uploads/avatars
$dir = __DIR__ . '/uploads/avatars/';
echo "\n=== uploads/avatars ===\n";
echo "exists: "   . (is_dir($dir)     ? "YES" : "NO") . "\n";
echo "writable: " . (is_writable($dir) ? "YES" : "NO") . "\n";
echo "</pre>";
