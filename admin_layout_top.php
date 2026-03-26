<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

if (!isset($pageTitle)) {
    $pageTitle = "Admin Panel";
}

if (!isset($activeMenu)) {
    $activeMenu = "";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?></title>

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

:root{
    --dark:#151617;
    --bg:#f4f6f9;
    --brand:#6f970f;
    --brand2:#85b315;
    --text:#222;
    --muted:#666;
    --card:#ffffff;
    --line:#e8e8e8;
}

html, body{
    height:100%;
}

body{
    font-family:Tahoma, sans-serif;
    background:var(--bg);
    color:var(--text);
    overflow-x:hidden;
}

.wrapper{
    display:flex;
    min-height:100vh;
}

.sidebar{
    width:230px;
    height:100vh;
    background:var(--dark);
    color:#fff;
    padding:22px 16px 16px;
    position:fixed;
    left:0;
    top:0;
    display:flex;
    flex-direction:column;
    z-index:1000;
}

.sidebar h2{
    color:var(--brand);
    font-size:18px;
    font-weight:800;
    text-align:center;
    margin:8px 0 22px;
    flex-shrink:0;
}

.sidebar-scroll{
    flex:1;
    overflow-y:auto;
    overflow-x:hidden;
    padding-right:6px;
}

/* แถบเลื่อน */
.sidebar-scroll::-webkit-scrollbar{
    width:8px;
}
.sidebar-scroll::-webkit-scrollbar-track{
    background:rgba(255,255,255,.06);
    border-radius:10px;
}
.sidebar-scroll::-webkit-scrollbar-thumb{
    background:rgba(133,179,21,.85);
    border-radius:10px;
}
.sidebar-scroll::-webkit-scrollbar-thumb:hover{
    background:rgba(133,179,21,1);
}

.sidebar-menu{
    display:flex;
    flex-direction:column;
    gap:8px;
    padding-bottom:14px;
}

.menu-item{
    display:flex;
    align-items:center;
    gap:10px;
    width:100%;
    padding:12px 14px;
    border-radius:10px;
    color:#ffffff;
    text-decoration:none;
    font-size:14px;
    font-weight:700;
    line-height:1.25;
    transition:0.2s ease;
}

.menu-item .icon{
    width:18px;
    text-align:center;
    flex-shrink:0;
}

.menu-item .text{
    display:block;
    word-break:break-word;
}

.menu-item:hover{
    background:#232425;
}

.menu-item.active{
    background:var(--brand);
    color:#fff;
}

.sidebar-footer{
    padding-top:14px;
    flex-shrink:0;
    border-top:1px solid rgba(255,255,255,.08);
    margin-top:10px;
}

.back-btn{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    width:100%;
    padding:12px 14px;
    border-radius:12px;
    text-decoration:none;
    font-weight:800;
    font-size:14px;
    background:linear-gradient(135deg,var(--brand),var(--brand2));
    color:#fff;
    box-shadow:0 8px 18px rgba(0,0,0,.25);
    transition:.2s ease;
}

.back-btn:hover{
    filter:brightness(1.05);
    transform:translateY(-1px);
}

.main{
    margin-left:230px;
    width:calc(100% - 230px);
    padding:26px;
    min-height:100vh;
}

.content-card{
    background:var(--card);
    border-radius:18px;
    padding:20px;
    box-shadow:0 8px 24px rgba(0,0,0,0.06);
    margin-bottom:24px;
}

.page-title{
    font-size:18px;
    font-weight:800;
    margin-bottom:16px;
}

.btn{
    border:none;
    padding:10px 16px;
    border-radius:10px;
    cursor:pointer;
    font-weight:700;
}

.btn-brand{
    background:var(--brand);
    color:#fff;
}

.btn-brand:hover{
    background:var(--brand2);
}

input, select, textarea, button{
    font-family:inherit;
}

@media (max-width: 900px){
    .sidebar{
        width:210px;
    }

    .main{
        margin-left:210px;
        width:calc(100% - 210px);
    }
}

@media (max-width: 700px){
    .wrapper{
        flex-direction:column;
    }

    .sidebar{
        width:100%;
        height:auto;
        min-height:auto;
        position:relative;
        border-radius:0 0 18px 18px;
    }

    .sidebar-scroll{
        max-height:320px;
    }

    .main{
        margin-left:0;
        width:100%;
        padding:18px;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <div class="sidebar">
        <h2>Admin Panel</h2>

        <div class="sidebar-scroll">
            <div class="sidebar-menu">

                <a href="admin_dashboard.php" class="menu-item <?php echo ($activeMenu === 'dashboard') ? 'active' : ''; ?>">
                    <span class="icon">📄</span>
                    <span class="text">Dashboard</span>
                </a>

                <a href="daily_report.php" class="menu-item <?php echo ($activeMenu === 'report') ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    <span class="text">รายงาน</span>
                </a>

                <a href="change_banner.php" class="menu-item <?php echo ($activeMenu === 'banner') ? 'active' : ''; ?>">
                    <span class="icon">🖼️</span>
                    <span class="text">เปลี่ยนรูปหน้าเว็บ</span>
                </a>

                <a href="admin_booking_list.php" class="menu-item <?php echo ($activeMenu === 'booking') ? 'active' : ''; ?>">
                    <span class="icon">🏨</span>
                    <span class="text">ข้อมูลการเข้าพัก</span>
                </a>

                <a href="admin_booking_approved.php" class="menu-item <?php echo ($activeMenu === 'booking_approved') ? 'active' : ''; ?>">
                    <span class="icon">✅</span>
                    <span class="text">รายการอนุมัติแล้ว</span>
                </a>

                <a href="admin_booking_archive.php" class="menu-item <?php echo ($activeMenu === 'booking_archive') ? 'active' : ''; ?>">
                    <span class="icon">🗂️</span>
                    <span class="text">รายการอนุมัติย้อนหลัง</span>
                </a>

                <a href="manage_rooms.php" class="menu-item <?php echo ($activeMenu === 'rooms') ? 'active' : ''; ?>">
                    <span class="icon">🛏️</span>
                    <span class="text">จัดการห้องพัก</span>
                </a>

                <a href="admin_add_news.php" class="menu-item <?php echo ($activeMenu === 'news_add') ? 'active' : ''; ?>">
                    <span class="icon">📰</span>
                    <span class="text">เพิ่มข่าวสาร</span>
                </a>

                <a href="manage_news.php" class="menu-item <?php echo ($activeMenu === 'news_manage') ? 'active' : ''; ?>">
                    <span class="icon">✏️</span>
                    <span class="text">จัดการข่าว</span>
                </a>

                <a href="edit_about.php" class="menu-item <?php echo ($activeMenu === 'about') ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    <span class="text">จัดการเกี่ยวกับ</span>
                </a>

                <a href="change_password.php" class="menu-item <?php echo ($activeMenu === 'password') ? 'active' : ''; ?>">
                    <span class="icon">🔐</span>
                    <span class="text">เปลี่ยนรหัสผ่าน</span>
                </a>

            </div>
        </div>

        <div class="sidebar-footer">
            <a href="index.php" class="back-btn">← กลับหน้าเว็บไซต์</a>
        </div>
    </div>

    <div class="main">