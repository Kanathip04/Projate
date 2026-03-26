<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

/* =========================
   เชื่อมต่อฐานข้อมูล
========================= */
$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =========================
   ตัวแปรเริ่มต้น
========================= */
$success = "";
$error = "";

$fullname = "";
$email = "";
$phone = "";

/* =========================
   สมัครสมาชิก
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ตรวจสอบค่าว่าง
    if ($fullname === "" || $email === "" || $password === "" || $confirm_password === "") {
        $error = "กรุณากรอกข้อมูลให้ครบทุกช่องที่จำเป็น";
    }
    // ตรวจสอบรูปแบบอีเมล
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    }
    // ตรวจสอบความยาวรหัสผ่าน
    elseif (strlen($password) < 6) {
        $error = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    }
    // ตรวจสอบยืนยันรหัสผ่าน
    elseif ($password !== $confirm_password) {
        $error = "รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน";
    } else {
        // ตรวจสอบอีเมลซ้ำ
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $error = "อีเมลนี้ถูกใช้งานแล้ว";
        } else {
            // เข้ารหัสรหัสผ่าน
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // บันทึกข้อมูล
            $insertStmt = $conn->prepare("INSERT INTO users (fullname, email, phone, password) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("ssss", $fullname, $email, $phone, $hashedPassword);

            if ($insertStmt->execute()) {
                $success = "สมัครสมาชิกสำเร็จ กำลังไปยังหน้าเข้าสู่ระบบ...";
                header("refresh:2;url=login.php");
            } else {
                $error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }

            $insertStmt->close();
        }

        $checkStmt->close();
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
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family:'Segoe UI', Tahoma, sans-serif;
            background: linear-gradient(135deg, #eef4e3, #f7f9f3);
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:20px;
        }

        .register-container{
            width:100%;
            max-width:480px;
            background:#fff;
            border-radius:22px;
            box-shadow:0 15px 40px rgba(0,0,0,0.10);
            overflow:hidden;
            border:1px solid rgba(0,0,0,0.05);
        }

        .register-header{
            background: linear-gradient(135deg, #638411, #7aa51a);
            color:#fff;
            padding:30px 25px;
            text-align:center;
        }

        .register-header h1{
            font-size:28px;
            margin-bottom:8px;
        }

        .register-header p{
            font-size:14px;
            opacity:0.95;
        }

        .register-body{
            padding:28px 24px 24px;
        }

        .alert{
            padding:12px 14px;
            border-radius:12px;
            margin-bottom:18px;
            font-size:14px;
            line-height:1.5;
        }

        .alert.error{
            background:#ffe9e9;
            color:#c62828;
            border:1px solid #f5bcbc;
        }

        .alert.success{
            background:#eaf7e8;
            color:#2e7d32;
            border:1px solid #b8dfb5;
        }

        .form-group{
            margin-bottom:16px;
        }

        .form-group label{
            display:block;
            margin-bottom:8px;
            font-size:14px;
            font-weight:600;
            color:#333;
        }

        .form-control{
            width:100%;
            height:48px;
            border:1px solid #dcdcdc;
            border-radius:12px;
            padding:0 14px;
            font-size:15px;
            outline:none;
            transition:0.25s;
            background:#fafafa;
        }

        .form-control:focus{
            border-color:#7aa51a;
            background:#fff;
            box-shadow:0 0 0 4px rgba(122,165,26,0.12);
        }

        .register-btn{
            width:100%;
            height:50px;
            border:none;
            border-radius:14px;
            background: linear-gradient(135deg, #638411, #7aa51a);
            color:#fff;
            font-size:16px;
            font-weight:700;
            cursor:pointer;
            transition:0.25s;
            margin-top:4px;
        }

        .register-btn:hover{
            transform:translateY(-1px);
            box-shadow:0 10px 20px rgba(99,132,17,0.20);
        }

        .bottom-text{
            margin-top:18px;
            text-align:center;
            font-size:14px;
            color:#666;
        }

        .bottom-text a{
            color:#638411;
            text-decoration:none;
            font-weight:700;
        }

        .bottom-text a:hover{
            text-decoration:underline;
        }

        .back-home{
            display:inline-block;
            margin-top:14px;
            text-align:center;
            width:100%;
            color:#555;
            text-decoration:none;
            font-size:14px;
        }

        .back-home:hover{
            color:#638411;
        }

        @media (max-width: 520px){
            .register-header h1{
                font-size:24px;
            }

            .register-body{
                padding:22px 18px 20px;
            }
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="register-header">
        <h1>สมัครสมาชิก</h1>
        <p>กรอกข้อมูลเพื่อสร้างบัญชีผู้ใช้งาน</p>
    </div>

    <div class="register-body">

        <?php if ($error !== ""): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success !== ""): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="fullname">ชื่อ-นามสกุล</label>
                <input type="text" id="fullname" name="fullname" class="form-control"
                       value="<?php echo htmlspecialchars($fullname); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">อีเมล</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div class="form-group">
                <label for="phone">เบอร์โทรศัพท์</label>
                <input type="text" id="phone" name="phone" class="form-control"
                       value="<?php echo htmlspecialchars($phone); ?>">
            </div>

            <div class="form-group">
                <label for="password">รหัสผ่าน</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">ยืนยันรหัสผ่าน</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="register-btn">สมัครสมาชิก</button>
        </form>

        <div class="bottom-text">
            มีบัญชีอยู่แล้ว? <a href="login.php">เข้าสู่ระบบ</a>
        </div>

        <a href="index.php" class="back-home">← กลับหน้าแรก</a>
    </div>
</div>

</body>
</html>