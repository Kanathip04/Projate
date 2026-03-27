<?php
// debug_profile.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

echo "<pre>";
echo "SESSION: "; print_r($_SESSION);
echo "\n";

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id) {
    $user = $conn->query("SELECT * FROM users WHERE id=$user_id LIMIT 1")->fetch_assoc();
    echo "USER: "; print_r($user);
} else {
    echo "ไม่มี user_id ใน session";
}
echo "</pre>";
?>