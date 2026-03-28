<?php
include 'config.php';
echo "<pre>";

// 1. Check avatar column exists
$res = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
if ($res && $res->num_rows > 0) {
    echo "avatar column: EXISTS\n";
} else {
    echo "avatar column: MISSING — กำลัง ADD...\n";
    $ok = $conn->query("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL");
    echo $ok ? "ADD COLUMN สำเร็จ ✓\n" : "ADD COLUMN ล้มเหลว: " . $conn->error . "\n";
}

// 2. Check all columns
$res2 = $conn->query("SHOW COLUMNS FROM users");
echo "\n=== users columns ===\n";
while ($r = $res2->fetch_assoc()) echo $r['Field'] . " (" . $r['Type'] . ")\n";

// 3. Check uploads/avatars
$dir = __DIR__ . '/uploads/avatars/';
echo "\n=== uploads/avatars ===\n";
echo "exists: "   . (is_dir($dir)    ? "YES" : "NO") . "\n";
echo "writable: " . (is_writable($dir) ? "YES" : "NO") . "\n";

echo "</pre>";
