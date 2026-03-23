<?php
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

body{
    font-family:Tahoma, sans-serif;
    background:var(--bg);
    color:var(--text);
}

.wrapper{
    display:flex;
    min-height:100vh;
}

.sidebar{
    width:230px;
    min-height:100vh;
    background:var(--dark);
    color:#fff;
    padding:22px 16px;
    position:fixed;
    left:0;
    top:0;
    display:flex;
    flex-direction:column;
}

.sidebar h2{
    color:var(--brand);
    font-size:18px;
    font-weight:800;
    text-align:center;
    margin:8px 0 30px;
}

.sidebar-menu{
    display:flex;
    flex-direction:column;
    gap:8px;
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
    line-height:1.2;
    transition:0.2s ease;
}

.menu-item .icon{
    width:18px;
    text-align:center;
    flex-shrink:0;
}

.menu-item .text{
    white-space:nowrap;
}

.menu-item:hover{
    background:#232425;
}

.menu-item.active{
    background:var(--brand);
    color:#fff;
}

.sidebar-footer{
    margin-top:auto;
    padding-top:16px;
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

input, select, textarea{
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
    .sidebar{
        width:100%;
        min-height:auto;
        position:relative;
        border-radius:0 0 18px 18px;
    }

    .main{
        margin-left:0;
        width:100%;
        padding:18px;
    }

    .wrapper{
        flex-direction:column;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <div class="sidebar">
        <div>
            <h2>Admin Panel</h2>

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

                <a href="manage_games.php" class="menu-item <?php echo ($activeMenu === 'games') ? 'active' : ''; ?>">
                    <span class="icon">🎮</span>
                    <span class="text">จัดการเกม</span>
                </a>

                <a href="admin_add_news.php" class="menu-item <?php echo ($activeMenu === 'news_manage') ? 'active' : ''; ?>">
                    <span class="icon">📰</span>
                    <span class="text">จัดการข่าว</span>
                </a>

                <a href="manage_news.php" class="menu-item <?php echo ($activeMenu === 'news_edit') ? 'active' : ''; ?>">
                    <span class="icon">✏️</span>
                    <span class="text">จัดการข่าว</span>
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