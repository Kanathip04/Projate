<?php
session_start();
require_once 'auth_guard.php';
include 'config.php';
date_default_timezone_set('Asia/Bangkok');

$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
if ($user_email === '') die('ไม่พบ session email');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function safeGet($row,$key,$default=null){ return array_key_exists($key,$row)?$row[$key]:$default; }

/* ══ ดึงข้อมูลย้อนหลัง (ก่อนวันนี้) ทั้ง 3 ตาราง ══ */
$allBookings = [];

/* ── ห้องพัก ── */
$st = $conn->prepare("SELECT * FROM room_bookings WHERE email=? AND (archived IS NULL OR archived=0) AND booking_status NOT IN ('cancelled','rejected') AND DATE(created_at)<CURDATE() ORDER BY id DESC");
if($st){
    $st->bind_param("s",$user_email); $st->execute(); $res=$st->get_result();
    while($r=$res->fetch_assoc()){
        $r['type']       = 'room';
        $r['title']      = $r['room_type'] ?? 'ห้องพัก';
        $r['date_from']  = $r['checkin_date']  ?? null;
        $r['date_to']    = $r['checkout_date'] ?? null;
        $r['rooms_json'] = $r['rooms'] ?? null;
        $r['items_json'] = null;
        $r['daily_queue_no'] = null;
        $r['boat_count']     = null;
        $allBookings[] = $r;
    }
    $st->close();
}

/* ── เต็นท์ ── */
$st2 = $conn->prepare("SELECT * FROM equipment_bookings WHERE email=? AND (archived IS NULL OR archived=0) AND booking_status NOT IN ('cancelled','rejected') AND DATE(created_at)<CURDATE() ORDER BY id DESC");
if($st2){
    $st2->bind_param("s",$user_email); $st2->execute(); $res2=$st2->get_result();
    while($r=$res2->fetch_assoc()){
        $r['type']       = 'tent';
        $r['title']      = 'เช่าอุปกรณ์แคมป์ปิ้ง';
        $r['date_from']  = $r['checkin_date']  ?? null;
        $r['date_to']    = $r['checkout_date'] ?? null;
        $r['rooms_json'] = null;
        $r['total_price']= $r['total_price'] ?? null;
        $r['daily_queue_no'] = null;
        $r['boat_count']     = null;
        $allBookings[] = $r;
    }
    $st2->close();
}

/* ── เรือ ── */
$st3 = $conn->prepare("SELECT * FROM boat_bookings WHERE email=? AND (archived IS NULL OR archived=0) AND booking_status NOT IN ('cancelled','rejected') AND DATE(created_at)<CURDATE() ORDER BY id DESC");
if($st3){
    $st3->bind_param("s",$user_email); $st3->execute(); $res3=$st3->get_result();
    while($r=$res3->fetch_assoc()){
        $r['type']       = 'boat';
        $r['title']      = $r['queue_name'] ?? 'พายเรือ';
        $r['date_from']  = $r['boat_date'] ?? null;
        $r['date_to']    = $r['boat_date'] ?? null;
        $r['total_price']= $r['total_amount'] ?? null;
        $r['rooms_json'] = null;
        $r['items_json'] = null;
        $r['guests']     = null;
        $allBookings[] = $r;
    }
    $st3->close();
}

usort($allBookings, fn($a,$b) => strtotime($b['created_at']) <=> strtotime($a['created_at']));

/* ── helpers ── */
function bsText($s){
    return ['pending'=>'รออนุมัติ','approved'=>'อนุมัติแล้ว','rejected'=>'ไม่อนุมัติ',
            'cancelled'=>'ยกเลิก','completed'=>'เสร็จสิ้น',
            'paid'=>'ชำระแล้ว','waiting_verify'=>'รอตรวจสลิป','failed'=>'สลิปไม่ผ่าน',
            'manual_review'=>'รอตรวจสอบ','suspicious'=>'น่าสงสัย','unpaid'=>'ยังไม่ชำระ'][$s] ?? $s;
}
function bsDot($s){
    return ['pending'=>'#f59e0b','approved'=>'#16a34a','rejected'=>'#dc2626','cancelled'=>'#6b7280',
            'completed'=>'#2563eb','paid'=>'#059669','waiting_verify'=>'#d97706','failed'=>'#dc2626',
            'manual_review'=>'#d97706','unpaid'=>'#9ca3af'][$s] ?? '#9ca3af';
}
function typeInfo($t){
    return ['room'=>['icon'=>'🏨','label'=>'ห้องพัก','color'=>'#7c3aed','bg'=>'#ede9fe','grad'=>'135deg,#7c3aed,#a855f7'],
            'tent'=>['icon'=>'⛺','label'=>'เต็นท์','color'=>'#d97706','bg'=>'#fef3c7','grad'=>'135deg,#d97706,#f59e0b'],
            'boat'=>['icon'=>'🚣','label'=>'พายเรือ','color'=>'#0369a1','bg'=>'#e0f2fe','grad'=>'135deg,#0369a1,#0ea5e9']][$t];
}
function thDate($s){
    if(!$s||$s==='0000-00-00') return '-';
    $m=['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $ts=strtotime($s);
    return date('j',$ts).' '.$m[(int)date('m',$ts)].' '.(date('Y',$ts)+543);
}
function genBillRef($type,$created_at,$id,$conn){
    $d=date('Ymd',strtotime($created_at));
    $prefix=['room'=>'ROOM-','tent'=>'EQUIP-','boat'=>'BOAT-'][$type]??'REF-';
    $tbl=['room'=>'room_bookings','tent'=>'equipment_bookings','boat'=>'boat_bookings'][$type];
    $r=$conn->query("SELECT COUNT(*) AS n FROM $tbl WHERE DATE(created_at)=DATE('".date('Y-m-d',strtotime($created_at))."') AND id<=$id");
    $n=(int)($r?$r->fetch_assoc()['n']:1);
    return $prefix.$d.'-'.str_pad($n,3,'0',STR_PAD_LEFT);
}

$counts=['room'=>0,'tent'=>0,'boat'=>0];
foreach($allBookings as $b) $counts[$b['type']]++;

/* ── สร้าง map วันที่ → ประเภทบริการ สำหรับปฏิทิน ── */
$calMap = []; // ['2026-04-01' => ['boat','room'], ...]
foreach($allBookings as $b){
    $d = date('Y-m-d', strtotime($b['created_at']));
    if(!isset($calMap[$d])) $calMap[$d] = [];
    if(!in_array($b['type'], $calMap[$d])) $calMap[$d][] = $b['type'];
}
$calMapJson = json_encode($calMap);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ประวัติการจอง</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&family=Kanit:wght@700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --ink:#0d1b2a;--muted:#64748b;--gold:#c9a96e;
  --bg:#f1f5f9;--card:#fff;--border:#e2e8f0;
  --navy:#0d1b2a;--navy2:#1e3a5c;
  --success:#059669;--warning:#d97706;--danger:#dc2626;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;}
a{text-decoration:none;}

/* ─── HERO ─── */
.hero{
  background:linear-gradient(135deg,#1a0a2e 0%,#2a1a4a 40%,#1a2a3a 100%);
  padding:28px 20px 80px;position:relative;overflow:hidden;
}
.hero::before{content:'';position:absolute;width:500px;height:500px;border-radius:50%;
  background:radial-gradient(circle,rgba(201,169,110,.12) 0%,transparent 70%);
  top:-150px;right:-100px;pointer-events:none;}
.hero::after{content:'';position:absolute;width:300px;height:300px;border-radius:50%;
  background:rgba(201,169,110,.06);bottom:-100px;left:-50px;pointer-events:none;}
.wrap{width:min(1000px,94%);margin:0 auto;}
.hero-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px;position:relative;z-index:1;}
.hero-nav a{
  display:inline-flex;align-items:center;gap:5px;
  padding:7px 16px;border-radius:99px;color:#fff;font-size:.8rem;font-weight:700;
  border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.08);
  transition:all .2s;
}
.hero-nav a:hover{background:rgba(255,255,255,.16);transform:translateY(-1px);}
.hero-title{position:relative;z-index:1;}
.hero-title h1{font-family:'Kanit',sans-serif;font-size:2.6rem;font-weight:900;color:#fff;line-height:1.15;margin-bottom:6px;}
.hero-title h1 em{font-style:normal;color:var(--gold);}
.hero-sub{font-size:.88rem;color:rgba(255,255,255,.65);font-weight:500;}

/* ─── DATE GROUP ─── */
.date-group{width:min(1000px,94%);margin:0 auto 10px;}
.date-label{
  display:flex;align-items:center;gap:8px;
  font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;
  margin:18px 0 10px;
}
.date-label::after{content:'';flex:1;height:1px;background:var(--border);}

/* ─── STATS ─── */
.stats-wrap{
  width:min(1000px,94%);margin:0 auto;
  margin-top:-44px;position:relative;z-index:10;
  display:grid;grid-template-columns:repeat(4,1fr);gap:14px;
  margin-bottom:28px;
}
.stat-card{
  background:var(--card);border-radius:16px;
  box-shadow:0 4px 20px rgba(13,27,42,.1);border:1px solid var(--border);
  padding:16px 18px;display:flex;align-items:center;gap:12px;
  transition:transform .2s;
}
.stat-card:hover{transform:translateY(-2px);}
.stat-ico{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0;}
.stat-num{font-family:'Kanit',sans-serif;font-size:1.7rem;font-weight:900;line-height:1;}
.stat-lbl{font-size:.72rem;color:var(--muted);font-weight:600;margin-top:2px;}

/* ─── FILTER ─── */
.filter-wrap{width:min(1000px,94%);margin:0 auto 20px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.f-chip{
  display:inline-flex;align-items:center;gap:5px;
  padding:7px 18px;border-radius:99px;font-size:.82rem;font-weight:700;cursor:pointer;
  border:2px solid var(--border);background:var(--card);color:var(--muted);
  transition:all .2s;user-select:none;
}
.f-chip:hover{border-color:var(--navy2);color:var(--navy);}
.f-chip.on{background:var(--navy);border-color:var(--navy);color:#fff;}

/* ─── CARDS ─── */
.list-wrap{width:min(1000px,94%);margin:0 auto;padding-bottom:60px;display:grid;gap:14px;}
.bk{background:var(--card);border-radius:20px;overflow:hidden;
  box-shadow:0 2px 12px rgba(13,27,42,.07);border:1px solid var(--border);
  transition:box-shadow .2s,transform .2s;}
.bk:hover{box-shadow:0 6px 24px rgba(13,27,42,.11);transform:translateY(-1px);}
.bk-inner{display:flex;}
.bk-stripe{width:6px;flex-shrink:0;border-radius:0;}
.bk-body{flex:1;padding:18px 20px 14px;min-width:0;}
.bk-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;}
.type-pill{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:99px;font-size:.7rem;font-weight:800;flex-shrink:0;}
.bk-bill{font-family:'Kanit',sans-serif;font-size:.72rem;letter-spacing:.05em;color:var(--muted);margin-top:2px;}
.bk-name{font-weight:800;font-size:1rem;color:var(--ink);margin-bottom:6px;}
.bk-info{display:flex;flex-wrap:wrap;gap:8px 18px;font-size:.82rem;color:var(--muted);}
.bi{display:inline-flex;align-items:center;gap:4px;font-size:.8rem;}
.bi b{color:var(--ink);font-weight:700;}
.price-tag{font-family:'Kanit',sans-serif;font-size:1.1rem;font-weight:800;color:var(--gold);white-space:nowrap;}
.st-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:99px;font-size:.72rem;font-weight:700;white-space:nowrap;}
.st-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;}
.pm-cash{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:.7rem;font-weight:700;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;}
.pm-tf{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:6px;font-size:.7rem;font-weight:700;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;}
.bk-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px;padding-top:10px;border-top:1px solid var(--border);}
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 16px;border-radius:10px;
  font-family:'Sarabun',sans-serif;font-size:.8rem;font-weight:700;cursor:pointer;
  border:none;transition:all .2s;white-space:nowrap;text-decoration:none;}
.btn:hover{transform:translateY(-1px);}
.btn-green{background:#059669;color:#fff;}.btn-green:hover{background:#047857;}
.btn-amber{background:#d97706;color:#fff;}.btn-amber:hover{background:#b45309;}
.btn-red{background:#dc2626;color:#fff;}.btn-red:hover{background:#b91c1c;}
.btn-blue{background:#0369a1;color:#fff;}.btn-blue:hover{background:#075985;}
.btn-ghost{background:#f8fafc;color:var(--muted);border:1.5px solid var(--border);}.btn-ghost:hover{border-color:var(--navy);color:var(--navy);}
.expand-row{display:flex;align-items:center;gap:6px;cursor:pointer;padding:10px 20px;
  border-top:1px solid var(--border);background:#fafbfc;font-size:.78rem;font-weight:700;
  color:var(--muted);transition:background .15s;user-select:none;}
.expand-row:hover{background:#f1f5f9;color:var(--ink);}
.expand-arrow{transition:transform .25s;font-size:.7rem;}
.bk-detail{display:none;padding:18px 20px 20px;background:#f8fafc;border-top:1px dashed var(--border);}
.bk-detail.open{display:block;}
.detail-section{margin-bottom:14px;}
.detail-title{font-size:.68rem;font-weight:800;color:var(--muted);text-transform:uppercase;
  letter-spacing:.08em;margin-bottom:8px;display:flex;align-items:center;gap:6px;}
.detail-title::after{content:'';flex:1;height:1px;background:var(--border);}
.d-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:8px;}
.d-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:10px 12px;}
.d-lbl{font-size:.66rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;}
.d-val{font-size:.85rem;font-weight:700;color:var(--ink);}
.d-val.green{color:#059669;}.d-val.amber{color:#d97706;}.d-val.red{color:#dc2626;}
.item-row{display:flex;align-items:center;justify-content:space-between;padding:6px 10px;
  border-radius:8px;background:var(--card);border:1px solid var(--border);font-size:.8rem;margin-bottom:5px;}
.item-name{font-weight:700;color:var(--ink);}.item-qty{color:var(--muted);}
.empty{background:var(--card);border-radius:20px;padding:70px 24px;text-align:center;
  box-shadow:0 2px 12px rgba(13,27,42,.07);border:1px solid var(--border);}
.empty-icon{font-size:3.5rem;margin-bottom:16px;opacity:.35;}
.empty h3{font-size:1.2rem;font-weight:800;margin-bottom:8px;}
.empty p{color:var(--muted);font-size:.88rem;line-height:1.7;}
/* ─── DATE PICKER ─── */
.dp-wrap{width:min(1000px,94%);margin:0 auto 20px;}
.dp-box{background:var(--card);border-radius:16px;box-shadow:0 2px 12px rgba(13,27,42,.07);border:1px solid var(--border);padding:16px 20px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.dp-label{font-size:.78rem;font-weight:800;color:var(--muted);white-space:nowrap;}
.dp-input{padding:8px 12px;border:1.5px solid var(--border);border-radius:10px;font-family:'Sarabun',sans-serif;font-size:.88rem;color:var(--ink);background:#fafaf8;outline:none;transition:.2s;cursor:pointer;}
.dp-input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12);}
.dp-btn{padding:8px 18px;border-radius:10px;border:none;font-family:'Sarabun',sans-serif;font-size:.82rem;font-weight:700;cursor:pointer;transition:.15s;}
.dp-btn-find{background:var(--navy);color:#fff;}.dp-btn-find:hover{background:#2a2a4a;}
.dp-btn-clear{background:#f1f5f9;color:var(--muted);border:1.5px solid var(--border);}.dp-btn-clear:hover{border-color:var(--navy);color:var(--navy);}
.dp-result{font-size:.78rem;color:var(--gold);font-weight:700;white-space:nowrap;}
.cal-filter-label{width:min(1000px,94%);margin:0 auto 10px;font-size:.78rem;color:var(--muted);font-weight:600;display:none;}
.cal-filter-label.visible{display:block;}

@media(max-width:640px){
  .stats-wrap{grid-template-columns:repeat(2,1fr);}
  .hero-title h1{font-size:1.9rem;}
  .d-grid{grid-template-columns:repeat(2,1fr);}
  .cal-day{font-size:.72rem;}
}
</style>
</head>
<body>

<section class="hero">
  <div class="wrap">
    <nav class="hero-nav">
      <a href="index.php">← หน้าหลัก</a>
      <a href="booking_status.php" style="border-color:rgba(201,169,110,.5);color:var(--gold);">📋 การจองวันนี้</a>
      <a href="booking_boat.php">🚣 จองพายเรือ</a>
      <a href="booking_room.php">🏨 จองห้องพัก</a>
      <a href="booking_tent.php">⛺ จองเต็นท์</a>
    </nav>
    <div class="hero-title">
      <h1>ประวัติ<em>การจอง</em></h1>
      <p class="hero-sub">รายการจองทั้งหมดก่อนวันนี้ · ห้องพัก · เต็นท์ · พายเรือ</p>
    </div>
  </div>
</section>

<!-- STATS -->
<div class="stats-wrap">
  <div class="stat-card">
    <div class="stat-ico" style="background:#f5f3ff;color:#7c3aed;">🕐</div>
    <div class="stat-body">
      <div class="stat-num" style="color:var(--ink);"><?= count($allBookings) ?></div>
      <div class="stat-lbl">รายการทั้งหมด</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-ico" style="background:#ede9fe;color:#7c3aed;">🏨</div>
    <div class="stat-body">
      <div class="stat-num" style="color:#7c3aed;"><?= $counts['room'] ?></div>
      <div class="stat-lbl">ห้องพัก</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-ico" style="background:#fef3c7;color:#d97706;">⛺</div>
    <div class="stat-body">
      <div class="stat-num" style="color:#d97706;"><?= $counts['tent'] ?></div>
      <div class="stat-lbl">เต็นท์/อุปกรณ์</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-ico" style="background:#e0f2fe;color:#0369a1;">🚣</div>
    <div class="stat-body">
      <div class="stat-num" style="color:#0369a1;"><?= $counts['boat'] ?></div>
      <div class="stat-lbl">พายเรือ</div>
    </div>
  </div>
</div>

<!-- FILTER -->
<div class="filter-wrap">
  <button class="f-chip on" data-f="all">ทั้งหมด <span>(<?= count($allBookings) ?>)</span></button>
  <button class="f-chip" data-f="room">🏨 ห้องพัก <span>(<?= $counts['room'] ?>)</span></button>
  <button class="f-chip" data-f="tent">⛺ เต็นท์ <span>(<?= $counts['tent'] ?>)</span></button>
  <button class="f-chip" data-f="boat">🚣 พายเรือ <span>(<?= $counts['boat'] ?>)</span></button>
</div>

<!-- DATE PICKER -->
<div class="dp-wrap">
  <div class="dp-box">
    <span class="dp-label">📅 เลือกวันที่:</span>
    <input type="date" id="dpInput" class="dp-input" max="<?= date('Y-m-d', strtotime('-1 day')) ?>">
    <button class="dp-btn dp-btn-find" onclick="filterByDate()">ดูรายการ</button>
    <button class="dp-btn dp-btn-clear" id="dpClear" onclick="clearFilter()" style="display:none;">✕ ล้าง</button>
    <span class="dp-result" id="dpResult"></span>
  </div>
</div>
<div class="cal-filter-label" id="calFilterLabel"></div>

<!-- LIST -->
<div class="list-wrap">
<?php if(empty($allBookings)): ?>
  <div class="empty">
    <div class="empty-icon">🕐</div>
    <h3>ยังไม่มีประวัติการจอง</h3>
    <p>รายการจองจากวันก่อนหน้าจะแสดงที่นี่</p>
  </div>
<?php else:
  $prevDate = null;
  foreach($allBookings as $i=>$b):
    $ti    = typeInfo($b['type']);
    $bkSt  = $b['booking_status'] ?? 'pending';
    $payS  = $b['payment_status'] ?? null;
    $pm    = trim($b['payment_method'] ?? '');
    $isCash= ($pm === 'เงินสด' || $pm === 'cash');
    $price = (float)($b['total_price'] ?? 0);
    $billRef = $b['booking_ref'] ?? genBillRef($b['type'], $b['created_at'], $b['id'], $conn);

    $roomsArr = $b['rooms_json'] ? json_decode($b['rooms_json'],true) : [];
    $itemsArr = [];
    if(!empty($b['items_json'])){
      $dec=json_decode($b['items_json'],true);
      if(is_array($dec)) foreach($dec as $it){
        $n=$it['name']??($it['tent_name']??'');
        $q=(int)($it['qty']??($it['quantity']??1));
        $u=$it['unit']??'ชิ้น';
        $p=(float)($it['price_per_night']??($it['price']??0));
        if($n) $itemsArr[]=['name'=>$n,'qty'=>$q,'unit'=>$u,'price'=>$p];
      }
    }

    $nights=1;
    if(!empty($b['date_from'])&&!empty($b['date_to'])&&$b['date_from']!==$b['date_to'])
      $nights=max(1,(int)(new DateTime($b['date_from']))->diff(new DateTime($b['date_to']))->days);

    $needPay=(in_array($b['type'],['boat','tent'])&&in_array($payS,['unpaid','failed',null,''])&&!$isCash);
    $isWaiting=($payS==='waiting_verify'||$payS==='manual_review');

    $dotColor=bsDot($bkSt);
    $stBg=['pending'=>'#fef9c3','approved'=>'#d1fae5','rejected'=>'#fee2e2',
           'cancelled'=>'#f3f4f6','completed'=>'#dbeafe','paid'=>'#d1fae5',
           'waiting_verify'=>'#fef3c7','failed'=>'#fee2e2','manual_review'=>'#fef3c7',
           'unpaid'=>'#f3f4f6'][$bkSt]??'#f3f4f6';
    $stTx=['pending'=>'#92400e','approved'=>'#065f46','rejected'=>'#991b1b',
           'cancelled'=>'#374151','completed'=>'#1d4ed8','paid'=>'#065f46',
           'waiting_verify'=>'#92400e','failed'=>'#991b1b','manual_review'=>'#92400e',
           'unpaid'=>'#6b7280'][$bkSt]??'#6b7280';

    /* แสดง date separator */
    $bookDate = date('Y-m-d', strtotime($b['created_at']));
    if($bookDate !== $prevDate):
      $prevDate = $bookDate;
      $thM=['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
      $ts=strtotime($bookDate);
      $dLabel=date('j',$ts).' '.$thM[(int)date('m',$ts)].' '.(date('Y',$ts)+543);
?>
</div>
<div class="date-group" data-date="<?= h($bookDate) ?>"><div class="date-label">📅 <?= $dLabel ?></div></div>
<div class="list-wrap" style="margin-top:0;">
<?php endif; ?>

<div class="bk" data-t="<?= $b['type'] ?>" data-date="<?= h($bookDate) ?>">
  <div class="bk-inner">
    <div class="bk-stripe" style="background:linear-gradient(<?= $ti['grad'] ?>);"></div>
    <div class="bk-body">

      <div class="bk-head">
        <div>
          <span class="type-pill" style="background:<?= $ti['bg'] ?>;color:<?= $ti['color'] ?>;">
            <?= $ti['icon'] ?> <?= $ti['label'] ?>
          </span>
          <div class="bk-bill"><?= h($billRef) ?> · #<?= $b['id'] ?></div>
        </div>
        <span class="st-pill" style="background:<?= $stBg ?>;color:<?= $stTx ?>;">
          <span class="st-dot" style="background:<?= $dotColor ?>;"></span>
          <?= bsText($bkSt) ?>
        </span>
      </div>

      <div class="bk-name"><?= h($b['title']??'-') ?>
        <?php if($b['type']==='boat'&&!empty($b['daily_queue_no'])): ?>
          <span style="font-family:'Kanit',sans-serif;font-size:.75rem;padding:2px 8px;background:#f0fdf4;color:#15803d;border-radius:6px;margin-left:6px;font-weight:800;">
            Q<?= str_pad($b['daily_queue_no'],4,'0',STR_PAD_LEFT) ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="bk-info">
        <?php if($b['date_from']): ?>
        <span class="bi">📅 <b><?= thDate($b['date_from']) ?><?= ($b['date_to']&&$b['date_to']!==$b['date_from']) ? ' → '.thDate($b['date_to']) : '' ?></b></span>
        <?php endif; ?>
        <?php if($b['type']!=='boat'&&$nights>1): ?>
        <span class="bi">🌙 <b><?= $nights ?> คืน</b></span>
        <?php endif; ?>
        <?php if($b['guests']): ?>
        <span class="bi">👥 <b><?= (int)$b['guests'] ?> คน</b></span>
        <?php endif; ?>
        <?php if($b['type']==='boat'&&!empty($b['boat_count'])): ?>
        <span class="bi">🚣 <b><?= (int)$b['boat_count'] ?> ลำ</b></span>
        <?php endif; ?>
        <?php if($price>0): ?>
        <span class="price-tag">฿<?= number_format($price,0) ?></span>
        <?php endif; ?>
        <?php if($pm): ?>
          <?php if($isCash): ?>
          <span class="pm-cash">💵 เงินสด</span>
          <?php else: ?>
          <span class="pm-tf">🏦 <?= h($pm) ?></span>
          <?php endif; ?>
        <?php endif; ?>
        <?php if($payS&&$payS!==$bkSt): ?>
        <span class="bi" style="color:<?= bsDot($payS) ?>;font-weight:700;">● <?= bsText($payS) ?></span>
        <?php endif; ?>
        <span class="bi" style="margin-left:auto;">🕐 <?= date('d/m/Y H:i',strtotime($b['created_at'])) ?></span>
      </div>

      <div class="bk-actions">
        <?php if($b['type']==='boat'&&$needPay&&!empty($b['booking_ref'])): ?>
          <a href="payment_slip.php?ref=<?= urlencode($b['booking_ref']) ?>" class="btn btn-blue">💳 ชำระเงิน</a>
        <?php elseif($b['type']==='tent'&&$needPay): ?>
          <a href="equipment_bill.php?id=<?= (int)$b['id'] ?>" class="btn btn-blue">💳 ชำระเงิน</a>
        <?php elseif($b['type']==='room'&&in_array($payS,['unpaid',null,''])&&$bkSt==='approved'): ?>
          <a href="room_bill.php?id=<?= (int)$b['id'] ?>" class="btn btn-blue">💳 ชำระเงิน</a>
        <?php endif; ?>
        <?php if($isWaiting&&$b['type']==='tent'): ?>
          <a href="equipment_bill.php?id=<?= (int)$b['id'] ?>" class="btn btn-amber">⏳ ติดตามสถานะ</a>
        <?php elseif($isWaiting&&$b['type']==='room'): ?>
          <a href="room_bill.php?id=<?= (int)$b['id'] ?>" class="btn btn-amber">⏳ ติดตามสถานะ</a>
        <?php endif; ?>
        <?php if($b['type']==='tent'&&$payS==='paid'): ?>
          <a href="equipment_ticket.php?id=<?= (int)$b['id'] ?>" class="btn btn-green">🎫 ดูใบรับเงิน</a>
        <?php endif; ?>
        <?php if($b['type']==='room'&&($payS==='paid'||$bkSt==='approved')): ?>
          <a href="room_ticket.php?id=<?= (int)$b['id'] ?>" class="btn btn-green">🎫 ดูใบรับเงิน</a>
        <?php endif; ?>
        <?php if($b['type']==='boat'&&!empty($b['booking_ref'])&&$bkSt==='approved'): ?>
          <a href="queue_ticket.php?ref=<?= urlencode($b['booking_ref']) ?>" class="btn btn-green">🎫 ดูบัตรคิว</a>
        <?php endif; ?>
        <?php if($b['type']==='tent'&&$payS==='failed'): ?>
          <a href="equipment_bill.php?id=<?= (int)$b['id'] ?>&retry=1" class="btn btn-red">🔄 ส่งสลิปใหม่</a>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <div class="expand-row" onclick="toggle(<?= $i ?>)">
    <span class="expand-arrow" id="arr-<?= $i ?>">▶</span> รายละเอียดทั้งหมด
  </div>

  <div class="bk-detail" id="det-<?= $i ?>">
    <div class="detail-section">
      <div class="detail-title">ข้อมูลผู้จอง</div>
      <div class="d-grid">
        <div class="d-card"><div class="d-lbl">ชื่อ</div><div class="d-val"><?= h($b['full_name']??'-') ?></div></div>
        <div class="d-card"><div class="d-lbl">วันที่จอง</div><div class="d-val"><?= date('d/m/Y H:i',strtotime($b['created_at'])) ?></div></div>
        <?php if(!empty($b['approved_at'])): ?>
        <div class="d-card"><div class="d-lbl">วันที่อนุมัติ</div><div class="d-val"><?= date('d/m/Y H:i',strtotime($b['approved_at'])) ?></div></div>
        <?php endif; ?>
        <?php if(!empty($b['paid_at'])): ?>
        <div class="d-card"><div class="d-lbl">ชำระเมื่อ</div><div class="d-val green"><?= date('d/m/Y H:i',strtotime($b['paid_at'])) ?></div></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-title">รายละเอียดการจอง</div>
      <div class="d-grid">
        <div class="d-card"><div class="d-lbl">เลขที่บิล</div><div class="d-val"><?= h($billRef) ?></div></div>
        <?php if($b['date_from']): ?>
        <div class="d-card">
          <div class="d-lbl"><?= $b['type']==='boat'?'วันที่':'เช็คอิน' ?></div>
          <div class="d-val"><?= thDate($b['date_from']) ?></div>
        </div>
        <?php endif; ?>
        <?php if($b['date_to']&&$b['date_to']!==$b['date_from']): ?>
        <div class="d-card"><div class="d-lbl">เช็คเอาท์</div><div class="d-val"><?= thDate($b['date_to']) ?></div></div>
        <div class="d-card"><div class="d-lbl">จำนวนคืน</div><div class="d-val"><?= $nights ?> คืน</div></div>
        <?php endif; ?>
        <?php if($b['guests']): ?>
        <div class="d-card"><div class="d-lbl">จำนวนคน</div><div class="d-val"><?= (int)$b['guests'] ?> คน</div></div>
        <?php endif; ?>
        <?php if($price>0): ?>
        <div class="d-card"><div class="d-lbl">ยอดรวม</div><div class="d-val green">฿<?= number_format($price,2) ?></div></div>
        <?php endif; ?>
        <?php if($pm): ?>
        <div class="d-card"><div class="d-lbl">วิธีชำระ</div><div class="d-val"><?= $isCash ? '💵 เงินสด' : '🏦 '.h($pm) ?></div></div>
        <?php endif; ?>
        <div class="d-card"><div class="d-lbl">สถานะการจอง</div><div class="d-val" style="color:<?= $stTx ?>"><?= bsText($bkSt) ?></div></div>
        <?php if($payS): ?>
        <div class="d-card"><div class="d-lbl">สถานะชำระเงิน</div><div class="d-val" style="color:<?= bsDot($payS) ?>"><?= bsText($payS) ?></div></div>
        <?php endif; ?>
      </div>
    </div>
    <?php if($b['type']==='room'&&!empty($roomsArr)): ?>
    <div class="detail-section">
      <div class="detail-title">ห้องพักที่จอง</div>
      <?php foreach($roomsArr as $rm): ?>
      <div class="item-row"><span class="item-name">🔑 ห้อง <?= h($rm) ?></span></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if($b['type']==='tent'&&!empty($itemsArr)): ?>
    <div class="detail-section">
      <div class="detail-title">อุปกรณ์ที่เช่า</div>
      <?php foreach($itemsArr as $it): ?>
      <div class="item-row">
        <span class="item-name">🎒 <?= h($it['name']) ?></span>
        <span class="item-qty"><?= $it['qty'] ?> <?= h($it['unit']) ?><?= $it['price']>0?' · ฿'.number_format($it['price'],0).'/คืน':'' ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; endif; ?>
</div>

<script>
function toggle(i){
  const d=document.getElementById('det-'+i);
  const a=document.getElementById('arr-'+i);
  d.classList.toggle('open');
  a.style.transform=d.classList.contains('open')?'rotate(90deg)':'';
}
document.querySelectorAll('.f-chip').forEach(c=>{
  c.addEventListener('click',function(){
    document.querySelectorAll('.f-chip').forEach(x=>x.classList.remove('on'));
    this.classList.add('on');
    const f=this.dataset.f;
    document.querySelectorAll('.bk').forEach(b=>{
      b.style.display=(f==='all'||b.dataset.t===f)?'':'none';
    });
    // ล้าง calendar filter เมื่อกด filter chip
    clearCalFilter();
  });
});

/* ══ DATE PICKER FILTER ══ */
const calMap = <?= $calMapJson ?>;
const thMM = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];

function filterByDate(){
  const val = document.getElementById('dpInput').value;
  if(!val) return;
  const dt = new Date(val+'T00:00:00');
  const label = `${dt.getDate()} ${thMM[dt.getMonth()+1]} ${dt.getFullYear()+543}`;
  const types = calMap[val] || [];

  document.querySelectorAll('.bk').forEach(b=>{
    b.style.display = (b.dataset.date === val) ? '' : 'none';
  });
  document.querySelectorAll('.date-group').forEach(dg=>{
    dg.style.display = (dg.dataset.date === val) ? '' : 'none';
  });

  const res = document.getElementById('dpResult');
  if(types.length > 0){
    res.textContent = `พบ ${document.querySelectorAll('.bk[data-date="'+val+'"]').length} รายการ`;
  } else {
    res.textContent = 'ไม่พบรายการจองในวันนี้';
  }
  document.getElementById('dpClear').style.display = '';
  document.querySelector('.list-wrap') && document.querySelector('.list-wrap').scrollIntoView({behavior:'smooth',block:'start'});
}

function clearFilter(){
  document.getElementById('dpInput').value = '';
  document.getElementById('dpResult').textContent = '';
  document.getElementById('dpClear').style.display = 'none';
  document.querySelectorAll('.bk').forEach(b=> b.style.display='');
  document.querySelectorAll('.date-group').forEach(dg=> dg.style.display='');
}

// กด Enter ใน input
document.getElementById('dpInput').addEventListener('keydown', e=>{ if(e.key==='Enter') filterByDate(); });
</script>
</body>
</html>
