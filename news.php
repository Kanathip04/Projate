<?php
require_once 'auth_guard.php';
include 'config.php';
$result = $conn->query("SELECT * FROM news ORDER BY id DESC");
$newsItems = [];
if ($result) while ($r = $result->fetch_assoc()) $newsItems[] = $r;
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ข่าวสาร &amp; กิจกรรม</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
:root{
  --ink:#1a1a2e;--gold:#c9a96e;--gold-lt:rgba(201,169,110,.14);--gold-bd:rgba(201,169,110,.3);
  --bg:#f5f1eb;--card:#fff;--muted:#7a7a8c;--border:#e8e4de;
  --navy:#1a1a2e;--navy2:#16213e;
  --shadow:0 8px 32px rgba(26,26,46,.09);--r:18px;
}
html{scroll-behavior:smooth;}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;}
a{text-decoration:none;color:inherit;}

/* ── HERO ── */
.page-hero{
  background:linear-gradient(135deg,#1a1a2e 0%,#16213e 55%,#0f3460 100%);
  padding:48px 18px 56px;text-align:center;position:relative;overflow:hidden;
}
.page-hero::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(ellipse at 50% 0%,rgba(201,169,110,.18) 0%,transparent 65%);
}
.topbar{display:flex;justify-content:flex-start;max-width:1100px;margin:0 auto 28px;position:relative;z-index:1;}
.back-btn{
  display:inline-flex;align-items:center;gap:8px;
  background:transparent;color:#fff;border:1.5px solid rgba(255,255,255,.3);
  padding:9px 20px;border-radius:999px;font-size:.88rem;font-weight:600;transition:.2s;
}
.back-btn:hover{background:var(--gold);border-color:var(--gold);color:var(--navy);}
.hero-badge{
  display:inline-block;background:var(--gold-lt);color:var(--gold);
  border:1px solid var(--gold-bd);font-size:.78rem;font-weight:700;
  padding:6px 16px;border-radius:999px;margin-bottom:14px;
  letter-spacing:.04em;position:relative;z-index:1;
}
.hero-title{font-family:'Kanit',sans-serif;font-size:clamp(2rem,5vw,3rem);font-weight:900;color:#fff;margin-bottom:10px;position:relative;z-index:1;}
.hero-title span{color:var(--gold);}
.hero-sub{color:rgba(255,255,255,.62);font-size:.95rem;max-width:620px;margin:0 auto;line-height:1.85;position:relative;z-index:1;}

/* ── WRAP ── */
.page-wrap{max-width:1100px;margin:0 auto;padding:36px 18px 64px;}

/* ── NEWS GRID ── */
.news-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(320px,1fr));
  gap:24px;
}

/* ── CARD ── */
.news-card{
  background:var(--card);border-radius:var(--r);
  box-shadow:var(--shadow);border:1px solid var(--border);
  overflow:hidden;cursor:pointer;
  transition:transform .25s,box-shadow .25s;
  display:flex;flex-direction:column;
}
.news-card:hover{transform:translateY(-5px);box-shadow:0 20px 48px rgba(26,26,46,.13);}
.news-card.dismissed{opacity:.55;filter:grayscale(.4);}
.news-card.dismissed .dismissed-chip{display:flex;}

.card-img{width:100%;height:200px;object-fit:cover;display:block;background:var(--border);}
.card-img-ph{
  width:100%;height:200px;
  background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 55%,#0f3460 100%);
  display:flex;align-items:center;justify-content:center;font-size:3rem;
}
.card-body{padding:20px 22px 22px;flex:1;display:flex;flex-direction:column;}
.card-date{
  display:inline-flex;align-items:center;gap:6px;
  background:var(--gold-lt);color:#6b4f10;border:1px solid var(--gold-bd);
  padding:4px 12px;border-radius:999px;font-size:.7rem;font-weight:700;
  margin-bottom:12px;align-self:flex-start;
}
.card-title{font-family:'Kanit',sans-serif;font-size:1.05rem;font-weight:800;color:var(--ink);line-height:1.4;margin-bottom:10px;
  border-left:3px solid var(--gold);padding-left:10px;}
.card-excerpt{font-size:.82rem;color:var(--muted);line-height:1.7;flex:1;
  display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;}
.card-footer{display:flex;align-items:center;justify-content:space-between;margin-top:14px;}
.read-more{font-size:.78rem;font-weight:700;color:var(--gold);display:flex;align-items:center;gap:5px;}
.dismissed-chip{
  display:none;align-items:center;gap:5px;
  background:#f3f4f6;color:var(--muted);
  border-radius:99px;padding:3px 10px;font-size:.7rem;font-weight:600;
}

/* ── EMPTY ── */
.empty-box{background:var(--card);border-radius:var(--r);box-shadow:var(--shadow);
  border:1px solid var(--border);padding:64px 30px;text-align:center;}
.empty-icon{font-size:3rem;margin-bottom:14px;}
.empty-box h2{font-size:1.4rem;font-weight:800;margin-bottom:8px;}
.empty-box p{color:var(--muted);font-size:.9rem;line-height:1.8;}

/* ── POPUP OVERLAY ── */
.news-overlay{
  display:none;position:fixed;inset:0;z-index:2000;
  background:rgba(10,12,24,.75);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
  align-items:center;justify-content:center;padding:20px;overflow:auto;
}
.news-overlay.open{display:flex;}

/* ── POPUP ── */
.news-popup{
  width:min(700px,100%);background:var(--card);border-radius:24px;
  box-shadow:0 32px 80px rgba(0,0,0,.35);overflow:hidden;
  animation:popIn .38s cubic-bezier(.34,1.56,.64,1) both;
  display:flex;flex-direction:column;max-height:90vh;
}
@keyframes popIn{from{opacity:0;transform:scale(.88) translateY(24px)}to{opacity:1;transform:none}}

.popup-header{
  background:linear-gradient(135deg,#1a1a2e 0%,#16213e 55%,#0f3460 100%);
  padding:0;position:relative;flex-shrink:0;
}
.popup-header-img{width:100%;height:240px;object-fit:cover;display:block;}
.popup-header-ph{
  width:100%;height:240px;
  background:linear-gradient(135deg,#1a1a2e 0%,#16213e 55%,#0f3460 100%);
  display:flex;align-items:center;justify-content:center;font-size:4rem;
}
.popup-header-overlay{
  position:absolute;inset:0;
  background:linear-gradient(to top,rgba(10,12,24,.8) 0%,transparent 55%);
  display:flex;align-items:flex-end;padding:20px 24px;
}
.popup-close{
  position:absolute;top:14px;right:14px;
  width:38px;height:38px;border-radius:50%;
  background:rgba(0,0,0,.45);backdrop-filter:blur(6px);
  border:1.5px solid rgba(255,255,255,.25);
  color:#fff;font-size:1.1rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:.2s;z-index:2;
}
.popup-close:hover{background:rgba(200,50,50,.7);border-color:rgba(255,255,255,.5);}

.popup-date-badge{
  background:var(--gold-lt);color:var(--gold);
  border:1px solid rgba(201,169,110,.5);
  padding:4px 12px;border-radius:999px;font-size:.72rem;font-weight:700;
  backdrop-filter:blur(4px);
}
.popup-body{padding:24px 28px;overflow-y:auto;flex:1;}
.popup-title{font-family:'Kanit',sans-serif;font-size:1.4rem;font-weight:900;color:var(--ink);
  line-height:1.4;margin-bottom:16px;border-left:4px solid var(--gold);padding-left:12px;}
.popup-content{font-size:.95rem;line-height:2;color:#3a3a4a;white-space:pre-line;word-break:break-word;}

.popup-footer{
  padding:16px 28px 20px;border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
  flex-shrink:0;background:#fdfcfb;
}
.btn-dismiss{
  display:inline-flex;align-items:center;gap:7px;
  background:transparent;border:1.5px solid var(--border);color:var(--muted);
  padding:9px 18px;border-radius:99px;font-family:'Sarabun',sans-serif;
  font-size:.82rem;font-weight:700;cursor:pointer;transition:.2s;
}
.btn-dismiss:hover{border-color:#f87171;color:#dc2626;background:#fef2f2;}
.btn-close-popup{
  display:inline-flex;align-items:center;gap:7px;
  background:linear-gradient(135deg,var(--navy),#0f3460);color:#fff;
  padding:9px 22px;border-radius:99px;font-family:'Sarabun',sans-serif;
  font-size:.82rem;font-weight:700;cursor:pointer;border:none;transition:.2s;
}
.btn-close-popup:hover{background:linear-gradient(135deg,#0f3460,#1565c0);}

/* ── Responsive ── */
@media(max-width:640px){
  .news-grid{grid-template-columns:1fr;}
  .popup-body{padding:18px 20px;}
  .popup-footer{padding:14px 20px 18px;}
  .popup-header-img,.popup-header-ph{height:180px;}
  .popup-title{font-size:1.15rem;}
}
</style>
</head>
<body>

<div class="page-hero">
  <div class="topbar">
    <a href="index.php" class="back-btn">← กลับหน้าหลัก</a>
  </div>
  <div class="hero-badge">ประชาสัมพันธ์</div>
  <h1 class="hero-title">ข่าวสาร<span> &amp; กิจกรรม</span></h1>
  <p class="hero-sub">ติดตามข่าวสาร กิจกรรม ประกาศ และความเคลื่อนไหวล่าสุดของหน่วยงานได้ที่นี่</p>
</div>

<div class="page-wrap">
  <?php if (empty($newsItems)): ?>
    <div class="empty-box">
      <div class="empty-icon">📰</div>
      <h2>ยังไม่มีข่าวสาร</h2>
      <p>ขณะนี้ยังไม่มีข้อมูลข่าวประชาสัมพันธ์ กรุณาตรวจสอบอีกครั้งภายหลัง</p>
    </div>
  <?php else: ?>
  <div class="news-grid">
    <?php foreach ($newsItems as $row):
      $excerpt = mb_substr(strip_tags($row['content']), 0, 120) . (mb_strlen($row['content']) > 120 ? '...' : '');
    ?>
    <div class="news-card"
         data-id="<?= (int)$row['id'] ?>"
         data-title="<?= h($row['title']) ?>"
         data-content="<?= h($row['content']) ?>"
         data-date="<?= h(date('d/m/Y H:i', strtotime($row['created_at']))) ?>"
         data-img="<?= !empty($row['image']) ? h('uploads/'.$row['image']) : '' ?>"
         onclick="openNews(this)">
      <?php if (!empty($row['image'])): ?>
        <img class="card-img" src="uploads/<?= h($row['image']) ?>" alt="" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
        <div class="card-img-ph" style="display:none;">📰</div>
      <?php else: ?>
        <div class="card-img-ph">📰</div>
      <?php endif; ?>
      <div class="card-body">
        <div class="card-date">📅 <?= date('d/m/Y', strtotime($row['created_at'])) ?></div>
        <div class="card-title"><?= h($row['title']) ?></div>
        <div class="card-excerpt"><?= h($excerpt) ?></div>
        <div class="card-footer">
          <span class="read-more">อ่านต่อ →</span>
          <span class="dismissed-chip">🚫 ซ่อนแล้ววันนี้</span>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── POPUP OVERLAY ── -->
<div class="news-overlay" id="newsOverlay" onclick="overlayClick(event)">
  <div class="news-popup" id="newsPopup">
    <div class="popup-header" id="popupHeader">
      <img class="popup-header-img" id="popupImg" src="" alt="" style="display:none;" onerror="this.style.display='none';document.getElementById('popupImgPh').style.display='flex';">
      <div class="popup-header-ph" id="popupImgPh">📰</div>
      <div class="popup-header-overlay">
        <span class="popup-date-badge" id="popupDate"></span>
      </div>
      <button class="popup-close" onclick="closePopup()" title="ปิด">✕</button>
    </div>
    <div class="popup-body">
      <div class="popup-title" id="popupTitle"></div>
      <div class="popup-content" id="popupContent"></div>
    </div>
    <div class="popup-footer">
      <button class="btn-dismiss" id="btnDismiss" onclick="dismissToday()">
        🚫 ไม่แสดงอีกวันนี้
      </button>
      <button class="btn-close-popup" onclick="closePopup()">
        ✓ รับทราบแล้ว
      </button>
    </div>
  </div>
</div>

<script>
(function(){
  const TODAY = new Date().toISOString().slice(0,10);
  let currentCard = null;

  // mark dismissed cards on load
  document.querySelectorAll('.news-card').forEach(card => {
    const key = 'news_dismiss_' + card.dataset.id + '_' + TODAY;
    if (localStorage.getItem(key)) {
      card.classList.add('dismissed');
    }
  });

  window.openNews = function(card) {
    const id    = card.dataset.id;
    const key   = 'news_dismiss_' + id + '_' + TODAY;

    currentCard = card;
    document.getElementById('popupTitle').textContent   = card.dataset.title;
    document.getElementById('popupContent').textContent = card.dataset.content;
    document.getElementById('popupDate').textContent    = '📅 ' + card.dataset.date;

    const imgEl = document.getElementById('popupImg');
    const imgPh = document.getElementById('popupImgPh');
    const imgSrc = card.dataset.img;
    if (imgSrc) {
      imgEl.src = imgSrc;
      imgEl.style.display = 'block';
      imgPh.style.display = 'none';
    } else {
      imgEl.style.display = 'none';
      imgPh.style.display = 'flex';
    }

    // update dismiss button
    const dismissed = !!localStorage.getItem(key);
    const btn = document.getElementById('btnDismiss');
    btn.disabled  = dismissed;
    btn.style.opacity = dismissed ? '.45' : '1';
    btn.textContent = dismissed ? '🚫 ซ่อนแล้ววันนี้' : '🚫 ไม่แสดงอีกวันนี้';

    document.getElementById('newsOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
  };

  window.closePopup = function() {
    document.getElementById('newsOverlay').classList.remove('open');
    document.body.style.overflow = '';
    currentCard = null;
  };

  window.overlayClick = function(e) {
    if (e.target === document.getElementById('newsOverlay')) closePopup();
  };

  window.dismissToday = function() {
    if (!currentCard) return;
    const key = 'news_dismiss_' + currentCard.dataset.id + '_' + TODAY;
    localStorage.setItem(key, '1');
    currentCard.classList.add('dismissed');
    const btn = document.getElementById('btnDismiss');
    btn.disabled = true;
    btn.style.opacity = '.45';
    btn.textContent = '🚫 ซ่อนแล้ววันนี้';
    // brief visual feedback then close
    setTimeout(() => closePopup(), 600);
  };

  // keyboard close
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePopup();
  });
})();
</script>
</body>
</html>
