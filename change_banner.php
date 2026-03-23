<?php
$conn = new mysqli("localhost", "root", "", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$banner_msg = "";
$currentBanner = null;

$resB = $conn->query("SELECT * FROM site_banners ORDER BY id DESC LIMIT 1");
if ($resB && $resB->num_rows > 0) $currentBanner = $resB->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

  // ====== 1) ถ้ามีรูปจากการครอป (base64) ให้ใช้ทางนี้ก่อน ======
  if (!empty($_POST["cropped_base64"])) {

    $data = $_POST["cropped_base64"];

    if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,/', $data, $m)) {
      $banner_msg = "ข้อมูลรูปภาพไม่ถูกต้อง";
    } else {
      $type = strtolower($m[1]);
      $data = substr($data, strpos($data, ',') + 1);
      $bin  = base64_decode($data);

      if ($bin === false) {
        $banner_msg = "แปลงรูปภาพไม่สำเร็จ";
      } elseif (strlen($bin) > 3 * 1024 * 1024) {
        // กันรูปครอปใหญ่เกิน 3MB
        $banner_msg = "ไฟล์ใหญ่เกิน 3MB (ลองซูมออก/ครอปเล็กลง)";
      } else {
        $ext = ($type === "jpeg") ? "jpg" : $type;

        $uploadDir = __DIR__ . "/uploads/banners/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $newName = "banner_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $targetPath = $uploadDir . $newName;

        if (file_put_contents($targetPath, $bin) === false) {
          $banner_msg = "บันทึกไฟล์ไม่สำเร็จ (เช็คสิทธิ์โฟลเดอร์ uploads/banners)";
        } else {
          $dbPath = "uploads/banners/" . $newName;
          $oldBanner = $currentBanner;

          $stmt = $conn->prepare("INSERT INTO site_banners (image_path) VALUES (?)");
          $stmt->bind_param("s", $dbPath);
          $stmt->execute();
          $stmt->close();

          if ($oldBanner && !empty($oldBanner["image_path"])) {
            $oldFile = __DIR__ . "/" . $oldBanner["image_path"];
            if (file_exists($oldFile)) @unlink($oldFile);
          }

          $resB2 = $conn->query("SELECT * FROM site_banners ORDER BY id DESC LIMIT 1");
          if ($resB2 && $resB2->num_rows > 0) $currentBanner = $resB2->fetch_assoc();

          $banner_msg = "บันทึกรูปใหม่เรียบร้อย";
        }
      }
    }

  } else {
    // ====== 2) สำรอง: ถ้าไม่ได้ครอป ก็ใช้ upload ปกติ (โค้ดเดิมของคุณ) ======
    if (!isset($_FILES["banner"]) || $_FILES["banner"]["error"] !== UPLOAD_ERR_OK) {
      $banner_msg = "อัปโหลดไม่สำเร็จ";
    } else {
      $file = $_FILES["banner"];
      $allowedExt = ["jpg","jpeg","png","webp"];
      $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

      if (!in_array($ext, $allowedExt)) {
        $banner_msg = "ไฟล์ต้องเป็น JPG/PNG/WEBP เท่านั้น";
      } elseif ($file["size"] > 3 * 1024 * 1024) {
        $banner_msg = "ไฟล์ใหญ่เกิน 3MB";
      } elseif (@getimagesize($file["tmp_name"]) === false) {
        $banner_msg = "ไฟล์ไม่ใช่รูปภาพ";
      } else {
        $uploadDir = __DIR__ . "/uploads/banners/";
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $newName = "banner_" . date("Ymd_His") . "_" . bin2hex(random_bytes(4)) . "." . $ext;
        $targetPath = $uploadDir . $newName;

        if (!move_uploaded_file($file["tmp_name"], $targetPath)) {
          $banner_msg = "ย้ายไฟล์ไม่สำเร็จ (เช็คสิทธิ์โฟลเดอร์ uploads/banners)";
        } else {
          $dbPath = "uploads/banners/" . $newName;
          $oldBanner = $currentBanner;

          $stmt = $conn->prepare("INSERT INTO site_banners (image_path) VALUES (?)");
          $stmt->bind_param("s", $dbPath);
          $stmt->execute();
          $stmt->close();

          if ($oldBanner && !empty($oldBanner["image_path"])) {
            $oldFile = __DIR__ . "/" . $oldBanner["image_path"];
            if (file_exists($oldFile)) @unlink($oldFile);
          }

          $resB2 = $conn->query("SELECT * FROM site_banners ORDER BY id DESC LIMIT 1");
          if ($resB2 && $resB2->num_rows > 0) $currentBanner = $resB2->fetch_assoc();

          $banner_msg = "บันทึกรูปใหม่เรียบร้อย";
        }
      }
    }
  }
}

$pageTitle = "เปลี่ยนรูปหน้าเว็บ";
$activeMenu = "banner";
include "admin_layout_top.php";
?>

<!-- CropperJS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>

<style>
  .banner-grid{ display:grid; grid-template-columns: 1.2fr 1fr; gap:18px; }
  .banner-preview{
    border:1px dashed #ccc; border-radius:12px; height:260px;
    background:#fafafa; overflow:hidden;
    display:flex; align-items:center; justify-content:center;
  }
  .banner-preview img{ width:100%; height:100%; object-fit:cover; }
  .hint{ font-size:12px; color:#666; margin-top:8px; }
  .msg{ margin:12px 0; padding:10px 12px; border-radius:10px; background:#eef7ee; border:1px solid #cfe9cf; }
  .msg.err{ background:#fff0f0; border-color:#ffd0d0; }

  .btn{
    padding:10px 14px; border:none; border-radius:10px;
    background:var(--brand); color:#fff; font-weight:700; cursor:pointer;
  }
  .btn:hover{ background:var(--brand2); }

  /* ====== โซนครอป ====== */
  .crop-wrap{ margin-top:12px; }
  .crop-box{
    width:100%;
    height:260px;                 /* กรอบแบนเนอร์ */
    background:#f3f4f6;
    border:1px solid #e5e7eb;
    border-radius:12px;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .crop-actions{
    display:flex;
    gap:8px;
    margin-top:10px;
    flex-wrap:wrap;
  }
  .crop-actions button{
    padding:8px 12px;
    border:1px solid #d1d5db;
    background:#fff;
    border-radius:10px;
    cursor:pointer;
    font-weight:700;
  }
  .crop-actions button:hover{ background:#f9fafb; }

  @media(max-width: 1000px){ .banner-grid{ grid-template-columns:1fr; } }
</style>

<div class="content-card">
  <h1 class="page-title">🖼️ เปลี่ยนรูปหน้าเว็บ (แบนเนอร์)</h1>

  <?php if ($banner_msg !== ""): ?>
    <div class="msg <?php echo (str_contains($banner_msg,'ไม่') ? 'err' : ''); ?>">
      <?php echo htmlspecialchars($banner_msg); ?>
    </div>
  <?php endif; ?>

  <div class="banner-grid">
    <div>
      <div style="font-weight:700;margin-bottom:8px;">ตัวอย่างรูปปัจจุบัน</div>
      <div class="banner-preview">
        <?php if ($currentBanner && !empty($currentBanner["image_path"])): ?>
          <img src="<?php echo htmlspecialchars($currentBanner["image_path"]); ?>" alt="banner">
        <?php else: ?>
          <div style="color:#777;">ยังไม่มีรูป</div>
        <?php endif; ?>
      </div>
      <div class="hint">รองรับ JPG/PNG/WEBP ขนาดไม่เกิน 3MB</div>
    </div>

    <div>
      <div style="font-weight:700;margin-bottom:8px;">อัปโหลดรูปใหม่ (จัดรูป/ครอปได้)</div>

      <form method="POST" enctype="multipart/form-data" id="bannerForm">
        <input type="file" name="banner" id="bannerInput" accept=".jpg,.jpeg,.png,.webp" required>

        <div class="crop-wrap">
          <div class="crop-box">
            <img id="previewCrop" alt="preview" style="max-width:100%; display:none;">
          </div>

          <div class="crop-actions">
            <button type="button" id="zoomIn">ซูมเข้า</button>
            <button type="button" id="zoomOut">ซูมออก</button>
            <button type="button" id="resetCrop">รีเซ็ต</button>
          </div>

          <div class="hint">ลากรูปเพื่อจัดตำแหน่ง แล้วซูมได้ จากนั้นกด “บันทึก”</div>
        </div>

        <!-- base64 จากการครอป -->
        <input type="hidden" name="cropped_base64" id="croppedBase64">

        <div style="height:10px;"></div>
        <button class="btn" type="submit" id="saveBtn">บันทึก</button>
      </form>
    </div>
  </div>
</div>

<script>
let cropper = null;

const input = document.getElementById('bannerInput');
const img = document.getElementById('previewCrop');
const croppedBase64 = document.getElementById('croppedBase64');
const form = document.getElementById('bannerForm');

input.addEventListener('change', (e) => {
  const file = e.target.files && e.target.files[0];
  if (!file) return;

  const url = URL.createObjectURL(file);
  img.src = url;
  img.style.display = 'block';

  if (cropper) cropper.destroy();

  cropper = new Cropper(img, {
    viewMode: 1,
    dragMode: 'move',
    aspectRatio: 16 / 6,     // สัดส่วนแบนเนอร์ (แก้ได้)
    autoCropArea: 1,
    responsive: true,
    background: false
  });
});

document.getElementById('zoomIn').addEventListener('click', () => {
  if (cropper) cropper.zoom(0.1);
});
document.getElementById('zoomOut').addEventListener('click', () => {
  if (cropper) cropper.zoom(-0.1);
});
document.getElementById('resetCrop').addEventListener('click', () => {
  if (cropper) cropper.reset();
});

form.addEventListener('submit', (e) => {
  // ถ้ายังไม่เลือกไฟล์ ปล่อยให้ required ทำงาน
  if (!cropper) return;

  e.preventDefault();

  // ครอปเป็นภาพขนาดแนะนำสำหรับแบนเนอร์ (ลดโอกาสไฟล์ใหญ่)
  const canvas = cropper.getCroppedCanvas({
    width: 1600,
    height: 600
  });

  // ส่งเป็น jpg คุณภาพ 0.9
  croppedBase64.value = canvas.toDataURL('image/jpeg', 0.9);

  // ส่งฟอร์มจริง
  form.submit();
});
</script>

<?php
include "admin_layout_bottom.php";
$conn->close();
?>