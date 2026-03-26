<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$message = $_SESSION['login_message'] ?? '';
unset($_SESSION['login_message']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | ระบบผู้ดูแล</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://accounts.google.com/gsi/client" async defer></script>

    <style>
        :root{
            --bg1:#eef4e8;
            --bg2:#dfead1;
            --brand:#638411;
            --brand-dark:#4f6a0d;
            --brand-light:#7aa51a;
            --text:#1f2937;
            --muted:#6b7280;
            --white:#ffffff;
            --border:#e5e7eb;
            --shadow:0 20px 50px rgba(0,0,0,.12);
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:'Noto Sans Thai', sans-serif;
            min-height:100vh;
            background:linear-gradient(135deg, var(--bg1), var(--bg2));
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .login-wrap{
            width:100%;
            max-width:1150px;
            display:grid;
            grid-template-columns:1.05fr .95fr;
            background:rgba(255,255,255,.72);
            border-radius:32px;
            overflow:hidden;
            box-shadow:var(--shadow);
        }
        .login-left{
            padding:56px 48px;
            background:linear-gradient(160deg, rgba(99,132,17,.97), rgba(122,165,26,.90));
            color:#fff;
            min-height:720px;
        }
        .login-left h1{
            font-size:42px;
            line-height:1.25;
            font-weight:800;
            margin-bottom:14px;
        }
        .login-left p{
            font-size:16px;
            line-height:1.8;
            max-width:560px;
        }
        .login-right{
            background:rgba(255,255,255,.9);
            padding:46px 38px;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .form-card{
            width:100%;
            max-width:450px;
        }
        .form-top h2{
            font-size:31px;
            font-weight:800;
            margin-bottom:8px;
            color:#1f2937;
        }
        .form-top p{
            font-size:15px;
            color:#6b7280;
            line-height:1.75;
            margin-bottom:24px;
        }
        .alert{
            margin-bottom:18px;
            padding:14px 16px;
            border-radius:14px;
            font-size:14px;
            line-height:1.6;
            border:1px solid #f6d77a;
            background:#fff8e1;
            color:#8a6500;
        }
        .google-box{
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:18px;
            padding:18px;
            box-shadow:0 8px 20px rgba(0,0,0,.04);
        }
        .google-title{
            font-size:15px;
            font-weight:700;
            color:#111827;
            margin-bottom:12px;
        }
        .divider{
            display:flex;
            align-items:center;
            gap:14px;
            margin:24px 0;
            color:#9ca3af;
            font-size:13px;
            font-weight:600;
        }
        .divider::before,.divider::after{
            content:"";
            flex:1;
            height:1px;
            background:#e5e7eb;
        }
        .tabs{
            display:flex;
            background:#f3f4f6;
            border-radius:16px;
            padding:6px;
            margin-bottom:18px;
        }
        .tab-btn{
            flex:1;
            padding:12px 14px;
            border:none;
            background:transparent;
            border-radius:12px;
            font-size:14px;
            font-weight:700;
            cursor:pointer;
            color:#6b7280;
        }
        .tab-btn.active{
            background:#fff;
            color:#4f6a0d;
            box-shadow:0 4px 14px rgba(0,0,0,.06);
        }
        .tab-panel{display:none;}
        .tab-panel.active{display:block;}
        .form-group{margin-bottom:16px;}
        .form-label{
            display:block;
            margin-bottom:8px;
            font-size:14px;
            font-weight:700;
            color:#374151;
        }
        .form-control{
            width:100%;
            height:54px;
            border-radius:16px;
            border:1px solid #d1d5db;
            outline:none;
            padding:0 16px;
            font-size:15px;
            background:#fff;
        }
        .otp-row{
            display:grid;
            grid-template-columns:1fr auto;
            gap:10px;
        }
        .btn{
            border:none;
            outline:none;
            cursor:pointer;
            border-radius:16px;
            padding:14px 18px;
            font-size:15px;
            font-weight:700;
        }
        .btn-primary{
            width:100%;
            background:linear-gradient(135deg, #638411, #7aa51a);
            color:#fff;
        }
        .btn-outline{
            min-width:128px;
            background:#fff;
            border:1px solid #d1d5db;
            color:#374151;
        }
        .helper{
            margin-top:8px;
            font-size:13px;
            color:#6b7280;
            line-height:1.6;
        }
        .footer-note{
            margin-top:18px;
            text-align:center;
            font-size:13px;
            color:#6b7280;
            line-height:1.7;
        }
        .back-link{
            display:inline-flex;
            align-items:center;
            gap:8px;
            margin-top:18px;
            color:#4f6a0d;
            font-size:14px;
            font-weight:700;
            text-decoration:none;
        }
        .top-actions{
            display:flex;
            gap:10px;
            margin-bottom:18px;
            flex-wrap:wrap;
        }
        .top-actions a{
            text-decoration:none;
            padding:10px 14px;
            border-radius:12px;
            font-size:14px;
            font-weight:700;
        }
        .logout-btn{
            background:#b91c1c;
            color:#fff;
        }
        .dashboard-btn{
            background:#1f2937;
            color:#fff;
        }
        .hidden-form{display:none;}
        .status-text{
            margin-top:10px;
            font-size:13px;
            color:#6b7280;
        }
        @media (max-width:960px){
            .login-wrap{grid-template-columns:1fr;}
            .login-left{min-height:auto;padding:40px 28px;}
            .login-right{padding:32px 22px 38px;}
            .login-left h1{font-size:30px;}
        }
        @media (max-width:560px){
            body{padding:14px;}
            .login-left,.login-right{padding:24px 18px;}
            .form-top h2{font-size:24px;}
            .otp-row{grid-template-columns:1fr;}
            .btn-outline{width:100%;}
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-left">
            <h1>เข้าสู่ระบบหลังบ้านอย่างปลอดภัย</h1>
            <p>รองรับการเข้าสู่ระบบด้วยบัญชี Google และการยืนยันตัวตนผ่านอีเมลด้วยรหัส OTP</p>
        </div>

        <div class="login-right">
            <div class="form-card">
                <div class="top-actions">
                    <a href="logout.php" class="logout-btn">ออกจากระบบ</a>
                    <a href="admin_dashboard.php" class="dashboard-btn">ไปหน้า Dashboard</a>
                </div>

                <div class="form-top">
                    <h2>เข้าสู่ระบบ</h2>
                    <p>เลือกวิธีเข้าสู่ระบบที่ต้องการใช้งาน</p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <div class="google-box">
                    <div class="google-title">เข้าสู่ระบบด้วย Google</div>

                    <div id="g_id_onload"
                        data-client_id="YOUR_GOOGLE_CLIENT_ID"
                        data-callback="handleGoogleCredential"
                        data-auto_prompt="false">
                    </div>

                    <div class="g_id_signin"
                        data-type="standard"
                        data-shape="pill"
                        data-theme="outline"
                        data-text="signin_with"
                        data-size="large"
                        data-logo_alignment="left"
                        data-width="100%">
                    </div>

                    <div class="status-text">เมื่อกดปุ่ม ระบบจะส่ง Google credential ไปที่ google_verify.php</div>
                </div>

                <form id="googleLoginForm" class="hidden-form" action="google_verify.php" method="post">
                    <input type="hidden" name="credential" id="googleCredentialInput">
                </form>

                <div class="divider">หรือ</div>

                <div class="tabs">
                    <button type="button" class="tab-btn active" data-tab="request-otp">รับรหัส OTP</button>
                    <button type="button" class="tab-btn" data-tab="verify-otp">ยืนยัน OTP</button>
                </div>

                <div id="request-otp" class="tab-panel active">
                    <form action="send_otp.php" method="post">
                        <div class="form-group">
                            <label class="form-label">อีเมล</label>
                            <input type="email" name="email" class="form-control" placeholder="example@email.com" required>
                            <div class="helper">ระบบจะส่งรหัส OTP ไปยังอีเมลที่คุณกรอก</div>
                        </div>
                        <button type="submit" class="btn btn-primary">ส่งรหัส OTP</button>
                    </form>
                </div>

                <div id="verify-otp" class="tab-panel">
                    <form action="verify_otp.php" method="post">
                        <div class="form-group">
                            <label class="form-label">อีเมล</label>
                            <input type="email" name="email" class="form-control" placeholder="example@email.com" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">รหัส OTP</label>
                            <div class="otp-row">
                                <input type="text" name="otp" class="form-control" placeholder="กรอกรหัส 6 หลัก" maxlength="6" required>
                                <button type="button" class="btn btn-outline" onclick="switchToRequestOtp()">ขอรหัสใหม่</button>
                            </div>
                            <div class="helper">กรอกรหัส OTP ที่ได้รับจากอีเมลเพื่อยืนยันตัวตน</div>
                        </div>

                        <button type="submit" class="btn btn-primary">ยืนยัน OTP และเข้าสู่ระบบ</button>
                    </form>
                </div>

                <div class="footer-note">
                    ระบบนี้สำหรับผู้ดูแลเว็บไซต์เท่านั้น<br>
                    หากไม่มีสิทธิ์ใช้งาน กรุณาติดต่อผู้ดูแลระบบ
                </div>

                <a href="index.php" class="back-link">← กลับหน้าเว็บไซต์</a>
            </div>
        </div>
    </div>

    <script>
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabPanels = document.querySelectorAll('.tab-panel');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const target = button.getAttribute('data-tab');
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabPanels.forEach(panel => panel.classList.remove('active'));
                button.classList.add('active');
                document.getElementById(target).classList.add('active');
            });
        });

        function switchToRequestOtp() {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            document.querySelector('[data-tab="request-otp"]').classList.add('active');
            document.getElementById('request-otp').classList.add('active');
        }

        function handleGoogleCredential(response) {
            if (response && response.credential) {
                document.getElementById('googleCredentialInput').value = response.credential;
                document.getElementById('googleLoginForm').submit();
            } else {
                alert('ไม่สามารถรับข้อมูลจาก Google ได้');
            }
        }
    </script>
</body>
</html>