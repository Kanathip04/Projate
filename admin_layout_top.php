<?php
session_start();
date_default_timezone_set('Asia/Bangkok');

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit;
}

$conn = new mysqli("localhost", "root", "Kanathip04", "backoffice_db");
$conn->set_charset("utf8mb4");
if ($conn->connect_error) die("DB Error: " . $conn->connect_error);

$user_id = (int)$_SESSION['user_id'];
$user = $conn->query("SELECT fullname, email FROM users WHERE id = $user_id")->fetch_assoc();

$pageTitle = $pageTitle ?? "Admin Panel";
$activeMenu = $activeMenu ?? "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; background: #f5f5f5; }
        .admin-nav { background: #1a1a2e; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { font-size: 1.2rem; font-weight: bold; }
        .nav-menu { display: flex; list-style: none; gap: 20px; }
        .nav-menu a { color: white; text-decoration: none; padding: 8px 15px; border-radius: 5px; }
        .nav-menu a:hover, .nav-menu .active a { background: #c9a96e; color: #1a1a2e; }
        .content { padding: 20px; }
        .stat-card, .lm-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .stat-card { display: inline-block; width: calc(25% - 20px); margin: 10px; }
        .btn { padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; }
        .btn-primary { background: #1a1a2e; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-sm { padding: 5px 12px; font-size: 0.8rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; }
        .badge-blue { background: #e3f2fd; color: #1565c0; }
        .badge-orange { background: #fff3e0; color: #e65100; }
        .badge-green { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="nav-brand">⚡ Admin Panel</div>
        <ul class="nav-menu">
            <li class="<?= $activeMenu === 'dashboard' ? 'active' : '' ?>"><a href="admin_dashboard.php">Dashboard</a></li>
            <li class="<?= $activeMenu === 'profile' ? 'active' : '' ?>"><a href="admin_profile.php">โปรไฟล์</a></li>
        </ul>
        <div>
            <span>👤 <?= htmlspecialchars($user['fullname'] ?? 'Admin') ?></span>
            <a href="logout.php" style="color: white; margin-left: 15px;">🚪 ออกจากระบบ</a>
        </div>
    </nav>
    <div class="content">