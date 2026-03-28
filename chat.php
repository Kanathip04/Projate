<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header("Location: login.php"); exit;
}
$myId   = (int)$_SESSION['user_id'];
$myName = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$myRole = $_SESSION['user_role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Global Chat 🌐</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0f0f1a;
  --surface:#1a1a2e;
  --surface2:#22223b;
  --border:rgba(255,255,255,.08);
  --gold:#c9a96e;
  --gold2:#f5d89a;
  --text:#f0eeff;
  --muted:#8888aa;
  --online:#22c55e;
  --radius:16px;
  --me:#2d2d5e;
  --them:#1e1e32;
}
body{font-family:'Sarabun',sans-serif;background:var(--bg);color:var(--text);height:100vh;display:flex;flex-direction:column;overflow:hidden}

/* ── HEADER ── */
.header{
  display:flex;align-items:center;gap:12px;
  padding:14px 20px;
  background:var(--surface);
  border-bottom:1px solid var(--border);
  flex-shrink:0;
  backdrop-filter:blur(12px);
}
.header-logo{font-size:22px}
.header-title{font-weight:700;font-size:18px;flex:1}
.header-title span{color:var(--gold)}
.online-pill{
  display:flex;align-items:center;gap:6px;
  background:rgba(34,197,94,.12);
  border:1px solid rgba(34,197,94,.25);
  padding:5px 12px;border-radius:999px;
  font-size:13px;font-weight:600;color:var(--online);
}
.online-dot{width:7px;height:7px;border-radius:50%;background:var(--online);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.btn-back{
  display:inline-flex;align-items:center;gap:6px;
  background:transparent;border:1px solid var(--border);
  color:var(--muted);padding:7px 14px;border-radius:10px;
  text-decoration:none;font-size:13px;font-weight:600;transition:.2s;
}
.btn-back:hover{border-color:var(--gold);color:var(--gold)}
.btn-export{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.3);
  color:#22c55e;padding:7px 14px;border-radius:10px;
  text-decoration:none;font-size:13px;font-weight:600;transition:.2s;cursor:pointer;
}
.btn-export:hover{background:rgba(34,197,94,.22);border-color:#22c55e}
/* Export modal */
.export-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:200;display:none;align-items:center;justify-content:center}
.export-overlay.show{display:flex}
.export-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:28px 28px 24px;width:360px;max-width:95vw;position:relative}
.export-title{font-size:16px;font-weight:700;margin-bottom:18px;color:var(--gold)}
.export-row{margin-bottom:14px}
.export-row label{display:block;font-size:11px;color:var(--muted);margin-bottom:5px;letter-spacing:.05em;text-transform:uppercase}
.export-row input{width:100%;padding:9px 12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:13px;outline:none}
.export-row input:focus{border-color:var(--gold)}
.btn-dl{width:100%;padding:12px;background:#22c55e;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;margin-top:6px;transition:.2s}
.btn-dl:hover{background:#16a34a}
.export-close{position:absolute;top:12px;right:14px;background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer}

/* ── LAYOUT ── */
.layout{display:flex;flex:1;overflow:hidden}

/* ── SIDEBAR ── */
.sidebar{
  width:230px;flex-shrink:0;
  background:var(--surface);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  overflow:hidden;
}
.sidebar-title{
  padding:14px 16px;font-size:11px;font-weight:700;
  color:var(--muted);letter-spacing:.1em;text-transform:uppercase;
  border-bottom:1px solid var(--border);
}
.online-list{flex:1;overflow-y:auto;padding:8px}
.online-list::-webkit-scrollbar{width:4px}
.online-list::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.online-item{
  display:flex;align-items:center;gap:10px;
  padding:8px 10px;border-radius:10px;
  transition:.15s;cursor:default;
}
.online-item:hover{background:var(--surface2)}
.oa{width:36px;height:36px;border-radius:50%;overflow:hidden;background:var(--surface2);
    display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;
    color:var(--gold);border:2px solid var(--border);flex-shrink:0;position:relative}
.oa img{width:100%;height:100%;object-fit:cover}
.oa-dot{position:absolute;bottom:1px;right:1px;width:8px;height:8px;
        border-radius:50%;background:var(--online);border:1.5px solid var(--surface)}
.on{flex:1;min-width:0}
.on-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.on-role{font-size:11px;color:var(--muted)}
.badge-admin{color:var(--gold);font-size:10px}

/* ── CHAT AREA ── */
.chat-area{flex:1;display:flex;flex-direction:column;overflow:hidden;position:relative}

.messages{
  flex:1;overflow-y:auto;padding:20px 20px 10px;
  display:flex;flex-direction:column;gap:4px;
}
.messages::-webkit-scrollbar{width:5px}
.messages::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

/* ── MESSAGE BUBBLE ── */
.msg-wrap{display:flex;gap:10px;align-items:flex-end;max-width:75%;margin-bottom:4px}
.msg-wrap.me{align-self:flex-end;flex-direction:row-reverse}
.msg-wrap.them{align-self:flex-start}

.msg-av{width:32px;height:32px;border-radius:50%;overflow:hidden;background:var(--surface2);
        display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;
        color:var(--gold);flex-shrink:0}
.msg-av img{width:100%;height:100%;object-fit:cover}

.msg-body{display:flex;flex-direction:column;gap:2px}
.me .msg-body{align-items:flex-end}

.msg-meta{display:flex;align-items:center;gap:6px;margin-bottom:3px}
.me .msg-meta{flex-direction:row-reverse}
.msg-name{font-size:12px;font-weight:700;color:var(--gold)}
.msg-time{font-size:11px;color:var(--muted)}
.msg-admin-tag{font-size:10px;background:rgba(201,169,110,.15);
               color:var(--gold);padding:1px 6px;border-radius:4px;font-weight:700}

.bubble{
  padding:10px 14px;border-radius:var(--radius);
  font-size:14.5px;line-height:1.6;word-break:break-word;
  position:relative;cursor:default;
  transition:transform .1s;
}
.bubble:hover{transform:scale(1.01)}
.me .bubble{background:var(--me);border-bottom-right-radius:4px;color:var(--text)}
.them .bubble{background:var(--them);border-bottom-left-radius:4px;color:var(--text)}

.bubble-actions{
  display:none;position:absolute;top:-32px;
  background:var(--surface2);border:1px solid var(--border);
  border-radius:10px;padding:4px;gap:2px;
  white-space:nowrap;z-index:10;
}
.me .bubble-actions{right:0}
.them .bubble-actions{left:0}
.bubble:hover .bubble-actions{display:flex}
.bubble-actions button{
  background:none;border:none;cursor:pointer;
  font-size:15px;padding:3px 6px;border-radius:6px;
  transition:.15s;color:var(--text);
}
.bubble-actions button:hover{background:rgba(255,255,255,.1)}

/* Reactions */
.reactions{display:flex;gap:4px;flex-wrap:wrap;margin-top:4px}
.react-btn{
  display:inline-flex;align-items:center;gap:3px;
  background:rgba(255,255,255,.07);border:1px solid var(--border);
  border-radius:999px;padding:2px 8px;font-size:13px;cursor:pointer;
  transition:.15s;color:var(--text);
}
.react-btn:hover{background:rgba(255,255,255,.14);border-color:var(--gold)}
.react-btn.mine{background:rgba(201,169,110,.15);border-color:var(--gold)}
.react-count{font-size:12px;font-weight:700}

/* Emoji picker */
.emoji-picker{
  position:absolute;bottom:calc(100% + 8px);
  background:var(--surface2);border:1px solid var(--border);
  border-radius:14px;padding:8px;display:none;gap:4px;z-index:20;
  box-shadow:0 8px 32px rgba(0,0,0,.5);
}
.me .emoji-picker{right:0}
.them .emoji-picker{left:0}
.emoji-picker.show{display:flex}
.ep-btn{background:none;border:none;cursor:pointer;font-size:20px;padding:4px;
        border-radius:8px;transition:.15s}
.ep-btn:hover{background:rgba(255,255,255,.1);transform:scale(1.2)}

/* system message */
.sys-msg{text-align:center;font-size:12px;color:var(--muted);padding:6px 0;font-style:italic}

/* ── PROFILE MODAL ── */
.profile-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);z-index:100;display:none;align-items:center;justify-content:center}
.profile-overlay.show{display:flex}
.profile-card{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:32px 28px;min-width:280px;max-width:340px;width:90%;position:relative;animation:popIn .2s cubic-bezier(.34,1.56,.64,1)}
@keyframes popIn{from{opacity:0;transform:scale(.85)}to{opacity:1;transform:scale(1)}}
.profile-close{position:absolute;top:12px;right:14px;background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer;transition:.15s}
.profile-close:hover{color:var(--text)}
.profile-av{width:80px;height:80px;border-radius:50%;overflow:hidden;background:var(--surface2);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:var(--gold);border:3px solid var(--gold);margin:0 auto 14px}
.profile-av img{width:100%;height:100%;object-fit:cover}
.profile-name{font-size:18px;font-weight:700;text-align:center;color:var(--text)}
.profile-role-tag{display:inline-block;margin:6px auto 0;padding:3px 12px;border-radius:999px;font-size:12px;font-weight:700}
.profile-role-tag.admin{background:rgba(201,169,110,.15);color:var(--gold);border:1px solid var(--gold)}
.profile-role-tag.user{background:rgba(255,255,255,.07);color:var(--muted);border:1px solid var(--border)}
.profile-info{margin-top:18px;display:flex;flex-direction:column;gap:8px;border-top:1px solid var(--border);padding-top:14px}
.profile-row{display:flex;gap:8px;font-size:13px;color:var(--muted)}
.profile-row span:first-child{min-width:70px;flex-shrink:0}
.profile-row span:last-child{color:var(--text);font-weight:500}
.profile-bio{margin-top:12px;font-size:13px;color:var(--muted);line-height:1.6;background:var(--bg);border-radius:10px;padding:10px 12px;font-style:italic;word-break:break-word}

/* clickable avatars/names */
.msg-av.clickable,.msg-name.clickable,.online-item.clickable{cursor:pointer}
.msg-av.clickable:hover{opacity:.8}
.msg-name.clickable:hover{text-decoration:underline;text-underline-offset:2px}

/* ── TYPING ── */
.typing-bar{
  min-height:24px;padding:0 20px 4px;
  font-size:12px;color:var(--muted);font-style:italic;
  display:flex;align-items:center;gap:6px;
}
.typing-dots span{display:inline-block;width:5px;height:5px;border-radius:50%;
                  background:var(--muted);margin:0 1px;animation:td .8s infinite}
.typing-dots span:nth-child(2){animation-delay:.15s}
.typing-dots span:nth-child(3){animation-delay:.3s}
@keyframes td{0%,80%,100%{transform:scale(.8);opacity:.5}40%{transform:scale(1.1);opacity:1}}

/* ── INPUT ── */
.input-area{
  padding:14px 20px 16px;
  background:var(--surface);
  border-top:1px solid var(--border);
  flex-shrink:0;
}
.input-wrap{display:flex;gap:10px;align-items:flex-end}
.input-box{
  flex:1;background:var(--surface2);border:1.5px solid var(--border);
  border-radius:14px;padding:12px 16px;
  color:var(--text);font-family:'Sarabun',sans-serif;font-size:14px;
  resize:none;outline:none;max-height:120px;overflow-y:auto;
  transition:.2s;line-height:1.5;
}
.input-box:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,169,110,.12)}
.input-box::placeholder{color:var(--muted)}
.char-count{font-size:11px;color:var(--muted);text-align:right;margin-top:4px}
.char-count.warn{color:#ef4444}

.btn-send{
  width:44px;height:44px;border-radius:12px;border:none;
  background:var(--gold);color:#1a1a2e;font-size:20px;
  cursor:pointer;flex-shrink:0;transition:.2s;
  display:flex;align-items:center;justify-content:center;font-weight:700;
}
.btn-send:hover{background:var(--gold2);transform:scale(1.05)}
.btn-send:active{transform:scale(.95)}

.input-toolbar{display:flex;gap:8px;margin-bottom:8px;align-items:center}
.tb-btn{background:none;border:1px solid var(--border);border-radius:8px;
        color:var(--muted);padding:5px 10px;cursor:pointer;font-size:14px;
        transition:.2s;font-family:'Sarabun',sans-serif}
.tb-btn:hover{border-color:var(--gold);color:var(--gold)}
.tb-emoji-panel{position:relative}
.tb-emoji-list{
  position:absolute;bottom:calc(100% + 8px);left:0;
  background:var(--surface2);border:1px solid var(--border);
  border-radius:14px;padding:10px;
  display:none;grid-template-columns:repeat(6,1fr);gap:4px;
  z-index:50;box-shadow:0 8px 32px rgba(0,0,0,.6);min-width:200px;
}
.tb-emoji-list.open{display:grid}
.te-btn{background:none;border:none;font-size:22px;cursor:pointer;
        padding:4px;border-radius:8px;transition:.15s}
.te-btn:hover{background:rgba(255,255,255,.1);transform:scale(1.15)}

/* ── SCROLL TO BOTTOM ── */
.scroll-btn{
  position:absolute;bottom:80px;right:20px;
  width:40px;height:40px;border-radius:50%;
  background:var(--gold);color:#1a1a2e;border:none;
  font-size:18px;cursor:pointer;display:none;
  align-items:center;justify-content:center;
  box-shadow:0 4px 16px rgba(0,0,0,.4);transition:.2s;z-index:30;
}
.scroll-btn:hover{transform:scale(1.1)}
.scroll-btn.show{display:flex}
.scroll-badge{
  position:absolute;top:-6px;right:-6px;
  background:#ef4444;color:#fff;
  border-radius:999px;font-size:10px;font-weight:700;
  padding:1px 5px;min-width:18px;text-align:center;
}

/* ── SOUND TOGGLE ── */
.sound-btn{background:none;border:none;cursor:pointer;font-size:18px;color:var(--muted);transition:.2s}
.sound-btn:hover{color:var(--gold)}

/* ── RESPONSIVE ── */
@media(max-width:700px){
  .sidebar{display:none}
  .msg-wrap{max-width:90%}
}
</style>
</head>
<body>

<div class="header">
  <span class="header-logo">🌐</span>
  <div class="header-title">Global <span>Chat</span></div>
  <div class="online-pill">
    <span class="online-dot"></span>
    <span id="onlineCount">0</span> ออนไลน์
  </div>
  <button class="sound-btn" id="soundBtn" title="เสียงแจ้งเตือน">🔔</button>
  <?php if ($myRole === 'admin'): ?>
  <button class="btn-export" onclick="openExport()">📥 Export Excel</button>
  <?php endif; ?>
  <a href="index.php" class="btn-back">← กลับ</a>
</div>

<!-- Export Modal -->
<div class="export-overlay" id="exportOverlay" onclick="if(event.target===this)closeExport()">
  <div class="export-card">
    <button class="export-close" onclick="closeExport()">✕</button>
    <div class="export-title">📥 Export ประวัติแชท</div>
    <div class="export-row">
      <label>วันที่เริ่มต้น</label>
      <input type="date" id="expFrom">
    </div>
    <div class="export-row">
      <label>วันที่สิ้นสุด</label>
      <input type="date" id="expTo">
    </div>
    <div class="export-row">
      <label>ค้นหาข้อความ (ถ้ามี)</label>
      <input type="text" id="expSearch" placeholder="พิมพ์คำค้นหา...">
    </div>
    <button class="btn-dl" onclick="doExport()">⬇ Download Excel</button>
  </div>
</div>

<div class="layout">
  <div class="sidebar">
    <div class="sidebar-title">ผู้ใช้ออนไลน์</div>
    <div class="online-list" id="onlineList"></div>
  </div>

  <div class="chat-area">
    <div class="messages" id="messages"></div>

    <div class="typing-bar" id="typingBar"></div>

    <button class="scroll-btn" id="scrollBtn" onclick="scrollToBottom(true)">
      ↓<span class="scroll-badge" id="newBadge" style="display:none"></span>
    </button>

    <div class="input-area">
      <div class="input-toolbar">
        <div class="tb-emoji-panel">
          <button class="tb-btn" onclick="toggleEmojiPanel()" title="Emoji">😊 Emoji</button>
          <div class="tb-emoji-list" id="emojiPanel">
            <?php
            $emojis = ['😀','😂','🥹','😍','🤩','😎','🥳','🤔','😅','😭','😤','🤯','👍','👎','❤️','🔥','💯','✨','🎉','🙏','👀','💪','🤝','😴','🤣','😇','🤗','😏','😒','🥰'];
            foreach($emojis as $e) echo "<button class='te-btn' onclick=\"insertEmoji('$e')\">$e</button>";
            ?>
          </div>
        </div>
        <span style="font-size:12px;color:var(--muted);margin-left:auto" id="myNameTag">👤 <?= $myName ?></span>
      </div>

      <div class="input-wrap">
        <textarea class="input-box" id="msgInput" placeholder="พิมพ์ข้อความ... (Enter ส่ง, Shift+Enter ขึ้นบรรทัด)" rows="1"></textarea>
        <button class="btn-send" id="sendBtn" onclick="sendMessage()">➤</button>
      </div>
      <div class="char-count" id="charCount">0 / 500</div>
    </div>
  </div>
</div>

<!-- Profile Modal -->
<div class="profile-overlay" id="profileOverlay" onclick="closeProfile(event)">
  <div class="profile-card" id="profileCard">
    <button class="profile-close" onclick="closeProfileDirect()">✕</button>
    <div class="profile-av" id="profileAv"></div>
    <div class="profile-name" id="profileName"></div>
    <div style="text-align:center"><span class="profile-role-tag" id="profileRoleTag"></span></div>
    <div class="profile-info">
      <div class="profile-row"><span>สมาชิกตั้งแต่</span><span id="profileSince">—</span></div>
    </div>
    <div class="profile-bio" id="profileBio" style="display:none"></div>
  </div>
</div>

<audio id="notifSound" preload="auto">
  <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivsKSShHR1g5mptKqXhoFxcXmMpbKvnI2Fd3J3iJ+vtqqajId4cneMo7KxopOIe3J2iZ+xsqKSiHtyd4eesrGikIh7cXaInLCxoZCIe3F2h5ywsaGPh3txdomcs7KhoI+He3F1iJqxsaKQiHtxdYiYsbGikI" type="audio/wav">
</audio>

<script>
const MY_ID   = <?= $myId ?>;
const MY_ROLE = '<?= $myRole ?>';
let latestId  = 0;
let soundOn   = true;
let autoScroll= true;
let newMsgCount = 0;
let typingTimer = null;

// ── Emoji Panel ──────────────────────────────────────────
function toggleEmojiPanel(){
  document.getElementById('emojiPanel').classList.toggle('open');
}
function insertEmoji(e){
  const box = document.getElementById('msgInput');
  const pos = box.selectionStart;
  box.value = box.value.slice(0,pos) + e + box.value.slice(pos);
  box.selectionStart = box.selectionEnd = pos + e.length;
  box.focus();
  updateCharCount();
  document.getElementById('emojiPanel').classList.remove('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('.tb-emoji-panel')) {
    document.getElementById('emojiPanel').classList.remove('open');
  }
});

// ── Sound ────────────────────────────────────────────────
document.getElementById('soundBtn').onclick = function(){
  soundOn = !soundOn;
  this.textContent = soundOn ? '🔔' : '🔕';
};
function playSound(){
  if (!soundOn) return;
  try {
    const ctx = new (window.AudioContext||window.webkitAudioContext)();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.connect(g); g.connect(ctx.destination);
    o.frequency.value = 880;
    g.gain.setValueAtTime(0.3, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3);
    o.start(ctx.currentTime); o.stop(ctx.currentTime + 0.3);
  } catch(e){}
}

// ── Scroll ───────────────────────────────────────────────
const msgBox = document.getElementById('messages');
const scrollBtn = document.getElementById('scrollBtn');
const newBadge = document.getElementById('newBadge');

msgBox.addEventListener('scroll', () => {
  const atBottom = msgBox.scrollHeight - msgBox.scrollTop - msgBox.clientHeight < 60;
  autoScroll = atBottom;
  scrollBtn.classList.toggle('show', !atBottom);
  if (atBottom) { newMsgCount = 0; newBadge.style.display = 'none'; }
});
function scrollToBottom(force=false){
  if (force || autoScroll) {
    msgBox.scrollTop = msgBox.scrollHeight;
    newMsgCount = 0;
    newBadge.style.display = 'none';
    scrollBtn.classList.remove('show');
    autoScroll = true;
  }
}

// ── Avatar helper ────────────────────────────────────────
function avatarEl(av, name, role, userId){
  const first = name ? name.charAt(0).toUpperCase() : '?';
  const click = userId ? `onclick="showProfile(${userId})" class="msg-av clickable"` : `class="msg-av"`;
  if (av && av !== '') {
    return `<div ${click} title="${escHtml(name)}"><img src="${av}?v=1" alt="${escHtml(name)}" onerror="this.style.display='none'"><span style="display:none">${first}</span></div>`;
  }
  return `<div ${click} title="${escHtml(name)}">${first}</div>`;
}
function escHtml(s){ const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

// ── Relative time ────────────────────────────────────────
function relTime(ts){
  const diff = Math.floor(Date.now()/1000) - ts;
  if (diff < 10)  return 'เมื่อกี้';
  if (diff < 60)  return diff + ' วิ';
  if (diff < 3600) return Math.floor(diff/60) + ' นาที';
  if (diff < 86400) return Math.floor(diff/3600) + ' ชม.';
  return new Date(ts*1000).toLocaleDateString('th-TH');
}

// ── Render messages ──────────────────────────────────────
const rendered = new Set();
function renderMessages(msgs){
  msgs.forEach(m => {
    if (rendered.has(m.id)) {
      // update reactions only
      const rb = document.getElementById('reactions-'+m.id);
      if (rb) rb.innerHTML = renderReactions(m);
      return;
    }
    rendered.add(m.id);

    const isMe = m.is_me;
    const wrap = document.createElement('div');
    wrap.className = 'msg-wrap ' + (isMe ? 'me' : 'them');
    wrap.id = 'msg-' + m.id;

    const roleTag = m.role === 'admin' ? `<span class="msg-admin-tag">⚡ Admin</span>` : '';
    const metaMe  = isMe
      ? `<span class="msg-time">${relTime(m.ts)}</span>`
      : `<span class="msg-name clickable" onclick="showProfile(${m.user_id})">${escHtml(m.user_name)}</span>${roleTag}<span class="msg-time">${relTime(m.ts)}</span>`;

    const delBtn = (isMe || MY_ROLE === 'admin')
      ? `<button onclick="deleteMsg(${m.id})" title="ลบ">🗑️</button>` : '';

    wrap.innerHTML = `
      ${avatarEl(m.avatar, m.user_name, m.role, m.user_id)}
      <div class="msg-body">
        <div class="msg-meta">${metaMe}</div>
        <div class="bubble" id="bubble-${m.id}">
          <div class="bubble-actions">
            <button onclick="showReactPicker(${m.id})" title="Reaction">😊</button>
            ${delBtn}
          </div>
          <div class="emoji-picker" id="ep-${m.id}">
            ${['👍','❤️','😂','😮','😢','🔥'].map(e=>`<button class="ep-btn" onclick="sendReact(${m.id},'${e}')">${e}</button>`).join('')}
          </div>
          ${escHtml(m.message).replace(/\n/g,'<br>')}
        </div>
        <div class="reactions" id="reactions-${m.id}">${renderReactions(m)}</div>
      </div>`;
    msgBox.appendChild(wrap);
  });
}
function renderReactions(m){
  if (!m.reactions || !Object.keys(m.reactions).length) return '';
  return Object.entries(m.reactions).map(([emoji, uids]) => {
    const mine = uids.includes(MY_ID) ? 'mine' : '';
    return `<button class="react-btn ${mine}" onclick="sendReact(${m.id},'${emoji}')">${emoji}<span class="react-count">${uids.length}</span></button>`;
  }).join('');
}

// ── Reaction picker ──────────────────────────────────────
let openPicker = null;
function showReactPicker(id){
  if (openPicker && openPicker !== id) {
    const prev = document.getElementById('ep-'+openPicker);
    if (prev) prev.classList.remove('show');
  }
  const el = document.getElementById('ep-'+id);
  if (!el) return;
  el.classList.toggle('show');
  openPicker = el.classList.contains('show') ? id : null;
}
document.addEventListener('click', e => {
  if (!e.target.closest('.bubble')) {
    document.querySelectorAll('.emoji-picker.show').forEach(el => el.classList.remove('show'));
    openPicker = null;
  }
});

// ── Send reaction ────────────────────────────────────────
function sendReact(msgId, emoji){
  const ep = document.getElementById('ep-'+msgId);
  if (ep) ep.classList.remove('show');
  openPicker = null;
  fetch('chat_api.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=react&msg_id=${msgId}&emoji=${encodeURIComponent(emoji)}`
  }).then(r=>r.json()).then(d=>{
    if (d.ok) {
      const rb = document.getElementById('reactions-'+msgId);
      if (rb) rb.innerHTML = renderReactions({id:msgId, reactions:d.reactions});
    }
  });
}

// ── Delete message ───────────────────────────────────────
function deleteMsg(msgId){
  if (!confirm('ลบข้อความนี้?')) return;
  fetch('chat_api.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=delete&msg_id=${msgId}`
  }).then(r=>r.json()).then(d=>{
    if (d.ok && d.deleted) {
      const el = document.getElementById('msg-'+msgId);
      if (el) { el.style.opacity='0'; el.style.transform='scale(.9)'; el.style.transition='.2s'; setTimeout(()=>el.remove(),200); rendered.delete(msgId); }
    }
  });
}

// ── Send message ─────────────────────────────────────────
function sendMessage(){
  const box = document.getElementById('msgInput');
  const msg = box.value.trim();
  if (!msg) return;
  box.value = ''; updateCharCount(); box.style.height='auto';
  autoScroll = true;
  fetch('chat_api.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=send&message='+encodeURIComponent(msg)
  }).then(r=>r.json()).then(()=>poll());
}

// ── Input events ─────────────────────────────────────────
const input = document.getElementById('msgInput');
input.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); return; }
  clearTimeout(typingTimer);
  fetch('chat_api.php?action=typing');
  typingTimer = setTimeout(()=>{}, 3000);
});
input.addEventListener('input', () => {
  input.style.height = 'auto';
  input.style.height = Math.min(input.scrollHeight, 120) + 'px';
  updateCharCount();
});
function updateCharCount(){
  const len = input.value.length;
  const el = document.getElementById('charCount');
  el.textContent = len + ' / 500';
  el.classList.toggle('warn', len > 450);
  document.getElementById('sendBtn').disabled = len === 0 || len > 500;
}

// ── Poll ─────────────────────────────────────────────────
function poll(){
  fetch(`chat_api.php?action=fetch&since=${latestId}`)
    .then(r=>r.json())
    .then(d=>{
      if (!d.messages) return;
      const hadNew = d.messages.length > 0;

      if (d.latest > latestId) {
        const newMsgs = d.messages.filter(m => m.id > latestId);
        if (newMsgs.length && latestId > 0) {
          const hasOthers = newMsgs.some(m => !m.is_me);
          if (hasOthers) {
            playSound();
            if (!autoScroll) {
              newMsgCount += newMsgs.filter(m=>!m.is_me).length;
              newBadge.textContent = newMsgCount;
              newBadge.style.display = 'block';
            }
          }
        }
        renderMessages(d.messages);
        latestId = d.latest;
        scrollToBottom();
      } else if (d.messages.length) {
        renderMessages(d.messages);
        scrollToBottom();
      }

      // Online
      updateOnline(d.online || []);
      document.getElementById('onlineCount').textContent = (d.online||[]).length;

      // Typing
      updateTyping(d.typing || []);
    })
    .catch(()=>{});
}

function updateOnline(users){
  const list = document.getElementById('onlineList');
  list.innerHTML = users.map(u => {
    const av = u.avatar ? `<img src="${u.avatar}?v=1" alt="" onerror="this.style.display='none'">` : u.user_name.charAt(0).toUpperCase();
    const roleTag = u.role === 'admin' ? `<span class="badge-admin">⚡ Admin</span>` : 'Member';
    return `<div class="online-item clickable" onclick="showProfile(${u.user_id})">
      <div class="oa">${av}<span class="oa-dot"></span></div>
      <div class="on">
        <div class="on-name">${escHtml(u.user_name)}</div>
        <div class="on-role">${roleTag}</div>
      </div>
    </div>`;
  }).join('');
}

function updateTyping(names){
  const bar = document.getElementById('typingBar');
  if (!names.length) { bar.innerHTML = ''; return; }
  const who = names.length === 1 ? names[0] : names.slice(0,-1).join(', ') + ' และ ' + names[names.length-1];
  bar.innerHTML = `<div class="typing-dots"><span></span><span></span><span></span></div><span>${escHtml(who)} กำลังพิมพ์...</span>`;
}

// ── Export modal ─────────────────────────────────────────
function openExport(){
  // ตั้งค่า default: วันนี้
  const today = new Date().toISOString().slice(0,10);
  document.getElementById('expTo').value = today;
  document.getElementById('exportOverlay').classList.add('show');
}
function closeExport(){
  document.getElementById('exportOverlay').classList.remove('show');
}
function doExport(){
  const from   = document.getElementById('expFrom').value;
  const to     = document.getElementById('expTo').value;
  const search = document.getElementById('expSearch').value;
  let url = 'chat_export.php?';
  if (from)   url += 'from='   + encodeURIComponent(from)   + '&';
  if (to)     url += 'to='     + encodeURIComponent(to)     + '&';
  if (search) url += 'search=' + encodeURIComponent(search) + '&';
  window.location.href = url;
  closeExport();
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeExport(); closeProfileDirect(); }
});

// ── Profile modal ─────────────────────────────────────────
function showProfile(userId){
  fetch(`chat_api.php?action=profile&user_id=${userId}`)
    .then(r=>r.json())
    .then(d=>{
      if (!d.ok) return;
      const u = d.user;
      const first = u.fullname ? u.fullname.charAt(0).toUpperCase() : '?';
      const avEl = document.getElementById('profileAv');
      avEl.innerHTML = u.avatar
        ? `<img src="${u.avatar}?v=1" alt="" onerror="this.style.display='none'">${first}`
        : first;
      document.getElementById('profileName').textContent = u.fullname;
      const rt = document.getElementById('profileRoleTag');
      rt.textContent = u.role === 'admin' ? '⚡ Administrator' : '👤 Member';
      rt.className = 'profile-role-tag ' + (u.role === 'admin' ? 'admin' : 'user');
      const since = u.created_at ? new Date(u.created_at).toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'}) : '—';
      document.getElementById('profileSince').textContent = since;
      const bioEl = document.getElementById('profileBio');
      if (u.bio && u.bio.trim()) {
        bioEl.textContent = u.bio.trim();
        bioEl.style.display = 'block';
      } else {
        bioEl.style.display = 'none';
      }
      document.getElementById('profileOverlay').classList.add('show');
    });
}
function closeProfile(e){
  if (e.target === document.getElementById('profileOverlay'))
    document.getElementById('profileOverlay').classList.remove('show');
}
function closeProfileDirect(){
  document.getElementById('profileOverlay').classList.remove('show');
}
document.addEventListener('keydown', e => {
});

// ── Heartbeat ────────────────────────────────────────────
function heartbeat(){ fetch('chat_api.php?action=heartbeat'); }

// ── Init ─────────────────────────────────────────────────
poll();
setInterval(poll, 1500);
setInterval(heartbeat, 10000);
heartbeat();
updateCharCount();
</script>
</body>
</html>
