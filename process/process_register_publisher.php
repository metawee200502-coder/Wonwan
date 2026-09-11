<?php
session_start();
require_once '../config/config.php';

// ต้องผ่านขั้นตอนที่ 1 มาก่อน (มีข้อมูลบัญชีอยู่ใน session)
if (!isset($_SESSION['publisher_register'])) {
    header('Location: ../pages/register_publisher.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/register_publisher_step2.php');
    exit;
}

$company_name    = trim($_POST['company_name'] ?? '');
$contact_name    = trim($_POST['contact_name'] ?? '');
$contact_email   = trim($_POST['contact_email'] ?? '');
$contact_position= trim($_POST['contact_position'] ?? '');
$contact_phone   = trim($_POST['contact_phone'] ?? '');

// 1) ตรวจสอบว่ากรอกครบทุกช่องที่จำเป็น
if ($company_name === '' || $contact_name === '' || $contact_email === '' || $contact_position === '' || $contact_phone === '') {
    header('Location: ../pages/register_publisher_step2.php?error=empty');
    exit;
}

// 2) ตรวจสอบรูปแบบอีเมลผู้ติดต่อ
if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../pages/register_publisher_step2.php?error=email');
    exit;
}

// 3) ตรวจสอบไฟล์เอกสารบริษัท (บังคับแนบ)
if (!isset($_FILES['company_document']) || $_FILES['company_document']['error'] === UPLOAD_ERR_NO_FILE) {
    header('Location: ../pages/register_publisher_step2.php?error=empty');
    exit;
}

if ($_FILES['company_document']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../pages/register_publisher_step2.php?error=upload');
    exit;
}

$allowedExt  = ['pdf', 'jpg', 'jpeg', 'png'];
$maxFileSize = 10 * 1024 * 1024; // 10MB

$originalName = $_FILES['company_document']['name'];
$fileExt      = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$fileSize     = $_FILES['company_document']['size'];
$tmpPath      = $_FILES['company_document']['tmp_name'];

if (!in_array($fileExt, $allowedExt, true)) {
    header('Location: ../pages/register_publisher_step2.php?error=filetype');
    exit;
}

if ($fileSize > $maxFileSize) {
    header('Location: ../pages/register_publisher_step2.php?error=filesize');
    exit;
}

// 4) ย้ายไฟล์ไปเก็บในโฟลเดอร์ uploads/publisher_documents/
$uploadDir = __DIR__ . '/uploads/publisher_documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$safeFileName   = uniqid('pubdoc_', true) . '.' . $fileExt;
$destinationPath = $uploadDir . $safeFileName;

if (!move_uploaded_file($tmpPath, $destinationPath)) {
    header('Location: ../pages/register_publisher_step2.php?error=upload');
    exit;
}

// path/url ที่จะเก็บลงคอลัมน์ company_document_url
$documentUrl = 'uploads/publisher_documents/' . $safeFileName;

// อัปโหลดโลโก้ (บังคับ)
$profileUrl = null;
if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
    header('Location: ../pages/register_publisher_step2.php?error=empty_logo');
    exit;
}
if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../pages/register_publisher_step2.php?error=upload_logo');
    exit;
}

$profExt = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
$profAllowed = ['jpg', 'jpeg', 'png'];
if (!in_array($profExt, $profAllowed, true)) {
    header('Location: ../pages/register_publisher_step2.php?error=filetype_logo');
    exit;
}
if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
    header('Location: ../pages/register_publisher_step2.php?error=filesize_logo');
    exit;
}

$profUploadDir = __DIR__ . '/uploads/publisher_profiles/';
if (!is_dir($profUploadDir)) mkdir($profUploadDir, 0755, true);
$profFileName = uniqid('logo_', true) . '.' . $profExt;
if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $profUploadDir . $profFileName)) {
    $profileUrl = '/process/uploads/publisher_profiles/' . $profFileName;
} else {
    header('Location: ../pages/register_publisher_step2.php?error=upload_logo');
    exit;
}

try {
    $pdo->beginTransaction();

    // 5) สร้างบัญชีผู้ใช้ (users) — บทบาทเป็นผู้ดูแลสำนักพิมพ์
    $stmtUser = $pdo->prepare(
        'INSERT INTO users (email, display_name, password_hash, role, notify_preference, created_at)
         VALUES (:email, :display_name, :password_hash, :role, :notify_preference, NOW())'
    );
    $stmtUser->execute([
        ':email'             => $_SESSION['publisher_register']['email'],
        ':display_name'      => $_SESSION['publisher_register']['fullname'],
        ':password_hash'     => $_SESSION['publisher_register']['password_hash'],
        ':role'               => 'publisher_admin',
        ':notify_preference' => 'email',
    ]);
    $newUserId = $pdo->lastInsertId();

    // 6) สร้างข้อมูลสำนักพิมพ์ (publishers) — สถานะเริ่มต้นเป็น "pending" รอตรวจสอบ
    $stmtPublisher = $pdo->prepare(
        'INSERT INTO publishers
            (company_name, description, profile_image, company_document_url,
             contact_name, contact_email, contact_position, contact_phone,
             followers_count, status, created_at)
         VALUES
            (:company_name, NULL, :profile_image, :company_document_url,
             :contact_name, :contact_email, :contact_position, :contact_phone,
             0, :status, NOW())'
    );
    $stmtPublisher->execute([
        ':company_name'          => $company_name,
        ':profile_image'         => $profileUrl,
        ':company_document_url'  => $documentUrl,
        ':contact_name'          => $contact_name,
        ':contact_email'         => $contact_email,
        ':contact_position'      => $contact_position,
        ':contact_phone'         => $contact_phone,
        ':status'                => 'pending',
    ]);
    $newPublisherId = $pdo->lastInsertId();

    // 7) เชื่อมผู้ใช้เข้ากับสำนักพิมพ์ (publisher_staff)
    $stmtStaff = $pdo->prepare(
        'INSERT INTO publisher_staff (user_id, publisher_id) VALUES (:user_id, :publisher_id)'
    );
    $stmtStaff->execute([
        ':user_id'      => $newUserId,
        ':publisher_id' => $newPublisherId,
    ]);

    $pdo->commit();

    // เคลียร์ข้อมูลขั้นตอนที่ 1 ออกจาก session
    unset($_SESSION['publisher_register']);

    header('Location: ../pages/register_publisher_step2.php?success=1');
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    // ลบไฟล์ที่อัปโหลดไปแล้วถ้าบันทึกฐานข้อมูลไม่สำเร็จ
    if (file_exists($destinationPath)) {
        unlink($destinationPath);
    }
    header('Location: ../pages/register_publisher_step2.php?error=server');
    exit;
}
