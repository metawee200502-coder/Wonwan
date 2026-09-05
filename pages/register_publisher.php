<?php
// เริ่ม session ใหม่ทุกครั้งที่เข้าหน้านี้ เพื่อเคลียร์ข้อมูลการสมัครสำนักพิมพ์เก่า (ถ้ามี)
session_start();
unset($_SESSION['publisher_register']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสำนักพิมพ์พันธมิตร - วนวรรณ</title>

    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">

    <style>
        /* =========================================
           สไตล์สำหรับหน้า สมัครสำนักพิมพ์พันธมิตร (ขั้นตอนที่ 1)
           ใช้โครงสร้างเดียวกับหน้า register_reader.php เพื่อความกลมกลืนกัน
           ========================================= */
        .signup-section {
            min-height: 100vh;
            padding-top: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            background-color: var(--bg-color);

            background-image: url('../assets/image/Background.png');
            background-size: cover;
            background-position: center;
        }

        .signup-card {
            background-color: var(--card-bg);
            padding: 40px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 380px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
            text-align: center;
        }

        .signup-logo {
            width: 56px;
            height: 56px;
            margin: 0 auto 10px;
            display: block;
        }

        .signup-title {
            font-size: var(--text-xl);
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 20px;
        }

        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .step-dot {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--text-xs);
            font-weight: 600;
        }

        .step-dot.active {
            background-color: var(--primary-color);
            color: var(--text-light);
        }

        .step-dot.inactive {
            background-color: var(--accent-pink-light);
            color: var(--text-muted);
        }

        .step-line {
            width: 30px;
            height: 2px;
            background-color: var(--border-color);
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
            color: var(--text-dark);
        }

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
            padding: 12px 40px 12px 40px;
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

        .btn-primary {
            background-color: var(--primary-color);
            color: var(--text-light);
            border: none;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            font-size: var(--text-md);
            font-weight: 600;
            transition: background-color 0.3s;
            font-family: var(--font-family);
            margin-top: 10px;
        }

        .btn-block {
            width: 100%;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
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

        .form-error {
            color: var(--accent-red);
            text-align: center;
            margin-bottom: 15px;
            font-size: var(--text-xs);
        }
    </style>
    <link rel="stylesheet" href="../assets/css/auth-layout.css">
</head>
<body>

    <?php include '../components/header.php'; ?>

    <main class="signup-section">

        <div class="signup-card">
            <img src="../assets/image/minilogo.png" alt="โลโก้ วนวรรณ" class="signup-logo">
            <h2 class="signup-title">สมัครสำนักพิมพ์พันธมิตร</h2>

            <div class="step-indicator">
                <span class="step-dot active">1</span>
                <span class="step-line"></span>
                <span class="step-dot inactive">2</span>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <p class="form-error">
                    <?php
                        $errors = [
                            'empty'    => 'กรุณากรอกข้อมูลให้ครบทุกช่อง',
                            'email'    => 'รูปแบบอีเมลไม่ถูกต้อง',
                            'mismatch' => 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน',
                            'short'    => 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร',
                            'exists'   => 'อีเมลนี้มีผู้ใช้งานแล้ว',
                            'server'   => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง',
                        ];
                        $code = $_GET['error'];
                        echo htmlspecialchars($errors[$code] ?? $errors['server']);
                    ?>
                </p>
            <?php endif; ?>

            <form action="../process/process_register_publisher_step1.php" method="POST">
                <div class="input-group">
                    <label for="fullname">ชื่อ-สกุล</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input type="text" id="fullname" name="fullname" placeholder="กรอกชื่อ-สกุลของคุณ"
                               value="<?php echo isset($_SESSION['publisher_register']['fullname']) ? htmlspecialchars($_SESSION['publisher_register']['fullname']) : ''; ?>" required>
                    </div>
                </div>

                <div class="input-group">
                    <label for="email">อีเมล</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                        <input type="password" id="password" name="password" placeholder="กรอกรหัสของคุณ" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', 'eyeIcon1')">
                            <svg id="eyeIcon1" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="input-group">
                    <label for="confirm_password">ยืนยันรหัสผ่าน</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="กรอกรหัสของคุณ" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', 'eyeIcon2')">
                            <svg id="eyeIcon2" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary btn-block">ถัดไป</button>
            </form>

            <p class="signup-text">มีบัญชีอยู่แล้วใช่ไหม? <a href="login.php">เข้าสู่ระบบ</a></p>
        </div>

    </main>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>

</body>
</html>
