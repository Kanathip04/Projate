<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

echo "<pre>";
echo "Session: ";
print_r($_SESSION);
echo "</pre>";

echo "<h1>Test Dashboard</h1>";
echo "<p>ถ้าเห็นหน้านี้ แสดงว่า PHP ทำงานปกติ</p>";
?>