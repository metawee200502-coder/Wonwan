<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['reset_password_csrf'])) {
    $_SESSION['reset_password_csrf'] = bin2hex(random_bytes(24));
}

$token = trim((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
$tokenHash = $token !== '' ? hash('sha256', $token) : '';
$find = $pdo->prepare('SELECT reset_id,user_id FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>=NOW() LIMIT 1');
$find->execute([$tokenHash]);
$reset = $find->fetch(PDO::FETCH_ASSOC);
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (!hash_equals($_SESSION['reset_password_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'คำขอหมดอายุ กรุณาเปิดลิงก์ใหม่อีกครั้ง';
    } elseif (!$reset) {
        $error = 'ลิงก์นี้หมดอายุหรือถูกใช้งานแล้ว';
    } elseif (strlen($password) < 8) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    } elseif ($password !== $confirm) {
        $error = 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT reset_id,user_id FROM password_reset_tokens WHERE reset_id=? AND used_at IS NULL AND expires_at>=NOW() FOR UPDATE');
            $lock->execute([(int) $reset['reset_id']]);
            $valid = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$valid) throw new RuntimeException('expired');
            $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $valid['user_id']]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL')
                ->execute([(int) $valid['user_id']]);
            $pdo->commit();
            $success = true;
            unset($_SESSION['reset_password_csrf']);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'ไม่สามารถตั้งรหัสผ่านใหม่ได้ กรุณาลองอีกครั้ง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ตั้งรหัสผ่านใหม่ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        *{box-sizing:border-box}.reset-page{min-height:calc(100vh - 76px);display:grid;place-items:center;padding:32px 18px;background:url('../assets/image/Background.png') center/cover}.reset-card{width:min(440px,100%);padding:38px;background:#fff;border-radius:18px;box-shadow:0 10px 30px #0002}.reset-card h1{margin:0 0 9px;color:var(--secondary-color);font-size:28px}.reset-card>p{color:var(--text-muted);line-height:1.65}.field{margin-top:18px}.field label{display:block;margin-bottom:7px;font-weight:600}.field input{width:100%;height:48px;padding:0 14px;border:1px solid #ccd3d0;border-radius:9px;font:inherit}.submit,.login-link{width:100%;min-height:48px;margin-top:20px;border:0;border-radius:9px;display:grid;place-items:center;padding:10px;background:var(--secondary-color);color:#fff;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.notice{margin-top:18px;padding:14px;border-radius:9px;line-height:1.55}.ok{background:#e8f4ec;color:#28633d}.bad{background:#fde7e3;color:#a64139}.expired{margin-top:20px;color:#9a4c45}.back{display:block;margin-top:18px;text-align:center;color:var(--primary-color);text-decoration:none}
    </style>
</head>
<body>
<?php require __DIR__ . '/../components/header.php'; ?>
<main class="reset-page">
    <section class="reset-card">
        <h1>ตั้งรหัสผ่านใหม่</h1>
        <?php if ($success): ?>
            <div class="notice ok">เปลี่ยนรหัสผ่านเรียบร้อยแล้ว คุณสามารถเข้าสู่ระบบด้วยรหัสผ่านใหม่ได้ทันที</div>
            <a class="login-link" href="/pages/login.php">ไปหน้าเข้าสู่ระบบ</a>
        <?php elseif (!$reset): ?>
            <p class="expired">ลิงก์นี้หมดอายุ ถูกใช้งานแล้ว หรือไม่ถูกต้อง กรุณาขอลิงก์ใหม่</p>
            <a class="login-link" href="/pages/forgot_password.php">ขอลิงก์ใหม่</a>
        <?php else: ?>
            <p>ตั้งรหัสผ่านใหม่อย่างน้อย 8 ตัวอักษร</p>
            <?php if ($error): ?><div class="notice bad"><?=htmlspecialchars($error)?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['reset_password_csrf'])?>">
                <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
                <div class="field"><label for="password">รหัสผ่านใหม่</label><input id="password" name="password" type="password" minlength="8" required autocomplete="new-password"></div>
                <div class="field"><label for="confirm_password">ยืนยันรหัสผ่านใหม่</label><input id="confirm_password" name="confirm_password" type="password" minlength="8" required autocomplete="new-password"></div>
                <button class="submit" type="submit">บันทึกรหัสผ่านใหม่</button>
            </form>
            <a class="back" href="/pages/login.php">ยกเลิก</a>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../components/footer.php'; ?>
</body>
</html>

