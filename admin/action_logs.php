<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Strict Access Control for Master Admin Only
if (($_SESSION['email'] ?? '') !== 'admin1@wonwan') {
    http_response_code(403);
    exit('Access Denied');
}

$active_menu = 'action_logs';

// Handle filters and search
$searchQuery = trim($_GET['search'] ?? '');
$adminFilter = trim($_GET['admin_email'] ?? '');

$whereClauses = ["1=1"];
$params = [];

if ($searchQuery !== '') {
    $whereClauses[] = "(l.action_type LIKE :search OR l.target_id LIKE :search OR l.details LIKE :search)";
    $params['search'] = "%{$searchQuery}%";
}

if ($adminFilter !== '') {
    $whereClauses[] = "l.admin_email = :admin_email";
    $params['admin_email'] = $adminFilter;
}

$whereSql = implode(' AND ', $whereClauses);

// Fetch logs with admin details
$sql = "
    SELECT 
        l.*, 
        COALESCE(a.name, 'Master Admin') as admin_name,
        COALESCE(a.position, 'Super Admin') as admin_position
    FROM admin_action_logs l
    LEFT JOIN admins a ON l.admin_email = a.email
    WHERE $whereSql
    ORDER BY l.created_at DESC 
    LIMIT 500
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all distinct admins for the dropdown
$adminList = $pdo->query("
    SELECT DISTINCT l.admin_email, COALESCE(a.name, 'Master Admin') as admin_name 
    FROM admin_action_logs l 
    LEFT JOIN admins a ON l.admin_email = a.email 
    ORDER BY admin_name
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติการทำรายการ (Action Logs) - Wonwan Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    
    <style>
        body { background: #fdfbfb; margin: 0; display: flex; min-height: 100vh; overflow-y: scroll; font-family: 'Prompt', sans-serif;}
        .admin-main-wrap { flex: 1; min-width: 0; padding: 40px 50px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-title { color: #00796B; font-size: 26px; font-weight: bold; display: flex; align-items: center; gap: 10px; margin: 0;}
        
        /* Filters */
        .filter-bar { display: flex; gap: 15px; align-items: center; background: #fff; padding: 15px 20px; border-radius: 12px; border: 1px solid #e0f2f1; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.01); }
        .filter-bar input, .filter-bar select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            min-width: 200px;
        }
        .filter-bar input:focus, .filter-bar select:focus {
            border-color: #00796B;
        }
        .btn-search {
            background: #00796B;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: inherit;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-search:hover { background: #00695C; }
        .btn-clear {
            background: #f0f0f0;
            color: #555;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-clear:hover { background: #e0e0e0; }

        /* Table */
        .tablebox { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e0f2f1; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .orders { width: 100%; border-collapse: collapse; min-width: 800px; }
        .orders th, .orders td { padding: 18px 20px; border-bottom: 1px solid #f0f0f0; text-align: left; }
        .orders th { background: #f5fbfb; color: #555; font-weight: 600; font-size: 14px; }
        .orders tr:hover { background: #fafafa; }
        .orders td { color: #444; font-size: 14px; vertical-align: top; }
        
        .admin-profile { display: flex; flex-direction: column; gap: 2px; }
        .admin-name { font-weight: 600; color: #333; }
        .admin-pos { font-size: 12px; color: #777; }
        .admin-email-tag { font-size: 12px; color: #00796B; background: #e0f2f1; padding: 2px 6px; border-radius: 4px; display: inline-block; width: fit-content; margin-top: 4px;}

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
    </style>
</head>
<body>

<?php include '../components/admin_sidebar.php'; ?>

<div class="admin-main-wrap">
    
    <div class="page-header">
        <h2 class="page-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            ประวัติการทำรายการ (Action Logs)
        </h2>
    </div>

    <form method="GET" class="filter-bar">
        <div>
            <select name="admin_email">
                <option value="">-- เลือกแอดมินทั้งหมด --</option>
                <?php foreach($adminList as $adm): ?>
                    <option value="<?= htmlspecialchars($adm['admin_email']) ?>" <?= $adminFilter === $adm['admin_email'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($adm['admin_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <input type="text" name="search" placeholder="ค้นหา รหัส, รายละเอียด, แอคชัน..." value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
        <button type="submit" class="btn-search">ค้นหา</button>
        <?php if($searchQuery !== '' || $adminFilter !== ''): ?>
            <a href="action_logs.php" class="btn-clear">ล้างค่า</a>
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
                <?php if (empty($logs)): ?>
                    <tr><td colspan="6"><div class="empty-state">ไม่พบประวัติการทำรายการ</div></td></tr>
                <?php else: ?>
                    <?php foreach($logs as $log): ?>
                    <tr>
                        <td style="font-weight: 500; color: #00796B;">#<?= $log['log_id'] ?></td>
                        <td>
                            <div class="admin-profile">
                                <span class="admin-name"><?= htmlspecialchars($log['admin_name']) ?></span>
                                <span class="admin-pos"><?= htmlspecialchars($log['admin_position']) ?></span>
                                <span class="admin-email-tag"><?= htmlspecialchars($log['admin_email']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="action-badge"><?= htmlspecialchars($log['action_type']) ?></span>
                        </td>
                        <td style="font-weight: 600;">#<?= htmlspecialchars($log['target_id']) ?></td>
                        <td><div class="details-text"><?= htmlspecialchars($log['details']) ?></div></td>
                        <td style="color: #666;"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
