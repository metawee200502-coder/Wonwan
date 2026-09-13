<?php
session_start();
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../includes/mailer.php';

if(empty($_SESSION['user_id'])){header('Location: login.php');exit;}
$userId=(int)$_SESSION['user_id'];
$role=$_SESSION['role']??'';

if(!in_array($role,['publisher_admin','shop_admin'],true)){header('Location: home.php');exit;}

$sellerType=$role==='publisher_admin'?'publisher':'shop';
if($sellerType==='publisher') {
    $q=$pdo->prepare('SELECT p.publisher_id id, p.company_name name, ps.staff_role FROM publisher_staff ps JOIN publishers p ON p.publisher_id=ps.publisher_id WHERE ps.user_id=? LIMIT 1');
} else {
    $q=$pdo->prepare('SELECT s.shop_id id, s.shop_name name, ss.staff_role FROM shop_staff ss JOIN shops s ON s.shop_id=ss.shop_id WHERE ss.user_id=? LIMIT 1');
}
$q->execute([$userId]);
$seller=$q->fetch(PDO::FETCH_ASSOC);

if(!$seller || $seller['staff_role'] !== 'owner'){
    header('Location: dashboard.php');
    exit;
}

$sellerId=(int)$seller['id'];
$seller_name=$seller['name'];
$active_menu='manage_seller_admins';

$successMsg = '';
$errorMsg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_admin') {
        $name = trim($_POST['name'] ?? '');
        $contact_email = trim($_POST['email'] ?? '');
        $position = trim($_POST['position'] ?? '');

        if ($name && filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $randSuffix = strtoupper(substr(md5(uniqid()), 0, 4));
            $username = 'staff_' . $randSuffix;
            $dummyEmail = $username . '@system.wonwan.com';
            $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 8);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, display_name, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $dummyEmail, $name, $passwordHash, $role]);
                $newUserId = (int)$pdo->lastInsertId();

                // Insert into staff table
                $staffTable = $sellerType === 'publisher' ? 'publisher_staff' : 'shop_staff';
                $staffCol = $sellerType === 'publisher' ? 'publisher_id' : 'shop_id';
                
                $stmt = $pdo->prepare("INSERT INTO $staffTable (user_id, $staffCol, staff_role, position, contact_email) VALUES (?, ?, 'sub_admin', ?, ?)");
                $stmt->execute([$newUserId, $sellerId, $position, $contact_email]);

                // Send email with new password
                $subject = "ยินดีต้อนรับสู่ระบบผู้ดูแล " . $seller_name;
                $bodyHTML = "
                    <h2>ยินดีต้อนรับคุณ $name</h2>
                    <p>บัญชีผู้ดูแลระบบร้านค้า/สำนักพิมพ์ของคุณถูกสร้างเรียบร้อยแล้ว</p>
                    <p><b>Username (ใช้สำหรับ Login):</b> $username<br>
                    <b>Password:</b> $password</p>
                    <p>กรุณาล็อกอินและเปลี่ยนรหัสผ่านในภายหลัง</p>
                ";
                sendSystemEmail($contact_email, $subject, $bodyHTML);

                logSellerAction($pdo, $sellerType, $sellerId, $userId, 'CREATE_ADMIN', (string)$newUserId, "เพิ่มแอดมินใหม่: {$name} (Username: {$username}) ตำแหน่ง: {$position}");
                $pdo->commit();
                $successMsg = "สร้างบัญชีผู้ดูแลระบบสำเร็จ (ส่งข้อมูลล็อกอินไปที่อีเมลแล้ว)";
            } catch (Exception $e) {
                $pdo->rollBack();
                $errorMsg = "เกิดข้อผิดพลาด: " . $e->getMessage();
            }
        } else {
            $errorMsg = "ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบอีเมล";
        }
    }

    if ($action === 'remove_admin') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId && $targetUserId !== $userId) {
            $staffTable = $sellerType === 'publisher' ? 'publisher_staff' : 'shop_staff';
            $staffCol = $sellerType === 'publisher' ? 'publisher_id' : 'shop_id';
            
            $stmt = $pdo->prepare("DELETE FROM $staffTable WHERE user_id = ? AND $staffCol = ? AND staff_role = 'sub_admin'");
            $stmt->execute([$targetUserId, $sellerId]);
            
            if ($stmt->rowCount() > 0) {
                logSellerAction($pdo, $sellerType, $sellerId, $userId, 'REMOVE_ADMIN', (string)$targetUserId, "ลบสิทธิ์ผู้ดูแลระบบรหัส: $targetUserId");
                $successMsg = "ลบสิทธิ์ผู้ดูแลระบบเรียบร้อยแล้ว";
            } else {
                $errorMsg = "ไม่สามารถลบสิทธิ์ผู้ดูแลระบบคนนี้ได้";
            }
        }
    }

    if ($action === 'reset_password') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId) {
            // Verify they belong to this shop/publisher
            $staffTable = $sellerType === 'publisher' ? 'publisher_staff' : 'shop_staff';
            $staffCol = $sellerType === 'publisher' ? 'publisher_id' : 'shop_id';
            
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.username, s.contact_email, u.display_name
                FROM $staffTable s 
                JOIN users u ON u.user_id = s.user_id 
                WHERE s.$staffCol = ? AND s.user_id = ?
            ");
            $stmt->execute([$sellerId, $targetUserId]);
            $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($targetUser) {
                $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 8);
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")->execute([$passwordHash, $targetUserId]);
                
                $subject = "รีเซ็ตรหัสผ่านระบบผู้ดูแล " . $seller_name;
                $bodyHTML = "
                    <h2>สวัสดีคุณ {$targetUser['display_name']}</h2>
                    <p>รหัสผ่านของคุณถูกรีเซ็ตโดยเจ้าของร้าน/สำนักพิมพ์แล้ว</p>
                    <p><b>Username (สำหรับ Login):</b> {$targetUser['username']}</p>
                    <p><b>รหัสผ่านใหม่:</b> $password</p>
                    <p>กรุณาล็อกอินและเปลี่ยนรหัสผ่านทันที</p>
                ";
                
                $sendTo = !empty($targetUser['contact_email']) ? $targetUser['contact_email'] : $targetUser['username'].'@no-email.local';
                if(strpos($sendTo, '@') !== false && strpos($sendTo, '.local') === false) {
                    sendSystemEmail($sendTo, $subject, $bodyHTML);
                }
                
                logSellerAction($pdo, $sellerType, $sellerId, $userId, 'RESET_ADMIN_PASSWORD', (string)$targetUserId, "รีเซ็ตรหัสผ่านแอดมิน ID {$targetUserId} (Username: {$targetUser['username']})");
                
                $successMsg = "รีเซ็ตรหัสผ่านสำเร็จ (ระบบส่งรหัสผ่านใหม่ไปที่อีเมลแอดมินแล้ว)";
            }
        }
    }
}

// Fetch Admins
$staffTable = $sellerType === 'publisher' ? 'publisher_staff' : 'shop_staff';
$staffCol = $sellerType === 'publisher' ? 'publisher_id' : 'shop_id';

$stmt = $pdo->prepare("
    SELECT u.user_id, u.username, u.email, u.display_name, s.staff_role, s.position, s.contact_email
    FROM $staffTable s 
    JOIN users u ON u.user_id = s.user_id 
    WHERE s.$staffCol = ?
    ORDER BY s.staff_role ASC, u.display_name ASC
");
$stmt->execute([$sellerId]);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>จัดการผู้ดูแลระบบ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:var(--font-family); background:#fdfbfb; color:var(--text-dark); display:flex; min-height:100vh; }
        
        /* Layout overrides */
        .admin-main-wrap { margin-left: 260px; flex: 1; min-width: 0; padding: 40px 50px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-title { color: #91576d; font-size: 26px; font-weight: bold; display: flex; align-items: center; gap: 10px; margin: 0;}
        
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; font-size: 15px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        /* Form Card */
        .form-card { background: #fff; border-radius: 16px; border: 1px solid #e3c3ce; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .form-card-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 20px; border-bottom: 2px solid #e3c3ce; padding-bottom: 10px; display: inline-block; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group label { font-size: 14px; font-weight: 500; color: #555; }
        .form-group input, .form-group select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus { border-color: #c77c99; }
        .btn-submit { background: #c77c99; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; font-family: inherit; }
        .btn-submit:hover { background: #ad637f; box-shadow: 0 4px 10px rgba(199, 124, 153, 0.2); }

        /* Table Box */
        .tablebox { background: #fff; border-radius: 16px; border: 1px solid #e3c3ce; overflow-x: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .orders { width: 100%; border-collapse: collapse; min-width: 900px; }
        .orders th, .orders td { padding: 18px 20px; border-bottom: 1px solid #f4e8eb; text-align: left; }
        .orders th { background: #fbf2f5; color: #91576d; font-weight: 600; font-size: 14px; }
        .orders tr:hover { background: #fdf9fa; }
        .orders td { color: #444; font-size: 14px; vertical-align: middle; }
        
        .action-badge { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: bold; display: inline-block; }
        .bg-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .bg-owner { background: #f0d4de; color: #91576d; border: 1px solid #e3c3ce; }

        .btn-action { padding: 8px 15px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; font-family: inherit; }
        .btn-warning { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        .btn-warning:hover { background: #ffe0b2; }
        .btn-suspend { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .btn-suspend:hover { background: #ffcdd2; }
        
        .actions-flex { display: flex; gap: 8px; }

        @media(max-width:800px) {
            .admin-main-wrap { margin-left: 0; width: 100%; padding: 25px 16px; }
        }
    </style>
</head>
<body>

<?php include __DIR__.'/../components/sidebar.php'; ?>

<div class="admin-main-wrap">
    
    <div class="page-header">
        <h2 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            จัดการผู้ดูแลระบบ (Sub-Admin Control)
        </h2>
    </div>

    <?php if($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
    <?php endif; ?>
    <?php if($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
    <?php endif; ?>

    <!-- ฟอร์มสร้างแอดมินใหม่ -->
    <div class="form-card">
        <div class="form-card-title">สร้างบัญชีผู้ดูแลระบบ (Sub-Admin)</div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create_admin">
            <div class="form-grid">
                <div class="form-group">
                    <label>ชื่อ-นามสกุล</label>
                    <input type="text" name="name" required placeholder="เช่น สมชาย ใจดี">
                </div>
                <div class="form-group">
                    <label>อีเมล</label>
                    <input type="email" name="email" required placeholder="admin@wonwan.com">
                </div>
                <div class="form-group">
                    <label>ตำแหน่ง</label>
                    <select name="position" required>
                        <option value="">-- เลือกตำแหน่ง --</option>
                        <option value="Customer Support">Customer Support</option>
                        <option value="Moderator">Moderator</option>
                        <option value="Finance">Finance</option>
                        <option value="Dispute Manager">Dispute Manager</option>
                        <option value="General Admin">General Admin</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-submit">สร้างบัญชี & ส่งรหัสผ่านเข้าอีเมล</button>
        </form>
    </div>

    <!-- ตารางรายชื่อ -->
    <div class="tablebox">
        <table class="orders">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>ชื่อ</th>
                    <th>อีเมลที่ติดต่อ</th>
                    <th>ตำแหน่ง</th>
                    <th>สถานะ</th>
                    <th>จัดการ (Actions)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($admins as $adm): ?>
                <tr>
                    <td style="font-weight: 500; color: #91576d;">#<?= $adm['user_id'] ?></td>
                    <td style="font-weight: 600; color: #333;"><?= htmlspecialchars($adm['username'] ?? '-') ?></td>
                    <td style="font-weight: 600; color: #333;"><?= htmlspecialchars($adm['display_name']) ?></td>
                    <td><?= htmlspecialchars(!empty($adm['contact_email']) ? $adm['contact_email'] : $adm['email']) ?></td>
                    <td><?= htmlspecialchars($adm['position'] ?? 'Customer Support') ?></td>
                    <td>
                        <?php if($adm['staff_role'] === 'owner'): ?>
                            <span class="action-badge bg-owner">Owner</span>
                        <?php else: ?>
                            <span class="action-badge bg-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($adm['staff_role'] !== 'owner' && $adm['user_id'] !== $userId): ?>
                            <div class="actions-flex">
                                <!-- Reset Password Form -->
                                <form method="POST" action="" onsubmit="return confirm('ยืนยันการสุ่มรหัสผ่านใหม่และส่งเข้าอีเมลแอดมินคนนี้?');" style="margin:0;">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="target_user_id" value="<?= $adm['user_id'] ?>">
                                    <button class="btn-action btn-warning">Reset Password</button>
                                </form>

                                <!-- Remove Admin Form (Suspends/Removes access) -->
                                <form method="POST" action="" onsubmit="return confirm('ยืนยันการลบสิทธิ์ผู้ดูแลระบบคนนี้? (บัญชีจะถูกตัดออกจากร้านค้า/สำนักพิมพ์นี้)');" style="margin:0;">
                                    <input type="hidden" name="action" value="remove_admin">
                                    <input type="hidden" name="target_user_id" value="<?= $adm['user_id'] ?>">
                                    <button class="btn-action btn-suspend">Suspend</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span style="color:#999; font-style:italic; font-size: 13px;">เจ้าของ (Owner)</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
