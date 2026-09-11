<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

$active_menu = 'manage_users';

// ================= Handle POST Actions =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $userId = (int)$_POST['user_id'];
    
    if ($action === 'ban') {
        $type = $_POST['ban_type'] ?? 'permanent';
        $reason = trim($_POST['ban_reason'] ?? '');
        
        if ($type === 'temporary') {
            $days = (int)$_POST['ban_days'];
            $ban_until = date('Y-m-d H:i:s', strtotime("+$days days"));
            $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_until = ?, ban_reason = ? WHERE user_id = ?");
            $stmt->execute([$ban_until, $reason, $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_banned = 1, ban_until = NULL, ban_reason = ? WHERE user_id = ?");
            $stmt->execute([$reason, $userId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'unban') {
        $stmt = $pdo->prepare("UPDATE users SET is_banned = 0, ban_until = NULL, ban_reason = NULL WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'edit') {
        $name = trim($_POST['display_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE users SET display_name = ?, email = ? WHERE user_id = ?");
        $stmt->execute([$name, $email, $userId]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'delete') {
        try {
            $pdo->beginTransaction();
            $role = $pdo->query("SELECT role FROM users WHERE user_id = $userId")->fetchColumn();
            
            if ($role === 'shop_admin') {
                $shopId = $pdo->query("SELECT shop_id FROM shop_staff WHERE user_id = $userId")->fetchColumn();
                if ($shopId) {
                    $pdo->prepare("DELETE FROM shop_staff WHERE shop_id = ?")->execute([$shopId]);
                    $pdo->prepare("DELETE FROM shops WHERE shop_id = ?")->execute([$shopId]);
                }
            } elseif ($role === 'publisher_admin') {
                $pubId = $pdo->query("SELECT publisher_id FROM publisher_staff WHERE user_id = $userId")->fetchColumn();
                if ($pubId) {
                    $pdo->prepare("DELETE FROM publisher_staff WHERE publisher_id = ?")->execute([$pubId]);
                    $pdo->prepare("DELETE FROM publishers WHERE publisher_id = ?")->execute([$pubId]);
                }
            }
            
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$userId]);
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบได้ เนื่องจากบัญชีนี้มีข้อมูลผูกพันในระบบ (เช่น คำสั่งซื้อ สินค้า)']);
        }
        exit;
    }
}

// ================= Fetch Data =================
// Helpers
function getOnlineStatus($last_active) {
    if (!$last_active) return ['status' => 'offline', 'text' => 'ไม่เคยออนไลน์'];
    $time = strtotime($last_active);
    $diff = time() - $time;
    if ($diff <= 300) { // 5 minutes
        return ['status' => 'online', 'text' => 'กำลังออนไลน์'];
    }
    
    // Format past time
    if ($diff < 3600) return ['status' => 'offline', 'text' => floor($diff/60) . ' นาทีที่แล้ว'];
    if ($diff < 86400) return ['status' => 'offline', 'text' => floor($diff/3600) . ' ชั่วโมงที่แล้ว'];
    return ['status' => 'offline', 'text' => date('d/m/Y H:i', $time)];
}

function getBanStatus($user) {
    if ($user['is_banned']) return ['banned' => true, 'text' => 'แบนถาวร', 'color' => '#dc3545'];
    if ($user['ban_until'] && strtotime($user['ban_until']) > time()) {
        return ['banned' => true, 'text' => 'แบนชั่วคราว', 'color' => '#fd7e14'];
    }
    return ['banned' => false, 'text' => 'ปกติ', 'color' => '#28a745'];
}

// ================= Filter & Sort =================
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'signup_desc';

$whereClause = "";
$params = [];
if ($search) {
    $whereClause = " AND (u.display_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$orderBy = "u.user_id DESC";
if ($sort === 'recent_online') {
    $orderBy = "u.last_active DESC";
} elseif ($sort === 'signup_asc') {
    $orderBy = "u.created_at ASC";
} elseif ($sort === 'name_asc') {
    $orderBy = "u.display_name ASC";
} elseif ($sort === 'name_desc') {
    $orderBy = "u.display_name DESC";
}

// All Users
$sqlAll = "SELECT u.user_id, u.email, u.display_name, u.profile_image, u.role, u.last_active, u.created_at, u.is_banned, u.ban_until, u.ban_reason,
    COALESCE(p.company_name, s.shop_name) as biz_name, 
    COALESCE(p.profile_image, s.profile_image) as shop_profile 
    FROM users u 
    LEFT JOIN publisher_staff ps ON u.user_id = ps.user_id 
    LEFT JOIN publishers p ON ps.publisher_id = p.publisher_id 
    LEFT JOIN shop_staff ss ON u.user_id = ss.user_id 
    LEFT JOIN shops s ON ss.shop_id = s.shop_id
    WHERE 1=1 $whereClause ORDER BY $orderBy";
$stmt = $pdo->prepare($sqlAll);
$stmt->execute($params);
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Readers
$sqlReaders = "SELECT u.user_id, u.email, u.display_name, u.profile_image, u.role, u.last_active, u.created_at, u.is_banned, u.ban_until, u.ban_reason, NULL as biz_name, NULL as shop_profile 
    FROM users u WHERE (u.role = 'reader' OR u.role IS NULL) $whereClause ORDER BY $orderBy";
$stmt = $pdo->prepare($sqlReaders);
$stmt->execute($params);
$readers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Publishers
$sqlPublishers = "SELECT u.user_id, u.email, u.display_name, u.profile_image, u.role, u.last_active, u.created_at, u.is_banned, u.ban_until, u.ban_reason, p.company_name as biz_name, p.profile_image as shop_profile 
    FROM users u 
    LEFT JOIN publisher_staff ps ON u.user_id = ps.user_id 
    LEFT JOIN publishers p ON ps.publisher_id = p.publisher_id 
    WHERE u.role = 'publisher_admin' $whereClause ORDER BY $orderBy";
$stmt = $pdo->prepare($sqlPublishers);
$stmt->execute($params);
$publishers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Shops
$sqlShops = "SELECT u.user_id, u.email, u.display_name, u.profile_image, u.role, u.last_active, u.created_at, u.is_banned, u.ban_until, u.ban_reason, s.shop_name as biz_name, s.profile_image as shop_profile 
    FROM users u 
    LEFT JOIN shop_staff ss ON u.user_id = ss.user_id 
    LEFT JOIN shops s ON ss.shop_id = s.shop_id 
    WHERE u.role = 'shop_admin' $whereClause ORDER BY $orderBy";
$stmt = $pdo->prepare($sqlShops);
$stmt->execute($params);
$shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้งาน - ระบบหลังบ้าน</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .admin-wrap { max-width: 1300px; margin: 40px auto; padding: 0 20px; font-family: 'Prompt', sans-serif; }
        .admin-title { font-size: 24px; color: var(--secondary-color); margin-bottom: 24px; font-weight: 600; }
        
        .segmented-control {
            display: inline-flex;
            background-color: #fdfaf8;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 24px;
            gap: 4px;
        }
        .segment-btn {
            padding: 8px 32px;
            border-radius: 6px;
            font-size: 15px;
            color: var(--text-dark);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: transparent;
            font-family: inherit;
        }
        .segment-btn.active {
            background-color: #0b5963;
            color: #fff;
        }
        
        .section-container { display: none; }
        .section-container.active { display: block; }
        
        .user-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }
        .user-table th {
            background: #fdfaf8;
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 1px solid var(--border-color);
        }
        .user-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }
        .user-table tr:hover { background: #fdfaf8; }
        
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #e0e0e0; }
        .user-name { font-weight: 600; color: var(--text-dark); max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-email { font-size: 13px; color: var(--text-muted); max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .status-badge {
            display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500;
        }
        .online-dot {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #28a745; margin-right: 6px;
        }
        .offline-dot {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #ccc; margin-right: 6px;
        }
        
        .action-btns { display: flex; gap: 8px; flex-wrap: nowrap; }
        .btn-edit, .btn-ban, .btn-unban, .btn-delete, .btn-info { 
            display: inline-flex; align-items: center; gap: 6px; 
            border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 500;
        }
        .btn-edit { background: #f0f0f0; color: #333; }
        .btn-info { background: #e0f2fe; color: #0284c7; }
        .btn-ban { background: #fee2e2; color: #dc2626; }
        .btn-unban { background: #dcfce7; color: #16a34a; }
        .btn-delete { background: #fff1f2; color: #e11d48; border: 1px solid #ffe4e6; }
        .btn-edit svg, .btn-ban svg, .btn-unban svg, .btn-delete svg, .btn-info svg { width: 14px; height: 14px; flex-shrink: 0; }
        
        /* Modal for editing */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 1000;
            display: none; align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: #fff; padding: 30px; border-radius: 12px; width: 100%; max-width: 400px;
        }
        .modal-card h3 { margin-top: 0; margin-bottom: 20px; }
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .input-group input { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; box-sizing: border-box; font-family: inherit; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        
        .swal-prompt-font { font-family: 'Prompt', sans-serif !important; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    
    <div class="admin-main-wrap">
        <main class="admin-wrap">
            <h1 class="admin-title">จัดการผู้ใช้งานระบบ</h1>
            
            <form method="GET" action="" style="display: flex; gap: 15px; margin-bottom: 24px; align-items: center; background: #fdfaf8; padding: 16px; border-radius: 8px; border: 1px solid var(--border-color);">
                <input type="text" name="search" placeholder="ค้นหาชื่อ, อีเมล..." value="<?= htmlspecialchars($search) ?>" style="padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 6px; flex-grow: 1; max-width: 300px; font-family: inherit;">
                
                <select name="sort" style="padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; min-width: 180px;" onchange="this.form.submit()">
                    <option value="signup_desc" <?= $sort === 'signup_desc' ? 'selected' : '' ?>>วันที่สมัคร (ใหม่สุด)</option>
                    <option value="signup_asc" <?= $sort === 'signup_asc' ? 'selected' : '' ?>>วันที่สมัคร (เก่าสุด)</option>
                    <option value="recent_online" <?= $sort === 'recent_online' ? 'selected' : '' ?>>ออนไลน์ล่าสุด</option>
                    <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>ชื่อ (ก-ฮ)</option>
                    <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>ชื่อ (ฮ-ก)</option>
                </select>
                
                <?php if ($search): ?>
                    <button type="button" onclick="window.location='manage_users.php'" style="padding: 10px 20px; background: #e0e0e0; color: #333; border: none; border-radius: 6px; cursor: pointer; font-family: inherit; font-weight: 500;">ล้างค่า</button>
                <?php endif; ?>
                <button type="submit" style="padding: 10px 20px; background: #0b5963; color: white; border: none; border-radius: 6px; cursor: pointer; font-family: inherit; font-weight: 500;">ค้นหา / เรียงลำดับ</button>
            </form>

            <div class="segmented-control">
                <button class="segment-btn active" onclick="switchTab('all')">ทั้งหมด</button>
                <button class="segment-btn" onclick="switchTab('publisher')">สำนักพิมพ์</button>
                <button class="segment-btn" onclick="switchTab('shop')">ร้านมือสอง</button>
                <button class="segment-btn" onclick="switchTab('reader')">ผู้ใช้ปกติ</button>
            </div>
            
            <?php 
            $tabs = [
                'all' => $allUsers,
                'publisher' => $publishers,
                'shop' => $shops,
                'reader' => $readers
            ];
            
            foreach ($tabs as $tabId => $users): 
            ?>
            <div id="section-<?= $tabId ?>" class="section-container <?= $tabId === 'all' ? 'active' : '' ?>">
                <table class="user-table">
                    <thead>
                        <tr>
                            <th style="width: 25%;">บัญชีผู้ใช้</th>
                            <th style="width: 20%; text-align: left;">ชื่อร้าน/สำนักพิมพ์</th>
                            <th style="width: 140px; text-align: center;">สถานะออนไลน์</th>
                            <th style="width: 180px; text-align: center; white-space: nowrap;">สถานะบัญชี</th>
                            <th style="min-width: 300px;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            $online = getOnlineStatus($user['last_active']);
                            $ban = getBanStatus($user);
                            $actual_avatar = $user['shop_profile'] ?? $user['profile_image'];
                            $avatar = $actual_avatar ? '/pages/' . ltrim($actual_avatar, '/') : '/assets/image/default_avatar.svg';
                            $bizName = $user['biz_name'] ?? '-';
                        ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <img src="<?= htmlspecialchars($avatar) ?>" alt="avatar" class="user-avatar" onerror="this.onerror=null; this.src='/assets/image/default_avatar.svg'">
                                    <div>
                                        <div class="user-name" id="name-<?= $user['user_id'] ?>"><?= htmlspecialchars($user['display_name'] ?: 'ไม่มีชื่อ') ?></div>
                                        <div class="user-email" id="email-<?= $user['user_id'] ?>"><?= htmlspecialchars($user['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="text-align: left;"><?= htmlspecialchars($bizName) ?></td>
                            <td style="white-space: nowrap; text-align: center;">
                                <span class="<?= $online['status'] === 'online' ? 'online-dot' : 'offline-dot' ?>"></span>
                                <?= $online['text'] ?>
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <span class="status-badge" style="color: <?= $ban['color'] ?>; background: <?= $ban['color'] ?>20;">
                                    <?= $ban['text'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-info" title="ข้อมูลบัญชี" style="padding: 6px 10px;" onclick="viewUserDetails(<?= htmlspecialchars(json_encode([
                                        'display_name' => $user['display_name'],
                                        'email' => $user['email'],
                                        'created_at' => $user['created_at'],
                                        'last_active' => $user['last_active'],
                                        'is_banned' => $user['is_banned'],
                                        'ban_until' => $user['ban_until'],
                                        'ban_reason' => $user['ban_reason'],
                                        'biz_name' => $bizName,
                                        'role' => $tabId
                                    ])) ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                    </button>
                                    
                                    <button class="btn-edit" onclick="openEditModal(<?= $user['user_id'] ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        แก้ไข
                                    </button>
                                    
                                    <?php if ($ban['banned']): ?>
                                        <button class="btn-unban" onclick="unbanUser(<?= $user['user_id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>
                                            ปลดแบน
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-ban" onclick="banUser(<?= $user['user_id'] ?>)">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                                            แบน
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button class="btn-delete" onclick="deleteUser(<?= $user['user_id'] ?>)">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                        ลบ
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                        <tr><td colspan="5" style="text-align:center; color:#999; padding:40px;">ไม่มีข้อมูลผู้ใช้งาน</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
            
        </main>
    </div>

    <!-- Info Modal -->
    <div class="modal-overlay" id="infoModal">
        <div class="modal-card">
            <h3>รายละเอียดบัญชี</h3>
            <div style="font-size: 14px; line-height: 1.6; color: var(--text-dark);">
                <p><strong>ชื่อผู้ใช้:</strong> <span id="info_name"></span></p>
                <p><strong>อีเมล:</strong> <span id="info_email"></span></p>
                <p id="info_biz_wrap" style="display:none;"><strong>ชื่อร้าน/บริษัท:</strong> <span id="info_biz"></span></p>
                <p><strong>วันที่สมัคร:</strong> <span id="info_created"></span></p>
                <p><strong>ใช้งานล่าสุด:</strong> <span id="info_active"></span></p>
                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 15px 0;">
                <p><strong>สถานะบัญชี:</strong> <span id="info_status" class="status-badge" style="padding: 2px 8px;"></span></p>
                <p id="info_ban_reason_wrap" style="display:none; color: var(--accent-red); margin-top: 5px;"><strong>เหตุผลที่แบน:</strong> <span id="info_ban_reason"></span></p>
            </div>
            <div class="modal-actions">
                <button class="btn-edit" onclick="closeInfoModal()" style="padding:10px 20px;">ปิด</button>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-card">
            <h3>แก้ไขข้อมูลพื้นฐาน</h3>
            <input type="hidden" id="edit_user_id">
            <div class="input-group">
                <label>ชื่อ (Display Name)</label>
                <input type="text" id="edit_name">
            </div>
            <div class="input-group">
                <label>อีเมล (Email)</label>
                <input type="email" id="edit_email">
            </div>
            <div class="modal-actions">
                <button class="btn-edit" onclick="closeEditModal()" style="padding:10px 20px;">ยกเลิก</button>
                <button class="btn-unban" style="background:#0b5963; color:#fff; padding:10px 20px;" onclick="saveUserEdit()">บันทึกข้อมูล</button>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.segment-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.section-container').forEach(sec => sec.classList.remove('active'));
            
            const activeBtn = document.querySelector(`.segment-btn[onclick="switchTab('${tabId}')"]`);
            if (activeBtn) activeBtn.classList.add('active');
            
            const activeSection = document.getElementById('section-' + tabId);
            if (activeSection) activeSection.classList.add('active');
            
            localStorage.setItem('activeManageUsersTab', tabId);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const savedTab = localStorage.getItem('activeManageUsersTab');
            if (savedTab) {
                switchTab(savedTab);
            }
        });

        // ---------- Info Modal ----------
        function viewUserDetails(data) {
            document.getElementById('info_name').innerText = data.display_name || 'ไม่มีชื่อ';
            document.getElementById('info_email').innerText = data.email || '-';
            
            if (data.role !== 'reader' && data.biz_name !== '-') {
                document.getElementById('info_biz_wrap').style.display = 'block';
                document.getElementById('info_biz').innerText = data.biz_name;
            } else {
                document.getElementById('info_biz_wrap').style.display = 'none';
            }
            
            // Format dates
            const createdObj = new Date(data.created_at);
            document.getElementById('info_created').innerText = !isNaN(createdObj) ? createdObj.toLocaleString('th-TH') : '-';
            
            if (data.last_active) {
                document.getElementById('info_active').innerText = new Date(data.last_active).toLocaleString('th-TH');
            } else {
                document.getElementById('info_active').innerText = 'ไม่เคยออนไลน์';
            }
            
            // Status
            const statusEl = document.getElementById('info_status');
            const reasonWrap = document.getElementById('info_ban_reason_wrap');
            if (data.is_banned === 1) {
                statusEl.innerText = 'แบนถาวร';
                statusEl.style.color = '#dc3545';
                statusEl.style.background = '#dc354520';
                reasonWrap.style.display = 'block';
                document.getElementById('info_ban_reason').innerText = data.ban_reason || 'ไม่มีเหตุผลระบุ';
            } else if (data.ban_until && new Date(data.ban_until) > new Date()) {
                const untilObj = new Date(data.ban_until);
                statusEl.innerText = 'แบนชั่วคราวถึง ' + untilObj.toLocaleString('th-TH');
                statusEl.style.color = '#fd7e14';
                statusEl.style.background = '#fd7e1420';
                reasonWrap.style.display = 'block';
                document.getElementById('info_ban_reason').innerText = data.ban_reason || 'ไม่มีเหตุผลระบุ';
            } else {
                statusEl.innerText = 'ปกติ';
                statusEl.style.color = '#28a745';
                statusEl.style.background = '#28a74520';
                reasonWrap.style.display = 'none';
            }
            
            document.getElementById('infoModal').classList.add('active');
        }
        
        function closeInfoModal() {
            document.getElementById('infoModal').classList.remove('active');
        }

        // ---------- Edit Modal ----------
        function openEditModal(userId) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_name').value = document.getElementById('name-' + userId).innerText;
            document.getElementById('edit_email').value = document.getElementById('email-' + userId).innerText;
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function saveUserEdit() {
            const userId = document.getElementById('edit_user_id').value;
            const name = document.getElementById('edit_name').value;
            const email = document.getElementById('edit_email').value;
            
            fetch('manage_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'edit', user_id: userId, display_name: name, email: email })
            }).then(r => r.json()).then(res => {
                if (res.success) {
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: 'บันทึกข้อมูลเรียบร้อยแล้ว',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        customClass: { popup: 'swal-prompt-font', title: 'swal-prompt-font' }
                    }).then(() => location.reload());
                }
            });
        }

        // ---------- Ban / Unban ----------
        function banUser(userId) {
            Swal.fire({
                title: 'ระงับการใช้งาน',
                html: `
                    <div style="text-align:left; margin-bottom:15px; font-family:'Prompt', sans-serif;">
                        <label style="display:block; margin-bottom:5px; font-size:14px; font-weight:500;">รูปแบบการแบน</label>
                        <select id="swal-ban-type" class="swal2-select" style="display:flex; width:100%; margin:0; font-size:15px;">
                            <option value="permanent">แบนถาวร</option>
                            <option value="temporary">แบนชั่วคราว (กำหนดวัน)</option>
                        </select>
                    </div>
                    <div id="swal-days-container" style="text-align:left; margin-bottom:15px; display:none; font-family:'Prompt', sans-serif;">
                        <label style="display:block; margin-bottom:5px; font-size:14px; font-weight:500;">จำนวนวัน</label>
                        <input id="swal-ban-days" type="number" class="swal2-input" style="width:100%; margin:0; font-size:15px;" value="7" min="1">
                    </div>
                    <div style="text-align:left; font-family:'Prompt', sans-serif;">
                        <label style="display:block; margin-bottom:5px; font-size:14px; font-weight:500;">หมายเหตุ / เหตุผล</label>
                        <textarea id="swal-ban-reason" class="swal2-textarea" style="width:100%; margin:0; font-size:15px;" placeholder="ตัวอย่าง: ละเมิดกฎการใช้งาน..."></textarea>
                    </div>
                `,
                didOpen: () => {
                    document.getElementById('swal-ban-type').addEventListener('change', function() {
                        document.getElementById('swal-days-container').style.display = this.value === 'temporary' ? 'block' : 'none';
                    });
                },
                showCancelButton: true,
                confirmButtonText: 'ยืนยันแบน',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#dc3545',
                customClass: {
                    popup: 'swal-prompt-font',
                    title: 'swal-prompt-font',
                    confirmButton: 'swal-prompt-font',
                    cancelButton: 'swal-prompt-font'
                },
                preConfirm: () => {
                    return {
                        ban_type: document.getElementById('swal-ban-type').value,
                        ban_days: document.getElementById('swal-ban-days').value,
                        ban_reason: document.getElementById('swal-ban-reason').value
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const data = new URLSearchParams({
                        action: 'ban',
                        user_id: userId,
                        ban_type: result.value.ban_type,
                        ban_days: result.value.ban_days,
                        ban_reason: result.value.ban_reason
                    });
                    fetch('manage_users.php', { 
                        method: 'POST', 
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: data 
                    }).then(r => r.json()).then(res => {
                        if (res.success) {
                            Swal.fire({
                                title: 'สำเร็จ!',
                                text: 'แบนผู้ใช้งานเรียบร้อยแล้ว',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false,
                                customClass: { popup: 'swal-prompt-font', title: 'swal-prompt-font' }
                            }).then(() => location.reload());
                        }
                    });
                }
            });
        }
        
        function unbanUser(userId) {
            Swal.fire({
                title: 'ยืนยันปลดแบน?',
                text: "ผู้ใช้งานจะสามารถกลับมาเข้าสู่ระบบได้ตามปกติ",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ปลดแบน',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#28a745',
                customClass: {
                    popup: 'swal-prompt-font',
                    title: 'swal-prompt-font',
                    confirmButton: 'swal-prompt-font',
                    cancelButton: 'swal-prompt-font'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('manage_users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'unban', user_id: userId })
                    }).then(r => r.json()).then(res => {
                        if (res.success) {
                            Swal.fire({
                                title: 'สำเร็จ!',
                                text: 'ปลดแบนเรียบร้อยแล้ว',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false,
                                customClass: { popup: 'swal-prompt-font', title: 'swal-prompt-font' }
                            }).then(() => location.reload());
                        }
                    });
                }
            });
        }
        
        // ---------- Delete ----------
        function deleteUser(userId) {
            Swal.fire({
                title: 'ยืนยันการลบบัญชี?',
                text: "การกระทำนี้จะลบข้อมูลผู้ใช้งานและข้อมูลร้านค้า/สำนักพิมพ์ (หากมี) ออกจากระบบ และไม่สามารถกู้คืนได้",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ยืนยันลบ',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#e11d48',
                customClass: {
                    popup: 'swal-prompt-font',
                    title: 'swal-prompt-font',
                    confirmButton: 'swal-prompt-font',
                    cancelButton: 'swal-prompt-font'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('manage_users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'delete', user_id: userId })
                    }).then(r => r.json()).then(res => {
                        if (res.success) {
                            Swal.fire({
                                title: 'ลบสำเร็จ!',
                                text: 'ลบข้อมูลเรียบร้อยแล้ว',
                                icon: 'success',
                                timer: 1500,
                                showConfirmButton: false,
                                customClass: { popup: 'swal-prompt-font', title: 'swal-prompt-font' }
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({
                                title: 'ไม่สามารถลบได้',
                                text: res.error,
                                icon: 'error',
                                confirmButtonText: 'ตกลง',
                                customClass: { popup: 'swal-prompt-font' }
                            });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
