<?php
session_start();
require_once '../config/config.php';

// รับเฉพาะ method POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/register_publisher.php');
    exit;
}

$fullname         = trim($_POST['fullname'] ?? '');
$email            = trim($_POST['email'] ?? '');
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 1) ตรวจสอบว่ากรอกครบทุกช่อง
if ($fullname === '' || $email === '' || $password === '' || $confirm_password === '') {
    header('Location: ../pages/register_publisher.php?error=empty');
    exit;
}

// 2) ตรวจสอบรูปแบบอีเมล
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../pages/register_publisher.php?error=email');
    exit;
}

// 3) ตรวจสอบความยาวรหัสผ่าน
if (strlen($password) < 8) {
    header('Location: ../pages/register_publisher.php?error=short');
    exit;
}

// 4) ตรวจสอบรหัสผ่านตรงกัน
if ($password !== $confirm_password) {
    header('Location: ../pages/register_publisher.php?error=mismatch');
    exit;
}

try {
    // 5) ตรวจสอบว่าอีเมลนี้ถูกใช้ไปแล้วหรือยัง
    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        header('Location: ../pages/register_publisher.php?error=exists');
        exit;
    }

    // เก็บข้อมูลขั้นตอนที่ 1 ไว้ใน session (แฮชรหัสผ่านไว้เลยเพื่อความปลอดภัย)
    $_SESSION['publisher_register'] = [
        'fullname'      => $fullname,
        'email'         => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ];

    header('Location: ../pages/register_publisher_step2.php');
    exit;

} catch (PDOException $e) {
    header('Location: ../pages/register_publisher.php?error=server');
    exit;
}
