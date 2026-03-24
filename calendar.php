<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Calendar - Visitor Count</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --ink: #1a1a2e;
      --gold: #c9a84c;
      --gold-light: #e8d5a3;
      --gold-pale: #f7f0e0;
      --cream: #faf8f3;
      --surface: #ffffff;
      --muted: #8a8a9a;
      --border: rgba(201,168,76,0.18);
      --shadow: 0 8px 40px rgba(26,26,46,0.10);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--cream);
      color: var(--ink);
      min-height: 100vh;
      background-image:
        radial-gradient(ellipse 80% 60% at 20% 0%, rgba(201,168,76,0.07) 0%, transparent 60%),
        radial-gradient(ellipse 60% 50% at 80% 100%, rgba(26,26,46,0.04) 0%, transparent 60%);
    }

    /* ─── Topbar ─── */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(250,248,243,0.85);
      backdrop-filter: blur(16px);
      border-bottom: 1px solid var(--border);
    }

    .topbar-inner {
      max-width: 1100px;
      margin: auto;
      padding: 14px 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .menu-btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: transparent;
      border: 1px solid var(--gold);
      color: var(--gold);
      font-family: 'DM Sans', sans-serif;
      font-size: 13px;
      font-weight: 500;
      letter-spacing: 0.06em;
      padding: 8px 18px;
      border-radius: 100px;
      cursor: pointer;
      transition: all 0.25s ease;
    }
    .menu-btn:hover {
      background: var(--gold);
      color: #fff;
    }

    .title {
      font-family: 'Cormorant Garamond', serif;
      font-size: 20px;
      font-weight: 400;
      letter-spacing: 0.12em;
      color: var(--ink);
      text-align: center;
    }
    .title span { color: var(--gold); }

    .topbar-spacer { width: 110px; }

    /* ─── Container ─── */
    .container {
      max-width: 1100px;
      margin: auto;
      padding: 40px 24px 60px;
    }

    /* ─── Card ─── */
    .card {
      background: var(--surface);
      border-radius: 24px;
      padding: 36px 40px;
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
      max-width: 860px;
      margin: auto;
    }

    /* ─── Calendar Header ─── */
    .cal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 32px;
    }

    .nav-btn {
      width: 40px;
      height: 40px;
      border: 1px solid var(--border);
      background: var(--cream);
      border-radius: 50%;
      cursor: pointer;
      font-size: 16px;
      color: var(--ink);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    .nav-btn:hover {
      border-color: var(--gold);
      background: var(--gold-pale);
      color: var(--gold);
    }

    .month-year {
      text-align: center;
    }
    .month-year .month-text {
      font-family: 'Cormorant Garamond', serif;
      font-size: 2rem;
      font-weight: 500;
      letter-spacing: 0.04em;
      color: var(--ink);
      display: block;
      line-height: 1;
    }
    .month-year .divider {
      width: 40px;
      height: 1px;
      background: var(--gold);
      margin: 8px auto;
    }

    /* ─── Grid ─── */
    .calendar {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 8px;
    }

    .day-name {
      text-align: center;
      font-size: 11px;
      font-weight: 500;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: var(--muted);
      padding: 8px 0 14px;
    }

    .day {
      border-radius: 14px;
      padding: 10px 6px;
      min-height: 88px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      position: relative;
      transition: all 0.22s ease;
      border: 1px solid transparent;
      background: var(--cream);
      user-select: none;
    }

    .day .date-num {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.5rem;
      font-weight: 400;
      color: var(--ink);
      line-height: 1;
      z-index: 2;
    }

    .day:hover {
      background: var(--gold-pale);
      border-color: var(--gold-light);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(201,168,76,0.12);
    }

    /* Today */
    .today {
      background: var(--ink) !important;
      border-color: var(--ink) !important;
    }
    .today .date-num {
      color: #fff !important;
    }
    .today:hover {
      background: #2a2a40 !important;
      box-shadow: 0 6px 24px rgba(26,26,46,0.25) !important;
    }
    .today .visitor-badge {
      background: var(--gold) !important;
      color: #fff !important;
    }

    /* Visitor badge */
    .visitor-badge {
      position: absolute;
      top: 8px;
      right: 8px;
      font-size: 10.5px;
      font-weight: 600;
      letter-spacing: 0.03em;
      background: var(--gold);
      color: #fff;
      border-radius: 100px;
      min-width: 22px;
      height: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 5px;
      z-index: 3;
      box-shadow: 0 2px 8px rgba(201,168,76,0.4);
    }

    .has-data {
      border-color: rgba(201,168,76,0.3);
    }

    .empty {
      background: transparent !important;
      cursor: default !important;
      border: none !important;
      box-shadow: none !important;
      transform: none !important;
      min-height: 88px;
    }

    /* ─── Modal ─── */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(26,26,46,0.45);
      z-index: 200;
      backdrop-filter: blur(4px);
    }

    .modal {
      display: none;
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0.96);
      background: var(--surface);
      border-radius: 24px;
      padding: 32px 28px;
      box-shadow: 0 24px 80px rgba(26,26,46,0.22);
      z-index: 201;
      width: 90%;
      max-width: 380px;
      border: 1px solid var(--border);
      opacity: 0;
      transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .modal.open {
      opacity: 1;
      transform: translate(-50%, -50%) scale(1);
    }

    .modal-date-label {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.35rem;
      font-weight: 500;
      color: var(--ink);
      margin-bottom: 4px;
    }

    .modal-sub {
      font-size: 11px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 22px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .modal-sub::before {
      content: '';
      display: inline-block;
      width: 20px;
      height: 1px;
      background: var(--gold);
    }

    .detail-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 11px 0;
      border-bottom: 1px solid #f2f0eb;
      font-size: 14px;
    }
    .detail-row .label { color: var(--muted); }
    .detail-row .value {
      font-weight: 500;
      color: var(--ink);
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 18px;
      padding: 14px 18px;
      background: var(--ink);
      border-radius: 12px;
      color: #fff;
    }
    .total-row .t-label {
      font-size: 12px;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      opacity: 0.6;
    }
    .total-row .t-value {
      font-family: 'Cormorant Garamond', serif;
      font-size: 1.6rem;
      font-weight: 600;
      color: var(--gold);
    }

    .close-btn {
      margin-top: 18px;
      width: 100%;
      background: var(--cream);
      border: 1px solid var(--border);
      padding: 12px;
      border-radius: 12px;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      font-size: 13px;
      font-weight: 500;
      letter-spacing: 0.08em;
      color: var(--muted);
      transition: all 0.2s;
    }
    .close-btn:hover {
      border-color: var(--gold);
      color: var(--gold);
      background: var(--gold-pale);
    }

    /* Animated show */
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .day { animation: fadeUp 0.35s ease both; }
  </style>
</head>
<body>

  <div class="topbar">
    <div class="topbar-inner">
      <button class="menu-btn" onclick="location.href='index.php'">
        ← หน้าหลัก
      </button>
      <div class="title">ตรวจสอบ<span>สถิติ</span>รายวัน</div>
      <div class="topbar-spacer"></div>
    </div>
  </div>

  <div class="container">
    <div class="card">
      <div class="cal-header">
        <button class="nav-btn" type="button" onclick="prevMonth()">&#8249;</button>
        <div class="month-year">
          <span class="month-text" id="monthYear"></span>
          <div class="divider"></div>
        </div>
        <button class="nav-btn" type="button" onclick="nextMonth()">&#8250;</button>
      </div>

      <div class="calendar" id="calendar"></div>
    </div>
  </div>

  <div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>
  <div class="modal" id="detailModal">
    <div class="modal-date-label" id="modalDate"></div>
    <div class="modal-sub" id="modalSub">สรุปผู้เยี่ยมชม</div>
    <div id="modalBody"></div>
    <button class="close-btn" type="button" onclick="closeModal()">ปิดหน้าต่าง</button>
  </div>

  <script>
    const i18n = {
      th: {
        months: ["มกราคม","กุมภาพันธ์","มีนาคม","เมษายน","พฤษภาคม","มิถุนายน","กรกฎาคม","สิงหาคม","กันยายน","ตุลาคม","พฤศจิกายน","ธันวาคม"],
        days: ["อาทิตย์","จันทร์","อังคาร","พุธ","พฤหัสบดี","ศุกร์","เสาร์"]
      }
    };

    const calendarEl = document.getElementById('calendar');
    const monthYearEl = document.getElementById('monthYear');

    let currentDate = new Date();
    let rawVisitorData = [];

    async function fetchVisitorData() {
      try {
        const response = await fetch('get_calendar_events.php?v=' + Date.now());
        rawVisitorData = await response.json();
      } catch (err) {
        rawVisitorData = [];
      }
      renderCalendar();
    }

    function renderCalendar() {
      calendarEl.innerHTML = "";

      const year  = currentDate.getFullYear();
      const month = currentDate.getMonth();
      const today = new Date();

      monthYearEl.textContent = `${i18n.th.months[month]}  ${year + 543}`;

      i18n.th.days.forEach(d => {
        const div = document.createElement("div");
        div.className = "day-name";
        div.textContent = d.substring(0, 2);
        calendarEl.appendChild(div);
      });

      const firstDay = new Date(year, month, 1).getDay();
      const lastDate = new Date(year, month + 1, 0).getDate();

      for (let i = 0; i < firstDay; i++) {
        const div = document.createElement("div");
        div.className = "day empty";
        calendarEl.appendChild(div);
      }

      for (let d = 1; d <= lastDate; d++) {
        const div = document.createElement("div");
        div.className = "day";
        div.style.animationDelay = `${(d + firstDay) * 0.018}s`;

        const dateKey = `${year}-${String(month + 1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const numEl = document.createElement("span");
        numEl.className = "date-num";
        numEl.textContent = d;
        div.appendChild(numEl);

        const dayData = rawVisitorData.filter(item => item.date === dateKey);
        const total = dayData.reduce((s, item) => s + Number(item.count || 0), 0);

        if (total > 0) {
          div.classList.add("has-data");
          const badge = document.createElement("div");
          badge.className = "visitor-badge";
          badge.textContent = total;
          div.appendChild(badge);
          div.onclick = () => showDetails(dateKey, dayData);
        } else {
          div.onclick = null;
        }

        if (d === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
          div.classList.add("today");
        }

        calendarEl.appendChild(div);
      }
    }

    function showDetails(dateKey, dayData) {
      const [y, m, d] = dateKey.split('-');
      document.getElementById('modalDate').textContent =
        `${parseInt(d)} ${i18n.th.months[parseInt(m) - 1]} ${parseInt(y) + 543}`;

      let html = '';
      let grandTotal = 0;
      dayData.forEach(item => {
        const count = Number(item.count || 0);
        html += `
          <div class="detail-row">
            <span class="label">กลุ่ม: ${item.type}</span>
            <span class="value">${count} คน</span>
          </div>`;
        grandTotal += count;
      });

      html += `
        <div class="total-row">
          <span class="t-label">รวมทั้งหมด</span>
          <span class="t-value">${grandTotal} <small style="font-size:1rem;font-weight:400;color:rgba(255,255,255,0.5)">คน</small></span>
        </div>`;

      document.getElementById('modalBody').innerHTML = html;
      document.getElementById('modalOverlay').style.display = 'block';
      const modal = document.getElementById('detailModal');
      modal.style.display = 'block';
      requestAnimationFrame(() => modal.classList.add('open'));
    }

    function closeModal() {
      const modal = document.getElementById('detailModal');
      modal.classList.remove('open');
      setTimeout(() => {
        modal.style.display = 'none';
        document.getElementById('modalOverlay').style.display = 'none';
      }, 250);
    }

    function prevMonth() {
      currentDate.setMonth(currentDate.getMonth() - 1);
      fetchVisitorData();
    }
    function nextMonth() {
      currentDate.setMonth(currentDate.getMonth() + 1);
      fetchVisitorData();
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    fetchVisitorData();
  </script>
</body>
</html>