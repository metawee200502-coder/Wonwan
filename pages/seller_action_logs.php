<?php
session_start();
require_once __DIR__.'/../config/config.php';

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
$active_menu='seller_action_logs';

// Handle filters and search
$searchQuery = trim($_GET['search'] ?? '');
$adminFilter = (int)($_GET['user_id'] ?? 0);

$whereClauses = ["l.seller_type = :seller_type", "l.seller_id = :seller_id"];
$params = [
    'seller_type' => $sellerType,
    'seller_id' => $sellerId
];

if ($searchQuery !== '') {
    $whereClauses[] = "(l.action_type LIKE :search OR l.target_id LIKE :search OR l.details LIKE :search)";
    $params['search'] = "%{$searchQuery}%";
}

if ($adminFilter > 0) {
    $whereClauses[] = "l.user_id = :user_id";
    $params['user_id'] = $adminFilter;
}

$whereSql = implode(' AND ', $whereClauses);

// Fetch logs
$staffTable = $sellerType === 'publisher' ? 'publisher_staff' : 'shop_staff';
$staffCol = $sellerType === 'publisher' ? 'publisher_id' : 'shop_id';

$sql = "
    SELECT 
        l.*, 
        u.display_name,
        u.username,
        u.email,
        s.position,
        s.contact_email
    FROM seller_action_logs l
    JOIN users u ON l.user_id = u.user_id
    LEFT JOIN $staffTable s ON s.user_id = l.user_id AND s.$staffCol = l.seller_id
    WHERE $whereSql
    ORDER BY l.created_at DESC 
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all distinct admins for the dropdown filter
$adminListSql = "
    SELECT DISTINCT u.user_id, u.display_name, u.email 
    FROM seller_action_logs l 
    JOIN users u ON l.user_id = u.user_id 
    WHERE l.seller_type = ? AND l.seller_id = ?
    ORDER BY u.display_name
";
$adminListStmt = $pdo->prepare($adminListSql);
$adminListStmt->execute([$sellerType, $sellerId]);
$adminList = $adminListStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ประวัติการทำรายการ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        body { background: #fdfbfb; margin: 0; display: flex; min-height: 100vh; font-family: 'Prompt', sans-serif;}
        .admin-main-wrap { flex: 1; min-width: 0; padding: 40px 50px; margin-left: 260px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-title { color: #91576d; font-size: 26px; font-weight: bold; display: flex; align-items: center; gap: 10px; margin: 0;}
        
        /* Filter Bar */
        .filter-bar { background: #fff; border-radius: 12px; border: 1px solid #e3c3ce; padding: 20px; margin-bottom: 30px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .filter-bar select, .filter-bar input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            min-width: 250px;
            transition: border-color 0.2s;
        }
        .filter-bar select:focus, .filter-bar input:focus { border-color: #c77c99; }
        .btn-search { background: #c77c99; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: 0.2s; font-family: inherit; }
        .btn-search:hover { background: #ad637f; box-shadow: 0 4px 10px rgba(199, 124, 153, 0.2); }

        /* Table */
        .tablebox { background: #fff; border-radius: 16px; overflow-x: auto; border: 1px solid #e3c3ce; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .orders { width: 100%; border-collapse: collapse; min-width: 800px; }
        .orders th, .orders td { padding: 18px 20px; border-bottom: 1px solid #f4e8eb; text-align: left; }
        .orders th { background: #fbf2f5; color: #91576d; font-weight: 600; font-size: 14px; }
        .orders tr:hover { background: #fdf9fa; }
        .orders td { color: #444; font-size: 14px; vertical-align: top; }
        
        .admin-profile { display: flex; flex-direction: column; gap: 2px; }
        .admin-name { font-weight: 600; color: #333; }
        .admin-pos { font-size: 12px; color: #777; }
        .admin-email-tag { font-size: 12px; color: #91576d; background: #f0d4de; padding: 2px 6px; border-radius: 4px; display: inline-block; width: fit-content; margin-top: 4px;}

        .action-badge { 
            padding: 6px 12px; 
            border-radius: 8px; 
            font-size: 12px; 
            font-weight: bold; 
            background: #e8f5e9; 
            color: #2e7d32; 
            border: 1px solid #c8e6c9;
            display: inline-block;
        }
        .details-text { line-height: 1.5; color: #555; max-width: 400px; white-space: pre-wrap; }
        
        .empty-state { text-align: center; padding: 50px 20px; color: #888; font-size: 15px; }

        @media(max-width:800px) {
            .admin-main-wrap { margin-left: 0; padding: 25px 16px; }
        }
    </style>
</head>
<body>

<?php include __DIR__.'/../components/sidebar.php'; ?>

<div class="admin-main-wrap">
    
    <div class="page-header">
        <h2 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            ประวัติการทำรายการ (Action Logs)
        </h2>
    </div>

    <form method="GET" class="filter-bar">
        <div>
            <select name="user_id">
                <option value="">-- เลือกแอดมินทั้งหมด --</option>
                <?php foreach($adminList as $adm): ?>
                    <option value="<?= htmlspecialchars($adm['user_id']) ?>" <?= $adminFilter === (int)$adm['user_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($adm['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <input type="text" name="search" placeholder="ค้นหา รหัส, รายละเอียด, แอคชัน..." value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
        <button type="submit" class="btn-search">ค้นหา</button>
        <?php if($searchQuery || $adminFilter): ?>
            <a href="seller_action_logs.php" style="text-decoration:none; color:#c77c99; font-size:14px; font-weight:500;">ล้างตัวกรอง</a>
        <?php endif; ?>
    </form>

    <div class="tablebox">
        <table class="orders">
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>ข้อมูลแอดมิน</th>
                    <th>ประเภทแอคชัน</th>
                    <th>เป้าหมาย (Target)</th>
                    <th>รายละเอียด</th>
                    <th>วัน-เวลาที่ทำรายการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($logs)): ?>
                <tr>
                    <td colspan="6"><div class="empty-state">ยังไม่มีประวัติการทำรายการในระบบ</div></td>
                </tr>
                <?php else: foreach($logs as $log): ?>
                <tr>
                    <td style="font-weight: 500; color: #91576d;">#<?= $log['log_id'] ?></td>
                    <td>
                        <div class="admin-profile">
                            <span class="admin-name"><?= htmlspecialchars($log['display_name'] ?? 'Unknown Admin') ?></span>
                            <span class="admin-pos"><?= htmlspecialchars($log['position'] ?? 'Customer Support') ?></span>
                            <?php if(!empty($log['username'])): ?>
                                <span class="admin-email-tag">@<?= htmlspecialchars($log['username']) ?></span>
                            <?php else: ?>
                                <span class="admin-email-tag"><?= htmlspecialchars(!empty($log['contact_email']) ? $log['contact_email'] : $log['email']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><span class="action-badge"><?= htmlspecialchars($log['action_type']) ?></span></td>
                    <td style="font-weight: 500; color: #555;">
                        <?= $log['target_id'] ? '#'.htmlspecialchars($log['target_id']) : '-' ?>
                    </td>
                    <td>
                        <div class="details-text"><?= htmlspecialchars($log['details']) ?></div>
                    </td>
                    <td style="color: #666;">
                        <?= date('d/m/Y', strtotime($log['created_at'])) ?><br>
                        <?= date('H:i', strtotime($log['created_at'])) ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
