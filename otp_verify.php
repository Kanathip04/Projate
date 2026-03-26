<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['pending_user_id']) || !isset($_SESSION['pending_user_email'])) {
    header("Location: register.php");
    exit;
}

$userId = (int)$_SESSION['pending_user_id'];
$userEmail = $_SESSION['pending_user_email'];

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $otp = trim($_POST['otp'] ?? '');

    if ($otp === "") {
        $error = "กรุณากรอกรหัส OTP";
    } else {
        $stmt = $conn->prepare("
            SELECT id, otp_code, expires_at, is_used
            FROM user_otps
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "ไม่พบรหัส OTP กรุณาสมัครใหม่";
        } else {
            $otpRow = $result->fetch_assoc();

            if ((int)$otpRow['is_used'] === 1) {
                $error = "OTP นี้ถูกใช้งานแล้ว กรุณาขอรหัสใหม่";
            } elseif (strtotime($otpRow['expires_at']) < time()) {
                $error = "OTP หมดอายุแล้ว กรุณาขอรหัสใหม่";
            } elseif ($otp !== $otpRow['otp_code']) {
                $error = "รหัส OTP ไม่ถูกต้อง";
            } else {
                $otpId = (int)$otpRow['id'];

                $stmtUse = $conn->prepare("UPDATE user_otps SET is_used = 1 WHERE id = ?");
                $stmtUse->bind_param("i", $otpId);
                $stmtUse->execute();
                $stmtUse->close();

                $stmtVerify = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                $stmtVerify->bind_param("i", $userId);
                $stmtVerify->execute();
                $stmtVerify->close();

                unset($_SESSION['pending_user_id']);
                unset($_SESSION['pending_user_email']);
                unset($_SESSION['debug_otp']);

                $success = "ยืนยัน OTP สำเร็จ กำลังไปหน้าเข้าสู่ระบบ...";
                header("refresh:2;url=login.php");
            }
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยัน OTP</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:'Segoe UI', Tahoma, sans-serif;
            background:linear-gradient(135deg,#eef4e3,#f7f9f3);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:20px;
        }
        .card{
            width:100%;
            max-width:460px;
            background:#fff;
            border-radius:22px;
            overflow:hidden;
            box-shadow:0 20px 50px rgba(0,0,0,0.10);
            border:1px solid rgba(0,0,0,0.05);
        }
        .header{
            background:linear-gradient(135deg,#638411,#7aa51a);
            color:#fff;
            text-align:center;
            padding:28px 20px;
        }
        .header h1{
            font-size:28px;
            margin-bottom:8px;
        }
        .header p{
            font-size:14px;
            opacity:.95;
            line-height:1.6;
        }
        .body{
            padding:24px;
        }
        .alert{
            padding:12px 14px;
            border-radius:12px;
            font-size:14px;
            margin-bottom:16px;
            line-height:1.6;
        }
        .alert.error{
            background:#ffe9e9;
            color:#b42318;
            border:1px solid #f0b2b2;
        }
        .alert.success{
            background:#e8f7ea;
            color:#1f7a35;
            border:1px solid #b7e0c0;
        }
        .alert.info{
            background:#eef7ff;
            color:#175cd3;
            border:1px solid #b8d7ff;
        }
        label{
            display:block;
            margin-bottom:8px;
            font-weight:600;
            color:#333;
            font-size:14px;
        }
        .form-control{
            width:100%;
            height:52px;
            border:1px solid #ddd;
            border-radius:12px;
            padding:0 14px;
            font-size:20px;
            text-align:center;
            letter-spacing:6px;
            outline:none;
            background:#fafafa;
            transition:.25s;
        }
        .form-control:focus{
            border-color:#7aa51a;
            background:#fff;
            box-shadow:0 0 0 4px rgba(122,165,26,.12);
        }
        .btn{
            width:100%;
            height:50px;
            border:none;
            border-radius:14px;
            background:linear-gradient(135deg,#638411,#7aa51a);
            color:#fff;
            font-size:16px;
            font-weight:700;
            cursor:pointer;
            margin-top:14px;
        }
        .btn-link{
            display:block;
            text-align:center;
            margin-top:16px;
            color:#638411;
            text-decoration:none;
            font-weight:700;
        }
        .small{
            margin-top:14px;
            font-size:13px;
            color:#666;
            text-align:center;
            line-height:1.6;
        }
        .email{
            font-weight:700;
            color:#333;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>ยืนยัน OTP</h1>
            <p>
                กรุณากรอกรหัส OTP ที่ส่งไปยังอีเมล<br>
                <span class="email"><?php echo htmlspecialchars($userEmail); ?></span>
            </p>
        </div>

        <div class="body">
            <?php if ($error !== ""): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success !== ""): ?>
                <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['debug_otp'])): ?>
                <div class="alert info">
                    OTP สำหรับทดสอบตอนนี้คือ: <strong><?php echo htmlspecialchars($_SESSION['debug_otp']); ?></strong><br>
                    ตอนเชื่อมอีเมลจริงค่อยเอากล่องนี้ออก
                </div>
            <?php endif; ?>

            <form method="POST">
                <label for="otp">รหัส OTP</label>
                <input type="text" id="otp" name="otp" class="form-control" maxlength="6" placeholder="000000" required>
                <button type="submit" class="btn">ยืนยันรหัส</button>
            </form>

            <div class="small">
                OTP มีอายุ 5 นาที
            </div>

            <a href="register.php" class="btn-link">← กลับไปสมัครใหม่</a>
        </div>
    </div>
</body>
</html>