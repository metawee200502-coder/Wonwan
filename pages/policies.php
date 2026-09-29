<?php
declare(strict_types=1);
session_start();
$breadcrumbs = [
    ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'],
    ['label' => 'ข้อกำหนดและนโยบายความเป็นส่วนตัว', 'url' => '']
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อกำหนดและนโยบาย - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        body {
            background-color: var(--bg-color);
        }
        .policies-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 40px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.04);
            font-family: var(--font-family);
        }
        .policies-header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 30px;
        }
        .policies-header h1 {
            color: var(--secondary-color);
            font-size: 26px;
            margin-bottom: 10px;
        }
        .policies-section {
            margin-bottom: 35px;
        }
        .policies-section h3 {
            color: var(--secondary-color);
            font-size: 18px;
            margin-bottom: 16px;
            border-left: 4px solid var(--primary-color);
            padding-left: 12px;
        }
        .policies-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .policies-list a {
            color: var(--text-dark);
            text-decoration: none;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            transition: 0.2s;
            background: #fff;
        }
        .policies-list a:hover {
            color: var(--primary-color);
            border-color: var(--primary-color);
            background: var(--card-peach);
        }
        .policies-list a svg {
            width: 16px;
            height: 16px;
            color: var(--primary-color);
            opacity: 0.7;
        }
        
        .policies-footer {
            margin-top: 50px;
            padding-top: 25px;
            border-top: 1px solid var(--border-color);
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
        }
        
        @media (max-width: 760px) {
            .policies-container {
                margin: 20px;
                padding: 25px 20px;
            }
            .policies-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../components/header.php'; ?>

    <main class="policies-container">
        <div class="policies-header">
            <img src="/assets/image/logo.png" alt="วนวรรณ" style="height: 55px; margin-bottom: 15px;">
            <h1>ข้อกำหนดและนโยบายความเป็นส่วนตัว</h1>
            <p style="color: var(--text-muted); font-size: 14px;">สังคมออนไลน์สำหรับนักรักการอ่าน ซื้อ ขาย แลกเปลี่ยนหนังสือ</p>
        </div>

        <div class="policies-section">
            <h3>เกี่ยวกับเรา</h3>
            <ul class="policies-list">
                <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg> About Us</a></li>
                <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Help Center</a></li>
                <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Careers</a></li>
            </ul>
        </div>

        <div class="policies-section">
            <h3>นโยบาย</h3>
            <ul class="policies-list">
                <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Terms of Service</a></li>
                <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Privacy Policy</a></li>
                <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Refund Policy</a></li>
            </ul>
        </div>

        <div class="policies-section">
            <h3>สำหรับผู้ใช้</h3>
            <ul class="policies-list">
                <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Seller Center</a></li>
                <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Merchant Guide</a></li>
                <li><a href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg> Advertising</a></li>
            </ul>
        </div>

        <div class="policies-footer">
            <p style="margin: 0 0 5px;">© <?= date('Y') ?> วนวรรณ – Buy Sell Exchange</p>
            <p style="margin: 0;">Supported by Wannawan Archive</p>
        </div>
    </main>

    <?php require __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
