<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $res = $conn->query("SELECT image_path FROM rooms WHERE id = $id LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        if (!empty($row['image_path']) && file_exists($row['image_path'])) {
            unlink($row['image_path']);
        }
    }

    $conn->query("DELETE FROM rooms WHERE id = $id");
}

header("Location: manage_rooms.php");
exit;