<?php
session_start();
require_once __DIR__.'/../config/config.php';
if(empty($_SESSION['user_id'])){header('Location:login.php');exit;}
$role=$_SESSION['role']??'';
if(!in_array($role,['publisher_admin','shop_admin'],true)){header('Location:home.php');exit;}
$active_menu='support';$seller_name='';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>แชตและการสนับสนุน - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:var(--font-family);background:#fff;color:var(--text-dark);min-height:100vh}
        .main-content{min-height:100vh;padding:52px 42px}.page-title{font-size:30px;color:var(--secondary-color);margin:0 0 25px}
        .support-card{max-width:900px;border:1px solid #e2c8d1;border-radius:14px;background:#fff;padding:28px;box-shadow:0 2px 8px #0000000d}
        .support-card h2{color:var(--secondary-color);margin-top:0}.support-card p{color:var(--text-muted);line-height:1.7}
        .support-link{display:inline-block;margin-top:12px;padding:11px 18px;border-radius:8px;background:#d18aa7;color:#fff;text-decoration:none}
        @media(max-width:800px){.main-content{padding:30px 16px}}
    </style>
</head>
<body>
<?php include __DIR__.'/../components/sidebar.php'; ?>
<main class="main-content">
    <h1 class="page-title">แชตและการสนับสนุน</h1>
    <section class="support-card">
        <h2>ศูนย์ช่วยเหลือสำหรับร้านค้าและสำนักพิมพ์</h2>
        <p>หากท่านมีข้อสงสัยเกี่ยวกับการจัดการสต็อก รายงานยอดขาย ค่า GP หรือต้องการความช่วยเหลือจากเจ้าหน้าที่ Super Admin สามารถเปิด Live Chat พูดคุยได้ทันที</p>
        <div style="margin-top: 18px; display: flex; gap: 12px; flex-wrap: wrap;">
            <button type="button" class="support-link" onclick="if(window.wanawanChat){wanawanChat.openChat();}else{alert('กำลังเปิด Live Chat...');}" style="background: linear-gradient(135deg, #b05467, #C97B8B); border: none; cursor: pointer; font-size: 14px; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                <span>🌸</span> เปิด Live Chat ติดต่อ Super Admin / บอทน้องพิงค์
            </button>
            <a class="support-link" href="admin_notifications.php" style="background: #f3e5ea; color: #87384a; border: 1px solid #e0bac5;">เปิดการแจ้งเตือนหลังบ้าน</a>
        </div>
    </section>
</main>
</body>
</html>
