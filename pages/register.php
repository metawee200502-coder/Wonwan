<?php session_start(); ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกประเภทบัญชี - วนวรรณ</title>

    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">

    <style>
        /* =========================================
           สไตล์สำหรับหน้า เลือกประเภทบัญชี
           ========================================= */
        .register-section {
            min-height: 100vh;
            padding-top: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            background-color: var(--bg-color);

            /* ภาพพื้นหลังจางๆ ให้บรรยากาศเดียวกับหน้า login */
            background-image: url('../assets/image/Background.png');
            background-size: cover;
            background-position: center;
        }

        .register-card {
            background-color: var(--card-bg);
            padding: 45px 40px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            text-align: center;
        }

        .register-logo {
            width: 56px;
            height: 56px;
            margin: 0 auto 15px;
            display: block;
        }

        .register-title {
            font-size: var(--text-xl);
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 30px;
        }

        /* ----- ตัวเลือกประเภทบัญชี ----- */
        .account-type-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 30px;
        }

        .account-type-option {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            background-color: var(--accent-pink-light);
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: background-color 0.25s, transform 0.2s;
        }

        .account-type-option:hover {
            background-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .account-type-option:hover .type-name,
        .account-type-option:hover .type-desc,
        .account-type-option:hover .type-arrow {
            color: var(--text-light);
        }

        .type-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 50%;
            background-color: var(--card-bg);
            color: var(--primary-color);
            transition: color 0.25s;
        }

        .type-icon svg {
            display: block;
        }

        .type-text {
            flex: 1;
            text-align: left;
        }

        .type-name {
            display: block;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-dark);
            transition: color 0.25s;
        }

        .type-desc {
            display: block;
            font-size: var(--text-xs);
            color: var(--text-muted);
            margin-top: 2px;
            transition: color 0.25s;
        }

        .type-arrow {
            color: var(--text-muted);
            display: flex;
            align-items: center;
            transition: color 0.25s;
        }

        /* ----- ตัวคั่นดอกบัว ----- */
        .register-divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .register-divider span {
            flex: 1;
            height: 1px;
            background-color: var(--secondary-color);
            opacity: 0.3;
        }

        .register-divider img {
            width: 26px;
            height: 26px;
        }

        /* ----- ปุ่มย้อนกลับ ----- */
        .btn-outline {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            background-color: var(--card-bg);
            color: var(--text-dark);
            font-family: var(--font-family);
            font-size: var(--text-md);
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-outline:hover {
            background-color: var(--accent-pink-light);
        }
    </style>
    <link rel="stylesheet" href="../assets/css/auth-layout.css">
</head>
<body>

    <?php include '../components/header.php'; ?>

    <main class="register-section">

        <div class="register-card">
            <img src="../assets/image/minilogo.png" alt="โลโก้ วนวรรณ" class="register-logo">
            <h1 class="register-title">เลือกประเภทบัญชี</h1>

            <div class="account-type-list">

                <!-- นักอ่านทั่วไป -->
                <a href="register_reader.php" class="account-type-option">
                    <span class="type-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </span>
                    <span class="type-text">
                        <span class="type-name">นักอ่านทั่วไป</span>
                        <span class="type-desc">ค้นหาหนังสือเล่มโปรด แลกเปลี่ยนและแชร์เรื่องราว</span>
                    </span>
                    <span class="type-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </span>
                </a>

                <!-- ร้านหนังสือมือสอง -->
                <a href="register_shop.php" class="account-type-option">
                    <span class="type-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 9l1.5-5h15L21 9"></path>
                            <path d="M3 9h18v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9z"></path>
                            <path d="M9 21V13h6v8"></path>
                        </svg>
                    </span>
                    <span class="type-text">
                        <span class="type-name">ร้านหนังสือมือสอง</span>
                        <span class="type-desc">แปลงหนังสือเป็นรายได้ พร้อมส่งต่อคุณค่า</span>
                    </span>
                    <span class="type-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </span>
                </a>

                <!-- สำนักพิมพ์ -->
                <a href="register_publisher.php" class="account-type-option">
                    <span class="type-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                        </svg>
                    </span>
                    <span class="type-text">
                        <span class="type-name">สำนักพิมพ์</span>
                        <span class="type-desc">ขยายช่องทางจำหน่ายและบริหารหนังสือ</span>
                    </span>
                    <span class="type-arrow">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </span>
                </a>

            </div>

            <div class="register-divider">
                <span></span>
                <img src="../assets/image/minilogo.png" alt="ดอกบัว">
                <span></span>
            </div>

            <button type="button" class="btn-outline" onclick="window.location.href='login.php'">ย้อนกลับ</button>
        </div>

    </main>

</body>
</html>
