<?php
mysqli_report(MYSQLI_REPORT_OFF); // ปิด exception ก่อน

$conn = null;
$usedPass = null;
foreach (["Kanathip04", "root", ""] as $p) {
    $c = new mysqli("localhost", "root", $p, "backoffice_db");
    if (!$c->connect_error) { $conn = $c; $usedPass = $p; break; }
}

if (!$conn) {
    die("<b>เชื่อมต่อ MySQL ไม่ได้เลย</b><br>ลอง password: Kanathip04, root, (empty) แล้วทั้งหมด<br>กรุณาเปิด XAMPP และตรวจสอบ MySQL กำลังรันอยู่");
}

$conn->set_charset("utf8mb4");
echo "<pre>";
echo "Connected ✓  password='" . ($usedPass === "" ? "(empty)" : $usedPass) . "'\n\n";

// 1. Check/Add avatar column
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($res && $res->num_rows > 0) {
    echo "avatar column: EXISTS ✓\n";
} else {
    echo "avatar column: MISSING — กำลัง ADD...\n";
    $ok = $conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
    echo $ok ? "ADD COLUMN สำเร็จ ✓\n" : "Error: " . $conn->error . "\n";
}

// 2. Show all columns
$res2 = $conn->query("SHOW COLUMNS FROM users");
echo "\n=== users columns ===\n";
while ($r = $res2->fetch_assoc()) echo "  " . $r['Field'] . " (" . $r['Type'] . ")\n";

// 3. Check folder
$dir = __DIR__ . '/uploads/avatars/';
echo "\n=== uploads/avatars ===\n";
echo "  exists:   " . (is_dir($dir)     ? "YES ✓" : "NO ✗") . "\n";
echo "  writable: " . (is_writable($dir) ? "YES ✓" : "NO ✗") . "\n";
echo "</pre>";
