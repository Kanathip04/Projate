<?php
session_start();

// ถ้าล็อกอินแล้ว ให้เด้งไปหน้า dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}

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
            --radius:24px;
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family:'Noto Sans Thai', sans-serif;
            min-height:100vh;
            background:
                radial-gradient(circle at top left, rgba(122,165,26,.18), transparent 30%),
                radial-gradient(circle at bottom right, rgba(99,132,17,.16), transparent 30%),
                linear-gradient(135deg, var(--bg1), var(--bg2));
            color:var(--text);
            display:flex;
            align-items:center;
            justify-content:center;
            padding:24px;
        }

        .login-wrap{
            width:100%;
            max-width:1100px;
            display:grid;
            grid-template-columns: 1.1fr .9fr;
            background:rgba(255,255,255,.65);
            backdrop-filter: blur(14px);
            border:1px solid rgba(255,255,255,.45);
            border-radius:32px;
            overflow:hidden;
            box-shadow:var(--shadow);
        }

        .login-left{
            position:relative;
            padding:56px 48px;
            background:
                linear-gradient(160deg, rgba(99,132,17,.95), rgba(122,165,26,.88));
            color:#fff;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            min-height:680px;
        }

        .login-left::before{
            content:"";
            position:absolute;
            inset:0;
            background:
                radial-gradient(circle at 20% 20%, rgba(255,255,255,.18), transparent 25%),
                radial-gradient(circle at 80% 70%, rgba(255,255,255,.12), transparent 22%);
            pointer-events:none;
        }

        .brand{
            position:relative;
            z-index:2;
        }

        .brand-badge{
            display:inline-flex;
            align-items:center;
            gap:10px;
            padding:10px 16px;
            border-radius:999px;
            background:rgba(255,255,255,.14);
            border:1px solid rgba(255,255,255,.25);
            font-size:14px;
            font-weight:600;
            margin-bottom:20px;
        }

        .brand h1{
            font-size:40px;
            line-height:1.25;
            font-weight:800;
            margin-bottom:14px;
        }

        .brand p{
            font-size:16px;
            line-height:1.8;
            color:rgba(255,255,255,.9);
            max-width:520px;
        }

        .feature-box{
            position:relative;
            z-index:2;
            display:grid;
            gap:16px;
            margin-top:40px;
        }

        .feature-item{
            display:flex;
            gap:14px;
            align-items:flex-start;
            padding:16px 18px;
            border-radius:18px;
            background:rgba(255,255,255,.12);
            border:1px solid rgba(255,255,255,.18);
        }

        .feature-icon{
            min-width:42px;
            width:42px;
            height:42px;
            border-radius:12px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:rgba(255,255,255,.16);
            font-size:20px;
        }

        .feature-item h3{
            font-size:16px;
            margin-bottom:4px;
            font-weight:700;
        }

        .feature-item p{
            font-size:14px;
            color:rgba(255,255,255,.88);
            line-height:1.6;
        }

        .login-right{
            background:rgba(255,255,255,.85);
            padding:46px 38px;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .form-card{
            width:100%;
            max-width:430px;
        }

        .form-top{
            margin-bottom:28px;
        }

        .form-top h2{
            font-size:30px;
            font-weight:800;
            margin-bottom:8px;
            color:var(--text);
        }

        .form-top p{
            font-size:15px;
            color:var(--muted);
            line-height:1.7;
        }

        .alert{
            margin-bottom:18px;
            padding:14px 16px;
            border-radius:14px;
            background:#fff8e1;
            border:1px solid #f6d77a;
            color:#8a6500;
            font-size:14px;
            line-height:1.6;
        }

        .google-btn{
            width:100%;
            border:none;
            outline:none;
            cursor:pointer;
            border-radius:16px;
            padding:16px 18px;
            background:#fff;
            border:1px solid var(--border);
            display:flex;
            align-items:center;
            justify-content:center;
            gap:12px;
            font-size:15px;
            font-weight:700;
            color:#111827;
            transition:.25s ease;
            box-shadow:0 8px 20px rgba(0,0,0,.04);
        }

        .google-btn:hover{
            transform:translateY(-1px);
            box-shadow:0 12px 24px rgba(0,0,0,.08);
        }

        .google-icon{
            width:22px;
            height:22px;
            border-radius:50%;
            background:conic-gradient(#ea4335 0 25%, #fbbc05 25% 50%, #34a853 50% 75%, #4285f4 75% 100%);
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

        .divider::before,
        .divider::after{
            content:"";
            flex:1;
            height:1px;
            background:var(--border);
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
            transition:.2s ease;
        }

        .tab-btn.active{
            background:#fff;
            color:var(--brand-dark);
            box-shadow:0 4px 14px rgba(0,0,0,.06);
        }

        .tab-panel{
            display:none;
            animation:fadeIn .25s ease;
        }

        .tab-panel.active{
            display:block;
        }

        @keyframes fadeIn{
            from{opacity:0; transform:translateY(4px);}
            to{opacity:1; transform:translateY(0);}
        }

        .form-group{
            margin-bottom:16px;
        }

        .form-label{
            display:block;
            margin-bottom:8px;
            font-size:14px;
            font-weight:700;
            color:#374151;
        }

        .input-wrap{
            position:relative;
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
            transition:.2s ease;
        }

        .form-control:focus{
            border-color:var(--brand-light);
            box-shadow:0 0 0 4px rgba(122,165,26,.12);
        }

        .otp-row{
            display:grid;
            grid-template-columns: 1fr auto;
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
            transition:.25s ease;
        }

        .btn-primary{
            width:100%;
            background:linear-gradient(135deg, var(--brand), var(--brand-light));
            color:#fff;
            box-shadow:0 12px 22px rgba(99,132,17,.22);
        }

        .btn-primary:hover{
            transform:translateY(-1px);
            box-shadow:0 16px 28px rgba(99,132,17,.28);
        }

        .btn-outline{
            min-width:130px;
            background:#fff;
            border:1px solid #d1d5db;
            color:#374151;
        }

        .btn-outline:hover{
            background:#f9fafb;
        }

        .helper{
            margin-top:8px;
            font-size:13px;
            color:var(--muted);
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
            color:var(--brand-dark);
            font-size:14px;
            font-weight:700;
            text-decoration:none;
        }

        .back-link:hover{
            text-decoration:underline;
        }

        @media (max-width: 960px){
            .login-wrap{
                grid-template-columns:1fr;
            }

            .login-left{
                min-height:auto;
                padding:40px 28px;
            }

            .login-right{
                padding:32px 22px 38px;
            }

            .brand h1{
                font-size:30px;
            }
        }

        @media (max-width: 560px){
            body{
                padding:14px;
            }

            .login-left,
            .login-right{
                padding:24px 18px;
            }

            .form-top h2{
                font-size:24px;
            }

            .otp-row{
                grid-template-columns:1fr;
            }

            .btn-outline{
                width:100%;
            }
        }
    </style>
</head>
<body>

    <div class="login-wrap">
        <div class="login-left">
            <div class="brand">
                <div class="brand-badge">🔐 ระบบจัดการผู้ดูแล</div>
                <h1>ยินดีต้อนรับเข้าสู่ระบบหลังบ้าน</h1>
                <p>
                    เข้าสู่ระบบเพื่อจัดการข้อมูลเว็บไซต์ ข่าวประชาสัมพันธ์ แบนเนอร์
                    ข้อมูลผู้ใช้งาน และเมนูต่าง ๆ ภายในระบบผู้ดูแล
                </p>
            </div>

            <div class="feature-box">
                <div class="feature-item">
                    <div class="feature-icon">🌐</div>
                    <div>
                        <h3>Google Sign-In</h3>
                        <p>รองรับการเข้าสู่ระบบด้วยบัญชี Google สำหรับผู้ดูแลระบบ</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon">✉️</div>
                    <div>
                        <h3>Email OTP</h3>
                        <p>สามารถรับรหัส OTP ทางอีเมลเพื่อยืนยันตัวตนก่อนเข้าใช้งาน</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon">🛡️</div>
                    <div>
                        <h3>Secure Access</h3>
                        <p>ออกแบบสำหรับการเข้าถึงของผู้ดูแลโดยเฉพาะ พร้อมรองรับการต่อยอดระบบความปลอดภัย</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="login-right">
            <div class="form-card">
                <div class="form-top">
                    <h2>เข้าสู่ระบบ</h2>
                    <p>กรุณาเลือกวิธีเข้าสู่ระบบที่ต้องการใช้งาน</p>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <button type="button" class="google-btn" onclick="window.location.href='google_login.php'">
                    <span class="google-icon"></span>
                    <span>เข้าสู่ระบบด้วย Google</span>
                </button>

                <div class="divider">หรือ</div>

                <div class="tabs">
                    <button type="button" class="tab-btn active" data-tab="request-otp">รับรหัส OTP</button>
                    <button type="button" class="tab-btn" data-tab="verify-otp">ยืนยัน OTP</button>
                </div>

                <div id="request-otp" class="tab-panel active">
                    <form action="send_otp.php" method="post">
                        <div class="form-group">
                            <label class="form-label">อีเมล</label>
                            <div class="input-wrap">
                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    placeholder="example@email.com"
                                    required
                                >
                            </div>
                            <div class="helper">
                                กรอกอีเมลที่ต้องการรับรหัส OTP เพื่อใช้เข้าสู่ระบบ
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">ส่งรหัส OTP</button>
                    </form>
                </div>

                <div id="verify-otp" class="tab-panel">
                    <form action="verify_otp.php" method="post">
                        <div class="form-group">
                            <label class="form-label">อีเมล</label>
                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                placeholder="example@email.com"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label">รหัส OTP</label>
                            <div class="otp-row">
                                <input
                                    type="text"
                                    name="otp"
                                    class="form-control"
                                    placeholder="กรอกรหัส 6 หลัก"
                                    maxlength="6"
                                    required
                                >
                                <button type="button" class="btn btn-outline" onclick="switchToRequestOtp()">
                                    ขอรหัสใหม่
                                </button>
                            </div>
                            <div class="helper">
                                กรอกรหัส OTP ที่ได้รับทางอีเมลเพื่อเข้าสู่ระบบ
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">ยืนยันและเข้าสู่ระบบ</button>
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
    </script>

</body>
</html>