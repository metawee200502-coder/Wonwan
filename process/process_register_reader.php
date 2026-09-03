<?php
session_start();
require '../config/config.php'; // ได้ตัวแปร $pdo (PDO) มาใช้งาน

// อนุญาตเฉพาะ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/register_reader.php');
    exit;
}

// รับค่าจากฟอร์ม และตัดช่องว่างหน้า-หลัง
$fullname          = trim($_POST['fullname'] ?? '');
$email             = trim($_POST['email'] ?? '');
$password          = $_POST['password'] ?? '';
$confirm_password  = $_POST['confirm_password'] ?? '';

// 1) ตรวจสอบว่ากรอกครบทุกช่อง
if ($fullname === '' || $email === '' || $password === '' || $confirm_password === '') {
    header('Location: ../pages/register_reader.php?error=empty');
    exit;
}

// 2) ตรวจสอบรูปแบบอีเมล
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../pages/register_reader.php?error=email');
    exit;
}

// 3) ตรวจสอบความยาวรหัสผ่านขั้นต่ำ
if (strlen($password) < 8) {
    header('Location: ../pages/register_reader.php?error=short');
    exit;
}

// 4) ตรวจสอบว่ารหัสผ่านและยืนยันรหัสผ่านตรงกัน
if ($password !== $confirm_password) {
    header('Location: ../pages/register_reader.php?error=mismatch');
    exit;
}

try {
    // 5) ตรวจสอบว่าอีเมลนี้เคยสมัครไว้แล้วหรือยัง
    $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);

    if ($check->fetch()) {
        header('Location: ../pages/register_reader.php?error=exists');
        exit;
    }

    // 6) เข้ารหัสรหัสผ่านด้วย bcrypt
    $password_hash = password_hash($password, PASSWORD_BCRYPT);

    // 7) บันทึกข้อมูลสมาชิกใหม่ลงฐานข้อมูล
    $insert = $pdo->prepare(
        'INSERT INTO users (email, display_name, password_hash, role, created_at, notify_preference)
         VALUES (:email, :display_name, :password_hash, :role, NOW(), :notify_preference)'
    );

    $insert->execute([
        ':email'             => $email,
        ':display_name'      => $fullname,
        ':password_hash'     => $password_hash,
        ':role'              => 'reader',
        ':notify_preference' => 'email',
    ]);

    $_SESSION['needs_location'] = true;

    header('Location: ../pages/register_reader.php?success=1');
    exit;

} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการสมัครสมาชิก: " . $e->getMessage());
}
?>