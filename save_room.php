<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

function redirect_back($message, $editId = 0, $type = 'error') {
    $_SESSION['room_msg']      = $message;
    $_SESSION['room_msg_type'] = $type;
    $url = "manage_rooms.php";
    if ($editId > 0) $url .= "?edit=" . $editId;
    header("Location: " . $url);
    exit;
}

$checkTable = $conn->query("SHOW TABLES LIKE 'rooms'");
if (!$checkTable || $checkTable->num_rows === 0) die("ไม่พบตาราง rooms");

$id          = isset($_POST['id'])          ? (int)$_POST['id']          : 0;
$room_name   = trim($_POST['room_name']     ?? '');
$room_type   = trim($_POST['room_type']     ?? '');
$description = trim($_POST['description']   ?? '');
$price       = isset($_POST['price'])       ? (float)$_POST['price']     : 0;
$total_rooms = isset($_POST['total_rooms']) ? (int)$_POST['total_rooms'] : 5;
$max_guests  = isset($_POST['max_guests'])  ? (int)$_POST['max_guests']  : 2;
$room_size   = trim($_POST['room_size']     ?? '');
$bed_type    = trim($_POST['bed_type']      ?? '');
$capacity    = $max_guests;

// ✅ แก้: status เป็น ENUM('show','hide') ไม่ใช่ 0/1
$status = (isset($_POST['status']) && $_POST['status'] === 'hide') ? 'hide' : 'show';

if ($room_name === '' || $room_type === '') redirect_back("กรุณากรอกชื่อห้องพักและประเภทห้อง", $id);

if ($price       < 0)  $price       = 0;
if ($total_rooms <= 0) $total_rooms = 5;
if ($max_guests  <= 0) $max_guests  = 2;

$uploadRoot   = __DIR__ . '/uploads';
$uploadDir    = __DIR__ . '/uploads/rooms/';
$dbUploadPath = '';

if (!is_dir($uploadRoot)) mkdir($uploadRoot, 0777, true);
if (!is_dir($uploadDir))  mkdir($uploadDir,  0777, true);
if (!is_writable($uploadDir)) redirect_back("โฟลเดอร์ uploads/rooms เขียนไฟล์ไม่ได้");

if (isset($_FILES['room_image']) && $_FILES['room_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['room_image']['error'] !== UPLOAD_ERR_OK) redirect_back("อัปโหลดรูปไม่สำเร็จ", $id);

    $tmpName      = $_FILES['room_image']['tmp_name'];
    $originalName = $_FILES['room_image']['name'];
    $fileSize     = (int)$_FILES['room_image']['size'];

    if (!is_uploaded_file($tmpName))      redirect_back("ไม่พบไฟล์อัปโหลดที่ถูกต้อง", $id);
    if ($fileSize <= 0)                   redirect_back("ไฟล์รูปไม่ถูกต้อง", $id);
    if ($fileSize > 5 * 1024 * 1024)     redirect_back("ไฟล์รูปต้องมีขนาดไม่เกิน 5MB", $id);

    $ext     = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','gif'];
    if (!in_array($ext, $allowed, true))  redirect_back("รองรับเฉพาะ jpg, jpeg, png, webp, gif", $id);
    if (@getimagesize($tmpName) === false) redirect_back("ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ", $id);

    $newName      = 'room_' . date('Ymd_His') . '_' . mt_rand(1000,9999) . '.' . $ext;
    $dbUploadPath = 'uploads/rooms/' . $newName;
    if (!move_uploaded_file($tmpName, $uploadDir . $newName)) redirect_back("ย้ายไฟล์รูปไม่สำเร็จ", $id);
}

/* ══ UPDATE ══ */
if ($id > 0) {

    $oldImagePath = '';
    $stmtOld = $conn->prepare("SELECT image_path FROM rooms WHERE id = ? LIMIT 1");
    if (!$stmtOld) redirect_back("Prepare SELECT failed: " . $conn->error, $id);
    $stmtOld->bind_param("i", $id);
    $stmtOld->execute();
    $stmtOld->bind_result($oldImageValue);
    if ($stmtOld->fetch()) $oldImagePath = $oldImageValue ?? '';
    $stmtOld->close();

    if ($dbUploadPath === '') {
        $dbUploadPath = $oldImagePath;
    } else {
        if (!empty($oldImagePath)) {
            $oldFull = __DIR__ . '/' . ltrim($oldImagePath, '/');
            if (file_exists($oldFull)) @unlink($oldFull);
        }
    }

    $sql = "UPDATE rooms SET
                room_name   = ?,
                room_type   = ?,
                price       = ?,
                description = ?,
                status      = ?,
                room_size   = ?,
                bed_type    = ?,
                capacity    = ?,
                total_rooms = ?,
                max_guests  = ?,
                image_path  = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) redirect_back("Prepare UPDATE failed: " . $conn->error, $id);

    // s s d s s s s i i i s i
    $stmt->bind_param("ssdssssiissi",
        $room_name, $room_type, $price, $description, $status,
        $room_size, $bed_type, $capacity, $total_rooms, $max_guests,
        $dbUploadPath, $id
    );

    if (!$stmt->execute()) {
        $err = $stmt->error; $stmt->close();
        redirect_back("อัปเดตข้อมูลไม่สำเร็จ: " . $err, $id);
    }
    $stmt->close();
    redirect_back("อัปเดตข้อมูลห้องพักเรียบร้อยแล้ว", 0, 'success');

/* ══ INSERT ══ */
} else {

    $sql = "INSERT INTO rooms
                (room_name, room_type, price, description, status,
                 room_size, bed_type, capacity, total_rooms, max_guests, image_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) redirect_back("Prepare INSERT failed: " . $conn->error);

    // s s d s s s s i i i s
    $stmt->bind_param("ssdssssiiis",
        $room_name, $room_type, $price, $description, $status,
        $room_size, $bed_type, $capacity, $total_rooms, $max_guests,
        $dbUploadPath
    );

    if (!$stmt->execute()) {
        $err = $stmt->error; $stmt->close();
        redirect_back("บันทึกข้อมูลไม่สำเร็จ: " . $err);
    }
    $stmt->close();
    redirect_back("บันทึกห้องพักเรียบร้อยแล้ว", 0, 'success');
}