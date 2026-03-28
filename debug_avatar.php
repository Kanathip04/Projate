<?php
session_start();
if (empty($_SESSION['user_id'])) die("Not logged in");

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

echo "<pre>";

// 1. Check users table columns
$res = $conn->query("SHOW COLUMNS FROM users");
echo "=== users table columns ===\n";
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

// 2. Check uploads/avatars writable
$dir = __DIR__ . '/uploads/avatars/';
echo "\n=== uploads/avatars ===\n";
echo "exists: " . (is_dir($dir) ? "YES" : "NO") . "\n";
echo "writable: " . (is_writable($dir) ? "YES" : "NO") . "\n";

// 3. PHP upload settings
echo "\n=== PHP upload settings ===\n";
echo "file_uploads: " . ini_get('file_uploads') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";

echo "</pre>";
