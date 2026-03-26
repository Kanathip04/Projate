<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function redirect_back($message, $editId = 0) {
    $_SESSION['room_msg'] = $message;
    $url = "manage_rooms.php";
    if ($editId > 0) {
        $url .= "?edit=" . $editId;
    }
    header("Location: " . $url);
    exit;
}

function hasColumn(mysqli $conn, string $table, string $column): bool {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `$table` LIKE '$column'";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

/* =========================
   ตรวจสอบตาราง rooms
========================= */
$checkTable = $conn->query("SHOW TABLES LIKE 'rooms'");
if (!$checkTable || $checkTable->num_rows === 0) {
    die("ไม่พบตาราง rooms");
}

/* =========================
   รับค่าจากฟอร์ม
   รองรับหลายชื่อ field กันพลาด
========================= */
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$room_name   = trim($_POST['room_name'] ?? $_POST['name'] ?? '');
$room_type   = trim($_POST['room_type'] ?? $_POST['type'] ?? '');
$description = trim($_POST['description'] ?? $_POST['room_detail'] ?? $_POST['detail'] ?? '');
$price       = isset($_POST['price']) ? (float)$_POST['price'] : 0;
$total_rooms = isset($_POST['total_rooms']) ? (int)$_POST['total_rooms'] : ((isset($_POST['room_total'])) ? (int)$_POST['room_total'] : 5);
$max_guests  = isset($_POST['max_guests']) ? (int)$_POST['max_guests'] : ((isset($_POST['guests'])) ? (int)$_POST['guests'] : 2);
$room_size   = trim($_POST['room_size'] ?? $_POST['size'] ?? '');
$bed_type    = trim($_POST['bed_type'] ?? $_POST['bed'] ?? '');

$status_raw = trim($_POST['status'] ?? $_POST['display_status'] ?? 'show');
if ($status_raw === 'show' || $status_raw === 'แสดง' || $status_raw === '1') {
    $status = 'show';
} elseif ($status_raw === 'hide' || $status_raw === 'ซ่อน' || $status_raw === '0') {
    $status = 'hide';
} else {
    $status = 'show';
}

if ($room_name === '' || $room_type === '') {
    redirect_back("กรุณากรอกชื่อห้องพักและประเภทห้อง", $id);
}

if ($price < 0) {
    $price = 0;
}
if ($total_rooms <= 0) {
    $total_rooms = 5;
}
if ($max_guests <= 0) {
    $max_guests = 2;
}

/* =========================
   เตรียมโฟลเดอร์อัปโหลด
========================= */
$uploadDir = __DIR__ . '/uploads/rooms/';
$dbUploadPathPrefix = 'uploads/rooms/';

if (!is_dir(__DIR__ . '/uploads')) {
    if (!mkdir(__DIR__ . '/uploads', 0777, true)) {
        redirect_back("สร้างโฟลเดอร์ uploads ไม่สำเร็จ", $id);
    }
}

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        redirect_back("สร้างโฟลเดอร์ uploads/rooms ไม่สำเร็จ", $id);
    }
}

if (!is_writable($uploadDir)) {
    redirect_back("โฟลเดอร์ uploads/rooms เขียนไฟล์ไม่ได้ กรุณาตั้ง permission ของโฟลเดอร์", $id);
}

/* =========================
   อัปโหลดรูป
========================= */
$image_path = '';

if (isset($_FILES['room_image']) && is_array($_FILES['room_image'])) {

    if ($_FILES['room_image']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['room_image']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'ไฟล์ใหญ่เกินค่า upload_max_filesize ในเซิร์ฟเวอร์',
                UPLOAD_ERR_FORM_SIZE  => 'ไฟล์ใหญ่เกินค่าที่ฟอร์มกำหนด',
                UPLOAD_ERR_PARTIAL    => 'ไฟล์ถูกอัปโหลดมาไม่ครบ',
                UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มี temp folder',
                UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์ไม่สามารถเขียนไฟล์ลงดิสก์ได้',
                UPLOAD_ERR_EXTENSION  => 'มี extension ของ PHP ขัดขวางการอัปโหลดไฟล์',
            ];
            $msg = $uploadErrors[$_FILES['room_image']['error']] ?? 'เกิดข้อผิดพลาดระหว่างอัปโหลดรูป';
            redirect_back($msg, $id);
        }

        $tmpName = $_FILES['room_image']['tmp_name'];
        $originalName = $_FILES['room_image']['name'];
        $fileSize = (int)$_FILES['room_image']['size'];

        if (!is_uploaded_file($tmpName)) {
            redirect_back("ไม่พบไฟล์อัปโหลดที่ถูกต้อง", $id);
        }

        if ($fileSize <= 0) {
            redirect_back("ไฟล์รูปไม่ถูกต้อง", $id);
        }

        if ($fileSize > 5 * 1024 * 1024) {
            redirect_back("ไฟล์รูปต้องมีขนาดไม่เกิน 5MB", $id);
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (!in_array($ext, $allowed, true)) {
            redirect_back("รองรับเฉพาะไฟล์ jpg, jpeg, png, webp, gif", $id);
        }

        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false) {
            redirect_back("ไฟล์ที่อัปโหลดไม่ใช่รูปภาพ", $id);
        }

        $newName = 'room_' . date('Ymd_His') . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $targetFullPath = $uploadDir . $newName;
        $dbPath = $dbUploadPathPrefix . $newName;

        if (!move_uploaded_file($tmpName, $targetFullPath)) {
            redirect_back("ย้ายไฟล์รูปไม่สำเร็จ กรุณาตรวจสอบ permission ของ uploads/rooms", $id);
        }

        $image_path = $dbPath;
    }
}

/* =========================
   ตรวจสอบคอลัมน์จริงในตาราง
========================= */
$descColumn  = hasColumn($conn, 'rooms', 'description') ? 'description' : (hasColumn($conn, 'rooms', 'room_detail') ? 'room_detail' : '');
$imageColumn = hasColumn($conn, 'rooms', 'image_path') ? 'image_path' : (hasColumn($conn, 'rooms', 'room_image') ? 'room_image' : '');
$statusColumn = hasColumn($conn, 'rooms', 'status') ? 'status' : '';

if ($descColumn === '') {
    redirect_back("ตาราง rooms ไม่มีคอลัมน์ description หรือ room_detail", $id);
}
if ($imageColumn === '') {
    redirect_back("ตาราง rooms ไม่มีคอลัมน์ image_path หรือ room_image", $id);
}
if ($statusColumn === '') {
    redirect_back("ตาราง rooms ไม่มีคอลัมน์ status", $id);
}

/* =========================
   เพิ่ม / แก้ไขข้อมูล
========================= */
if ($id > 0) {

    $oldImage = '';

    $sqlOld = "SELECT `$imageColumn` FROM rooms WHERE id = ? LIMIT 1";
    $stmtOld = $conn->prepare($sqlOld);
    if (!$stmtOld) {
        redirect_back("Prepare SELECT failed: " . $conn->error, $id);
    }

    $stmtOld->bind_param("i", $id);
    $stmtOld->execute();
    $stmtOld->bind_result($oldImageValue);

    if ($stmtOld->fetch()) {
        $oldImage = $oldImageValue ?? '';
    }
    $stmtOld->close();

    if ($image_path === '') {
        $image_path = $oldImage;
    } else {
        if (!empty($oldImage)) {
            $oldFullPath = __DIR__ . '/' . ltrim($oldImage, '/');
            if (file_exists($oldFullPath)) {
                @unlink($oldFullPath);
            }
        }
    }

    $sql = "UPDATE rooms SET
                room_name = ?,
                room_type = ?,
                `$descColumn` = ?,
                price = ?,
                total_rooms = ?,
                max_guests = ?,
                room_size = ?,
                bed_type = ?,
                `$imageColumn` = ?,
                `$statusColumn` = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        redirect_back("Prepare UPDATE failed: " . $conn->error, $id);
    }

    $stmt->bind_param(
        "sssdiissssi",
        $room_name,
        $room_type,
        $description,
        $price,
        $total_rooms,
        $max_guests,
        $room_size,
        $bed_type,
        $image_path,
        $status,
        $id
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        redirect_back("อัปเดตข้อมูลไม่สำเร็จ: " . $err, $id);
    }

    $stmt->close();
    $_SESSION['room_msg'] = "อัปเดตข้อมูลห้องพักเรียบร้อยแล้ว";

} else {

    $sql = "INSERT INTO rooms (
                room_name,
                room_type,
                `$descColumn`,
                price,
                total_rooms,
                max_guests,
                room_size,
                bed_type,
                `$imageColumn`,
                `$statusColumn`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        redirect_back("Prepare INSERT failed: " . $conn->error, 0);
    }

    $stmt->bind_param(
        "sssdiissss",
        $room_name,
        $room_type,
        $description,
        $price,
        $total_rooms,
        $max_guests,
        $room_size,
        $bed_type,
        $image_path,
        $status
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        redirect_back("บันทึกข้อมูลไม่สำเร็จ: " . $err, 0);
    }

    $stmt->close();
    $_SESSION['room_msg'] = "บันทึกห้องพักเรียบร้อยแล้ว";
}

header("Location: manage_rooms.php");
exit;
?>