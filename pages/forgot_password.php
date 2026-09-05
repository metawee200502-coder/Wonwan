<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/password_reset.php';

if (empty($_SESSION['forgot_password_csrf'])) {
    $_SESSION['forgot_password_csrf'] = bin2hex(random_bytes(24));
}

$submitted = false;
$error = '';
$developmentUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!hash_equals($_SESSION['forgot_password_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'คำขอหมดอายุ กรุณาลองใหม่อีกครั้ง';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'กรุณากรอกอีเมลให้ถูกต้อง';
    } else {
        $submitted = true;
        $find = $pdo->prepare('SELECT user_id,email,COALESCE(NULLIF(display_name,\'\'),email) display_name FROM users WHERE email=? LIMIT 1');
        $find->execute([$email]);
        $user = $find->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $recent = $pdo->prepare('SELECT COUNT(*) FROM password_reset_tokens WHERE user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 60 SECOND)');
            $recent->execute([(int) $user['user_id']]);
            if ((int) $recent->fetchColumn() === 0) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=? AND (used_at IS NOT NULL OR expires_at<NOW())')
                    ->execute([(int) $user['user_id']]);
                $save = $pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,requested_ip,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))');
                $save->execute([
                    (int) $user['user_id'],
                    hash('sha256', $token),
                    substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                ]);
                $resetUrl = passwordResetBaseUrl() . '/pages/reset_password.php?token=' . rawurlencode($token);
                $mailSent = sendPasswordResetEmail((string) $user['email'], (string) $user['display_name'], $resetUrl);
                if (!$mailSent && passwordResetIsLocal()) {
                    $developmentUrl = $resetUrl;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ลืมรหัสผ่าน - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        *{box-sizing:border-box}body{min-height:100vh;margin:0}.reset-page{min-height:calc(100vh - 76px);display:grid;place-items:center;padding:32px 18px;background:url('../assets/image/Background.png') center/cover}.reset-card{width:min(440px,100%);padding:38px;background:#fff;border-radius:18px;box-shadow:0 10px 30px #0002}.reset-card h1{margin:0 0 9px;color:var(--secondary-color);font-size:28px}.reset-card>p{color:var(--text-muted);line-height:1.65}.field{margin-top:22px}.field label{display:block;margin-bottom:7px;font-weight:600}.field input{width:100%;height:48px;padding:0 14px;border:1px solid #ccd3d0;border-radius:9px;font:inherit}.submit,.dev-link{width:100%;min-height:48px;margin-top:18px;border:0;border-radius:9px;display:grid;place-items:center;padding:10px;background:var(--secondary-color);color:#fff;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.back{display:block;margin-top:18px;text-align:center;color:var(--primary-color);text-decoration:none}.notice{margin-top:18px;padding:14px;border-radius:9px;line-height:1.55}.notice.ok{background:#e8f4ec;color:#28633d}.notice.bad{background:#fde7e3;color:#a64139}.dev{margin-top:15px;padding:13px;border:1px dashed #d6a68f;border-radius:9px;background:#fff8f3;color:#77594c;font-size:13px}.dev-link{margin-top:10px;background:#d28f86}
    </style>
</head>
<body>
<?php require __DIR__ . '/../components/header.php'; ?>
<main class="reset-page">
    <section class="reset-card">
        <h1>ลืมรหัสผ่าน</h1>
        <p>กรอกอีเมลที่ใช้สมัครสมาชิก เราจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ให้คุณ</p>
        <?php if ($error): ?><div class="notice bad"><?=htmlspecialchars($error)?></div><?php endif; ?>
        <?php if ($submitted && !$error): ?>
            <div class="notice ok">หากอีเมลนี้มีบัญชีอยู่ในระบบ เราได้ส่งลิงก์ตั้งรหัสผ่านใหม่แล้ว กรุณาตรวจสอบกล่องจดหมายและอีเมลขยะ</div>
            <?php if ($developmentUrl): ?>
                <div class="dev">โหมดทดสอบบนเครื่อง: ระบบอีเมลของ Laragon อาจยังไม่ได้ตั้งค่า คุณสามารถเปิดลิงก์ทดสอบด้านล่างได้<a class="dev-link" href="<?=htmlspecialchars($developmentUrl)?>">ตั้งรหัสผ่านใหม่</a></div>
            <?php endif; ?>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['forgot_password_csrf'])?>">
                <div class="field"><label for="email">อีเมล</label><input id="email" name="email" type="email" required autocomplete="email" placeholder="example@email.com" value="<?=htmlspecialchars($_POST['email']??'')?>"></div>
                <button class="submit" type="submit">ส่งลิงก์ตั้งรหัสผ่านใหม่</button>
            </form>
        <?php endif; ?>
        <a class="back" href="/pages/login.php">← กลับไปหน้าเข้าสู่ระบบ</a>
    </section>
</main>
<?php require __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
