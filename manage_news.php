<?php
include 'config.php';

$result = $conn->query("SELECT * FROM news ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>จัดการข่าวสาร</title>
<style>
    *{
        box-sizing:border-box;
        margin:0;
        padding:0;
    }

    :root{
        --bg:#f4f6f8;
        --card:#ffffff;
        --text:#1f2937;
        --muted:#6b7280;
        --primary:#638411;
        --primary-dark:#4f6b0d;
        --danger:#dc2626;
        --danger-dark:#b91c1c;
        --line:#e5e7eb;
        --shadow:0 10px 30px rgba(0,0,0,.07);
        --radius:20px;
    }

    body{
        font-family:'Segoe UI', Tahoma, sans-serif;
        background:
            radial-gradient(circle at top, rgba(99,132,17,.08), transparent 28%),
            linear-gradient(180deg, #f7f9fb 0%, #f3f5f7 100%);
        color:var(--text);
        min-height:100vh;
    }

    a{
        text-decoration:none;
    }

    .page-wrap{
        width:100%;
        max-width:1150px;
        margin:0 auto;
        padding:28px 18px 60px;
    }

    .topbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:14px;
        flex-wrap:wrap;
        margin-bottom:24px;
    }

    .btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        padding:12px 18px;
        border-radius:999px;
        font-size:15px;
        font-weight:700;
        transition:.25s ease;
        border:none;
        cursor:pointer;
    }

    .btn-back{
        background:#fff;
        color:var(--primary);
        border:1px solid rgba(99,132,17,.18);
        box-shadow:0 6px 18px rgba(0,0,0,.05);
    }

    .btn-back:hover{
        background:var(--primary);
        color:#fff;
    }

    .btn-add{
        background:var(--primary);
        color:#fff;
    }

    .btn-add:hover{
        background:var(--primary-dark);
    }

    .page-head{
        text-align:center;
        padding:12px 0 10px;
        margin-bottom:28px;
    }

    .page-head .badge{
        display:inline-block;
        background:rgba(99,132,17,.12);
        color:var(--primary-dark);
        font-size:13px;
        font-weight:700;
        padding:8px 14px;
        border-radius:999px;
        margin-bottom:14px;
    }

    .page-head h1{
        font-size:40px;
        line-height:1.2;
        margin-bottom:10px;
        color:#121826;
        font-weight:800;
    }

    .page-head p{
        color:var(--muted);
        font-size:16px;
        max-width:760px;
        margin:0 auto;
        line-height:1.8;
    }

    .table-wrap{
        background:#fff;
        border-radius:22px;
        box-shadow:var(--shadow);
        overflow:hidden;
        border:1px solid rgba(0,0,0,.04);
    }

    table{
        width:100%;
        border-collapse:collapse;
    }

    thead{
        background:#f8fafc;
    }

    th, td{
        padding:18px 16px;
        text-align:left;
        vertical-align:middle;
        border-bottom:1px solid #edf0f2;
    }

    th{
        font-size:14px;
        color:#475569;
        font-weight:700;
    }

    td{
        font-size:15px;
        color:#1f2937;
    }

    .news-title{
        font-weight:700;
        line-height:1.6;
        word-break:break-word;
    }

    .thumb{
        width:90px;
        height:60px;
        border-radius:10px;
        overflow:hidden;
        background:#eef2f7;
        border:1px solid #e5e7eb;
        display:flex;
        align-items:center;
        justify-content:center;
        color:#94a3b8;
        font-size:12px;
    }

    .thumb img{
        width:100%;
        height:100%;
        object-fit:cover;
        display:block;
    }

    .date-badge{
        display:inline-block;
        background:#f1f5f9;
        color:#475569;
        padding:7px 12px;
        border-radius:999px;
        font-size:13px;
        font-weight:600;
        white-space:nowrap;
    }

    .delete-btn{
        display:inline-block;
        background:var(--danger);
        color:#fff;
        padding:10px 14px;
        border-radius:10px;
        font-size:14px;
        font-weight:700;
        transition:.25s ease;
    }

    .delete-btn:hover{
        background:var(--danger-dark);
    }

    .empty-box{
        background:#fff;
        border-radius:22px;
        box-shadow:var(--shadow);
        padding:60px 30px;
        text-align:center;
        color:#6b7280;
    }

    .empty-box h2{
        color:#111827;
        font-size:28px;
        margin-bottom:10px;
    }

    .footer-note{
        text-align:center;
        color:#8a8f98;
        font-size:14px;
        margin-top:22px;
    }

    @media (max-width: 900px){
        .table-wrap{
            overflow-x:auto;
        }

        table{
            min-width:760px;
        }
    }

    @media (max-width: 768px){
        .page-wrap{
            padding:20px 14px 40px;
        }

        .page-head h1{
            font-size:30px;
        }

        .page-head p{
            font-size:15px;
        }

        .btn{
            width:100%;
        }

        .topbar{
            flex-direction:column;
            align-items:stretch;
        }
    }
</style>
</head>
<body>

<div class="page-wrap">

    <div class="topbar">
        <a href="admin_dashboard.php" class="btn btn-back">← กลับหน้าหลัก</a>
        <a href="admin_add_news.php" class="btn btn-add">+ เพิ่มข่าวสาร</a>
    </div>

    <div class="page-head">
        <div class="badge">Manage News</div>
        <h1>จัดการข่าวสาร</h1>
        <p>เลือกลบข่าวที่ไม่ต้องการออกจากระบบได้จากหน้านี้</p>
    </div>

    <?php if($result && $result->num_rows > 0): ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:90px;">รูป</th>
                        <th>หัวข้อข่าว</th>
                        <th style="width:180px;">วันที่โพสต์</th>
                        <th style="width:120px;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="thumb">
                                    <?php if(!empty($row["image"])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($row["image"]); ?>" alt="">
                                    <?php else: ?>
                                        ไม่มีรูป
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="news-title">
                                    <?php echo htmlspecialchars($row["title"]); ?>
                                </div>
                            </td>
                            <td>
                                <span class="date-badge">
                                    <?php echo date("d/m/Y H:i", strtotime($row["created_at"])); ?>
                                </span>
                            </td>
                            <td>
                                <a 
                                    class="delete-btn" 
                                    href="delete_news.php?id=<?php echo (int)$row["id"]; ?>"
                                    onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบข่าวนี้?');"
                                >
                                    ลบข่าว
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-box">
            <h2>ยังไม่มีข่าวสาร</h2>
            <p>ขณะนี้ยังไม่มีรายการข่าวในระบบ</p>
        </div>
    <?php endif; ?>

    <div class="footer-note">
        © ระบบจัดการข่าวสาร
    </div>

</div>

</body>
</html>