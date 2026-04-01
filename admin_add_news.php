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
:root{
  --green:#15803d;--green2:#166534;--green-light:#f0fdf4;
  --ink:#0f172a;--muted:#64748b;--border:#e2e8f0;
  --card:#fff;--bg:#f8fafc;--gold:#c9a96e;
}
.an-wrap{max-width:860px;margin:0 auto;padding-bottom:60px;}

/* ── BANNER ── */
.an-banner{
  border-radius:20px;overflow:hidden;
  background:linear-gradient(135deg,#0f2a1a 0%,#14532d 50%,#166534 100%);
  padding:28px 32px;margin-bottom:28px;
  display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
  position:relative;
}
.an-banner::before{content:'';position:absolute;width:340px;height:340px;border-radius:50%;
  background:rgba(255,255,255,.04);top:-120px;right:-60px;pointer-events:none;}
.an-banner::after{content:'';position:absolute;width:200px;height:200px;border-radius:50%;
  background:rgba(201,169,110,.06);bottom:-80px;left:60px;pointer-events:none;}
.an-banner-left{position:relative;z-index:1;}
.an-banner-badge{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
  color:rgba(255,255,255,.9);font-size:.72rem;font-weight:700;
  padding:4px 12px;border-radius:99px;margin-bottom:12px;letter-spacing:.05em;
}
.an-banner h1{
  font-family:'Kanit',sans-serif;font-size:1.9rem;font-weight:900;
  color:#fff;margin:0 0 6px;line-height:1.2;
}
.an-banner h1 em{font-style:normal;color:var(--gold);}
.an-banner p{font-size:.83rem;color:rgba(255,255,255,.65);margin:0;max-width:420px;line-height:1.6;}
.an-banner-links{display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;}
.an-banner-link{
  display:inline-flex;align-items:center;gap:5px;
  padding:7px 14px;border-radius:99px;font-size:.78rem;font-weight:700;
  border:1.5px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);
  color:#fff;text-decoration:none;transition:all .2s;
}
.an-banner-link:hover{background:rgba(255,255,255,.2);transform:translateY(-1px);}

/* ── ALERT ── */
.an-alert{
  display:flex;align-items:flex-start;gap:10px;
  padding:14px 18px;border-radius:14px;font-size:.86rem;font-weight:600;
  margin-bottom:22px;line-height:1.6;
}
.an-alert-ok{background:#f0fdf4;border:1.5px solid #86efac;color:#166534;}
.an-alert-err{background:#fef2f2;border:1.5px solid #fca5a5;color:#dc2626;}

/* ── FORM CARD ── */
.an-card{
  background:var(--card);border-radius:20px;
  box-shadow:0 2px 16px rgba(15,42,26,.07);border:1px solid var(--border);
  overflow:hidden;
}
.an-card-head{
  padding:20px 26px;border-bottom:1px solid var(--border);
  background:linear-gradient(180deg,#fafcfa 0%,#fff 100%);
  display:flex;align-items:center;gap:14px;
}
.an-avatar{
  width:52px;height:52px;border-radius:14px;flex-shrink:0;
  background:linear-gradient(135deg,#dcfce7,#bbf7d0);
  display:flex;align-items:center;justify-content:center;font-size:1.4rem;
}
.an-card-title{font-size:.95rem;font-weight:800;color:var(--ink);margin-bottom:3px;}
.an-card-sub{font-size:.78rem;color:var(--muted);}

.an-card-body{padding:26px;}
.an-grid{display:grid;gap:22px;}

/* ── FIELD ── */
.an-field{display:flex;flex-direction:column;gap:6px;}
.an-label{
  font-size:.82rem;font-weight:800;color:var(--ink);
  display:flex;align-items:center;gap:6px;
}
.an-label span{
  font-size:.65rem;font-weight:700;padding:2px 7px;border-radius:4px;
  background:#fef9c3;color:#92400e;
}
.an-hint{font-size:.74rem;color:var(--muted);}

.an-input,.an-textarea{
  width:100%;padding:11px 14px;
  border:1.5px solid var(--border);border-radius:12px;
  font-family:'Sarabun',sans-serif;font-size:.88rem;color:var(--ink);
  background:#fff;outline:none;transition:all .2s;
}
.an-input:focus,.an-textarea:focus{
  border-color:var(--green);
  box-shadow:0 0 0 3px rgba(21,128,61,.1);
}
.an-textarea{min-height:200px;resize:vertical;line-height:1.75;}

/* ── UPLOAD ZONE ── */
.an-drop{
  border:2px dashed var(--border);border-radius:14px;
  background:#fafcfa;padding:24px 20px;text-align:center;
  cursor:pointer;transition:all .2s;position:relative;
}
.an-drop:hover,.an-drop.over{border-color:var(--green);background:var(--green-light);}
.an-drop-icon{font-size:2rem;margin-bottom:8px;opacity:.5;}
.an-drop-text{font-size:.82rem;font-weight:700;color:var(--muted);}
.an-drop-sub{font-size:.73rem;color:#94a3b8;margin-top:3px;}
.an-file-input{
  position:absolute;inset:0;width:100%;height:100%;
  opacity:0;cursor:pointer;
}

/* ── PREVIEW ── */
.an-preview{
  display:none;margin-top:14px;
  border:1.5px solid var(--border);border-radius:14px;
  overflow:hidden;background:#fff;
}
.an-preview-bar{
  padding:10px 14px;background:#f8fafc;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.an-preview-label{font-size:.75rem;font-weight:700;color:var(--muted);}
.an-preview-rm{
  font-size:.72rem;font-weight:700;color:#dc2626;cursor:pointer;
  background:#fee2e2;border:none;border-radius:6px;padding:3px 8px;
}
.an-preview img{display:block;width:100%;max-height:320px;object-fit:cover;}

/* ── ACTIONS ── */
.an-actions{
  display:flex;align-items:center;gap:12px;flex-wrap:wrap;
  padding:20px 26px;border-top:1px solid var(--border);
  background:#fafcfa;
}
.an-btn-submit{
  display:inline-flex;align-items:center;gap:7px;
  padding:11px 28px;border:none;border-radius:12px;
  font-family:'Sarabun',sans-serif;font-size:.88rem;font-weight:800;
  cursor:pointer;transition:all .2s;
  background:linear-gradient(135deg,var(--green),var(--green2));
  color:#fff;box-shadow:0 4px 14px rgba(21,128,61,.25);
}
.an-btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 18px rgba(21,128,61,.32);}
.an-btn-reset{
  display:inline-flex;align-items:center;gap:6px;
  padding:11px 18px;border:1.5px solid var(--border);border-radius:12px;
  font-family:'Sarabun',sans-serif;font-size:.85rem;font-weight:700;
  cursor:pointer;background:#fff;color:var(--muted);transition:all .2s;
}
.an-btn-reset:hover{border-color:#94a3b8;color:var(--ink);}
.an-char-count{font-size:.73rem;color:var(--muted);margin-left:auto;}

/* ── CHAR BAR ── */
.an-char-bar{height:3px;border-radius:99px;background:#e2e8f0;margin-top:5px;overflow:hidden;}
.an-char-fill{height:100%;border-radius:99px;background:var(--green);width:0%;transition:width .2s;}

@media(max-width:600px){
  .an-banner{padding:20px;}
  .an-banner h1{font-size:1.5rem;}
  .an-card-body,.an-actions{padding:18px;}
}
</style>

<div class="an-wrap">

  <!-- BANNER -->
  <div class="an-banner">
    <div class="an-banner-left">
      <div class="an-banner-badge">📰 News Admin Panel</div>
      <h1>โพสต์<em>ข่าวสาร</em></h1>
      <p>สร้างข่าวประชาสัมพันธ์ ข่าวกิจกรรม หรือประกาศต่าง ๆ เพื่อเผยแพร่บนเว็บไซต์</p>
    </div>
    <div class="an-banner-links">
      <a href="manage_news.php" class="an-banner-link">✏️ จัดการข่าว</a>
      <a href="news.php" target="_blank" class="an-banner-link">🌐 ดูหน้าข่าว</a>
    </div>
  </div>

  <?php if($message !== ""): ?>
  <div class="an-alert <?= $messageType==='success' ? 'an-alert-ok' : 'an-alert-err' ?>">
    <?= $messageType==='success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
    <?php if($messageType==='success'): ?>
      &nbsp;·&nbsp;<a href="manage_news.php" style="color:var(--green);font-weight:800;">ดูรายการข่าวทั้งหมด →</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- FORM CARD -->
  <div class="an-card">
    <div class="an-card-head">
      <div class="an-avatar">📝</div>
      <div>
        <div class="an-card-title">สร้างข่าวสารใหม่</div>
        <div class="an-card-sub">กรอกข้อมูลด้านล่างแล้วกดโพสต์ข่าว — จะแสดงผลทันทีบนหน้าเว็บไซต์</div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="newsForm">
      <div class="an-card-body">
        <div class="an-grid">

          <!-- หัวข้อ -->
          <div class="an-field">
            <label class="an-label" for="title">หัวข้อข่าว <span>จำเป็น</span></label>
            <div class="an-hint">ตั้งชื่อข่าวให้ชัดเจน กระชับ และเข้าใจง่าย</div>
            <input type="text" id="title" name="title" class="an-input"
              placeholder="เช่น ประกาศกำหนดการเข้าร่วมกิจกรรมประจำปี"
              value="<?= isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '' ?>"
              maxlength="200" oninput="updateTitle(this)" required>
            <div class="an-char-bar"><div class="an-char-fill" id="titleFill"></div></div>
          </div>

          <!-- เนื้อหา -->
          <div class="an-field">
            <label class="an-label" for="content">เนื้อหาข่าว <span>จำเป็น</span></label>
            <div class="an-hint">อธิบายรายละเอียดข่าวสาร สามารถพิมพ์ได้หลายบรรทัด</div>
            <textarea id="content" name="content" class="an-textarea"
              placeholder="พิมพ์รายละเอียดข่าวสารที่ต้องการเผยแพร่..."
              oninput="updateContent(this)" required><?= isset($_POST['content']) ? htmlspecialchars($_POST['content']) : '' ?></textarea>
            <div style="display:flex;justify-content:flex-end;">
              <span class="an-char-count" id="contentCount">0 ตัวอักษร</span>
            </div>
          </div>

          <!-- รูปภาพ -->
          <div class="an-field">
            <label class="an-label" for="image">รูปภาพประกอบ</label>
            <div class="an-hint">รองรับ JPG, PNG, WEBP ขนาดไม่เกิน 5MB (ไม่บังคับ)</div>
            <div class="an-drop" id="dropZone">
              <input type="file" name="image" id="image" accept="image/*"
                class="an-file-input" onchange="previewImg(event)">
              <div class="an-drop-icon">🖼️</div>
              <div class="an-drop-text">คลิกหรือลากไฟล์มาวางที่นี่</div>
              <div class="an-drop-sub">JPG · PNG · WEBP · สูงสุด 5MB</div>
            </div>
            <div class="an-preview" id="previewBox">
              <div class="an-preview-bar">
                <span class="an-preview-label">🖼️ ตัวอย่างรูปภาพ</span>
                <button type="button" class="an-preview-rm" onclick="clearImg()">✕ ลบรูป</button>
              </div>
              <img id="previewImg" alt="preview">
            </div>
          </div>

        </div>
      </div>

      <div class="an-actions">
        <button type="submit" class="an-btn-submit">
          📢 โพสต์ข่าวสาร
        </button>
        <button type="reset" class="an-btn-reset" onclick="clearImg()">
          ↺ ล้างข้อมูล
        </button>
        <span class="an-char-count" id="formHint" style="color:#94a3b8;font-size:.72rem;"></span>
      </div>
    </form>
  </div>

</div>

<script>
function previewImg(e){
  const f=e.target.files[0];
  if(!f)return;
  const r=new FileReader();
  r.onload=function(ev){
    document.getElementById('previewImg').src=ev.target.result;
    document.getElementById('previewBox').style.display='block';
    document.getElementById('dropZone').querySelector('.an-drop-text').textContent=f.name;
  };
  r.readAsDataURL(f);
}
function clearImg(){
  document.getElementById('image').value='';
  document.getElementById('previewBox').style.display='none';
  document.getElementById('previewImg').src='';
  document.getElementById('dropZone').querySelector('.an-drop-text').textContent='คลิกหรือลากไฟล์มาวางที่นี่';
}
function updateTitle(el){
  const pct=Math.min(100,(el.value.length/200)*100);
  document.getElementById('titleFill').style.width=pct+'%';
  document.getElementById('titleFill').style.background=pct>80?'#dc2626':'#15803d';
}
function updateContent(el){
  document.getElementById('contentCount').textContent=el.value.length+' ตัวอักษร';
}
// Drag-over highlight
const dz=document.getElementById('dropZone');
dz.addEventListener('dragover',e=>{e.preventDefault();dz.classList.add('over');});
dz.addEventListener('dragleave',()=>dz.classList.remove('over'));
dz.addEventListener('drop',e=>{e.preventDefault();dz.classList.remove('over');});
// Init counts
document.addEventListener('DOMContentLoaded',()=>{
  const t=document.getElementById('title');
  const c=document.getElementById('content');
  if(t.value)updateTitle(t);
  if(c.value)updateContent(c);
});
</script>

<?php
$conn->close();
include 'admin_layout_bottom.php';
?>
