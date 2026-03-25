<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

/* =========================
   เปิด error ชั่วคราว
========================= */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* =========================
   ตรวจสอบ admin login
========================= */
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

/* =========================
   เชื่อมต่อฐานข้อมูล
========================= */
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $conn->connect_error);
}

$message = "";
$message_type = "success";

/* =========================
   ฟังก์ชัน escape
========================= */
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/* =========================
   จัดการ Action ต่าง ๆ
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* -------------------------
       1) แก้ไขข้อมูลการจอง
    ------------------------- */
    if ($action === 'update_booking') {
        $id            = (int)($_POST['id'] ?? 0);
        $full_name     = trim($_POST['full_name'] ?? '');
        $phone         = trim($_POST['phone'] ?? '');
        $email         = trim($_POST['email'] ?? '');
        $room_type     = trim($_POST['room_type'] ?? '');
        $guests        = (int)($_POST['guests'] ?? 1);
        $checkin_date  = trim($_POST['checkin_date'] ?? '');
        $checkout_date = trim($_POST['checkout_date'] ?? '');
        $note          = trim($_POST['note'] ?? '');
        $booking_status= trim($_POST['booking_status'] ?? 'pending');

        $allowed_status = ['pending', 'approved', 'cancelled'];
        if (!in_array($booking_status, $allowed_status, true)) {
            $booking_status = 'pending';
        }

        if ($id <= 0 || $full_name === '' || $phone === '' || $room_type === '' || $checkin_date === '' || $checkout_date === '') {
            $message = "กรอกข้อมูลไม่ครบ";
            $message_type = "error";
        } elseif (strtotime($checkout_date) <= strtotime($checkin_date)) {
            $message = "วันเช็คเอาท์ต้องมากกว่าวันเช็คอิน";
            $message_type = "error";
        } else {
            $stmt = $conn->prepare("
                UPDATE room_bookings
                SET full_name = ?, phone = ?, email = ?, room_type = ?, guests = ?, checkin_date = ?, checkout_date = ?, note = ?, booking_status = ?
                WHERE id = ?
            ");
            if ($stmt) {
                $stmt->bind_param(
                    "ssssissssi",
                    $full_name,
                    $phone,
                    $email,
                    $room_type,
                    $guests,
                    $checkin_date,
                    $checkout_date,
                    $note,
                    $booking_status,
                    $id
                );
                if ($stmt->execute()) {
                    $message = "อัปเดตข้อมูลเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "อัปเดตไม่สำเร็จ: " . $stmt->error;
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "Prepare UPDATE ไม่สำเร็จ: " . $conn->error;
                $message_type = "error";
            }
        }
    }

    /* -------------------------
       2) ลบข้อมูล
    ------------------------- */
    if ($action === 'delete_booking') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM room_bookings WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "ลบข้อมูลเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "ลบข้อมูลไม่สำเร็จ: " . $stmt->error;
                    $message_type = "error";
                }
                $stmt->close();
            } else {
                $message = "Prepare DELETE ไม่สำเร็จ: " . $conn->error;
                $message_type = "error";
            }
        }
    }

    /* -------------------------
       3) เปลี่ยนสถานะ
    ------------------------- */
    if ($action === 'change_status') {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = trim($_POST['booking_status'] ?? 'pending');

        $allowed_status = ['pending', 'approved', 'cancelled'];
        if ($id > 0 && in_array($new_status, $allowed_status, true)) {
            $stmt = $conn->prepare("UPDATE room_bookings SET booking_status = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $new_status, $id);
                if ($stmt->execute()) {
                    $message = "เปลี่ยนสถานะเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "เปลี่ยนสถานะไม่สำเร็จ";
                    $message_type = "error";
                }
                $stmt->close();
            }
        }
    }

    /* -------------------------
       4) จัดเก็บข้อมูล
    ------------------------- */
    if ($action === 'archive_booking') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE room_bookings SET archived = 1 WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "จัดเก็บข้อมูลเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "จัดเก็บข้อมูลไม่สำเร็จ";
                    $message_type = "error";
                }
                $stmt->close();
            }
        }
    }

    /* -------------------------
       5) กู้คืนข้อมูลจาก archive
    ------------------------- */
    if ($action === 'unarchive_booking') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE room_bookings SET archived = 0 WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    $message = "กู้คืนข้อมูลเรียบร้อยแล้ว";
                    $message_type = "success";
                } else {
                    $message = "กู้คืนข้อมูลไม่สำเร็จ";
                    $message_type = "error";
                }
                $stmt->close();
            }
        }
    }

    header("Location: admin_bookings.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit;
}

/* =========================
   รับข้อความแจ้งเตือน
========================= */
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'] ?? 'success';
}

/* =========================
   ค้นหา / filter
========================= */
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$archive_filter = isset($_GET['archived']) ? (int)$_GET['archived'] : 0;
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

/* =========================
   โหลดข้อมูลที่จะแก้ไข
========================= */
$edit_data = null;
if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM room_bookings WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $edit_data = $res->fetch_assoc();
        $stmt->close();
    }
}

/* =========================
   สถิติสรุป
========================= */
$stat_total = 0;
$stat_pending = 0;
$stat_approved = 0;
$stat_cancelled = 0;

$sqlStat = "
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN booking_status='pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN booking_status='approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN booking_status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM room_bookings
    WHERE archived = $archive_filter
";
$resStat = $conn->query($sqlStat);
if ($resStat && $rowStat = $resStat->fetch_assoc()) {
    $stat_total = (int)$rowStat['total'];
    $stat_pending = (int)$rowStat['pending_count'];
    $stat_approved = (int)$rowStat['approved_count'];
    $stat_cancelled = (int)$rowStat['cancelled_count'];
}

/* =========================
   ดึงข้อมูลรายการจอง
========================= */
$where = "WHERE archived = ?";
$params = [$archive_filter];
$types = "i";

if ($search !== '') {
    $where .= " AND (
        full_name LIKE ?
        OR phone LIKE ?
        OR email LIKE ?
        OR room_type LIKE ?
        OR note LIKE ?
    )";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sssss";
}

if ($status_filter !== '' && in_array($status_filter, ['pending','approved','cancelled'], true)) {
    $where .= " AND booking_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "SELECT * FROM room_bookings $where ORDER BY id DESC";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare SELECT ไม่สำเร็จ: " . $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการข้อมูลการจองห้องพัก</title>
<style>
    *{box-sizing:border-box}
    body{
        margin:0;
        font-family:'Segoe UI',Tahoma,sans-serif;
        background:#f4f6f9;
        color:#222;
    }
    .container{
        max-width:1400px;
        margin:0 auto;
        padding:24px;
    }
    .topbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        flex-wrap:wrap;
        margin-bottom:20px;
    }
    .title{
        font-size:28px;
        font-weight:800;
        color:#1a1a1a;
    }
    .back-btn{
        text-decoration:none;
        background:#1d3557;
        color:#fff;
        padding:10px 16px;
        border-radius:12px;
        font-weight:700;
    }
    .msg{
        padding:14px 16px;
        border-radius:14px;
        margin-bottom:18px;
        font-weight:600;
    }
    .msg.success{
        background:#eaf8ef;
        color:#167c3f;
        border:1px solid #bfe5cb;
    }
    .msg.error{
        background:#fff0f0;
        color:#c0392b;
        border:1px solid #f1b4ae;
    }
    .stats{
        display:grid;
        grid-template-columns:repeat(4,1fr);
        gap:16px;
        margin-bottom:20px;
    }
    .card{
        background:#fff;
        border-radius:20px;
        padding:20px;
        box-shadow:0 8px 24px rgba(0,0,0,.06);
    }
    .card h3{
        margin:0 0 8px;
        font-size:16px;
        color:#666;
        font-weight:600;
    }
    .card .num{
        font-size:30px;
        font-weight:800;
    }
    .layout{
        display:grid;
        grid-template-columns: 1.1fr 2fr;
        gap:20px;
    }
    .section-title{
        margin:0 0 16px;
        font-size:20px;
        font-weight:800;
    }
    .form-group{
        margin-bottom:14px;
    }
    .form-group label{
        display:block;
        margin-bottom:6px;
        font-weight:700;
        color:#333;
    }
    .form-control, textarea, select{
        width:100%;
        border:1px solid #d9dfe7;
        border-radius:12px;
        padding:12px 14px;
        font-size:15px;
        outline:none;
        background:#fff;
    }
    textarea{
        min-height:110px;
        resize:vertical;
    }
    .row{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:12px;
    }
    .btn{
        border:none;
        border-radius:12px;
        padding:11px 16px;
        font-weight:700;
        cursor:pointer;
        text-decoration:none;
        display:inline-block;
    }
    .btn-primary{ background:#638411; color:#fff; }
    .btn-warning{ background:#f39c12; color:#fff; }
    .btn-danger{ background:#d9534f; color:#fff; }
    .btn-secondary{ background:#6c757d; color:#fff; }
    .btn-info{ background:#1d3557; color:#fff; }
    .btn-sm{
        padding:8px 12px;
        border-radius:10px;
        font-size:13px;
    }
    .filter-box{
        margin-bottom:18px;
    }
    .table-wrap{
        overflow:auto;
    }
    table{
        width:100%;
        border-collapse:collapse;
        min-width:1100px;
    }
    th, td{
        padding:12px 10px;
        border-bottom:1px solid #eceff3;
        text-align:left;
        vertical-align:top;
        font-size:14px;
    }
    th{
        background:#f8fafc;
        font-size:14px;
        font-weight:800;
        color:#333;
        position:sticky;
        top:0;
        z-index:1;
    }
    .badge{
        display:inline-block;
        padding:6px 10px;
        border-radius:999px;
        font-size:12px;
        font-weight:700;
    }
    .pending{
        background:#fff3cd;
        color:#856404;
    }
    .approved{
        background:#d4edda;
        color:#155724;
    }
    .cancelled{
        background:#f8d7da;
        color:#721c24;
    }
    .muted{
        color:#666;
        font-size:13px;
    }
    .action-group{
        display:flex;
        flex-wrap:wrap;
        gap:6px;
    }
    .inline-form{
        display:inline;
    }
    @media (max-width: 1100px){
        .layout{
            grid-template-columns:1fr;
        }
        .stats{
            grid-template-columns:repeat(2,1fr);
        }
    }
    @media (max-width: 640px){
        .stats{
            grid-template-columns:1fr;
        }
        .row{
            grid-template-columns:1fr;
        }
        .container{
            padding:14px;
        }
        .title{
            font-size:22px;
        }
    }
</style>
</head>
<body>
<div class="container">

    <div class="topbar">
        <div class="title">จัดการข้อมูลการจองห้องพัก</div>
        <a href="index.php" class="back-btn">← กลับหน้าเว็บไซต์</a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="msg <?php echo $message_type === 'error' ? 'error' : 'success'; ?>">
            <?php echo h($message); ?>
        </div>
    <?php endif; ?>

    <div class="stats">
        <div class="card">
            <h3>รายการทั้งหมด</h3>
            <div class="num"><?php echo $stat_total; ?></div>
        </div>
        <div class="card">
            <h3>รอยืนยัน</h3>
            <div class="num"><?php echo $stat_pending; ?></div>
        </div>
        <div class="card">
            <h3>อนุมัติแล้ว</h3>
            <div class="num"><?php echo $stat_approved; ?></div>
        </div>
        <div class="card">
            <h3>ยกเลิก</h3>
            <div class="num"><?php echo $stat_cancelled; ?></div>
        </div>
    </div>

    <div class="layout">
        <div class="card">
            <h2 class="section-title"><?php echo $edit_data ? 'แก้ไขข้อมูลการจอง' : 'เลือกข้อมูลจากตารางเพื่อแก้ไข'; ?></h2>

            <?php if ($edit_data): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="update_booking">
                    <input type="hidden" name="id" value="<?php echo (int)$edit_data['id']; ?>">

                    <div class="form-group">
                        <label>ชื่อผู้จอง</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo h($edit_data['full_name']); ?>" required>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <label>เบอร์โทร</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo h($edit_data['phone']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>อีเมล</label>
                            <input type="email" name="email" class="form-control" value="<?php echo h($edit_data['email']); ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <label>ประเภท / ชื่อห้อง</label>
                            <input type="text" name="room_type" class="form-control" value="<?php echo h($edit_data['room_type']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>จำนวนผู้เข้าพัก</label>
                            <input type="number" name="guests" class="form-control" min="1" value="<?php echo (int)$edit_data['guests']; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="form-group">
                            <label>วันเช็คอิน</label>
                            <input type="date" name="checkin_date" class="form-control" value="<?php echo h($edit_data['checkin_date']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>วันเช็คเอาท์</label>
                            <input type="date" name="checkout_date" class="form-control" value="<?php echo h($edit_data['checkout_date']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>สถานะการจอง</label>
                        <select name="booking_status" class="form-control">
                            <option value="pending" <?php echo $edit_data['booking_status'] === 'pending' ? 'selected' : ''; ?>>pending</option>
                            <option value="approved" <?php echo $edit_data['booking_status'] === 'approved' ? 'selected' : ''; ?>>approved</option>
                            <option value="cancelled" <?php echo $edit_data['booking_status'] === 'cancelled' ? 'selected' : ''; ?>>cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>หมายเหตุ</label>
                        <textarea name="note"><?php echo h($edit_data['note']); ?></textarea>
                    </div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
                        <a href="admin_bookings.php?archived=<?php echo $archive_filter; ?>" class="btn btn-secondary">ยกเลิก</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="muted">ยังไม่ได้เลือกรายการที่ต้องการแก้ไข ให้กดปุ่ม “แก้ไข” จากตารางด้านขวา</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="section-title">รายการจองทั้งหมด</h2>

            <form method="GET" class="filter-box">
                <div class="row">
                    <div class="form-group">
                        <label>ค้นหา</label>
                        <input type="text" name="search" class="form-control" placeholder="ชื่อ, เบอร์โทร, อีเมล, ห้อง, หมายเหตุ" value="<?php echo h($search); ?>">
                    </div>
                    <div class="form-group">
                        <label>สถานะ</label>
                        <select name="status" class="form-control">
                            <option value="">ทั้งหมด</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>approved</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="form-group">
                        <label>ประเภทข้อมูล</label>
                        <select name="archived" class="form-control">
                            <option value="0" <?php echo $archive_filter === 0 ? 'selected' : ''; ?>>ข้อมูลปัจจุบัน</option>
                            <option value="1" <?php echo $archive_filter === 1 ? 'selected' : ''; ?>>ข้อมูลที่จัดเก็บแล้ว</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:end; gap:10px;">
                        <button type="submit" class="btn btn-primary">ค้นหา</button>
                        <a href="admin_bookings.php" class="btn btn-secondary">รีเซ็ต</a>
                    </div>
                </div>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ผู้จอง</th>
                            <th>ติดต่อ</th>
                            <th>ห้อง</th>
                            <th>ผู้เข้าพัก</th>
                            <th>วันเข้าพัก</th>
                            <th>หมายเหตุ</th>
                            <th>สถานะ</th>
                            <th>สร้างเมื่อ</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td>
                                    <strong><?php echo h($row['full_name']); ?></strong><br>
                                    <span class="muted">room_id: <?php echo (int)$row['room_id']; ?></span>
                                </td>
                                <td>
                                    <?php echo h($row['phone']); ?><br>
                                    <span class="muted"><?php echo h($row['email']); ?></span>
                                </td>
                                <td><?php echo h($row['room_type']); ?></td>
                                <td><?php echo (int)$row['guests']; ?> คน</td>
                                <td>
                                    เข้า: <?php echo h($row['checkin_date']); ?><br>
                                    ออก: <?php echo h($row['checkout_date']); ?>
                                </td>
                                <td><?php echo nl2br(h($row['note'])); ?></td>
                                <td>
                                    <span class="badge <?php echo h($row['booking_status']); ?>">
                                        <?php echo h($row['booking_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo h($row['created_at']); ?></td>
                                <td>
                                    <div class="action-group">
                                        <a class="btn btn-info btn-sm" href="admin_bookings.php?edit=<?php echo (int)$row['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&archived=<?php echo $archive_filter; ?>">แก้ไข</a>

                                        <form method="POST" class="inline-form" onsubmit="return confirm('ยืนยันการลบข้อมูลนี้?');">
                                            <input type="hidden" name="action" value="delete_booking">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">ลบ</button>
                                        </form>

                                        <?php if ((int)$row['archived'] === 0): ?>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('จัดเก็บรายการนี้ใช่หรือไม่?');">
                                                <input type="hidden" name="action" value="archive_booking">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="btn btn-warning btn-sm">จัดเก็บ</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" class="inline-form" onsubmit="return confirm('กู้คืนรายการนี้ใช่หรือไม่?');">
                                                <input type="hidden" name="action" value="unarchive_booking">
                                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm">กู้คืน</button>
                                            </form>
                                        <?php endif; ?>

                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                            <select name="booking_status" onchange="this.form.submit()" class="form-control" style="padding:7px 10px; min-width:120px;">
                                                <option value="pending" <?php echo $row['booking_status'] === 'pending' ? 'selected' : ''; ?>>pending</option>
                                                <option value="approved" <?php echo $row['booking_status'] === 'approved' ? 'selected' : ''; ?>>approved</option>
                                                <option value="cancelled" <?php echo $row['booking_status'] === 'cancelled' ? 'selected' : ''; ?>>cancelled</option>
                                            </select>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align:center; padding:30px;">ไม่พบข้อมูลการจอง</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>