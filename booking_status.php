<?php
session_start();
include 'config.php';

/*
    สมมติว่าเวลาผู้ใช้ล็อกอินแล้ว
    มีค่า $_SESSION['user_id']
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$sql = "SELECT 
            rb.id,
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
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

function bookingStatusLabel($status){
    switch($status){
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
            return 'ไม่ระบุ';
    }
}

function bookingStatusClass($status){
    switch($status){
        case 'pending':
            return 'status pending';
        case 'approved':
            return 'status approved';
        case 'rejected':
            return 'status rejected';
        case 'cancelled':
            return 'status cancelled';
        case 'completed':
            return 'status completed';
        default:
            return 'status';
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
            background:linear-gradient(135deg, #6b7f22, #849b34);
            color:#fff;
            padding:40px 20px 90px;
            position:relative;
            overflow:hidden;
        }

        .hero::after{
            content:"";
            position:absolute;
            left:0;
            right:0;
            bottom:-40px;
            height:100px;
            background:linear-gradient(to bottom, rgba(255,255,255,0.08), #f4f6f9);
            filter:blur(8px);
        }

        .container{
            width:min(1200px, 92%);
            margin:0 auto;
        }

        .top-actions{
            display:flex;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:24px;
        }

        .top-btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:12px 20px;
            border-radius:999px;
            text-decoration:none;
            font-weight:700;
            font-size:15px;
            color:#fff;
            background:rgba(255,255,255,0.15);
            border:1px solid rgba(255,255,255,0.35);
            backdrop-filter:blur(8px);
            -webkit-backdrop-filter:blur(8px);
            transition:.25s ease;
        }

        .top-btn:hover{
            transform:translateY(-2px);
            background:rgba(255,255,255,0.24);
        }

        .hero h1{
            font-size:48px;
            line-height:1.2;
            margin-bottom:12px;
            font-weight:800;
        }

        .hero p{
            max-width:760px;
            font-size:18px;
            line-height:1.8;
            opacity:.95;
        }

        .content{
            margin-top:-40px;
            position:relative;
            z-index:3;
            padding-bottom:50px;
        }

        .card-wrap{
            display:grid;
            gap:22px;
        }

        .booking-card{
            display:grid;
            grid-template-columns:320px 1fr;
            background:#fff;
            border-radius:24px;
            overflow:hidden;
            box-shadow:0 12px 35px rgba(0,0,0,.08);
            border:1px solid rgba(0,0,0,0.05);
        }

        .booking-image{
            width:100%;
            height:100%;
            min-height:260px;
            object-fit:cover;
            background:#e5e7eb;
        }

        .booking-body{
            padding:24px;
        }

        .booking-top{
            display:flex;
            justify-content:space-between;
            gap:10px;
            align-items:flex-start;
            flex-wrap:wrap;
            margin-bottom:18px;
        }

        .booking-title{
            font-size:30px;
            font-weight:800;
            color:#111827;
        }

        .status{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:8px 14px;
            border-radius:999px;
            font-size:14px;
            font-weight:700;
        }

        .status.pending{
            background:#fff7d6;
            color:#a16207;
        }

        .status.approved{
            background:#dcfce7;
            color:#166534;
        }

        .status.rejected{
            background:#fee2e2;
            color:#991b1b;
        }

        .status.cancelled{
            background:#f3f4f6;
            color:#374151;
        }

        .status.completed{
            background:#dbeafe;
            color:#1d4ed8;
        }

        .info-grid{
            display:grid;
            grid-template-columns:repeat(2, minmax(220px, 1fr));
            gap:14px;
            margin-bottom:16px;
        }

        .info-item{
            background:#f9fafb;
            border:1px solid #eef2f7;
            border-radius:16px;
            padding:14px 16px;
        }

        .info-item .label{
            display:block;
            font-size:13px;
            color:#6b7280;
            margin-bottom:6px;
            font-weight:600;
        }

        .info-item .value{
            font-size:16px;
            color:#111827;
            font-weight:700;
        }

        .note-box{
            margin-top:10px;
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
            font-size:15px;
            color:#111827;
            line-height:1.7;
        }

        .empty-box{
            background:#fff;
            border-radius:24px;
            padding:50px 25px;
            text-align:center;
            box-shadow:0 12px 35px rgba(0,0,0,.07);
            border:1px solid rgba(0,0,0,.05);
        }

        .empty-box h3{
            font-size:28px;
            margin-bottom:10px;
            color:#111827;
        }

        .empty-box p{
            color:#6b7280;
            font-size:16px;
            margin-bottom:20px;
        }

        .go-booking{
            display:inline-block;
            text-decoration:none;
            background:#6b7f22;
            color:#fff;
            font-weight:700;
            padding:12px 22px;
            border-radius:999px;
            transition:.25s ease;
        }

        .go-booking:hover{
            background:#586a1a;
        }

        @media (max-width: 900px){
            .booking-card{
                grid-template-columns:1fr;
            }

            .booking-image{
                min-height:220px;
            }

            .hero h1{
                font-size:36px;
            }

            .info-grid{
                grid-template-columns:1fr;
            }
        }

        @media (max-width: 600px){
            .hero{
                padding:30px 15px 80px;
            }

            .hero h1{
                font-size:30px;
            }

            .hero p{
                font-size:16px;
            }

            .booking-title{
                font-size:24px;
            }

            .booking-body{
                padding:18px;
            }
        }
    </style>
</head>
<body>

<section class="hero">
    <div class="container">
        <div class="top-actions">
            <a href="booking_room.php" class="top-btn">← กลับไปหน้าจองห้อง</a>
            <a href="index.php" class="top-btn">หน้าหลัก</a>
        </div>

        <h1>ติดตามสถานะการจองห้องพัก</h1>
        <p>
            ตรวจสอบรายการจองทั้งหมดของคุณ พร้อมดูสถานะการอนุมัติ วันที่เข้าพัก วันที่ออก
            และรายละเอียดการจองได้ในหน้าเดียว
        </p>
    </div>
</section>

<section class="content">
    <div class="container">
        <div class="card-wrap">
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="booking-card">
                        <?php
                            $img = !empty($row['room_image']) ? $row['room_image'] : 'uploads/default-room.jpg';
                        ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="room image" class="booking-image">

                        <div class="booking-body">
                            <div class="booking-top">
                                <div class="booking-title">
                                    <?php echo htmlspecialchars($row['room_name'] ?? 'ไม่ระบุชื่อห้อง'); ?>
                                </div>

                                <div class="<?php echo bookingStatusClass($row['status']); ?>">
                                    <?php echo bookingStatusLabel($row['status']); ?>
                                </div>
                            </div>

                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="label">เลขที่การจอง</span>
                                    <span class="value">#<?php echo $row['id']; ?></span>
                                </div>

                                <div class="info-item">
                                    <span class="label">วันที่ทำรายการ</span>
                                    <span class="value"><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></span>
                                </div>

                                <div class="info-item">
                                    <span class="label">วันที่เข้าพัก</span>
                                    <span class="value"><?php echo date('d/m/Y', strtotime($row['check_in'])); ?></span>
                                </div>

                                <div class="info-item">
                                    <span class="label">วันที่ออก</span>
                                    <span class="value"><?php echo date('d/m/Y', strtotime($row['check_out'])); ?></span>
                                </div>

                                <div class="info-item">
                                    <span class="label">ราคาต่อคืน</span>
                                    <span class="value">
                                        <?php echo number_format((float)$row['price_per_night'], 2); ?> บาท
                                    </span>
                                </div>

                                <div class="info-item">
                                    <span class="label">ราคารวม</span>
                                    <span class="value">
                                        <?php echo number_format((float)$row['total_price'], 2); ?> บาท
                                    </span>
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
                <div class="empty-box">
                    <h3>ยังไม่มีรายการจอง</h3>
                    <p>เมื่อคุณทำรายการจองห้องแล้ว รายการจองและสถานะจะปรากฏในหน้านี้</p>
                    <a href="booking_room.php" class="go-booking">ไปจองห้องพัก</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

</body>
</html>