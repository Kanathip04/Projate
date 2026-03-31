<?php
/**
 * room_status_check.php
 * ตรวจสถานะการชำระเงินของการจองห้องพัก (AJAX endpoint)
 */
session_start();
require_once 'auth_guard.php';
header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) {
    echo json_encode(['error' => 'db']);
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['error' => 'id']);
    exit;
}

$st = $conn->prepare("SELECT payment_status, booking_status FROM room_bookings WHERE id = ? LIMIT 1");
$st->bind_param("i", $id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();
$conn->close();

if (!$row) {
    echo json_encode(['error' => 'not_found']);
    exit;
}

echo json_encode([
    'status'         => $row['payment_status'],
    'booking_status' => $row['booking_status'],
]);
