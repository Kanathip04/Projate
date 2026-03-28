<?php
$conn = new mysqli("localhost", "root", "", "backoffice_db");
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";
$error = "";

/* ดึงข้อมูลเดิม */
$result = $conn->query("SELECT * FROM about_content WHERE id = 1 LIMIT 1");
$about = $result ? $result->fetch_assoc() : null;

if (!$about) {
    $conn->query("INSERT INTO about_content (id, section_tag, title) VALUES (1, 'ABOUT', 'ประวัติ')");
    $result = $conn->query("SELECT * FROM about_content WHERE id = 1 LIMIT 1");
    $about = $result->fetch_assoc();
}

/* อัปเดตข้อมูล */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $section_tag       = trim($_POST['section_tag'] ?? '');
    $title             = trim($_POST['title'] ?? '');
    $lead_text         = trim($_POST['lead_text'] ?? '');
    $paragraph1        = trim($_POST['paragraph1'] ?? '');
    $paragraph2        = trim($_POST['paragraph2'] ?? '');
    $paragraph3        = trim($_POST['paragraph3'] ?? '');
    $image_badge_title = trim($_POST['image_badge_title'] ?? '');
    $image_badge_text  = trim($_POST['image_badge_text'] ?? '');

    $image_path = $about['image_path'] ?? '';

    if (!empty($_FILES['about_image']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["about_image"]["name"]);
        $targetFile = $targetDir . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($imageFileType, $allowed)) {
            $error = "อนุญาตเฉพาะไฟล์ jpg, jpeg, png, webp เท่านั้น";
        } else {
            if (move_uploaded_file($_FILES["about_image"]["tmp_name"], $targetFile)) {
                $image_path = $targetFile;
            } else {
                $error = "อัปโหลดรูปไม่สำเร็จ";
            }
        }
    }

    if ($section_tag === "" || $title === "") {
        $error = "กรุณากรอกข้อมูลหัวข้อให้ครบ";
    }

    if ($error === "") {
        $stmt = $conn->prepare("
            UPDATE about_content
            SET section_tag = ?, title = ?, lead_text = ?, paragraph1 = ?, paragraph2 = ?, paragraph3 = ?, image_path = ?, image_badge_title = ?, image_badge_text = ?
            WHERE id = 1
        ");
        $stmt->bind_param(
            "sssssssss",
            $section_tag,
            $title,
            $lead_text,
            $paragraph1,
            $paragraph2,
            $paragraph3,
            $image_path,
            $image_badge_title,
            $image_badge_text
        );

        if ($stmt->execute()) {
            $message = "บันทึกข้อมูลเรียบร้อยแล้ว";
            $result = $conn->query("SELECT * FROM about_content WHERE id = 1 LIMIT 1");
            $about = $result->fetch_assoc();
        } else {
            $error = "เกิดข้อผิดพลาด: " . $conn->error;
        }

        $stmt->close();
    }
}

$pageTitle = "แก้ไขข้อมูลหน้าประวัติ";
$activeMenu = "about";
include 'admin_layout_top.php';
?>

<style>
.page-wrap{
    max-width:1100px;
}
.topbar-local{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:24px;
    gap:12px;
    flex-wrap:wrap;
}
.topbar-local h1{
    font-size:32px;
    color:#09422A;
    margin:0;
}
.top-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.btn-local{
    display:inline-block;
    text-decoration:none;
    border:none;
    cursor:pointer;
    padding:12px 18px;
    border-radius:14px;
    font-size:15px;
    font-weight:700;
    transition:.25s ease;
}
.btn-main-local{
    background:linear-gradient(135deg, #09422A, #176046);
    color:#fff;
}
.btn-main-local:hover{transform:translateY(-2px)}
.btn-light-local{
    background:#fff;
    color:#09422A;
    border:1px solid #dfe7e2;
}
.card-local{
    background:#fff;
    border-radius:24px;
    box-shadow:0 12px 30px rgba(0,0,0,.08);
    padding:28px;
}
.alert-local{
    padding:14px 16px;
    border-radius:14px;
    margin-bottom:18px;
    font-weight:600;
}
.success-local{
    background:#eaf7ef;
    color:#176046;
    border:1px solid #cde8d7;
}
.error-local{
    background:#fff0f0;
    color:#b42318;
    border:1px solid #f3c7c7;
}
.grid-local{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}
.form-group-local{
    margin-bottom:18px;
}
.form-group-local.full{
    grid-column:1 / -1;
}
.form-group-local label{
    display:block;
    margin-bottom:8px;
    font-weight:700;
    color:#09422A;
}
.form-group-local input[type="text"],
.form-group-local textarea,
.form-group-local input[type="file"]{
    width:100%;
    border:1px solid #dfe7e2;
    border-radius:14px;
    padding:14px 15px;
    font-size:15px;
    background:#fff;
    outline:none;
}
.form-group-local textarea{
    min-height:130px;
    resize:vertical;
}
.form-group-local input[type="text"]:focus,
.form-group-local textarea:focus{
    border-color:#176046;
    box-shadow:0 0 0 4px rgba(23,96,70,.08);
}
.preview-box{
    margin-top:10px;
    padding:14px;
    border:1px dashed #cfd8d3;
    border-radius:16px;
    background:#fafcfb;
}
.preview-box img{
    width:100%;
    max-width:320px;
    border-radius:14px;
    display:block;
    box-shadow:0 10px 24px rgba(0,0,0,.10);
}
.note{
    color:#666;
    font-size:14px;
    margin-top:6px;
}
.submit-wrap{
    margin-top:10px;
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}
@media(max-width:768px){
    .grid-local{grid-template-columns:1fr}
    .topbar-local h1{font-size:24px}
}
</style>

<div class="page-wrap">
    <div class="topbar-local">
        <h1>แก้ไขข้อมูลหน้าประวัติ</h1>
        <div class="top-actions">
            <a href="about_us.php" class="btn-local btn-light-local">ดูหน้าจริง</a>
        </div>
    </div>

    <div class="card-local">
        <?php if ($message): ?>
            <div class="alert-local success-local"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-local error-local"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="grid-local">
                <div class="form-group-local">
                    <label>ข้อความหัวข้อย่อย</label>
                    <input type="text" name="section_tag" value="<?php echo htmlspecialchars($about['section_tag'] ?? ''); ?>">
                </div>

                <div class="form-group-local">
                    <label>หัวข้อหลัก</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($about['title'] ?? ''); ?>">
                </div>

                <div class="form-group-local full">
                    <label>ข้อความเกริ่นนำ</label>
                    <textarea name="lead_text"><?php echo htmlspecialchars($about['lead_text'] ?? ''); ?></textarea>
                </div>

                <div class="form-group-local full">
                    <label>ย่อหน้า 1</label>
                    <textarea name="paragraph1"><?php echo htmlspecialchars($about['paragraph1'] ?? ''); ?></textarea>
                </div>

                <div class="form-group-local full">
                    <label>ย่อหน้า 2</label>
                    <textarea name="paragraph2"><?php echo htmlspecialchars($about['paragraph2'] ?? ''); ?></textarea>
                </div>

                <div class="form-group-local full">
                    <label>ย่อหน้า 3</label>
                    <textarea name="paragraph3"><?php echo htmlspecialchars($about['paragraph3'] ?? ''); ?></textarea>
                </div>

                <div class="form-group-local">
                    <label>หัวข้อในกล่องบนรูป</label>
                    <input type="text" name="image_badge_title" value="<?php echo htmlspecialchars($about['image_badge_title'] ?? ''); ?>">
                </div>

                <div class="form-group-local">
                    <label>ข้อความในกล่องบนรูป</label>
                    <input type="text" name="image_badge_text" value="<?php echo htmlspecialchars($about['image_badge_text'] ?? ''); ?>">
                </div>

                <div class="form-group-local full">
                    <label>อัปโหลดรูปใหม่</label>
                    <input type="file" name="about_image" accept=".jpg,.jpeg,.png,.webp">
                    <div class="note">รองรับไฟล์ jpg, jpeg, png, webp</div>

                    <?php if (!empty($about['image_path'])): ?>
                        <div class="preview-box">
                            <div style="margin-bottom:10px;font-weight:700;color:#09422A;">รูปปัจจุบัน</div>
                            <img src="<?php echo htmlspecialchars($about['image_path']) . '?v=' . time(); ?>" alt="preview">
                            <div class="note" style="margin-top:10px;">
                                path: <?php echo htmlspecialchars($about['image_path']); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="submit-wrap">
                <button type="submit" class="btn-local btn-main-local">บันทึกข้อมูล</button>
                <a href="about_us.php" class="btn-local btn-light-local">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>

<?php
$conn->close();
include 'admin_layout_bottom.php';
?>