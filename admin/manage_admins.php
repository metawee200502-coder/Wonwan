<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';

// Strict Access Control
if (($_SESSION['email'] ?? '') !== 'admin1@wonwan') {
    http_response_code(403);
    exit('Access Denied');
}

$active_menu = 'manage_admins';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_admin') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $position = trim($_POST['position'] ?? '');

        if ($name && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Generate Random Username (e.g., admin_X8F2)
            $randSuffix = strtoupper(substr(md5(uniqid()), 0, 4));
            $username = 'admin_' . $randSuffix;
            
            // Generate Random Password (8 chars)
            $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 8);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, name, email, position) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $passwordHash, $name, $email, $position]);

                // Send email
                $subject = "ยินดีต้อนรับสู่ระบบผู้ดูแล Wonwan";
                $bodyHTML = "
                    <h2>ยินดีต้อนรับคุณ $name</h2>
                    <p>บัญชีผู้ดูแลระบบของคุณถูกสร้างเรียบร้อยแล้ว</p>
                    <p><b>Username:</b> $username<br>
                    <b>Password:</b> $password</p>
                    <p>กรุณาล็อกอินและเปลี่ยนรหัสผ่านทันที</p>
                ";
                sendSystemEmail($email, $subject, $bodyHTML);
                
                logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'CREATE_ADMIN', (string)$pdo->lastInsertId(), "สร้างบัญชีแอดมินใหม่: {$name} ({$email}) ตำแหน่ง: {$position}");

                $_SESSION['success_msg'] = "สร้างแอดมินใหม่สำเร็จ (ระบบได้ส่งข้อมูลล็อกอินไปที่อีเมลแล้ว)";
            } catch (PDOException $e) {
                $_SESSION['error_msg'] = "ไม่สามารถสร้างบัญชีได้ อาจมีอีเมลนี้อยู่ในระบบแล้ว";
            }
        } else {
            $_SESSION['error_msg'] = "ข้อมูลไม่ถูกต้อง";
        }
        header("Location: manage_admins.php");
        exit;
    }

    if ($action === 'toggle_status') {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        $currentStatus = $_POST['current_status'] ?? '';
        $newStatus = $currentStatus === 'active' ? 'suspended' : 'active';
        
        $pdo->prepare("UPDATE admins SET status = ? WHERE admin_id = ? AND email != 'admin1@wonwan'")->execute([$newStatus, $adminId]);
        
        logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'TOGGLE_ADMIN_STATUS', (string)$adminId, "เปลี่ยนสถานะแอดมิน ID {$adminId} เป็น {$newStatus}");
        
        $_SESSION['success_msg'] = "อัปเดตสถานะเรียบร้อย";
        header("Location: manage_admins.php");
        exit;
    }

    if ($action === 'reset_password') {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT name, email FROM admins WHERE admin_id = ? AND email != 'admin1@wonwan'");
        $stmt->execute([$adminId]);
        $adminUser = $stmt->fetch();

        if ($adminUser) {
            $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 8);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $pdo->prepare("UPDATE admins SET password_hash = ? WHERE admin_id = ?")->execute([$passwordHash, $adminId]);

            $subject = "รีเซ็ตรหัสผ่านระบบผู้ดูแล Wonwan";
            $bodyHTML = "
                <h2>สวัสดีคุณ {$adminUser['name']}</h2>
                <p>รหัสผ่านของคุณถูกรีเซ็ตโดย Super Admin แล้ว</p>
                <p><b>รหัสผ่านใหม่:</b> $password</p>
                <p>กรุณาล็อกอินและเปลี่ยนรหัสผ่านทันที</p>
            ";
            sendSystemEmail($adminUser['email'], $subject, $bodyHTML);
            
            logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'RESET_ADMIN_PASSWORD', (string)$adminId, "รีเซ็ตรหัสผ่านแอดมิน ID {$adminId} ({$adminUser['email']})");
            
            $_SESSION['success_msg'] = "รีเซ็ตรหัสผ่านสำเร็จ (ระบบส่งรหัสผ่านใหม่ไปที่อีเมลเรียบร้อยแล้ว)";
        }
        header("Location: manage_admins.php");
        exit;
    }
}

$admins = $pdo->query("SELECT * FROM admins ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ดูแลระบบ - Wonwan Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body { background: #fdfbfb; margin: 0; display: flex; min-height: 100vh; overflow-y: scroll; font-family: 'Prompt', sans-serif;}
        .admin-main-wrap { flex: 1; min-width: 0; padding: 40px 50px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-title { color: #00796B; font-size: 26px; font-weight: bold; display: flex; align-items: center; gap: 10px; margin: 0;}
        
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; font-size: 15px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-danger { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        /* Form Card */
        .form-card { background: #fff; border-radius: 16px; border: 1px solid #e0f2f1; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .form-card-title { font-size: 18px; font-weight: 600; color: #333; margin-bottom: 20px; border-bottom: 2px solid #e0f2f1; padding-bottom: 10px; display: inline-block; }
        
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
        .form-group input:focus, .form-group select:focus { border-color: #00796B; }
        
        .btn-submit {
            background: #00796B; color: #fff; border: none; padding: 12px 25px; border-radius: 8px; font-family: inherit; font-weight: 600; cursor: pointer; transition: 0.2s; font-size: 15px;
        }
        .btn-submit:hover { background: #00695C; }

        /* Table */
        .tablebox { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e0f2f1; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .orders { width: 100%; border-collapse: collapse; min-width: 800px; }
        .orders th, .orders td { padding: 18px 20px; border-bottom: 1px solid #f0f0f0; text-align: left; }
        .orders th { background: #f5fbfb; color: #555; font-weight: 600; font-size: 14px; }
        .orders tr:hover { background: #fafafa; }
        .orders td { color: #444; font-size: 14px; vertical-align: middle; }
        
        .action-badge { padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: bold; display: inline-block; }
        .bg-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .bg-danger { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        .btn-action { padding: 8px 15px; border-radius: 8px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: 0.2s; font-family: inherit; }
        .btn-warning { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        .btn-warning:hover { background: #ffe0b2; }
        .btn-suspend { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .btn-suspend:hover { background: #ffcdd2; }
        .btn-activate { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .btn-activate:hover { background: #c8e6c9; }
        
        .actions-flex { display: flex; gap: 8px; }
    </style>
</head>
<body>

<?php include '../components/admin_sidebar.php'; ?>

<div class="admin-main-wrap">
    
    <div class="page-header">
        <h2 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            จัดการผู้ดูแลระบบ (Sub-Admin Control)
        </h2>
    </div>

    <?php if(!empty($_SESSION['success_msg'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_msg']) ?></div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    
    <?php if(!empty($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_msg']) ?></div>
        <?php unset($_SESSION['error_msg']); ?>
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
                        <option value="Customer Support">Customer Support (ดูแลลูกค้า/แชท)</option>
                        <option value="Moderator">Moderator (ตรวจสอบร้านค้า/เนื้อหา)</option>
                        <option value="Finance">Finance (ดูแลการเงิน/อนุมัติถอนเงิน)</option>
                        <option value="Dispute Manager">Dispute Manager (จัดการข้อพิพาท/คืนเงิน)</option>
                        <option value="General Admin">General Admin (แอดมินทั่วไป)</option>
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
                    <th>อีเมล</th>
                    <th>ตำแหน่ง</th>
                    <th>สถานะ</th>
                    <th>จัดการ (Actions)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($admins as $adm): ?>
                <tr>
                    <td style="font-weight: 500; color: #00796B;">#<?= $adm['admin_id'] ?></td>
                    <td style="font-weight: 600; color: #333;"><?= htmlspecialchars($adm['username']) ?></td>
                    <td><?= htmlspecialchars($adm['name']) ?></td>
                    <td><?= htmlspecialchars($adm['email']) ?></td>
                    <td><?= htmlspecialchars($adm['position']) ?></td>
                    <td>
                        <?php if($adm['status'] === 'active'): ?>
                            <span class="action-badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="action-badge bg-danger">Suspended</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($adm['email'] !== 'admin1@wonwan'): ?>
                            <div class="actions-flex">
                                <!-- Reset Password Form -->
                                <form method="POST" action="" onsubmit="return confirm('ยืนยันการสุ่มรหัสผ่านใหม่และส่งเข้าอีเมลแอดมินคนนี้?');" style="margin:0;">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="admin_id" value="<?= $adm['admin_id'] ?>">
                                    <button class="btn-action btn-warning">Reset Password</button>
                                </form>

                                <!-- Toggle Status Form -->
                                <form method="POST" action="" onsubmit="return confirm('ยืนยันการเปลี่ยนสถานะผู้ใช้งานคนนี้?');" style="margin:0;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="admin_id" value="<?= $adm['admin_id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $adm['status'] ?>">
                                    <?php if($adm['status'] === 'active'): ?>
                                        <button class="btn-action btn-suspend">Suspend</button>
                                    <?php else: ?>
                                        <button class="btn-action btn-activate">Activate</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php else: ?>
                            <span style="color:#999; font-style:italic; font-size: 13px;">Master Admin</span>
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
