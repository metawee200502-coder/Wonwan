<?php session_start();
if (!empty($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'super_admin') {
        header('Location: /admin/index.php');
    } elseif (($_SESSION['role'] ?? '') === 'publisher_admin' && !empty($_SESSION['publisher_id'])) {
        header('Location: /pages/dashboard.php');
    } elseif (($_SESSION['role'] ?? '') === 'shop_admin' && !empty($_SESSION['shop_id'])) {
        header('Location: /pages/dashboard.php');
    } else {
        header('Location: /pages/home.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - วนวรรณ</title>
    
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">

    <style>
        /* =========================================
           สไตล์สำหรับหน้า LOGIN
           ========================================= */
        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        .site-header {
            flex-shrink: 0;
        }

        .login-section {
            flex: 1;
            min-height: 0;
            padding-top: clamp(20px, 5vh, 70px);
            padding-bottom: clamp(20px, 5vh, 70px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-left: 80px;
            padding-right: 80px;
            box-sizing: border-box;

            /* ภาพพื้นหลัง เต็มจอ ไม่มี overlay ทับ */
            background-image: url('../assets/image/Background.png');
            background-size: cover;
            background-position: center;
            position: relative;
        }

        /* ----- ฝั่งซ้าย: ข้อความต้อนรับ ----- */
        .login-intro {
            max-width: 420px;
            z-index: 1;
        }

        .login-intro h1 {
            font-size: 42px;
            color: var(--secondary-color); /* เขียวน้ำทะเล ให้เด่นตามหัวข้อบนหน้าโฮม */
            margin-bottom: 15px;
            font-weight: 700;
        }

        .login-intro p {
            font-size: var(--text-md);
            color: var(--text-dark);
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .intro-divider {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .intro-divider span {
            flex: 1;
            height: 1px;
            background-color: var(--secondary-color);
            opacity: 0.3;
        }

        .intro-divider img {
            width: 28px;
            height: 28px;
        }

        /* ----- ฝั่งขวา: การ์ด login ----- */
        .login-card {
            background-color: var(--card-bg);
            padding: 40px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 380px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .login-logo {
            width: 60px;
            height: 60px;
            margin: 0 auto 10px;
            display: block;
        }

        .login-title {
            font-size: var(--text-xl);
            color: var(--primary-color);
            margin-bottom: 20px;
            text-align: center;
        }

        .input-group {
            margin-bottom: 15px;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-size: var(--text-sm);
            margin-bottom: 5px;
            font-weight: 500;
        }

        /* ----- input ที่มีไอคอนอยู่ข้างใน ----- */
        .input-wrapper {
            position: relative;
        }

        .input-wrapper .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: var(--font-family);
            font-size: var(--text-sm);
            box-sizing: border-box;
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: var(--secondary-color);
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            cursor: pointer;
            background: none;
            border: none;
            display: flex;
            align-items: center;
            padding: 0;
        }

        .toggle-password:hover {
            color: var(--primary-color);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            font-size: var(--text-xs);
            margin-bottom: 20px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--text-light);
            border: none;
            padding: 12px;
            border-radius: 10px; /* ปุ่มโค้งมนแบบในภาพ */
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color 0.3s;
            font-family: var(--font-family);
        }

        .btn-block {
            width: 100%;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-social {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px;
            background-color: var(--card-bg);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            font-family: var(--font-family);
            font-size: var(--text-sm);
            font-weight: 500;
            margin-top: 12px;
            transition: background-color 0.3s;
        }

        .btn-social:hover {
            background-color: var(--accent-pink-light);
        }

        .btn-social img {
            width: 18px;
            height: 18px;
        }

        .signup-text {
            margin-top: 20px;
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .signup-text a {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: none;
        }

        /* ----- responsive: จอเล็กให้ซ้อนกัน ----- */
        @media (max-width: 900px) {
            .login-section {
                flex-direction: column;
                justify-content: center;
                gap: 20px;
                padding: 20px;
                text-align: center;
            }
            .login-card {
                padding: 24px;
            }
            .login-intro {
                display: none;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/auth-layout.css">
</head>
<body>

    <?php include '../components/header.php'; ?>

    <main class="login-section">

        <!-- ฝั่งซ้าย: ข้อความต้อนรับ -->
        <div class="login-intro">
            <h1>วนวรรณ</h1>
            <p>พื้นที่แลกเปลี่ยนหนังสือ<br>ที่ทุกเล่มมีคุณค่าอีกครั้ง</p>
            <div class="intro-divider">
                <span></span>
                <img src="../assets/image/minilogo.png" alt="ดอกบัว">
                <span></span>
            </div>
        </div>

        <!-- ฝั่งขวา: การ์ด login -->
        <div class="login-card">
            <img src="../assets/image/minilogo.png" alt="โลโก้ วนวรรณ" class="login-logo">
            <h2 class="login-title">เข้าสู่ระบบ</h2>

            <?php if (isset($_GET['error'])): ?>
                <p style="color: var(--accent-red); text-align: center; margin-bottom: 15px; font-size: var(--text-xs);">
                    อีเมลหรือรหัสผ่านไม่ถูกต้อง หรือยังไม่ได้สมัครสมาชิก
                </p>
            <?php endif; ?>

            <?php if (isset($_GET['google_error'])): ?>
                <p style="color: var(--accent-red); text-align: center; margin-bottom: 15px; font-size: var(--text-xs);">
                    ไม่สามารถเข้าสู่ระบบด้วย Google ได้ กรุณาลองอีกครั้ง
                </p>
            <?php endif; ?>

            <form action="../process/process_login.php" method="POST">
                <div class="input-group">
                    <label for="email">อีเมล</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16v16H4z" opacity="0"></path>
                                <path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6z"></path>
                                <path d="m22 6-10 7L2 6"></path>
                            </svg>
                        </span>
                        <input type="email" id="email" name="email" placeholder="กรอกอีเมลของคุณ" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">รหัสผ่าน</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" placeholder="กรอกรหัสของคุณ" required>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> จดจำฉันไว้
                    </label>
                    <a href="/pages/forgot_password.php" class="forgot-password">ลืมรหัสผ่าน?</a>
                </div>

                <button type="submit" class="btn-primary btn-block">เข้าสู่ระบบ</button>
            </form>

            <button type="button" class="btn-social" onclick="window.location.href='/pages/google_login.php'">
                <img src="../assets/image/gglogo.png" alt="Google"> เข้าสู่ระบบด้วย Google
            </button>

            <p class="signup-text">ยังไม่มีบัญชีใช่ไหม? <a href="register.php">สมัครสมาชิก</a></p>
        </div>

    </main>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
        }
    </script>

</body>
</html>
