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

$pageTitle = "โพสต์ข่าวสาร";
$activeMenu = "news_add";
include 'admin_layout_top.php';
?>

<style>
.news-wrap-local{
    max-width:980px;
    margin:0 auto;
}
.hero-local{
    text-align:center;
    padding:6px 0 26px;
}
.hero-badge-local{
    display:inline-block;
    padding:8px 16px;
    border-radius:999px;
    background:rgba(109,143,31,.12);
    color:#5d7c18;
    font-size:13px;
    font-weight:700;
    margin-bottom:16px;
}
.hero-local h1{
    font-size:42px;
    line-height:1.2;
    margin-bottom:10px;
    font-weight:800;
    color:#111827;
}
.hero-local p{
    max-width:720px;
    margin:0 auto;
    color:#6b7280;
    line-height:1.8;
    font-size:16px;
}
.card-local{
    background:#ffffff;
    border-radius:26px;
    box-shadow:0 14px 35px rgba(0,0,0,.06);
    border:1px solid rgba(0,0,0,.04);
    overflow:hidden;
}
.card-head-local{
    padding:26px 28px 18px;
    border-bottom:1px solid #eef1f4;
    background:linear-gradient(180deg, #ffffff 0%, #fbfcfd 100%);
}
.fake-head-local{
    display:flex;
    align-items:center;
    gap:14px;
}
.avatar-local{
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
.name-local{
    font-weight:800;
    font-size:18px;
    color:#111827;
    margin-bottom:4px;
}
.sub-local{
    color:#6b7280;
    font-size:14px;
    line-height:1.6;
}
.card-body-local{
    padding:26px 28px 30px;
}
.msg-local{
    margin-bottom:18px;
    padding:14px 16px;
    border-radius:14px;
    font-size:15px;
    font-weight:600;
    line-height:1.7;
}
.msg-local.success{
    background:#eefaf0;
    color:#18794e;
    border:1px solid #ccebd7;
}
.msg-local.error{
    background:#fff1f2;
    color:#be123c;
    border:1px solid #fecdd3;
}
.form-grid-local{
    display:grid;
    grid-template-columns:1fr;
    gap:18px;
}
.form-group-local{
    display:flex;
    flex-direction:column;
    gap:8px;
}
.form-group-local label{
    font-weight:700;
    color:#111827;
    font-size:15px;
}
.hint-local{
    color:#6b7280;
    font-size:13px;
    margin-top:-2px;
}
.form-group-local input[type="text"],
.form-group-local textarea,
.form-group-local input[type="file"]{
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
.form-group-local input[type="text"]:focus,
.form-group-local textarea:focus,
.form-group-local input[type="file"]:focus{
    border-color:#6d8f1f;
    box-shadow:0 0 0 4px rgba(109,143,31,.10);
}
.form-group-local textarea{
    min-height:220px;
    resize:vertical;
    line-height:1.8;
}
.upload-box-local{
    padding:14px;
    border:1px dashed #cfd6de;
    border-radius:18px;
    background:#fafbfc;
}
.image-preview-local{
    display:none;
    margin-top:16px;
    padding:14px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:16px;
}
.image-preview-title-local{
    font-size:14px;
    font-weight:700;
    color:#374151;
    margin-bottom:10px;
}
.image-preview-local img{
    display:block;
    width:100%;
    max-width:420px;
    border-radius:12px;
    border:1px solid #e5e7eb;
    box-shadow:0 8px 20px rgba(0,0,0,.08);
}
.actions-local{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-top:8px;
}
.btn-submit-local{
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
    background:#6d8f1f;
    color:#fff;
    box-shadow:0 10px 22px rgba(109,143,31,.18);
}
.btn-submit-local:hover{
    background:#5d7c18;
    transform:translateY(-1px);
}
@media (max-width: 768px){
    .hero-local h1{font-size:31px}
    .hero-local p{font-size:15px}
    .card-head-local,
    .card-body-local{padding:18px}
    .avatar-local{
        width:50px;
        height:50px;
        font-size:20px;
    }
    .name-local{font-size:16px}
    .form-group-local textarea{min-height:180px}
    .image-preview-local img{max-width:100%}
    .btn-submit-local{width:100%}
}
</style>

<div class="news-wrap-local">
    <div class="hero-local">
        <div class="hero-badge-local">News Admin Panel</div>
        <h1>โพสต์ข่าวสาร</h1>
        <p>สร้างข่าวประชาสัมพันธ์ ข่าวกิจกรรม หรือประกาศต่าง ๆ เพื่อเผยแพร่บนหน้าเว็บไซต์ได้จากหน้านี้</p>
    </div>

    <div class="card-local">
        <div class="card-head-local">
            <div class="fake-head-local">
                <div class="avatar-local">ข</div>
                <div>
                    <div class="name-local">ผู้ดูแลระบบข่าวสาร</div>
                    <div class="sub-local">กรอกหัวข้อ เนื้อหา และเลือกรูปภาพ จากนั้นกดโพสต์ข่าวเพื่อแสดงผลบนเว็บไซต์</div>
                </div>
            </div>
        </div>

        <div class="card-body-local">
            <?php if($message !== ""): ?>
                <div class="msg-local <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="form-grid-local">
                    <div class="form-group-local">
                        <label for="title">หัวข้อข่าว</label>
                        <div class="hint-local">ตั้งชื่อข่าวให้ชัดเจนและเข้าใจง่าย</div>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"
                            placeholder="เช่น ประกาศกำหนดการเข้าร่วมกิจกรรมประจำปี"
                            required
                        >
                    </div>

                    <div class="form-group-local">
                        <label for="content">เนื้อหาข่าว</label>
                        <div class="hint-local">สามารถพิมพ์รายละเอียดข่าวได้หลายบรรทัด</div>
                        <textarea
                            id="content"
                            name="content"
                            placeholder="พิมพ์รายละเอียดข่าวสารที่ต้องการเผยแพร่..."
                            required
                        ><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                    </div>

                    <div class="form-group-local">
                        <label for="image">เลือกรูปภาพ</label>
                        <div class="hint-local">รองรับไฟล์ภาพสำหรับใช้เป็นภาพประกอบข่าว</div>

                        <div class="upload-box-local">
                            <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(event)">

                            <div class="image-preview-local" id="previewBox">
                                <div class="image-preview-title-local">ตัวอย่างรูปที่เลือก</div>
                                <img id="previewImg" alt="ตัวอย่างรูปภาพที่เลือก">
                            </div>
                        </div>
                    </div>

                    <div class="actions-local">
                        <button type="submit" class="btn-submit-local">โพสต์ข่าว</button>
                    </div>
                </div>
            </form>
        </div>
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

<?php
$conn->close();
include 'admin_layout_bottom.php';
?>