<?php
include 'config.php';

// handle inline delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $did = (int)$_POST['delete_id'];
    // ดึงชื่อไฟล์ก่อนลบ
    $r = $conn->query("SELECT image FROM news WHERE id=$did LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) {
        if (!empty($row['image'])) {
            $imgFile = __DIR__ . '/uploads/' . $row['image'];
            if (file_exists($imgFile)) @unlink($imgFile);
        }
    }
    $conn->query("DELETE FROM news WHERE id=$did");
    header("Location: manage_news.php?deleted=1"); exit;
}

$msg = '';
if (isset($_GET['deleted'])) $msg = 'ลบข่าวเรียบร้อยแล้ว';

$result = $conn->query("SELECT * FROM news ORDER BY id DESC");
$total  = $result ? $result->num_rows : 0;

$pageTitle = "จัดการข่าวสาร";
$activeMenu = "news_manage";
include 'admin_layout_top.php';
?>
<style>
:root{
  --green:#15803d;--green2:#166534;--green-light:#f0fdf4;
  --ink:#0f172a;--muted:#64748b;--border:#e2e8f0;--card:#fff;--gold:#c9a96e;
}
.mn-wrap{max-width:1000px;margin:0 auto;padding-bottom:60px;}

/* BANNER */
.mn-banner{
  border-radius:20px;overflow:hidden;
  background:linear-gradient(135deg,#0f2a1a 0%,#14532d 50%,#166534 100%);
  padding:26px 32px;margin-bottom:26px;
  display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
  position:relative;
}
.mn-banner::before{content:'';position:absolute;width:300px;height:300px;border-radius:50%;
  background:rgba(255,255,255,.04);top:-80px;right:-40px;pointer-events:none;}
.mn-banner-left{position:relative;z-index:1;}
.mn-banner-badge{
  display:inline-flex;align-items:center;gap:5px;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
  color:rgba(255,255,255,.9);font-size:.7rem;font-weight:700;
  padding:4px 11px;border-radius:99px;margin-bottom:10px;letter-spacing:.05em;
}
.mn-banner h1{
  font-family:'Kanit',sans-serif;font-size:1.8rem;font-weight:900;
  color:#fff;margin:0 0 5px;line-height:1.2;
}
.mn-banner h1 em{font-style:normal;color:var(--gold);}
.mn-banner p{font-size:.82rem;color:rgba(255,255,255,.65);margin:0;}
.mn-banner-links{display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:1;}
.mn-link{
  display:inline-flex;align-items:center;gap:5px;
  padding:7px 14px;border-radius:99px;font-size:.78rem;font-weight:700;
  border:1.5px solid rgba(255,255,255,.25);background:rgba(255,255,255,.1);
  color:#fff;text-decoration:none;transition:all .2s;
}
.mn-link:hover{background:rgba(255,255,255,.2);transform:translateY(-1px);}
.mn-link-add{background:var(--gold);border-color:var(--gold);color:#0f2a1a;}
.mn-link-add:hover{background:#d4a96e;border-color:#d4a96e;}

/* ALERT */
.mn-alert{
  display:flex;align-items:center;gap:8px;
  padding:12px 16px;border-radius:12px;margin-bottom:18px;
  font-size:.84rem;font-weight:600;
  background:#f0fdf4;border:1.5px solid #86efac;color:#166534;
}

/* STATS ROW */
.mn-stats{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
.mn-stat{
  flex:1;min-width:140px;background:var(--card);border-radius:14px;
  border:1px solid var(--border);padding:14px 18px;
  box-shadow:0 2px 8px rgba(15,42,26,.05);
  display:flex;align-items:center;gap:12px;
}
.mn-stat-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0;}
.mn-stat-num{font-family:'Kanit',sans-serif;font-size:1.5rem;font-weight:900;line-height:1;}
.mn-stat-lbl{font-size:.7rem;color:var(--muted);font-weight:600;}

/* CARD */
.mn-card{
  background:var(--card);border-radius:18px;
  box-shadow:0 2px 12px rgba(15,42,26,.07);border:1px solid var(--border);
  overflow:hidden;
}
.mn-card-head{
  padding:16px 22px;border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:12px;
  background:linear-gradient(180deg,#fafcfa 0%,#fff 100%);
}
.mn-card-title{font-size:.88rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:7px;}
.mn-card-title::before{content:'';display:inline-block;width:3px;height:14px;background:var(--green);border-radius:2px;}
.mn-count{background:var(--green-light);color:var(--green);font-size:.7rem;font-weight:700;padding:3px 10px;border-radius:20px;}

/* TABLE */
.mn-table-wrap{overflow-x:auto;}
.mn-table{width:100%;border-collapse:collapse;min-width:620px;}
.mn-table thead th{
  padding:10px 16px;font-size:.67rem;letter-spacing:.08em;text-transform:uppercase;
  color:var(--muted);border-bottom:2px solid var(--border);text-align:left;
  font-weight:700;background:#fafcfa;
}
.mn-table tbody td{
  padding:14px 16px;font-size:.84rem;color:var(--ink);
  border-bottom:1px solid var(--border);vertical-align:middle;
}
.mn-table tbody tr:last-child td{border-bottom:none;}
.mn-table tbody tr:hover{background:#f8fdf9;}

/* THUMB */
.mn-thumb{
  width:80px;height:54px;border-radius:10px;overflow:hidden;
  background:#f1f5f9;border:1px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:.7rem;color:#94a3b8;flex-shrink:0;
}
.mn-thumb img{width:100%;height:100%;object-fit:cover;display:block;}

/* TITLE */
.mn-news-title{font-weight:700;line-height:1.5;word-break:break-word;}
.mn-preview{
  font-size:.74rem;color:var(--muted);margin-top:3px;
  display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;
}

/* DATE */
.mn-date{
  display:inline-flex;align-items:center;gap:5px;
  background:#f1f5f9;color:#475569;
  padding:5px 10px;border-radius:8px;font-size:.74rem;font-weight:600;white-space:nowrap;
}

/* BTNS */
.mn-btn-del{
  display:inline-flex;align-items:center;gap:5px;
  padding:7px 14px;border-radius:9px;font-family:'Sarabun',sans-serif;
  font-size:.78rem;font-weight:700;cursor:pointer;border:none;
  background:#fef2f2;color:#dc2626;border:1.5px solid #fca5a5;
  transition:all .2s;
}
.mn-btn-del:hover{background:#dc2626;color:#fff;transform:translateY(-1px);}

/* EMPTY */
.mn-empty{padding:64px 24px;text-align:center;}
.mn-empty-icon{font-size:2.8rem;opacity:.3;margin-bottom:12px;}
.mn-empty-text{font-size:.88rem;color:var(--muted);line-height:1.7;}
.mn-empty-link{
  display:inline-flex;align-items:center;gap:6px;margin-top:16px;
  padding:10px 22px;border-radius:99px;font-size:.84rem;font-weight:700;
  background:var(--green);color:#fff;text-decoration:none;
  transition:all .2s;
}
.mn-empty-link:hover{background:var(--green2);}

@media(max-width:600px){
  .mn-banner{padding:18px;}
  .mn-banner h1{font-size:1.5rem;}
  .mn-stats{flex-direction:column;}
}
</style>

<div class="mn-wrap">

  <!-- BANNER -->
  <div class="mn-banner">
    <div class="mn-banner-left">
      <div class="mn-banner-badge">📋 News Management</div>
      <h1>จัดการ<em>ข่าวสาร</em></h1>
      <p>ดู แก้ไข หรือลบข่าวสารที่เผยแพร่บนเว็บไซต์</p>
    </div>
    <div class="mn-banner-links">
      <a href="admin_add_news.php" class="mn-link mn-link-add">✏️ เพิ่มข่าวใหม่</a>
      <a href="news.php" target="_blank" class="mn-link">🌐 ดูหน้าข่าว</a>
    </div>
  </div>

  <?php if($msg): ?>
  <div class="mn-alert">✅ <?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <!-- STATS -->
  <div class="mn-stats">
    <div class="mn-stat">
      <div class="mn-stat-ico" style="background:#f0fdf4;color:var(--green);">📰</div>
      <div>
        <div class="mn-stat-num" style="color:var(--ink);"><?= $total ?></div>
        <div class="mn-stat-lbl">ข่าวทั้งหมด</div>
      </div>
    </div>
  </div>

  <!-- TABLE CARD -->
  <div class="mn-card">
    <div class="mn-card-head">
      <div class="mn-card-title">รายการข่าวสารทั้งหมด</div>
      <span class="mn-count"><?= $total ?> รายการ</span>
    </div>

    <div class="mn-table-wrap">
      <table class="mn-table">
        <thead>
          <tr>
            <th style="width:90px;">รูปภาพ</th>
            <th>หัวข้อข่าว</th>
            <th style="width:160px;">วันที่โพสต์</th>
            <th style="width:100px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="mn-thumb">
                  <?php if(!empty($row['image'])): ?>
                    <img src="uploads/<?= htmlspecialchars($row['image']) ?>" alt="">
                  <?php else: ?>
                    🖼️
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <div class="mn-news-title"><?= htmlspecialchars($row['title']) ?></div>
                <?php if(!empty($row['content'])): ?>
                <div class="mn-preview"><?= htmlspecialchars(mb_substr(strip_tags($row['content']),0,80)) ?>...</div>
                <?php endif; ?>
              </td>
              <td>
                <span class="mn-date">
                  🕐 <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?>
                </span>
              </td>
              <td>
                <form method="POST" onsubmit="return confirm('ยืนยันลบข่าว: <?= htmlspecialchars(addslashes($row['title'])) ?>?')">
                  <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                  <button type="submit" class="mn-btn-del">🗑 ลบ</button>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="4">
                <div class="mn-empty">
                  <div class="mn-empty-icon">📭</div>
                  <div class="mn-empty-text">ยังไม่มีข่าวสารในระบบ<br>เริ่มสร้างข่าวแรกได้เลย</div>
                  <a href="admin_add_news.php" class="mn-empty-link">✏️ เพิ่มข่าวใหม่</a>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php
$conn->close();
include 'admin_layout_bottom.php';
?>
