<?php
include 'config.php';

$result = $conn->query("SELECT * FROM news ORDER BY id DESC");

$pageTitle = "จัดการข่าวสาร";
$activeMenu = "news_manage";
include 'admin_layout_top.php';
?>

<style>
.page-wrap-local{
    width:100%;
    max-width:1150px;
    margin:0 auto;
}
.page-head-local{
    text-align:center;
    padding:12px 0 10px;
    margin-bottom:28px;
}
.page-head-local .badge{
    display:inline-block;
    background:rgba(99,132,17,.12);
    color:#4f6b0d;
    font-size:13px;
    font-weight:700;
    padding:8px 14px;
    border-radius:999px;
    margin-bottom:14px;
}
.page-head-local h1{
    font-size:40px;
    line-height:1.2;
    margin-bottom:10px;
    color:#121826;
    font-weight:800;
}
.page-head-local p{
    color:#6b7280;
    font-size:16px;
    max-width:760px;
    margin:0 auto;
    line-height:1.8;
}
.table-wrap-local{
    background:#fff;
    border-radius:22px;
    box-shadow:0 10px 30px rgba(0,0,0,.07);
    overflow:hidden;
    border:1px solid rgba(0,0,0,.04);
}
.table-local{
    width:100%;
    border-collapse:collapse;
}
.table-local thead{
    background:#f8fafc;
}
.table-local th,
.table-local td{
    padding:18px 16px;
    text-align:left;
    vertical-align:middle;
    border-bottom:1px solid #edf0f2;
}
.table-local th{
    font-size:14px;
    color:#475569;
    font-weight:700;
}
.table-local td{
    font-size:15px;
    color:#1f2937;
}
.news-title-local{
    font-weight:700;
    line-height:1.6;
    word-break:break-word;
}
.thumb-local{
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
.thumb-local img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
}
.date-badge-local{
    display:inline-block;
    background:#f1f5f9;
    color:#475569;
    padding:7px 12px;
    border-radius:999px;
    font-size:13px;
    font-weight:600;
    white-space:nowrap;
}
.delete-btn-local{
    display:inline-block;
    background:#dc2626;
    color:#fff;
    padding:10px 14px;
    border-radius:10px;
    font-size:14px;
    font-weight:700;
    transition:.25s ease;
    text-decoration:none;
}
.delete-btn-local:hover{
    background:#b91c1c;
}
.empty-box-local{
    background:#fff;
    border-radius:22px;
    box-shadow:0 10px 30px rgba(0,0,0,.07);
    padding:60px 30px;
    text-align:center;
    color:#6b7280;
}
.empty-box-local h2{
    color:#111827;
    font-size:28px;
    margin-bottom:10px;
}
@media (max-width: 900px){
    .table-wrap-local{overflow-x:auto}
    .table-local{min-width:760px}
}
@media (max-width: 768px){
    .page-head-local h1{font-size:30px}
    .page-head-local p{font-size:15px}
}
</style>

<div class="page-wrap-local">
    <div class="page-head-local">
        <div class="badge">Manage News</div>
        <h1>จัดการข่าวสาร</h1>
        <p>เลือกลบข่าวที่ไม่ต้องการออกจากระบบได้จากหน้านี้</p>
    </div>

    <?php if($result && $result->num_rows > 0): ?>
        <div class="table-wrap-local">
            <table class="table-local">
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
                                <div class="thumb-local">
                                    <?php if(!empty($row["image"])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($row["image"]); ?>" alt="">
                                    <?php else: ?>
                                        ไม่มีรูป
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="news-title-local">
                                    <?php echo htmlspecialchars($row["title"]); ?>
                                </div>
                            </td>
                            <td>
                                <span class="date-badge-local">
                                    <?php echo date("d/m/Y H:i", strtotime($row["created_at"])); ?>
                                </span>
                            </td>
                            <td>
                                <a 
                                    class="delete-btn-local" 
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
        <div class="empty-box-local">
            <h2>ยังไม่มีข่าวสาร</h2>
            <p>ขณะนี้ยังไม่มีรายการข่าวในระบบ</p>
        </div>
    <?php endif; ?>
</div>

<?php
$conn->close();
include 'admin_layout_bottom.php';
?>