<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
include 'config.php';

if (!isset($conn)) {
    die('ไม่พบตัวแปร $conn จากไฟล์ config.php');
}

if (!isset($_SESSION['user_id'])) {
    die('ยังไม่มี session user_id กรุณาเข้าสู่ระบบก่อน');
}

$user_id = (int)$_SESSION['user_id'];

$sql = "SELECT 
            rb.id,
            rb.user_id,
            rb.room_id,
            rb.booking_date,
            rb.check_in,
            rb.check_out,
            rb.total_price,
            rb.note,
            rb.status,
            rb.created_at,
            r.room_name,
            r.room_image,
            r.price_per_night
        FROM room_bookings rb
        LEFT JOIN rooms r ON rb.room_id = r.id
        WHERE rb.user_id = ?
        ORDER BY rb.id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('SQL prepare failed: ' . $conn->error);
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die('SQL execute failed: ' . $stmt->error);
}

$result = $stmt->get_result();

function statusText($status) {
    switch ($status) {
        case 'pending': return 'รออนุมัติ';
        case 'approved': return 'อนุมัติแล้ว';
        case 'rejected': return 'ไม่อนุมัติ';
        case 'cancelled': return 'ยกเลิกแล้ว';
        case 'completed': return 'เสร็จสิ้น';
        default: return 'ไม่ทราบสถานะ';
    }
}

function statusClass($status) {
    switch ($status) {
        case 'pending': return 'pending';
        case 'approved': return 'approved';
        case 'rejected': return 'rejected';
        case 'cancelled': return 'cancelled';
        case 'completed': return 'completed';
        default: return 'unknown';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามสถานะการจอง</title>
    <style>
        body{
            font-family:Arial, sans-serif;
            background:#f4f6f9;
            margin:0;
            padding:30px;
        }
        .box{
            max-width:1100px;
            margin:0 auto;
        }
        .top{
            margin-bottom:20px;
        }
        .top a{
            display:inline-block;
            text-decoration:none;
            padding:10px 16px;
            background:#6b7f22;
            color:#fff;
            border-radius:8px;
            margin-right:10px;
        }
        .card{
            background:#fff;
            border-radius:16px;
            padding:20px;
            margin-bottom:16px;
            box-shadow:0 4px 14px rgba(0,0,0,.08);
        }
        .badge{
            display:inline-block;
            padding:6px 12px;
            border-radius:999px;
            font-weight:bold;
            margin-bottom:10px;
        }
        .pending{ background:#fef3c7; color:#92400e; }
        .approved{ background:#dcfce7; color:#166534; }
        .rejected{ background:#fee2e2; color:#991b1b; }
        .cancelled{ background:#f3f4f6; color:#374151; }
        .completed{ background:#dbeafe; color:#1d4ed8; }
        .unknown{ background:#e5e7eb; color:#111827; }
        .title{
            font-size:24px;
            font-weight:bold;
            margin-bottom:10px;
        }
        .row{
            margin-bottom:8px;
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="top">
            <a href="booking_room.php">กลับไปหน้าจองห้อง</a>
            <a href="index.php">หน้าหลัก</a>
        </div>

        <h1>ติดตามสถานะการจอง</h1>

        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="card">
                    <div class="badge <?php echo statusClass($row['status']); ?>">
                        <?php echo statusText($row['status']); ?>
                    </div>

                    <div class="title">
                        <?php echo htmlspecialchars($row['room_name'] ?? 'ไม่ระบุชื่อห้อง'); ?>
                    </div>

                    <div class="row"><strong>เลขที่การจอง:</strong> #<?php echo $row['id']; ?></div>
                    <div class="row"><strong>วันที่จอง:</strong> <?php echo htmlspecialchars($row['booking_date'] ?? '-'); ?></div>
                    <div class="row"><strong>วันเข้าพัก:</strong> <?php echo htmlspecialchars($row['check_in'] ?? '-'); ?></div>
                    <div class="row"><strong>วันออก:</strong> <?php echo htmlspecialchars($row['check_out'] ?? '-'); ?></div>
                    <div class="row"><strong>ราคารวม:</strong> <?php echo number_format((float)$row['total_price'], 2); ?> บาท</div>
                    <div class="row"><strong>หมายเหตุ:</strong> <?php echo !empty($row['note']) ? htmlspecialchars($row['note']) : '-'; ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="card">
                ยังไม่มีรายการจอง
            </div>
        <?php endif; ?>
    </div>
</body>
</html>