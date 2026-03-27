<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
include 'config.php';

if (!isset($conn)) {
    die('ไม่พบตัวแปร $conn ใน config.php');
}

/*
|--------------------------------------------------------------------------
| หาค่า email ของผู้ใช้จาก session
|--------------------------------------------------------------------------
| ปรับได้ตามระบบจริงของคุณ
*/
$user_email = '';

if (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
    $user_email = trim($_SESSION['email']);
} elseif (isset($_SESSION['user_email']) && !empty($_SESSION['user_email'])) {
    $user_email = trim($_SESSION['user_email']);
} elseif (isset($_SESSION['user']['email']) && !empty($_SESSION['user']['email'])) {
    $user_email = trim($_SESSION['user']['email']);
}

if ($user_email === '') {
    die('ไม่พบ session email ของผู้ใช้ กรุณาตรวจสอบไฟล์ login ว่าเก็บ email ไว้ใน session หรือไม่');
}

$sql = "SELECT 
            id,
            full_name,
            phone,
            email,
            room_type,
            guests,
            checkin_date,
            checkout_date,
            note,
            status,
            booking_status,
            archived,
            created_at,
            room_id
        FROM room_bookings
        WHERE email = ?
        ORDER BY id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('SQL prepare failed: ' . $conn->error);
}

$stmt->bind_param("s", $user_email);

if (!$stmt->execute()) {
    die('SQL execute failed: ' . $stmt->error);
}

$result = $stmt->get_result();

function getBookingStatus($row) {
    if (!empty($row['booking_status'])) {
        return $row['booking_status'];
    }
    if (!empty($row['status'])) {
        return $row['status'];
    }
    return 'pending';
}

function statusText($status) {
    switch ($status) {
        case 'pending':
            return 'รออนุมัติ';
        case 'approved':
            return 'อนุมัติแล้ว';
        case 'rejected':
            return 'ไม่อนุมัติ';
        case 'cancelled':
            return 'ยกเลิกแล้ว';
        case 'completed':
            return 'เสร็จสิ้น';
        default:
            return 'ไม่ทราบสถานะ';
    }
}

function statusClass($status) {
    switch ($status) {
        case 'pending':
            return 'pending';
        case 'approved':
            return 'approved';
        case 'rejected':
            return 'rejected';
        case 'cancelled':
            return 'cancelled';
        case 'completed':
            return 'completed';
        default:
            return 'unknown';
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
        *{
            box-sizing:border-box;
            margin:0;
            padding:0;
            font-family:'Segoe UI', Tahoma, sans-serif;
        }

        body{
            background:#f4f6f9;
            color:#1f2937;
        }

        .hero{
            background:linear-gradient(135deg, #6b7f22, #879f31);
            color:#fff;
            padding:40px 20px 90px;
        }

        .container{
            width:min(1180px, 92%);
            margin:0 auto;
        }

        .top-menu{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:24px;
        }

        .top-menu a{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:12px 20px;
            border-radius:999px;
            text-decoration:none;
            color:#fff;
            font-weight:700;
            border:1px solid rgba(255,255,255,.35);
            background:rgba(255,255,255,.12);
            transition:.25s ease;
        }

        .top-menu a:hover{
            background:rgba(255,255,255,.2);
            transform:translateY(-2px);
        }

        .hero h1{
            font-size:46px;
            margin-bottom:10px;
            font-weight:800;
        }

        .hero p{
            font-size:18px;
            max-width:760px;
            line-height:1.7;
        }

        .content{
            margin-top:-38px;
            padding-bottom:50px;
        }

        .list{
            display:grid;
            gap:22px;
        }

        .card{
            background:#fff;
            border-radius:24px;
            overflow:hidden;
            box-shadow:0 12px 30px rgba(0,0,0,.08);
            border:1px solid rgba(0,0,0,.05);
        }

        .card-body{
            padding:24px;
        }

        .card-top{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:20px;
        }

        .room-name{
            font-size:30px;
            font-weight:800;
            color:#111827;
        }

        .badge{
            padding:8px 14px;
            border-radius:999px;
            font-size:14px;
            font-weight:700;
        }

        .pending{ background:#fef3c7; color:#92400e; }
        .approved{ background:#dcfce7; color:#166534; }
        .rejected{ background:#fee2e2; color:#991b1b; }
        .cancelled{ background:#f3f4f6; color:#374151; }
        .completed{ background:#dbeafe; color:#1d4ed8; }
        .unknown{ background:#e5e7eb; color:#111827; }

        .grid{
            display:grid;
            grid-template-columns:repeat(2, minmax(220px, 1fr));
            gap:14px;
            margin-bottom:16px;
        }

        .item{
            background:#f9fafb;
            border:1px solid #e5e7eb;
            border-radius:16px;
            padding:14px 16px;
        }

        .item .label{
            display:block;
            font-size:13px;
            color:#6b7280;
            margin-bottom:6px;
            font-weight:600;
        }

        .item .value{
            font-size:16px;
            color:#111827;
            font-weight:700;
        }

        .note-box{
            background:#f8fafc;
            border:1px solid #e5e7eb;
            border-radius:16px;
            padding:14px 16px;
        }

        .note-box .label{
            display:block;
            font-size:13px;
            color:#6b7280;
            margin-bottom:6px;
            font-weight:700;
        }

        .note-box .value{
            line-height:1.7;
            color:#111827;
        }

        .empty{
            background:#fff;
            border-radius:24px;
            padding:50px 24px;
            text-align:center;
            box-shadow:0 12px 30px rgba(0,0,0,.08);
        }

        .empty h3{
            font-size:28px;
            margin-bottom:10px;
        }

        .empty p{
            color:#6b7280;
            margin-bottom:20px;
        }

        .empty a{
            display:inline-block;
            padding:12px 22px;
            border-radius:999px;
            text-decoration:none;
            background:#6b7f22;
            color:#fff;
            font-weight:700;
        }

        @media (max-width: 900px){
            .grid{
                grid-template-columns:1fr;
            }

            .hero h1{
                font-size:34px;
            }

            .room-name{
                font-size:24px;
            }
        }
    </style>
</head>
<body>

<section class="hero">
    <div class="container">
        <div class="top-menu">
            <a href="booking_room.php">← กลับไปหน้าจองห้อง</a>
            <a href="index.php">หน้าหลัก</a>
        </div>

        <h1>ติดตามสถานะการจอง</h1>
        <p>ตรวจสอบรายการจองของคุณ พร้อมดูสถานะการอนุมัติ วันที่เข้าพัก วันที่ออก จำนวนผู้เข้าพัก และรายละเอียดการจอง</p>
    </div>
</section>

<section class="content">
    <div class="container">
        <div class="list">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <?php $currentStatus = getBookingStatus($row); ?>

                    <div class="card">
                        <div class="card-body">
                            <div class="card-top">
                                <div class="room-name">
                                    <?php echo htmlspecialchars($row['room_type'] ?? 'ไม่ระบุประเภทห้อง'); ?>
                                </div>

                                <div class="badge <?php echo statusClass($currentStatus); ?>">
                                    <?php echo statusText($currentStatus); ?>
                                </div>
                            </div>

                            <div class="grid">
                                <div class="item">
                                    <span class="label">เลขที่การจอง</span>
                                    <span class="value">#<?php echo $row['id']; ?></span>
                                </div>

                                <div class="item">
                                    <span class="label">ชื่อผู้จอง</span>
                                    <span class="value"><?php echo htmlspecialchars($row['full_name'] ?? '-'); ?></span>
                                </div>

                                <div class="item">
                                    <span class="label">อีเมล</span>
                                    <span class="value"><?php echo htmlspecialchars($row['email'] ?? '-'); ?></span>
                                </div>

                                <div class="item">
                                    <span class="label">เบอร์โทร</span>
                                    <span class="value"><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></span>
                                </div>

                                <div class="item">
                                    <span class="label">จำนวนผู้เข้าพัก</span>
                                    <span class="value"><?php echo htmlspecialchars($row['guests'] ?? '-'); ?> คน</span>
                                </div>

                                <div class="item">
                                    <span class="label">วันที่ทำรายการ</span>
                                    <span class="value">
                                        <?php echo !empty($row['created_at']) ? date('d/m/Y H:i', strtotime($row['created_at'])) : '-'; ?>
                                    </span>
                                </div>

                                <div class="item">
                                    <span class="label">วันเข้าพัก</span>
                                    <span class="value">
                                        <?php echo !empty($row['checkin_date']) ? date('d/m/Y', strtotime($row['checkin_date'])) : '-'; ?>
                                    </span>
                                </div>

                                <div class="item">
                                    <span class="label">วันออก</span>
                                    <span class="value">
                                        <?php echo !empty($row['checkout_date']) ? date('d/m/Y', strtotime($row['checkout_date'])) : '-'; ?>
                                    </span>
                                </div>

                                <div class="item">
                                    <span class="label">รหัสห้อง</span>
                                    <span class="value"><?php echo htmlspecialchars($row['room_id'] ?? '-'); ?></span>
                                </div>

                                <div class="item">
                                    <span class="label">สถานะเก็บถาวร</span>
                                    <span class="value"><?php echo ((int)$row['archived'] === 1) ? 'เก็บถาวรแล้ว' : 'อนุมัติแล้ว'; ?></span>
                                </div>
                            </div>

                            <div class="note-box">
                                <span class="label">หมายเหตุ</span>
                                <div class="value">
                                    <?php echo !empty($row['note']) ? nl2br(htmlspecialchars($row['note'])) : 'ไม่มีหมายเหตุเพิ่มเติม'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty">
                    <h3>ยังไม่มีรายการจอง</h3>
                    <p>เมื่อคุณจองห้องแล้ว รายการทั้งหมดจะแสดงในหน้านี้</p>
                    <a href="booking_room.php">ไปหน้าจองห้อง</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

</body>
</html>