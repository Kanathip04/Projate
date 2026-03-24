<?php
session_start();

/* ถ้าเว็บคุณมี login admin อยู่แล้ว ใช้อันนี้ได้ */
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

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

    /* อัปโหลดรูปใหม่ */
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
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>แก้ไขข้อมูลประวัติ</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
    --main:#09422A;
    --main2:#176046;
    --bg:#f4f6f8;
    --white:#fff;
    --text:#222;
    --muted:#666;
    --line:#dfe7e2;
    --shadow:0 12px 30px rgba(0,0,0,.08);
}
body{
    font-family:'Segoe UI',Tahoma,sans-serif;
    background:var(--bg);
    color:var(--text);
    padding:30px;
}
.wrapper{
    max-width:1100px;
    margin:0 auto;
}
.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:24px;
    gap:12px;
    flex-wrap:wrap;
}
.topbar h1{
    font-size:32px;
    color:var(--main);
}
.top-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.btn{
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
.btn-main{
    background:linear-gradient(135deg, var(--main), var(--main2));
    color:#fff;
}
.btn-main:hover{transform:translateY(-2px)}
.btn-light{
    background:#fff;
    color:var(--main);
    border:1px solid var(--line);
}
.card{
    background:var(--white);
    border-radius:24px;
    box-shadow:var(--shadow);
    padding:28px;
}
.alert{
    padding:14px 16px;
    border-radius:14px;
    margin-bottom:18px;
    font-weight:600;
}
.success{
    background:#eaf7ef;
    color:#176046;
    border:1px solid #cde8d7;
}
.error{
    background:#fff0f0;
    color:#b42318;
    border:1px solid #f3c7c7;
}
.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}
.form-group{
    margin-bottom:18px;
}
.form-group.full{
    grid-column:1 / -1;
}
label{
    display:block;
    margin-bottom:8px;
    font-weight:700;
    color:var(--main);
}
input[type="text"],
textarea,
input[type="file"]{
    width:100%;
    border:1px solid var(--line);
    border-radius:14px;
    padding:14px 15px;
    font-size:15px;
    background:#fff;
    outline:none;
}
textarea{
    min-height:130px;
    resize:vertical;
}
input[type="text"]:focus,
textarea:focus{
    border-color:var(--main2);
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
    color:var(--muted);
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
    body{padding:16px}
    .grid{grid-template-columns:1fr}
    .topbar h1{font-size:24px}
}
</style>
</head>
<body>

<div class="wrapper">
    <div class="topbar">
        <h1>แก้ไขข้อมูลหน้าประวัติ</h1>
        <div class="top-actions">
            <a href="about_us.php" class="btn btn-light">ดูหน้าจริง</a>
            <a href="admin_dashboard.php" class="btn btn-light">กลับแดชบอร์ด</a>
        </div>
    </div>

    <div class="card">
        <?php if ($message): ?>
            <div class="alert success"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="grid">
                <div class="form-group">
                    <label>ข้อความหัวข้อย่อย</label>
                    <input type="text" name="section_tag" value="<?php echo htmlspecialchars($about['section_tag'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>หัวข้อหลัก</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($about['title'] ?? ''); ?>">
                </div>

                <div class="form-group full">
                    <label>ข้อความเกริ่นนำ</label>
                    <textarea name="lead_text"><?php echo htmlspecialchars($about['lead_text'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full">
                    <label>ย่อหน้า 1</label>
                    <textarea name="paragraph1"><?php echo htmlspecialchars($about['paragraph1'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full">
                    <label>ย่อหน้า 2</label>
                    <textarea name="paragraph2"><?php echo htmlspecialchars($about['paragraph2'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full">
                    <label>ย่อหน้า 3</label>
                    <textarea name="paragraph3"><?php echo htmlspecialchars($about['paragraph3'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>หัวข้อในกล่องบนรูป</label>
                    <input type="text" name="image_badge_title" value="<?php echo htmlspecialchars($about['image_badge_title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>ข้อความในกล่องบนรูป</label>
                    <input type="text" name="image_badge_text" value="<?php echo htmlspecialchars($about['image_badge_text'] ?? ''); ?>">
                </div>

                <div class="form-group full">
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
                <button type="submit" class="btn btn-main">บันทึกข้อมูล</button>
                <a href="about_us.php" class="btn btn-light">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>

</body>
</html>