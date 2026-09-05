<?php
session_start();
require_once '../config/config.php';

$errors = [];
$old = [
    'name'  => '',
    'email' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name             = trim($_POST['name'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $old['name']  = $name;
    $old['email'] = $email;

    // ---------- ตรวจสอบข้อมูล ----------
    if ($name === '') {
        $errors[] = 'กรุณากรอกชื่อ-สกุล';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณากรอกอีเมลให้ถูกต้อง';
    }

    if (strlen($password) < 6) {
        $errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    }

    // ---------- เช็คอีเมลซ้ำในตาราง users ----------
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'อีเมลนี้มีผู้ใช้งานแล้ว กรุณาใช้อีเมลอื่นหรือเข้าสู่ระบบ';
        }
    }

    // ---------- เก็บข้อมูลไว้ใน Session แล้วไปหน้าถัดไป ----------
    if (empty($errors)) {
        $_SESSION['shop_register'] = [
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ];
        header('Location: register_shop_step2.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิกร้านค้า - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">

    <style>
        .register-section {
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

        .register-card {
            background-color: var(--card-bg);
            padding: 40px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 380px;
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
            margin-bottom: 25px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: var(--text-sm);
            color: var(--text-dark);
            font-weight: 500;
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 14px 12px 40px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: var(--font-family);
            font-size: var(--text-md);
            box-sizing: border-box;
            background-color: var(--bg-color);
            color: var(--text-dark);
        }

        .input-wrapper input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            display: flex;
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
            padding: 0;
        }

        .btn-primary {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: var(--radius-md);
            background-color: var(--primary-color);
            color: var(--text-light);
            font-family: var(--font-family);
            font-size: var(--text-md);
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: background-color 0.25s;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .login-link {
            margin-top: 18px;
            font-size: var(--text-sm);
            color: var(--text-muted);
        }

        .login-link a {
            color: var(--secondary-color);
            font-weight: 600;
            text-decoration: none;
        }

        .error-box {
            background-color: #FBE4E1;
            color: var(--accent-red);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            font-size: var(--text-xs);
            text-align: left;
            margin-bottom: 16px;
        }

        .error-box ul {
            margin: 0;
            padding-left: 18px;
        }
    </style>
    <link rel="stylesheet" href="../assets/css/auth-layout.css">
</head>
<body>

    <?php include '../components/header.php'; ?>

    <main class="register-section">
        <div class="register-card">
            <img src="../assets/image/minilogo.png" alt="โลโก้ วนวรรณ" class="register-logo">
            <h1 class="register-title">สมัครสมาชิกร้านค้า</h1>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register_shop.php">

                <div class="form-group">
                    <label for="name">ชื่อ-สกุล</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input type="text" id="name" name="name" placeholder="กรอกชื่อ-สกุลของคุณ"
                               value="<?= htmlspecialchars($old['name']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">อีเมล</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16v16H4z" opacity="0"></path>
                                <path d="M22 6l-10 7L2 6"></path>
                                <path d="M2 6h20v12H2z"></path>
                            </svg>
                        </span>
                        <input type="email" id="email" name="email" placeholder="กรอกอีเมลของคุณ"
                               value="<?= htmlspecialchars($old['email']) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" placeholder="กรอกรหัสของคุณ" required>
                        <button type="button" class="toggle-password" onclick="togglePw('password', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">ยืนยันรหัสผ่าน</label>
                    <div class="input-wrapper">
                        <span class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="กรอกรหัสของคุณ" required>
                        <button type="button" class="toggle-password" onclick="togglePw('confirm_password', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">ถัดไป</button>
            </form>

            <p class="login-link">มีบัญชีอยู่แล้วใช่ไหม? <a href="login.php">เข้าสู่ระบบ</a></p>
        </div>
    </main>

    <script>
        function togglePw(id, btn) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
        }
    </script>

</body>
</html>
