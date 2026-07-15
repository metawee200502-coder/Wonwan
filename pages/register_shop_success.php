<?php
session_start();

if (empty($_SESSION['shop_register_success'])) {
    header('Location: register.php');
    exit;
}
unset($_SESSION['shop_register_success']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนสำเร็จ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .success-section {
            min-height: 100vh;
            padding-top: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-color);
        }
        .success-card {
            background-color: var(--card-bg);
            padding: 50px 40px;
            border-radius: var(--radius-lg);
            max-width: 420px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        .success-icon { color: var(--accent-green); margin-bottom: 15px; }
        .success-title { font-size: var(--text-xl); color: var(--secondary-color); font-weight: 700; margin-bottom: 10px; }
        .success-desc { font-size: var(--text-sm); color: var(--text-muted); margin-bottom: 25px; }
        .btn-primary {
            display: inline-block;
            padding: 12px 28px;
            border-radius: var(--radius-md);
            background-color: var(--primary-color);
            color: var(--text-light);
            text-decoration: none;
            font-weight: 600;
        }
        .btn-primary:hover { background-color: var(--primary-hover); }
    </style>
    <link rel="stylesheet" href="../assets/css/auth-layout.css">
</head>
<body>
    <?php include '../components/header.php'; ?>
    <main class="success-section">
        <div class="success-card">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9 12l2 2 4-4"></path>
                </svg>
            </div>
            <h1 class="success-title">ลงทะเบียนร้านค้าสำเร็จ</h1>
            <p class="success-desc">ระบบได้รับข้อมูลของคุณแล้ว บัญชีร้านค้าของคุณอยู่ในสถานะ "รอตรวจสอบ" ทีมงานจะดำเนินการตรวจสอบและแจ้งผลผ่านอีเมลภายใน 1-3 วันทำการ</p>
            <a href="login.php" class="btn-primary">เข้าสู่ระบบ</a>
        </div>
    </main>
</body>
</html>
