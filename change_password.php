<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

$check = $conn->query("SHOW TABLES LIKE 'admin'");
if ($check->num_rows === 0) {
    $conn->query("
        CREATE TABLE admin (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL,
            password VARCHAR(255) NOT NULL
        )
    ");

    $hash = password_hash('password', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO admin (id, username, password) VALUES (1, 'admin', '$hash')");
}

$message = "";
$pageTitle = "Security Setting";
$activeMenu = "password";

if (isset($_POST['submit'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $message = "<div class='alert alert-error'>❌ รหัสผ่านใหม่ไม่ตรงกัน</div>";
    } elseif (strlen($new) < 6) {
        $message = "<div class='alert alert-error'>❌ รหัสผ่านต้องอย่างน้อย 6 ตัวอักษร</div>";
    } else {
        $stmt = $conn->prepare("SELECT password FROM admin WHERE id = 1");
        $stmt->execute();
        $stmt->bind_result($db_pass);
        $stmt->fetch();
        $stmt->close();

        if (!$db_pass || !password_verify($current, $db_pass)) {
            $message = "<div class='alert alert-error'>❌ รหัสผ่านปัจจุบันไม่ถูกต้อง</div>";
        } else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE admin SET password = ? WHERE id = 1");
            $update->bind_param("s", $new_hash);

            if ($update->execute()) {
                $message = "<div class='alert alert-success'>✅ เปลี่ยนรหัสผ่านสำเร็จ</div>";
            } else {
                $message = "<div class='alert alert-error'>❌ บันทึกไม่สำเร็จ: " . htmlspecialchars($conn->error) . "</div>";
            }

            $update->close();
        }
    }
}

include "admin_layout_top.php";
?>

<style>
    .security-page {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 30px 20px;
    }

    .security-card {
        width: 100%;
        max-width: 640px;
        background: #ffffff;
        border-radius: 20px;
        padding: 32px 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .security-header {
        margin-bottom: 24px;
    }

    .security-badge {
        display: inline-block;
        background: linear-gradient(135deg, var(--brand), var(--brand2));
        color: #fff;
        font-size: 13px;
        font-weight: 700;
        padding: 7px 14px;
        border-radius: 999px;
        margin-bottom: 14px;
        box-shadow: 0 6px 14px rgba(99, 132, 17, 0.18);
    }

    .security-card h1 {
        margin: 0;
        font-size: 28px;
        line-height: 1.3;
        color: #1f2937;
    }

    .security-subtitle {
        margin-top: 8px;
        color: #6b7280;
        font-size: 14px;
        line-height: 1.6;
    }

    .alert {
        padding: 14px 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        font-weight: 700;
        font-size: 14px;
        line-height: 1.5;
        border: 1px solid transparent;
    }

    .alert-success {
        background: #ecfdf3;
        color: #166534;
        border-color: #bbf7d0;
    }

    .alert-error {
        background: #fef2f2;
        color: #b91c1c;
        border-color: #fecaca;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 700;
        color: #374151;
        font-size: 14px;
    }

    .input-wrap {
        position: relative;
    }

    .input-wrap input {
        width: 100%;
        height: 50px;
        padding: 0 48px 0 14px;
        border-radius: 12px;
        border: 1px solid #d1d5db;
        background: #f9fafb;
        font-size: 15px;
        color: #111827;
        transition: all 0.2s ease;
    }

    .input-wrap input:focus {
        border-color: var(--brand);
        background: #ffffff;
        outline: none;
        box-shadow: 0 0 0 4px rgba(99, 132, 17, 0.12);
    }

    .toggle-eye {
        position: absolute;
        top: 50%;
        right: 14px;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        font-size: 18px;
        color: #6b7280;
        padding: 0;
        line-height: 1;
    }

    .toggle-eye:hover {
        color: #111827;
    }

    .btn-submit {
        width: 100%;
        height: 52px;
        border: none;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--brand), var(--brand2));
        color: #fff;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 8px 18px rgba(99, 132, 17, 0.22);
        margin-top: 8px;
    }

    .btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(99, 132, 17, 0.28);
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    .security-note {
        margin-top: 18px;
        padding: 14px 16px;
        background: #f9fafb;
        border: 1px dashed #d1d5db;
        border-radius: 12px;
        color: #6b7280;
        font-size: 13px;
        line-height: 1.6;
    }

    @media (max-width: 768px) {
        .security-page {
            padding: 20px 12px;
        }

        .security-card {
            padding: 24px 18px;
            border-radius: 16px;
        }

        .security-card h1 {
            font-size: 23px;
        }

        .security-subtitle {
            font-size: 13px;
        }
    }
</style>

<div class="security-page">
    <div class="security-card">
        <div class="security-header">
            <div class="security-badge">SECURITY</div>
            <h1>🔐 เปลี่ยนรหัสผ่าน</h1>
            <div class="security-subtitle">
                กรุณากรอกรหัสผ่านปัจจุบัน และตั้งรหัสผ่านใหม่ให้ปลอดภัยมากขึ้น
            </div>
        </div>

        <?php echo $message; ?>

        <form method="POST">
            <div class="form-group">
                <label for="current_password">รหัสผ่านปัจจุบัน</label>
                <div class="input-wrap">
                    <input type="password" id="current_password" name="current_password" required>
                    <button type="button" class="toggle-eye" onclick="togglePass('current_password', this)">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password">รหัสผ่านใหม่</label>
                <div class="input-wrap">
                    <input type="password" id="new_password" name="new_password" required>
                    <button type="button" class="toggle-eye" onclick="togglePass('new_password', this)">👁️</button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">ยืนยันรหัสผ่านใหม่</label>
                <div class="input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="toggle-eye" onclick="togglePass('confirm_password', this)">👁️</button>
                </div>
            </div>

            <button type="submit" name="submit" class="btn-submit">
                บันทึกรหัสผ่านใหม่
            </button>
        </form>

        <div class="security-note">
            แนะนำให้ตั้งรหัสผ่านอย่างน้อย 8 ตัวอักษร และควรมีทั้งตัวอักษร ตัวเลข และสัญลักษณ์เพื่อความปลอดภัย
        </div>
    </div>
</div>

<script>
    function togglePass(fieldId, btn) {
        const input = document.getElementById(fieldId);

        if (input.type === 'password') {
            input.type = 'text';
            btn.textContent = '🙈';
        } else {
            input.type = 'password';
            btn.textContent = '👁️';
        }
    }
</script>

<?php
include "admin_layout_bottom.php";
$conn->close();
?>