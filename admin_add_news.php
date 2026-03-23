<?php
include 'config.php';

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? '');
    $content = trim($_POST["content"] ?? '');
    $imageName = "";

    if (!empty($_FILES["image"]["name"])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $imageName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFile = $targetDir . $imageName;

        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            $message = "อัปโหลดรูปไม่สำเร็จ";
            $messageType = "error";
        }
    }

    if ($title !== "" && $content !== "" && $messageType !== "error") {
        $stmt = $conn->prepare("INSERT INTO news (title, content, image) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $title, $content, $imageName);

        if ($stmt->execute()) {
            $message = "โพสต์ข่าวสำเร็จ";
            $messageType = "success";
        } else {
            $message = "บันทึกข่าวไม่สำเร็จ";
            $messageType = "error";
        }

        $stmt->close();
    } elseif ($title === "" || $content === "") {
        $message = "กรุณากรอกหัวข้อและเนื้อหา";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>โพสต์ข่าวสาร</title>
<style>
    *{
        box-sizing:border-box;
        margin:0;
        padding:0;
    }

    :root{
        --bg:#f4f6f8;
        --card:#ffffff;
        --text:#1f2937;
        --muted:#6b7280;
        --primary:#6d8f1f;
        --primary-dark:#5d7c18;
        --soft-btn:#f8f8f1;
        --soft-border:#d8ddc6;
        --line:#e5e7eb;
        --shadow:0 14px 35px rgba(0,0,0,.06);
        --success-bg:#eefaf0;
        --success-text:#18794e;
        --error-bg:#fff1f2;
        --error-text:#be123c;
    }

    body{
        font-family:'Segoe UI', Tahoma, sans-serif;
        background:
            radial-gradient(circle at top, rgba(109,143,31,.06), transparent 25%),
            linear-gradient(180deg, #f5f7f6 0%, #f1f4f2 100%);
        color:var(--text);
        min-height:100vh;
        padding:30px 16px 50px;
    }

    a{
        text-decoration:none;
    }

    .wrap{
        max-width:980px;
        margin:0 auto;
    }

    .topbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:28px;
        padding:4px 2px 0;
    }

    .nav-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-height:48px;
        padding:0 24px;
        border-radius:999px;
        font-size:15px;
        font-weight:700;
        line-height:1;
        transition:.25s ease;
    }

    .nav-btn.back{
        background:var(--soft-btn);
        color:var(--primary);
        border:1px solid var(--soft-border);
    }

    .nav-btn.back:hover{
        background:#eef2e3;
        border-color:#cdd5b5;
        color:var(--primary-dark);
    }

    .nav-btn.manage{
        background:var(--primary);
        color:#fff;
        border:1px solid var(--primary);
    }

    .nav-btn.manage:hover{
        background:var(--primary-dark);
        border-color:var(--primary-dark);
    }

    .hero{
        text-align:center;
        padding:6px 0 26px;
    }

    .hero-badge{
        display:inline-block;
        padding:8px 16px;
        border-radius:999px;
        background:rgba(109,143,31,.12);
        color:var(--primary-dark);
        font-size:13px;
        font-weight:700;
        margin-bottom:16px;
    }

    .hero h1{
        font-size:42px;
        line-height:1.2;
        margin-bottom:10px;
        font-weight:800;
        color:#111827;
    }

    .hero p{
        max-width:720px;
        margin:0 auto;
        color:var(--muted);
        line-height:1.8;
        font-size:16px;
    }

    .card{
        background:var(--card);
        border-radius:26px;
        box-shadow:var(--shadow);
        border:1px solid rgba(0,0,0,.04);
        overflow:hidden;
    }

    .card-head{
        padding:26px 28px 18px;
        border-bottom:1px solid #eef1f4;
        background:linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
    }

    .fake-fb-head{
        display:flex;
        align-items:center;
        gap:14px;
    }

    .avatar{
        width:58px;
        height:58px;
        border-radius:50%;
        background:linear-gradient(135deg, #dbeafe, #bfdbfe);
        display:flex;
        align-items:center;
        justify-content:center;
        font-weight:800;
        color:#2563eb;
        font-size:22px;
        flex-shrink:0;
    }

    .name{
        font-weight:800;
        font-size:18px;
        color:#111827;
        margin-bottom:4px;
    }

    .sub{
        color:#6b7280;
        font-size:14px;
        line-height:1.6;
    }

    .card-body{
        padding:26px 28px 30px;
    }

    .msg{
        margin-bottom:18px;
        padding:14px 16px;
        border-radius:14px;
        font-size:15px;
        font-weight:600;
        line-height:1.7;
    }

    .msg.success{
        background:var(--success-bg);
        color:var(--success-text);
        border:1px solid #ccebd7;
    }

    .msg.error{
        background:var(--error-bg);
        color:var(--error-text);
        border:1px solid #fecdd3;
    }

    .form-grid{
        display:grid;
        grid-template-columns:1fr;
        gap:18px;
    }

    .form-group{
        display:flex;
        flex-direction:column;
        gap:8px;
    }

    label{
        font-weight:700;
        color:#111827;
        font-size:15px;
    }

    .hint{
        color:#6b7280;
        font-size:13px;
        margin-top:-2px;
    }

    input[type="text"],
    textarea,
    input[type="file"]{
        width:100%;
        border:1px solid #d9dee5;
        border-radius:16px;
        padding:15px 16px;
        font-size:15px;
        font-family:inherit;
        background:#fff;
        transition:.2s ease;
        outline:none;
    }

    input[type="text"]:focus,
    textarea:focus,
    input[type="file"]:focus{
        border-color:var(--primary);
        box-shadow:0 0 0 4px rgba(109,143,31,.10);
    }

    textarea{
        min-height:220px;
        resize:vertical;
        line-height:1.8;
    }

    .upload-box{
        padding:14px;
        border:1px dashed #cfd6de;
        border-radius:18px;
        background:#fafbfc;
    }

    .image-preview{
        display:none;
        margin-top:16px;
        padding:14px;
        background:#fff;
        border:1px solid #e5e7eb;
        border-radius:16px;
    }

    .image-preview-title{
        font-size:14px;
        font-weight:700;
        color:#374151;
        margin-bottom:10px;
    }

    .image-preview img{
        display:block;
        width:100%;
        max-width:420px;
        border-radius:12px;
        border:1px solid #e5e7eb;
        box-shadow:0 8px 20px rgba(0,0,0,.08);
    }

    .actions{
        display:flex;
        gap:12px;
        flex-wrap:wrap;
        margin-top:8px;
    }

    .btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        border:none;
        border-radius:14px;
        padding:14px 22px;
        font-size:15px;
        font-weight:800;
        cursor:pointer;
        transition:.25s ease;
    }

    .btn-submit{
        background:var(--primary);
        color:#fff;
        box-shadow:0 10px 22px rgba(109,143,31,.18);
    }

    .btn-submit:hover{
        background:var(--primary-dark);
        transform:translateY(-1px);
    }

    .footer-note{
        text-align:center;
        color:#8a8f98;
        font-size:14px;
        margin-top:22px;
    }

    @media (max-width: 768px){
        body{
            padding:18px 12px 36px;
        }

        .hero h1{
            font-size:31px;
        }

        .hero p{
            font-size:15px;
        }

        .card-head,
        .card-body{
            padding:18px;
        }

        .avatar{
            width:50px;
            height:50px;
            font-size:20px;
        }

        .name{
            font-size:16px;
        }

        .topbar{
            flex-direction:column;
            align-items:stretch;
            gap:12px;
        }

        .nav-btn,
        .btn{
            width:100%;
        }

        textarea{
            min-height:180px;
        }

        .image-preview img{
            max-width:100%;
        }
    }
</style>
</head>
<body>

<div class="wrap">

    <div class="topbar">
        <a href="admin_dashboard.php" class="nav-btn back">← กลับหน้าหลัก</a>
        <a href="manage_news.php" class="nav-btn manage">จัดการข่าวสาร</a>
    </div>

    <div class="hero">
        <div class="hero-badge">News Admin Panel</div>
        <h1>โพสต์ข่าวสาร</h1>
        <p>สร้างข่าวประชาสัมพันธ์ ข่าวกิจกรรม หรือประกาศต่าง ๆ เพื่อเผยแพร่บนหน้าเว็บไซต์ได้จากหน้านี้</p>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="fake-fb-head">
                <div class="avatar">ข</div>
                <div>
                    <div class="name">ผู้ดูแลระบบข่าวสาร</div>
                    <div class="sub">กรอกหัวข้อ เนื้อหา และเลือกรูปภาพ จากนั้นกดโพสต์ข่าวเพื่อแสดงผลบนเว็บไซต์</div>
                </div>
            </div>
        </div>

        <div class="card-body">

            <?php if($message !== ""): ?>
                <div class="msg <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="form-grid">

                    <div class="form-group">
                        <label for="title">หัวข้อข่าว</label>
                        <div class="hint">ตั้งชื่อข่าวให้ชัดเจนและเข้าใจง่าย</div>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                            placeholder="เช่น ประกาศกำหนดการเข้าร่วมกิจกรรมประจำปี"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="content">เนื้อหาข่าว</label>
                        <div class="hint">สามารถพิมพ์รายละเอียดข่าวได้หลายบรรทัด</div>
                        <textarea
                            id="content"
                            name="content"
                            placeholder="พิมพ์รายละเอียดข่าวสารที่ต้องการเผยแพร่..."
                            required
                        ><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="image">เลือกรูปภาพ</label>
                        <div class="hint">รองรับไฟล์ภาพสำหรับใช้เป็นภาพประกอบข่าว</div>

                        <div class="upload-box">
                            <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(event)">

                            <div class="image-preview" id="previewBox">
                                <div class="image-preview-title">ตัวอย่างรูปที่เลือก</div>
                                <img id="previewImg" alt="ตัวอย่างรูปภาพที่เลือก">
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-submit">โพสต์ข่าว</button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <div class="footer-note">
        © ระบบโพสต์ข่าวสาร
    </div>

</div>

<script>
function previewImage(event) {
    const file = event.target.files[0];
    const img = document.getElementById("previewImg");
    const box = document.getElementById("previewBox");

    if (!file) {
        img.src = "";
        box.style.display = "none";
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        img.src = e.target.result;
        box.style.display = "block";
    };
    reader.readAsDataURL(file);
}
</script>

</body>
</html>