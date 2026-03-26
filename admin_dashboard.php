<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "<h1>เข้า admin_dashboard.php ได้แล้ว</h1>";
echo "<p>Session admin_logged_in = ";
var_dump($_SESSION['admin_logged_in'] ?? null);
echo "</p>";
echo '<p><a href="login.php">กลับหน้า login</a></p>';
?>