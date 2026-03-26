<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

/* =========================
   DB Connection
========================= */
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";

$fullname = "";
$email = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($fullname === "" || $email === "" || $password === "" || $confirm_password === "") {
        $error = "กรุณากรอกข้อมูลให้ครบ";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } elseif (strlen($password) < 6) {
        $error = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    } elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน";
    } else {
        // ตรวจสอบอีเมลซ้ำ
        $stmtCheck = $conn->prepare("SELECT id, is_verified FROM users WHERE email = ?");
        $stmtCheck->bind_param("s", $email);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();

        if ($resultCheck->num_rows > 0) {
            $userRow = $resultCheck->fetch_assoc();

            if ((int)$userRow['is_verified'] === 1) {
                $error = "อีเมลนี้ถูกใช้งานแล้ว";
            } else {
                // ถ้ายังไม่ verify ให้ลบ OTP เก่าแล้วสร้าง OTP ใหม่
                $userId = (int)$userRow['id'];

                $otp = str_pad((string)rand(0, 999999), 6, "0", STR_PAD_LEFT);
                $expiresAt = date("Y-m-d H:i:s", strtotime("+5 minutes"));

                $conn->query("DELETE FROM user_otps WHERE user_id = {$userId} AND is_used = 0");

                $stmtOtp = $conn->prepare("INSERT INTO user_otps (user_id, otp_code, expires_at, is_used) VALUES (?, ?, ?, 0)");
                $stmtOtp->bind_param("iss", $userId, $otp, $expiresAt);
                $stmtOtp->execute();
                $stmtOtp->close();

                $_SESSION['pending_user_id'] = $userId;
                $_SESSION['pending_user_email'] = $email;
                $_SESSION['debug_otp'] = $otp; // เอาไว้ทดสอบก่อน

                header("Location: otp_verify.php");
                exit;
            }
        } else {
            // สร้าง user ใหม่
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmtInsert = $conn->prepare("INSERT INTO users (fullname, email, phone, password, is_verified) VALUES (?, ?, ?, ?, 0)");
            $stmtInsert->bind_param("ssss", $fullname, $email, $phone, $hashedPassword);

            if ($stmtInsert->execute()) {
                $userId = $stmtInsert->insert_id;

                $otp = str_pad((string)rand(0, 999999), 6, "0", STR_PAD_LEFT);
                $expiresAt = date("Y-m-d H:i:s", strtotime("+5 minutes"));

                $stmtOtp = $conn->prepare("INSERT INTO user_otps (user_id, otp_code, expires_at, is_used) VALUES (?, ?, ?, 0)");
                $stmtOtp->bind_param("iss", $userId, $otp, $expiresAt);
                $stmtOtp->execute();
                $stmtOtp->close();

                $_SESSION['pending_user_id'] = $userId;
                $_SESSION['pending_user_email'] = $email;
                $_SESSION['debug_otp'] = $otp; // เอาไว้ทดสอบก่อน

                header("Location: otp_verify.php");
                exit;
            } else {
                $error = "เกิดข้อผิดพลาดในการสมัครสมาชิก";
            }

            $stmtInsert->close();
        }

        $stmtCheck->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:'Segoe UI', Tahoma, sans-serif;
            background:linear-gradient(135deg,#eef4e3,#f7f9f3);
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:20px;
        }
        .card{
            width:100%;
            max-width:480px;
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
        }
        .body{
            padding:24px;
        }
        .alert{
            padding:12px 14px;
            border-radius:12px;
            font-size:14px;
            margin-bottom:16px;
        }
        .alert.error{
            background:#ffe9e9;
            color:#b42318;
            border:1px solid #f0b2b2;
        }
        .form-group{
            margin-bottom:15px;
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
            height:48px;
            border:1px solid #ddd;
            border-radius:12px;
            padding:0 14px;
            font-size:15px;
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
            margin-top:4px;
        }
        .btn:hover{
            opacity:.95;
        }
        .bottom{
            text-align:center;
            margin-top:16px;
            font-size:14px;
            color:#666;
        }
        .bottom a{
            color:#638411;
            text-decoration:none;
            font-weight:700;
        }
        .back-home{
            display:block;
            margin-top:14px;
            text-align:center;
            color:#555;
            text-decoration:none;
            font-size:14px;
        }
        .back-home:hover{
            color:#638411;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>สมัครสมาชิก</h1>
            <p>กรอกข้อมูลเพื่อสร้างบัญชีผู้ใช้งาน</p>
        </div>

        <div class="body">
            <?php if ($error !== ""): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($fullname); ?>" required>
                </div>

                <div class="form-group">
                    <label>อีเมล</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                </div>

                <div class="form-group">
                    <label>เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>">
                </div>

                <div class="form-group">
                    <label>รหัสผ่าน</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>ยืนยันรหัสผ่าน</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn">สมัครสมาชิก</button>
            </form>

            <div class="bottom">
                มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
            </div>

            <a href="index.php" class="back-home">← กลับหน้าแรก</a>
        </div>
    </div>
</body>
</html>