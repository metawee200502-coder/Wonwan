<?php
session_start();
require_once '../config/config.php';

// ต้องมีข้อมูลจากหน้าแรกก่อน ไม่งั้นเด้งกลับไปกรอกใหม่
if (empty($_SESSION['shop_register'])) {
    header('Location: register_shop.php');
    exit;
}

$step1 = $_SESSION['shop_register']; // name, email, password_hash
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
                    shop_name, description, id_card_number, id_card_document_url,
                    bank_account_name, bank_name, bank_account_number,
                    followers_count, status, created_at
                ) VALUES (
                    :shop_name, NULL, :id_card_number, :id_card_document_url,
                    :bank_account_name, :bank_name, :bank_account_number,
                    0, 'pending', NOW()
                )
            ");
            $stmt->execute([
                ':shop_name'             => $step1['name'],
                ':id_card_number'        => $id_card_number,
                ':id_card_document_url'  => $id_card_document_url,
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
    <link rel="stylesheet" href="../assets/css/styles.css">

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

        .upload-box {
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 30px 15px;
            text-align: center;
            cursor: pointer;
            color: var(--text-muted);
            background-color: var(--bg-color);
            transition: border-color 0.25s;
        }

        .upload-box:hover {
            border-color: var(--primary-color);
        }

        .upload-box svg {
            display: block;
            margin: 0 auto 8px;
            color: var(--primary-color);
        }

        .upload-box .upload-main {
            font-size: var(--text-sm);
            color: var(--text-dark);
        }

        .upload-box .upload-sub {
            font-size: var(--text-xs);
            margin-top: 4px;
            color: var(--text-muted);
        }

        .file-name {
            margin-top: 8px;
            font-size: var(--text-xs);
            color: var(--secondary-color);
            font-weight: 500;
            word-break: break-all;
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
                            <label for="id_card_number">เลขประจำตัวประชาชน</label>
                            <input type="text" id="id_card_number" name="id_card_number"
                                   placeholder="กรอกเลข 13 หลัก" maxlength="13" inputmode="numeric"
                                   value="<?= htmlspecialchars($old['id_card_number']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>หลักฐานยืนยันตัวตน - บัตรประชาชน (ID Upload)</label>
                            <label for="id_card_document" class="upload-box" style="display:block;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                    <circle cx="12" cy="13" r="4"></circle>
                                </svg>
                                <div class="upload-main">คลิกเพื่ออัปโหลด หรือลากไฟล์มาที่นี่</div>
                                <div class="upload-sub">รองรับไฟล์ JPG, PNG หรือ PDF (ไม่เกิน 5MB)</div>
                                <div class="file-name" id="file-name-display"></div>
                            </label>
                            <input type="file" id="id_card_document" name="id_card_document"
                                   accept=".jpg,.jpeg,.png,.pdf" style="display:none;" required>
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
            title: 'ลงทะเบียนร้านค้าสำเร็จ',
            text: 'ทีมงานวนวรรณจะตรวจสอบและแจ้งผลผ่านอีเมลของคุณ',
            confirmButtonText: 'เข้าสู่ระบบ',
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
