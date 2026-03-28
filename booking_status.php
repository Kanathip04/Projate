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
    if (isset($row['archived']) && (int)$row['archived'] === 1) {
        return 'unavailable';
    }

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
        case 'approved':
            return 'อนุมัติแล้ว';
        case 'pending':
            return 'รออนุมัติ';
        case 'rejected':
            return 'ไม่อนุมัติ';
        case 'cancelled':
            return 'ยกเลิกแล้ว';
        case 'completed':
            return 'เสร็จสิ้น';
        case 'unavailable':
            return 'ไม่พร้อมใช้งาน';
        default:
            return 'ไม่ทราบสถานะ';
    }
}

function statusClass($status) {
    switch ($status) {
        case 'approved':
            return 'approved';
        case 'pending':
            return 'pending';
        case 'rejected':
            return 'rejected';
        case 'cancelled':
            return 'cancelled';
        case 'completed':
            return 'completed';
        case 'unavailable':
            return 'unavailable';
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
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & Base ─────────────────────────────────────────── */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --ink:        #1a1a2e;
            --gold:       #c9a96e;
            --gold-light: #e8d5b0;
            --bg:         #f5f1eb;
            --card:       #fff;
            --muted:      #7a7a8c;
            --border:     #e8e4de;
        }

        body {
            background: var(--bg);
            color: var(--ink);
            font-family: 'Sarabun', 'Segoe UI', Tahoma, sans-serif;
            font-size: 16px;
            line-height: 1.6;
        }

        /* ── Layout helpers ───────────────────────────────────────── */
        .container {
            width: min(1180px, 92%);
            margin: 0 auto;
        }

        /* ── Hero ─────────────────────────────────────────────────── */
        .hero {
            background: linear-gradient(135deg, #0f0f1e 0%, #1a1a2e 55%, #252545 100%);
            color: #fff;
            padding: 44px 20px 96px;
            position: relative;
            overflow: hidden;
        }

        /* subtle decorative circle */
        .hero::after {
            content: '';
            position: absolute;
            right: -120px;
            top: -120px;
            width: 480px;
            height: 480px;
            border-radius: 50%;
            background: rgba(201, 169, 110, 0.07);
            pointer-events: none;
        }

        /* ── Top navigation menu ──────────────────────────────────── */
        .top-menu {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .top-menu a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 999px;
            text-decoration: none;
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            border: 1px solid rgba(201, 169, 110, 0.45);
            background: rgba(201, 169, 110, 0.12);
            transition: background .25s ease, transform .2s ease;
        }

        .top-menu a:hover {
            background: rgba(201, 169, 110, 0.25);
            transform: translateY(-2px);
        }

        /* ── Hero text ────────────────────────────────────────────── */
        .hero h1 {
            font-size: 46px;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .hero h1 span {
            color: var(--gold);
        }

        .hero p {
            font-size: 17px;
            max-width: 760px;
            line-height: 1.75;
            color: rgba(255, 255, 255, 0.82);
        }

        /* ── Content section ──────────────────────────────────────── */
        .content {
            margin-top: -42px;
            padding-bottom: 60px;
        }

        .list {
            display: grid;
            gap: 24px;
        }

        /* ── Card ─────────────────────────────────────────────────── */
        .card {
            background: var(--card);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(26, 26, 46, 0.10), 0 1px 4px rgba(26, 26, 46, 0.06);
            border: 1px solid var(--border);
            transition: box-shadow .25s ease, transform .2s ease;
        }

        .card:hover {
            box-shadow: 0 16px 44px rgba(26, 26, 46, 0.14);
            transform: translateY(-2px);
        }

        /* gold accent bar at the top of each card */
        .card::before {
            content: '';
            display: block;
            height: 4px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
        }

        .card-body {
            padding: 26px 28px 28px;
        }

        /* ── Card top row ─────────────────────────────────────────── */
        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .room-name {
            font-size: 28px;
            font-weight: 800;
            color: var(--ink);
            letter-spacing: -0.3px;
        }

        /* ── Status badges ────────────────────────────────────────── */
        .badge {
            padding: 7px 16px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }

        /* semantic badge colors kept as requested */
        .pending     { background: #fef3c7; color: #92400e; }
        .approved    { background: #dcfce7; color: #166534; }
        .rejected    { background: #fee2e2; color: #991b1b; }
        .cancelled   { background: #f3f4f6; color: #374151; }
        .completed   { background: #dbeafe; color: #1d4ed8; }
        .unavailable { background: #e5e7eb; color: #374151; }
        .unknown     { background: #e5e7eb; color: var(--ink); }

        /* ── Info grid ────────────────────────────────────────────── */
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .item {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px;
        }

        .item .label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .item .value {
            font-size: 16px;
            color: var(--ink);
            font-weight: 700;
        }

        /* ── Note box ─────────────────────────────────────────────── */
        .note-box {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px;
        }

        .note-box .label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 6px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .note-box .value {
            line-height: 1.75;
            color: var(--ink);
            font-size: 15px;
        }

        /* ── Empty state ──────────────────────────────────────────── */
        .empty {
            background: var(--card);
            border-radius: 20px;
            padding: 60px 24px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(26, 26, 46, 0.10);
            border: 1px solid var(--border);
        }

        .empty h3 {
            font-size: 26px;
            font-weight: 800;
            color: var(--ink);
            margin-bottom: 10px;
        }

        .empty p {
            color: var(--muted);
            margin-bottom: 24px;
            font-size: 16px;
        }

        .empty a {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 999px;
            text-decoration: none;
            background: var(--ink);
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            transition: background .25s ease, transform .2s ease;
        }

        .empty a:hover {
            background: #2a2a4a;
            transform: translateY(-2px);
        }

        /* ── Responsive ───────────────────────────────────────────── */
        @media (max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 32px;
            }

            .room-name {
                font-size: 22px;
            }

            .card-body {
                padding: 20px;
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

        <h1>ติดตามสถานะ<span>การจอง</span></h1>
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
                                    <span class="label">สถานะการอนุมัติ</span>
                                    <span class="value"><?php echo statusText($currentStatus); ?></span>
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
