<?php
session_start();
require_once '../config/config.php';

// ต้องมีข้อมูลจากหน้าแรกก่อน ไม่งั้นเด้งกลับไปกรอกใหม่
if (!isset($_GET['success']) && empty($_SESSION['shop_register'])) {
    header('Location: register_shop.php');
    exit;
}

$step1 = $_SESSION['shop_register'] ?? []; // name, email, password_hash
$errors = [];

$old = [
    'id_card_number'      => '',
    'bank_account_name'   => '',
    'bank_name'           => '',
    'bank_account_number' => '',
];

// รายชื่อธนาคารสำหรับ dropdown
$bank_list = [
    'ธนาคารกสิกรไทย',
    'ธนาคารไทยพาณิชย์',
    'ธนาคารกรุงไทย',
    'ธนาคารกรุงเทพ',
    'ธนาคารกรุงศรีอยุธยา',
    'ธนาคารทหารไทยธนชาต (TTB)',
    'ธนาคารออมสิน',
    'ธนาคารยูโอบี',
    'ธนาคารซีไอเอ็มบี ไทย',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ยกเลิก -> ล้าง session แล้วกลับไปหน้าเลือกประเภทบัญชี
    if (isset($_POST['cancel'])) {
        unset($_SESSION['shop_register']);
        header('Location: register.php');
        exit;
    }

    $id_card_number      = trim($_POST['id_card_number'] ?? '');
    $bank_account_name    = trim($_POST['bank_account_name'] ?? '');
    $bank_name            = trim($_POST['bank_name'] ?? '');
    $bank_account_number  = trim($_POST['bank_account_number'] ?? '');

    $old['id_card_number']      = $id_card_number;
    $old['bank_account_name']   = $bank_account_name;
    $old['bank_name']           = $bank_name;
    $old['bank_account_number'] = $bank_account_number;

    // ---------- ตรวจสอบข้อมูล ----------
    if (!preg_match('/^\d{13}$/', $id_card_number)) {
        $errors[] = 'เลขประจำตัวประชาชนต้องเป็นตัวเลข 13 หลัก';
    }

    if ($bank_account_name === '') {
        $errors[] = 'กรุณากรอกชื่อบัญชี (Account Name)';
    }

    if ($bank_name === '' || !in_array($bank_name, $bank_list, true)) {
        $errors[] = 'กรุณาเลือกธนาคาร';
    }

    if (!preg_match('/^\d{10,15}$/', $bank_account_number)) {
        $errors[] = 'กรุณากรอกเลขที่บัญชีให้ถูกต้อง';
    }

    // ---------- ตรวจสอบไฟล์อัปโหลดบัตรประชาชน ----------
    $id_card_document_url = null;

    if (empty($_FILES['id_card_document']) || $_FILES['id_card_document']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'กรุณาอัปโหลดหลักฐานยืนยันตัวตน (บัตรประชาชน)';
    } elseif ($_FILES['id_card_document']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'เกิดข้อผิดพลาดระหว่างอัปโหลดไฟล์';
    } else {
        $file = $_FILES['id_card_document'];
        $allowed_ext  = ['jpg', 'jpeg', 'png', 'pdf'];
        $max_size     = 5 * 1024 * 1024; // 5MB
        $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext, true)) {
            $errors[] = 'รองรับไฟล์ JPG, PNG หรือ PDF เท่านั้น';
        } elseif ($file['size'] > $max_size) {
            $errors[] = 'ไฟล์ต้องมีขนาดไม่เกิน 5MB';
        } else {
            $upload_dir = __DIR__ . '/uploads/id_documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $new_filename = 'id_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destination  = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // path ที่จะเก็บลงฐานข้อมูล (relative path สำหรับใช้แสดงผลบนเว็บ)
                $id_card_document_url = 'uploads/id_documents/' . $new_filename;
            } else {
                $errors[] = 'ไม่สามารถบันทึกไฟล์ที่อัปโหลดได้';
            }
        }
    }

    // ---------- ตรวจสอบไฟล์อัปโหลดโลโก้ร้านค้า ----------
    $profileUrl = null;
    if (empty($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'กรุณาอัปโหลดรูปโปรไฟล์ หรือ โลโก้ร้านค้า';
    } elseif ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'เกิดข้อผิดพลาดระหว่างอัปโหลดรูปร้านค้า';
    } else {
        $file = $_FILES['profile_image'];
        $allowed_ext  = ['jpg', 'jpeg', 'png'];
        $max_size     = 2 * 1024 * 1024; // 2MB
        $ext          = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext, true)) {
            $errors[] = 'รูปร้านค้ารองรับไฟล์ JPG, PNG เท่านั้น';
        } elseif ($file['size'] > $max_size) {
            $errors[] = 'รูปร้านค้าต้องมีขนาดไม่เกิน 2MB';
        } else {
            $upload_dir = __DIR__ . '/../process/uploads/shop_profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $new_filename = 'shop_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destination  = $upload_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $profileUrl = 'uploads/shop_profiles/' . $new_filename;
            } else {
                $errors[] = 'ไม่สามารถบันทึกรูปร้านค้าที่อัปโหลดได้';
            }
        }
    }

    // ---------- บันทึกข้อมูลลงฐานข้อมูล ----------
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1) เพิ่มผู้ใช้ในตาราง users (role = shop_admin)
            $stmt = $pdo->prepare("
                INSERT INTO users (email, display_name, password_hash, role, created_at, notify_preference)
                VALUES (:email, :display_name, :password_hash, 'shop_admin', NOW(), 'email')
            ");
            $stmt->execute([
                ':email'         => $step1['email'],
                ':display_name'  => $step1['name'],
                ':password_hash' => $step1['password_hash'],
            ]);
            $user_id = $pdo->lastInsertId();

            // 2) เพิ่มร้านค้าในตาราง shops (สถานะเริ่มต้น = pending รออนุมัติ)
            $stmt = $pdo->prepare("
                INSERT INTO shops (
                    shop_name, description, id_card_number, id_card_document_url, profile_image,
                    bank_account_name, bank_name, bank_account_number,
                    followers_count, status, created_at
                ) VALUES (
                    :shop_name, NULL, :id_card_number, :id_card_document_url, :profile_image,
                    :bank_account_name, :bank_name, :bank_account_number,
                    0, 'pending', NOW()
                )
            ");
            $stmt->execute([
                ':shop_name'             => $step1['name'],
                ':id_card_number'        => $id_card_number,
                ':id_card_document_url'  => $id_card_document_url,
                ':profile_image'         => $profileUrl ? '/process/' . $profileUrl : null,
                ':bank_account_name'     => $bank_account_name,
                ':bank_name'             => $bank_name,
                ':bank_account_number'   => $bank_account_number,
            ]);
            $shop_id = $pdo->lastInsertId();

            // 3) ผูกผู้ใช้กับร้านค้าในตาราง shop_staff
            $stmt = $pdo->prepare("
                INSERT INTO shop_staff (user_id, shop_id) VALUES (:user_id, :shop_id)
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':shop_id' => $shop_id,
            ]);

            $pdo->commit();

            // เคลียร์ข้อมูลชั่วคราวและไปหน้าถัดไป
            // ✅ ใหม่
                unset($_SESSION['shop_register']);
                header('Location: register_shop_step2.php?success=1');
                exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            // ลบไฟล์ที่อัปโหลดไปแล้วถ้าบันทึกฐานข้อมูลไม่สำเร็จ
            if ($id_card_document_url && file_exists(__DIR__ . '/' . $id_card_document_url)) {
                unlink(__DIR__ . '/' . $id_card_document_url);
            }
            $errors[] = 'บันทึกข้อมูลไม่สำเร็จ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนร้านค้า - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">

    <style>
        .register-section {
            min-height: 100vh;
            padding-top: 90px;
            padding-bottom: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
            background-color: var(--bg-color);
            background-image: url('../assets/image/Background.png');
            background-size: cover;
            background-position: center;
        }

        .shop-register-card {
            background-color: var(--card-bg);
            padding: 35px 40px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 800px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        .shop-register-title {
            font-size: var(--text-xl);
            color: var(--secondary-color);
            font-weight: 700;
            margin-bottom: 25px;
        }

        .form-columns {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
        }

        .form-column {
            flex: 1;
            min-width: 260px;
        }

        .column-heading {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 18px;
        }

        .form-group {
            text-align: left;
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: var(--text-sm);
            color: var(--text-dark);
            font-weight: 500;
            margin-bottom: 6px;
        }

        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: var(--font-family);
            font-size: var(--text-md);
            box-sizing: border-box;
            background-color: var(--bg-color);
            color: var(--text-dark);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .upload-dropzone {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            background-color: var(--bg-color);
            text-align: center;
            padding: 28px 16px;
            cursor: pointer;
            transition: border-color 0.25s, background-color 0.25s;
            position: relative;
        }

        .upload-dropzone:hover,
        .upload-dropzone.dragover {
            border-color: var(--primary-color);
            background-color: var(--accent-pink-light);
        }

        .upload-dropzone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon-circle {
            width: 44px;
            height: 44px;
            margin: 0 auto 10px;
            border-radius: 50%;
            background-color: var(--accent-pink-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }

        .upload-dropzone-text {
            font-size: var(--text-sm);
            color: var(--text-dark);
            margin: 0;
        }

        .upload-dropzone-text .link-text {
            color: var(--primary-color);
            font-weight: 600;
            text-decoration: underline;
        }

        .upload-dropzone-hint {
            font-size: var(--text-xs);
            color: var(--text-muted);
            margin-top: 4px;
        }

        .upload-preview {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background-color: var(--bg-color);
        }

        .upload-preview.show {
            display: flex;
        }

        .upload-preview-name {
            font-size: var(--text-xs);
            color: var(--text-dark);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .upload-preview-remove {
            background: none;
            border: none;
            color: var(--accent-red);
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 0;
        }

        .info-note {
            display: flex;
            gap: 10px;
            background-color: var(--accent-pink-light);
            border-radius: var(--radius-md);
            padding: 12px 14px;
            font-size: var(--text-xs);
            color: var(--text-dark);
            margin-top: 4px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-outline {
            padding: 11px 22px;
            border-radius: var(--radius-md);
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

        .btn-primary {
            padding: 11px 22px;
            border: none;
            border-radius: var(--radius-md);
            background-color: var(--primary-color);
            color: var(--text-light);
            font-family: var(--font-family);
            font-size: var(--text-md);
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.25s;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .error-box {
            background-color: #FBE4E1;
            color: var(--accent-red);
            border-radius: var(--radius-md);
            padding: 10px 14px;
            font-size: var(--text-xs);
            text-align: left;
            margin-bottom: 20px;
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
        <div class="shop-register-card">
            <h1 class="shop-register-title">ลงทะเบียนร้านค้า</h1>

            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!isset($_GET['success']) || $_GET['success'] != '1'): ?>
            <form method="POST" action="register_shop_step2.php" enctype="multipart/form-data">

                <div class="form-columns">

                    <!-- คอลัมน์ซ้าย: ข้อมูลผู้สมัคร -->
                    <div class="form-column">
                        <div class="column-heading">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                                <line x1="2" y1="10" x2="22" y2="10"></line>
                            </svg>
                            ข้อมูลผู้สมัคร
                        </div>

                        <div class="form-group">
                            <label>รูปโปรไฟล์ หรือ โลโก้ร้านค้า</label>
                            <label class="upload-dropzone" id="logoDropzone" style="overflow: hidden; padding: 0; display: flex; align-items: center; justify-content: center; min-height: 180px; position: relative;">
                                <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png" required>
                                
                                <div id="logoDropzoneContent" style="padding: 28px 16px; width: 100%; text-align: center;">
                                    <div class="upload-icon-circle">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline>
                                        </svg>
                                    </div>
                                    <p class="upload-dropzone-text">ลากรูปโลโก้มาวางที่นี่ หรือ <span class="link-text">คลิกเพื่อเลือกไฟล์</span></p>
                                    <p class="upload-dropzone-hint">รองรับ JPG, PNG (ไม่เกิน 2MB) • แนะนำขนาด 1:1</p>
                                </div>

                                <img id="logoPreviewImg" src="" alt="Logo Preview" style="width: 100%; height: 100%; object-fit: contain; position: absolute; inset: 0; background-color: var(--bg-color); display: none; z-index: 1;">
                            </label>
                            <div style="text-align: right; margin-top: 6px; min-height: 20px;">
                                <button type="button" id="removeLogoBtn" style="background:none; border:none; color:var(--accent-red); cursor:pointer; font-size:13px; text-decoration:underline; display: none;">ลบรูปโลโก้</button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="id_card_number">เลขประจำตัวประชาชน</label>
                            <input type="text" id="id_card_number" name="id_card_number"
                                   placeholder="กรอกเลข 13 หลัก" maxlength="13" inputmode="numeric"
                                   value="<?= htmlspecialchars($old['id_card_number']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>หลักฐานยืนยันตัวตน - บัตรประชาชน (ID Upload)</label>
                            <label class="upload-dropzone" id="dropzone" style="padding: 16px;">
                                <input type="file" id="id_card_document" name="id_card_document" accept=".pdf,.jpg,.jpeg,.png" required>
                                <div class="upload-icon-circle" style="width: 36px; height: 36px; margin-bottom: 8px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                        <circle cx="12" cy="13" r="4"></circle>
                                    </svg>
                                </div>
                                <p class="upload-dropzone-text">ลากไฟล์มาวางที่นี่ หรือ <span class="link-text">คลิกเพื่อเลือกไฟล์</span></p>
                                <p class="upload-dropzone-hint">รองรับ PDF, JPG, PNG (ไม่เกิน 5MB)</p>
                            </label>

                            <div class="upload-preview" id="uploadPreview">
                                <span class="upload-preview-name" id="uploadPreviewName" style="cursor:pointer; color:var(--primary-color); text-decoration:underline;" title="คลิกเพื่อดูตัวอย่างเอกสาร">Preview: ไฟล์เอกสาร</span>
                                <button type="button" class="upload-preview-remove" id="uploadPreviewRemove">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- คอลัมน์ขวา: บัญชีรับเงิน -->
                    <div class="form-column">
                        <div class="column-heading">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 21h18"></path>
                                <path d="M5 21V9l7-6 7 6v12"></path>
                            </svg>
                            บัญชีรับเงิน
                        </div>

                        <div class="form-group">
                            <label for="bank_account_name">ชื่อบัญชี (Account Name)</label>
                            <input type="text" id="bank_account_name" name="bank_account_name"
                                   placeholder="ต้องตรงกับชื่อในบัตรประชาชน"
                                   value="<?= htmlspecialchars($old['bank_account_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="bank_name">ธนาคาร (Bank Name)</label>
                            <select id="bank_name" name="bank_name" required>
                                <option value="" disabled <?= $old['bank_name'] === '' ? 'selected' : '' ?>>เลือกธนาคาร</option>
                                <?php foreach ($bank_list as $bank): ?>
                                    <option value="<?= htmlspecialchars($bank) ?>"
                                        <?= $old['bank_name'] === $bank ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bank) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="bank_account_number">เลขที่บัญชี (Account Number)</label>
                            <input type="text" id="bank_account_number" name="bank_account_number"
                                   placeholder="000-0-00000-0" inputmode="numeric" maxlength="15"
                                   value="<?= htmlspecialchars($old['bank_account_number']) ?>" required>
                        </div>

                        <div class="info-note">
                            <span>ℹ️</span>
                            <span>ข้อมูลบัญชีธนาคารจะใช้สำหรับโอนรายได้จากการขายสินค้า โปรดตรวจสอบข้อมูลให้ถูกต้องเพื่อป้องกันความล่าช้าในการโอนเงิน</span>
                        </div>
                    </div>

                </div>

                <div class="form-actions">
                    <button type="submit" name="cancel" value="1" class="btn-outline">ยกเลิก</button>
                    <button type="submit" class="btn-primary">บันทึกและดำเนินการต่อ</button>
                </div>

            </form>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .wanawan-minimal-popup {
        border-radius: 16px !important;
        padding: 2.5rem 2rem !important;
        box-shadow: 0 15px 35px rgba(201, 123, 139, 0.12) !important;
        font-family: 'Prompt', sans-serif !important;
    }
    .wanawan-minimal-title {
        font-size: 20px !important;
        font-weight: 500 !important;
        color: #175564 !important;
        margin-top: 15px !important;
    }
    .wanawan-minimal-text {
        font-size: 14px !important;
        color: #666666 !important;
        font-weight: 300 !important;
        margin-top: 8px !important;
    }
    .wanawan-minimal-btn {
        background-color: #C97B8B !important;
        color: #FFFFFF !important;
        border-radius: 8px !important;
        padding: 10px 30px !important;
        font-size: 14px !important;
        font-weight: 400 !important;
        box-shadow: none !important;
        transition: background-color 0.2s ease;
    }
    .wanawan-minimal-btn:hover {
        background-color: #B5677A !important;
    }
</style>

<?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'รอตรวจสอบ',
            text: 'และจะมีการแจ้งสถานะส่งถึง email',
            confirmButtonText: 'ตกลง',
            iconColor: '#C97B8B',
            background: '#FFFFFF',
            allowOutsideClick: false,
            backdrop: 'rgba(0, 0, 0, 0.15)',
            customClass: {
                popup: 'wanawan-minimal-popup',
                title: 'wanawan-minimal-title',
                htmlContainer: 'wanawan-minimal-text',
                confirmButton: 'wanawan-minimal-btn'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'login.php';
            }
        });
    });
</script>
<?php endif; ?>

</body>
</html>

<!-- Document Preview Modal -->
<div id="docPreviewModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; padding:20px;">
    <div style="background:#fff; border-radius:12px; width:100%; max-width:800px; height:80vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <div style="padding:15px 20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center; background:var(--bg-color);">
            <h3 style="margin:0; font-size:16px; color:var(--secondary-color); font-weight:600;">ตัวอย่างเอกสาร</h3>
            <button type="button" onclick="closeDocPreview()" style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>
        <div style="flex:1; padding:20px; overflow:hidden; display:flex; justify-content:center; align-items:center; background:#f5f5f5;" id="docPreviewContent">
        </div>
    </div>
</div>

<script>
    const dropzone   = document.getElementById('dropzone');
    const fileInput  = document.getElementById('id_card_document');
    const preview    = document.getElementById('uploadPreview');
    const previewName= document.getElementById('uploadPreviewName');
    const removeBtn  = document.getElementById('uploadPreviewRemove');

    const logoInput = document.getElementById('profile_image');
    const logoPreviewImg = document.getElementById('logoPreviewImg');
    const removeLogoBtn = document.getElementById('removeLogoBtn');
    const logoDropzone = document.getElementById('logoDropzone');
    const logoDropzoneContent = document.getElementById('logoDropzoneContent');

    function handleLogoFile(file) {
        if (!file) return;
        
        if (!['image/jpeg', 'image/png'].includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'ประเภทไฟล์ไม่รองรับ',
                text: 'กรุณาอัปโหลดรูปภาพ JPG หรือ PNG เท่านั้น',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#C97B8B'
            });
            logoInput.value = '';
            return;
        }
        
        if (file.size > 2 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'ขนาดไฟล์เกินกำหนด',
                text: 'กรุณาอัปโหลดรูปภาพโลโก้ที่มีขนาดไม่เกิน 2MB',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#C97B8B'
            });
            logoInput.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            logoPreviewImg.src = e.target.result;
            logoPreviewImg.style.display = 'block';
            logoDropzoneContent.style.visibility = 'hidden';
            removeLogoBtn.style.display = 'inline-block';
        }
        reader.readAsDataURL(file);
    }

    logoInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            handleLogoFile(this.files[0]);
        }
    });

    ['dragover', 'dragenter'].forEach(evt => {
        logoDropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            logoDropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        logoDropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            logoDropzone.classList.remove('dragover');
        });
    });

    logoDropzone.addEventListener('drop', function (e) {
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            logoInput.files = e.dataTransfer.files;
            handleLogoFile(e.dataTransfer.files[0]);
        }
    });

    removeLogoBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        logoInput.value = '';
        logoPreviewImg.src = '';
        logoPreviewImg.style.display = 'none';
        logoDropzoneContent.style.visibility = 'visible';
        removeLogoBtn.style.display = 'none';
    });

    fileInput.addEventListener('change', function () {
        if (this.files && this.files.length > 0) {
            previewName.textContent = 'Preview: ' + this.files[0].name;
            preview.classList.add('show');
        }
    });

    ['dragover', 'dragenter'].forEach(evt => {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', function (e) {
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            previewName.textContent = 'Preview: ' + e.dataTransfer.files[0].name;
            preview.classList.add('show');
        }
    });

    removeBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        fileInput.value = '';
        preview.classList.remove('show');
    });

    // Document Preview Logic
    let currentDocObjectUrl = null;

    window.showDocPreview = function() {
        const fileInput = document.getElementById('id_card_document');
        const docPreviewModal = document.getElementById('docPreviewModal');
        const docPreviewContent = document.getElementById('docPreviewContent');

        if (fileInput && fileInput.files && fileInput.files[0]) {
            const file = fileInput.files[0];
            if (currentDocObjectUrl) URL.revokeObjectURL(currentDocObjectUrl);
            currentDocObjectUrl = URL.createObjectURL(file);

            docPreviewContent.innerHTML = '';
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = currentDocObjectUrl;
                img.style.maxWidth = '100%';
                img.style.maxHeight = '100%';
                img.style.objectFit = 'contain';
                img.style.borderRadius = '8px';
                docPreviewContent.appendChild(img);
            } else if (file.type === 'application/pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = currentDocObjectUrl;
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                docPreviewContent.appendChild(iframe);
            } else {
                docPreviewContent.innerHTML = '<p>ไม่สามารถแสดงตัวอย่างไฟล์ประเภทนี้ได้</p>';
            }
            docPreviewModal.style.display = 'flex';
        }
    };

    window.closeDocPreview = function() {
        const docPreviewModal = document.getElementById('docPreviewModal');
        const docPreviewContent = document.getElementById('docPreviewContent');
        if (docPreviewModal) docPreviewModal.style.display = 'none';
        if (currentDocObjectUrl) {
            URL.revokeObjectURL(currentDocObjectUrl);
            currentDocObjectUrl = null;
        }
        if (docPreviewContent) docPreviewContent.innerHTML = '';
    };

    document.getElementById('uploadPreviewName').addEventListener('click', window.showDocPreview);
</script>
