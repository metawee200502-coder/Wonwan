<?php
session_start();
require_once __DIR__ . '/../config/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

// --- กรองข้อมูล (เลือกเดือน / ปี) ---
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedMonth = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : '';

$statWhere = "YEAR(created_at) = ?";
$statParams = [$selectedYear];
$orderWhere = "YEAR(paid_at) = ?";
$orderParams = [$selectedYear];

if ($selectedMonth) {
    $statWhere .= " AND MONTH(created_at) = ?";
    $statParams[] = $selectedMonth;
    $orderWhere .= " AND MONTH(paid_at) = ?";
    $orderParams[] = $selectedMonth;
}

// --- ดึงข้อมูลสถิติภาพรวม ตามช่วงเวลาที่เลือก ---
$totalSalesStmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'paid' AND $orderWhere");
$totalSalesStmt->execute($orderParams);
$totalSales = (float)$totalSalesStmt->fetchColumn();

$totalExchangesStmt = $pdo->prepare("SELECT COUNT(*) FROM exchanges WHERE $statWhere");
$totalExchangesStmt->execute($statParams);
$totalExchanges = (int)$totalExchangesStmt->fetchColumn();

$totalPublishersStmt = $pdo->prepare("SELECT COUNT(*) FROM publishers WHERE $statWhere");
$totalPublishersStmt->execute($statParams);
$totalPublishers = (int)$totalPublishersStmt->fetchColumn();

$totalShopsStmt = $pdo->prepare("SELECT COUNT(*) FROM shops WHERE $statWhere");
$totalShopsStmt->execute($statParams);
$totalShops = (int)$totalShopsStmt->fetchColumn();

$totalUsersStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'reader' AND $statWhere");
$totalUsersStmt->execute($statParams);
$totalUsers = (int)$totalUsersStmt->fetchColumn();

// --- ข้อมูลกราฟ ---
$dates = [];
$labels = [];

if ($selectedMonth) {
    // ดูรายเดือน (รายวันในเดือนนั้น)
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
    for ($i = 1; $i <= $daysInMonth; $i++) {
        $d = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $i);
        $dates[] = $d;
        $labels[] = (string)$i;
    }
    
    $salesSql = "SELECT DATE(paid_at) as key_date, SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND YEAR(paid_at) = ? AND MONTH(paid_at) = ? GROUP BY DATE(paid_at)";
    $userSql = "SELECT DATE(created_at) as key_date, COUNT(*) as count FROM users WHERE role = 'reader' AND YEAR(created_at) = ? AND MONTH(created_at) = ? GROUP BY DATE(created_at)";
    $pubSql = "SELECT DATE(created_at) as key_date, COUNT(*) as count FROM publishers WHERE YEAR(created_at) = ? AND MONTH(created_at) = ? GROUP BY DATE(created_at)";
    $shopSql = "SELECT DATE(created_at) as key_date, COUNT(*) as count FROM shops WHERE YEAR(created_at) = ? AND MONTH(created_at) = ? GROUP BY DATE(created_at)";
    $params = [$selectedYear, $selectedMonth];
    
} else {
    // ดูรายปี (12 เดือน)
    $thaiMonths = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    for ($i = 1; $i <= 12; $i++) {
        $dates[] = $i;
        $labels[] = $thaiMonths[$i-1];
    }
    
    $salesSql = "SELECT MONTH(paid_at) as key_date, SUM(total_amount) as total FROM orders WHERE payment_status = 'paid' AND YEAR(paid_at) = ? GROUP BY MONTH(paid_at)";
    $userSql = "SELECT MONTH(created_at) as key_date, COUNT(*) as count FROM users WHERE role = 'reader' AND YEAR(created_at) = ? GROUP BY MONTH(created_at)";
    $pubSql = "SELECT MONTH(created_at) as key_date, COUNT(*) as count FROM publishers WHERE YEAR(created_at) = ? GROUP BY MONTH(created_at)";
    $shopSql = "SELECT MONTH(created_at) as key_date, COUNT(*) as count FROM shops WHERE YEAR(created_at) = ? GROUP BY MONTH(created_at)";
    $params = [$selectedYear];
}

// Fetch Sales
$salesStmt = $pdo->prepare($salesSql); $salesStmt->execute($params);
$salesSeries = array_fill_keys($dates, 0);
foreach ($salesStmt as $row) { $salesSeries[$row['key_date']] = (float)$row['total']; }
$salesSeries = array_values($salesSeries);

// Fetch Users
$userStmt = $pdo->prepare($userSql); $userStmt->execute($params);
$userSeries = array_fill_keys($dates, 0);
foreach ($userStmt as $row) { $userSeries[$row['key_date']] = (int)$row['count']; }
$userSeries = array_values($userSeries);

// Fetch Pubs
$pubStmt = $pdo->prepare($pubSql); $pubStmt->execute($params);
$pubSeries = array_fill_keys($dates, 0);
foreach ($pubStmt as $row) { $pubSeries[$row['key_date']] = (int)$row['count']; }
$pubSeries = array_values($pubSeries);

// Fetch Shops
$shopStmt = $pdo->prepare($shopSql); $shopStmt->execute($params);
$shopSeries = array_fill_keys($dates, 0);
foreach ($shopStmt as $row) { $shopSeries[$row['key_date']] = (int)$row['count']; }
$shopSeries = array_values($shopSeries);

// --- ข้อมูลกล่องรายการล่าสุด (Widgets) ---
// 1. ตรวจสอบสลิป (คำสั่งซื้อที่ payment_status = 'reviewing')
$pendingOrders = $pdo->query("SELECT order_id, order_number, total_amount, created_at FROM orders WHERE payment_status = 'reviewing' ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// 2. สมัครร้านค้า/สำนักพิมพ์ (status = 'pending')
$pendingSellers = $pdo->query("
    SELECT 'shop' as type, shop_id as id, shop_name as name, created_at FROM shops WHERE status = 'pending'
    UNION ALL
    SELECT 'publisher' as type, publisher_id as id, company_name as name, created_at FROM publishers WHERE status = 'pending'
    ORDER BY created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 3. สมาชิกใหม่ล่าสุด
$latestUsers = $pdo->query("SELECT user_id, display_name, created_at FROM users WHERE role = 'reader' ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

// 4. QC สินค้าที่ลูกค้าส่งมา
$pendingQC = $pdo->query("
    SELECT request_id, created_at 
    FROM exchange_requests 
    WHERE status = 'admin_qc' AND (admin_qc_requester_status = 'pending' OR admin_qc_offer_status = 'pending')
    ORDER BY created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// 5. รายงานโพสต์/โปรไฟล์
// รอระบบ Report ในอนาคต
$pendingReports = [];

// --- Top 10 ร้านขายดี ---
$topSellersSql = "
    SELECT b.seller_type, b.seller_id, SUM(oi.price_at_purchase * oi.quantity) as total_sales, COUNT(DISTINCT o.order_id) as total_orders
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    JOIN books b ON b.book_id = oi.book_id
    WHERE o.payment_status = 'paid' AND $orderWhere
    GROUP BY b.seller_type, b.seller_id
    ORDER BY total_sales DESC
    LIMIT 5
";
$topSellersStmt = $pdo->prepare($topSellersSql);
$topSellersStmt->execute($orderParams);
$topSellers = $topSellersStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($topSellers as $idx => &$ts) {
    if ($ts['seller_type'] === 'shop') {
        $info = $pdo->query("SELECT shop_name, profile_image FROM shops WHERE shop_id = " . (int)$ts['seller_id'])->fetch(PDO::FETCH_ASSOC);
        $ts['name'] = $info['shop_name'] ?? 'ร้านค้าไม่ทราบชื่อ';
        $img = !empty($info['profile_image']) ? (strpos($info['profile_image'], 'http') === 0 ? $info['profile_image'] : '/' . ltrim($info['profile_image'], '/')) : null;
        $ts['image'] = $img ?: 'https://ui-avatars.com/api/?name='.urlencode($ts['name']).'&background=random';
    } else {
        $info = $pdo->query("SELECT company_name, profile_image FROM publishers WHERE publisher_id = " . (int)$ts['seller_id'])->fetch(PDO::FETCH_ASSOC);
        $ts['name'] = $info['company_name'] ?? 'สำนักพิมพ์ไม่ทราบชื่อ';
        $img = !empty($info['profile_image']) ? (strpos($info['profile_image'], 'http') === 0 ? $info['profile_image'] : '/' . ltrim($info['profile_image'], '/')) : null;
        $ts['image'] = $img ?: 'https://ui-avatars.com/api/?name='.urlencode($ts['name']).'&background=random';
    }
    // Mock data for UI demonstration
    $ts['rating'] = number_format(rand(45, 50)/10, 1);
    $ts['growth'] = '+'.rand(10, 35).'%';
}
unset($ts);

// --- Top 5 Book Categories ---
$topCategoriesStmt = $pdo->prepare("
    SELECT b.category, SUM(oi.price_at_purchase * oi.quantity) as total_sales
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    JOIN books b ON b.book_id = oi.book_id
    WHERE o.payment_status = 'paid' AND $orderWhere AND b.category IS NOT NULL AND b.category != ''
    GROUP BY b.category
    ORDER BY total_sales DESC
    LIMIT 5
");
$topCategoriesStmt->execute($orderParams);
$topCategories = $topCategoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$catLabels = [];
$catData = [];
foreach ($topCategories as $cat) {
    $catLabels[] = $cat['category'];
    $catData[] = (float)$cat['total_sales'];
}

// --- Top 10 Bestselling Books ---
$topBooksStmt = $pdo->prepare("
    SELECT b.title, b.author, b.image_cover, SUM(oi.price_at_purchase * oi.quantity) as total_sales, SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON o.order_id = oi.order_id
    JOIN books b ON b.book_id = oi.book_id
    WHERE o.payment_status = 'paid' AND $orderWhere
    GROUP BY b.book_id
    ORDER BY total_sales DESC
    LIMIT 5
");
$topBooksStmt->execute($orderParams);
$topBooks = $topBooksStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Exchange System Health ---
$excStatsStmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM exchange_requests 
    WHERE 1=1 AND " . str_replace('created_at', 'updated_at', $statWhere) . "
    GROUP BY status
");
$excStatsStmt->execute($statParams);
$excStats = $excStatsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalExcCompleted = $excStats['completed'] ?? 0;
$totalExcFailed = ($excStats['rejected'] ?? 0) + ($excStats['cancelled'] ?? 0);
$totalExcProcessed = $totalExcCompleted + $totalExcFailed;
$exchangeSuccessRate = $totalExcProcessed > 0 ? round(($totalExcCompleted / $totalExcProcessed) * 100, 1) : 0;

$openExchangesCount = $pdo->query("SELECT COUNT(*) FROM exchanges WHERE status = 'open'")->fetchColumn();
$exchangeInventoryValue = $openExchangesCount * 150; // Estimated 150 THB per book

// --- Shop & Publisher Health ---
$thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
$inactiveShopsCount = $pdo->query("
    SELECT COUNT(*) FROM shops s 
    WHERE NOT EXISTS (
        SELECT 1 FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN books b ON b.book_id = oi.book_id 
        WHERE b.seller_id = s.shop_id AND b.seller_type = 'shop' AND o.created_at > '$thirtyDaysAgo'
    ) AND s.status = 'approved'
")->fetchColumn();
$complaintRate = 1.2; // Mocked rate for UI

// Years list for dropdown
$availableYears = array_map('intval', $pdo->query("SELECT DISTINCT YEAR(created_at) FROM orders UNION SELECT DISTINCT YEAR(created_at) FROM users")->fetchAll(PDO::FETCH_COLUMN));
if (!in_array((int)date('Y'), $availableYears)) array_unshift($availableYears, (int)date('Y'));
rsort($availableYears);

$active_menu = 'dashboard';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ระบบหลังบ้าน (Admin) - วนวรรณ</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: var(--font-family);
            background-color: #f4f9f7;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
        }

        .admin-main-wrap {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Topbar */
        .topbar {
            background-color: #ffffff;
            border-bottom: 1px solid #cde3db;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 8px;
            background-color: #f4f9f7;
            border: 1px solid #cde3db;
            border-radius: var(--radius-md);
            padding: 8px 14px;
            width: 340px;
        }

        .search-box input {
            border: none;
            background: transparent;
            font-family: var(--font-family);
            font-size: var(--text-sm);
            color: var(--text-dark);
            outline: none;
            width: 100%;
        }

        .search-box svg { color: var(--text-muted); flex-shrink: 0; }

        .btn-export {
            background-color: var(--accent-green);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            padding: 9px 18px;
            font-family: var(--font-family);
            font-size: var(--text-sm);
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: background-color 0.2s;
        }
        .btn-export:hover { background-color: #23584f; }

        .page-body {
            padding: 28px;
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 22px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            background-color: #ffffff;
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            border: 1px solid #cde3db;
            box-shadow: 0 4px 12px rgba(23,85,100,0.03);
            display: flex;
            flex-direction: column;
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-md);
            background-color: #e8f3ef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-green);
        }

        .stat-badge {
            font-size: 11px;
            font-weight: 600;
            color: var(--accent-green);
            background-color: #E8F5E9;
            border-radius: 20px;
            padding: 3px 8px;
        }

        .stat-label {
            font-size: var(--text-sm);
            color: var(--text-muted);
            margin-bottom: 6px;
            font-weight: 500;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--secondary-color);
        }

        /* Charts */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 22px;
        }

        @media (max-width: 1000px) { .charts-row { grid-template-columns: 1fr; } }

        .chart-card {
            background-color: #ffffff;
            border-radius: var(--radius-lg);
            padding: 22px;
            border: 1px solid #cde3db;
            box-shadow: 0 4px 12px rgba(23,85,100,0.03);
        }

        .chart-header {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Widgets Row */
        .widgets-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-top: 22px;
        }
        
        .widgets-row-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
            margin-top: 18px;
        }
        
        @media (max-width: 1000px) { 
            .widgets-row, .widgets-row-2 { grid-template-columns: 1fr; } 
        }
        
        .widget-card {
            background-color: #ffffff;
            border-radius: var(--radius-lg);
            padding: 22px;
            border: 1px solid #cde3db;
            box-shadow: 0 4px 12px rgba(23,85,100,0.03);
            display: flex;
            flex-direction: column;
        }
        
        .widget-header {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .widget-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 14px;
            flex: 1;
            max-height: 320px;
            overflow-y: auto;
            /* Hide scrollbar */
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        
        .widget-list::-webkit-scrollbar { 
            display: none; /* Chrome, Safari and Opera */
        }
        
        .widget-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 14px;
            border-bottom: 1px solid #f4f9f7;
        }
        .widget-item:last-child { border-bottom: none; padding-bottom: 0; }
        
        .widget-item-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .widget-item-title { font-size: 14px; font-weight: 600; color: var(--secondary-color); }
        .widget-item-time { font-size: 12px; color: var(--text-muted); }
        
        .widget-item-action {
            font-size: 12px;
            padding: 5px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            background-color: #e8f3ef;
            color: var(--accent-green);
            transition: 0.2s;
            flex-shrink: 0;
        }
        .widget-item-action:hover { background-color: var(--accent-green); color: #fff; }
        
        .widget-empty {
            font-size: 13px;
            color: var(--text-muted);
            text-align: center;
            padding: 30px 0;
            background: #f4f9f7;
            border-radius: 8px;
            border: 1px dashed #cde3db;
        }
        
        .widget-footer {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f4f9f7;
            text-align: center;
        }
        
        .widget-view-all {
            font-size: 13px;
            font-weight: 600;
            color: var(--secondary-color);
            text-decoration: none;
            transition: 0.2s;
        }
        .widget-view-all:hover {
            color: var(--accent-green);
        }

        /* Leaderboard */
        .leaderboard-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 22px;
            margin-bottom: 22px;
        }
        
        .lb-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #f4f9f7;
            margin-bottom: 14px;
        }
        .lb-title { font-size: 15px; font-weight: 700; color: var(--text-dark); display: flex; align-items: center; gap: 8px; }
        .lb-controls { display: flex; gap: 12px; align-items: center; font-size: 12px; color: var(--text-muted); }
        .lb-btn { padding: 4px 10px; background: #fff; border: 1px solid #cde3db; border-radius: 6px; cursor: pointer; color: var(--text-dark); font-weight: 600; display: flex; align-items: center; gap: 4px; font-size: 12px; }

        .rank-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            margin: 0 16px 10px 16px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #eef2f5;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: transform 0.2s;
            gap: 12px;
        }
        .rank-item:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

        .rank-item.top-1 { background: linear-gradient(to right, #fff9e6, #fff4cc); border-color: #ffe680; }
        .rank-item.top-2 { background: linear-gradient(to right, #f4f6f8, #e9ecef); border-color: #d1d6dc; }
        .rank-item.top-3 { background: linear-gradient(to right, #faebe6, #f3dcd4); border-color: #e6bbaa; }

        .rank-number { font-size: 24px; font-weight: 800; color: #8da4b8; text-align: center; width: 30px; }
        .top-1 .rank-number { color: #d4af37; text-shadow: 1px 1px 0px #b38f22; }
        .top-2 .rank-number { color: #8fa0af; text-shadow: 1px 1px 0px #708090; }
        .top-3 .rank-number { color: #cd7f32; text-shadow: 1px 1px 0px #a66628; }

        .rank-shop { flex: 1; display: flex; align-items: center; gap: 12px; min-width: 0; }
        .rank-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); background: #fff; flex-shrink: 0; }
        .rank-name-wrap { display: flex; flex-direction: column; overflow: hidden; }
        .rank-name { font-size: 14px; font-weight: 700; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rank-meta { font-size: 11px; color: var(--text-muted); display: flex; gap: 8px; align-items: center; margin-top: 3px; }
        
        .rank-col-sales { text-align: right; flex-shrink: 0; }
        .rank-col-sales .value { font-size: 15px; font-weight: 700; color: #0f172a; }
        .rank-col-sales .growth { font-size: 11px; color: #16a34a; font-weight: 600; margin-top: 2px; }
        
        .rank-badge { padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; text-align: center; letter-spacing: 0; }
        .badge-premium { background: #ffe4b5; color: #b8860b; border: 1px solid #ffdaa8; }
        .badge-verified { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }

        @media (max-width: 1000px) {
            .leaderboard-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
    <div class="admin-main-wrap">
        <div class="topbar">
            <div class="search-box">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" placeholder="ค้นหาบันทึกข้อมูล...">
            </div>
            <button class="btn-export" onclick="window.print()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                ส่งออกข้อมูล
            </button>
        </div>

        <div class="page-body">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px;">
                <h1 class="page-title" style="margin-bottom: 0;">แผงควบคุมระบบ (Super Admin)</h1>
                
                <form method="GET" action="index.php" style="display: flex; gap: 10px;">
                    <select name="year" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #cde3db; border-radius: 6px; font-family: var(--font-family); color: var(--text-dark); background: #fff; outline: none; cursor: pointer;">
                        <?php foreach ($availableYears as $y): ?>
                            <option value="<?= $y ?>" <?= $selectedYear === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="month" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #cde3db; border-radius: 6px; font-family: var(--font-family); color: var(--text-dark); background: #fff; outline: none; cursor: pointer;">
                        <option value="">ตลอดทั้งปี</option>
                        <?php 
                        $thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
                        for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $selectedMonth === $m ? 'selected' : '' ?>><?= $thaiMonths[$m-1] ?></option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        </div>
                        <span class="stat-badge">+2.4%</span>
                    </div>
                    <div class="stat-label">ยอดขายรวมของเว็บ</div>
                    <div class="stat-value">฿<?= number_format($totalSales) ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>
                        </div>
                    </div>
                    <div class="stat-label">รายการแลกเปลี่ยน</div>
                    <div class="stat-value"><?= number_format($totalExchanges) ?> <span style="font-size:14px;color:var(--text-muted);font-weight:400">รายการ</span></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                        </div>
                    </div>
                    <div class="stat-label">สำนักพิมพ์ (Publishers)</div>
                    <div class="stat-value"><?= number_format($totalPublishers) ?> <span style="font-size:14px;color:var(--text-muted);font-weight:400">ร้าน</span></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                        </div>
                    </div>
                    <div class="stat-label">ร้านมือ 2 (Shops)</div>
                    <div class="stat-value"><?= number_format($totalShops) ?> <span style="font-size:14px;color:var(--text-muted);font-weight:400">ร้าน</span></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        </div>
                    </div>
                    <div class="stat-label">จำนวนผู้ใช้งาน (Users)</div>
                    <div class="stat-value"><?= number_format($totalUsers) ?> <span style="font-size:14px;color:var(--text-muted);font-weight:400">คน</span></div>
                </div>
            </div>

            <!-- Row 1: Charts Grid -->
            <div class="charts-row">
                <div class="chart-card">
                    <div class="chart-header" style="margin-bottom: 20px;">
                        ยอดขายประจำ<?= $selectedMonth ? 'เดือน ' . $thaiMonths[$selectedMonth-1] : 'ปี' ?> <?= $selectedYear ?>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header" style="margin-bottom: 20px;">
                        การสมัครสมาชิกใหม่ประจำ<?= $selectedMonth ? 'เดือน ' . $thaiMonths[$selectedMonth-1] : 'ปี' ?> <?= $selectedYear ?>
                    </div>
                    <div class="chart-container">
                        <canvas id="usersChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Row 2: Split Left/Right -->
            <div class="leaderboard-row">
                <!-- LEFT COLUMN: Top 5 Shops & Top 5 Books -->
                <div style="display: flex; flex-direction: column; gap: 18px;">
                    <!-- 1. Top 5 Sellers -->
                    <div class="widget-card" style="padding: 0; overflow: hidden; flex: 1;">
                        <div class="lb-header-row">
                            <div class="lb-title" style="display: flex; align-items: center;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: #fbbf24;"><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2v0a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12v0a2 2 0 0 1-2-2V7"/></svg> 
                            Top 5 Shops
                        </div>
                            <div class="lb-controls">
                                <?= $selectedMonth ? 'ประจำเดือน' : 'รายปี' ?>
                                <button class="lb-btn" onclick="window.print()">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                    Export
                                </button>
                            </div>
                        </div>
                        <div class="widget-list" style="padding: 0 0 10px 0; max-height: none;">
                            <?php if (empty($topSellers)): ?>
                                <div class="widget-empty" style="margin: 22px; border: none;">ยังไม่มีข้อมูลการขายในระยะเวลานี้</div>
                            <?php else: ?>
                                <?php foreach ($topSellers as $index => $seller): 
                                    $rank = $index + 1;
                                    $rankClass = $rank <= 3 ? "top-{$rank}" : "";
                                    $badgeClass = $rank <= 3 ? "badge-premium" : "badge-verified";
                                    $badgeText = $rank <= 3 ? "Premium Partner" : "Verified Seller";
                                ?>
                                    <div class="rank-item <?= $rankClass ?>">
                                        <div class="rank-number"><?= $rank ?></div>
                                        <div class="rank-shop">
                                            <img src="<?= htmlspecialchars($seller['image']) ?>" alt="" class="rank-avatar">
                                            <div class="rank-name-wrap">
                                                <span class="rank-name"><?= htmlspecialchars($seller['name']) ?></span>
                                                <div class="rank-meta">
                                                    <span><?= number_format($seller['total_orders']) ?> orders</span>
                                                    <span style="color: #fbbf24;">★ <?= $seller['rating'] ?></span>
                                                    <span class="rank-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="rank-col-sales">
                                            <div class="value">฿<?= number_format($seller['total_sales']) ?></div>
                                            <div class="growth"><?= $seller['growth'] ?> ↗</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. Top 5 Bestselling Books -->
                    <div class="widget-card" style="padding: 0; overflow: hidden; flex: 1;">
                        <div class="lb-header-row">
                            <div class="lb-title" style="display: flex; align-items: center;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: #3b82f6;"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg> 
                            Top 5 Bestselling Books
                        </div>
                        </div>
                        <div class="widget-list" style="padding: 0 0 10px 0; max-height: none;">
                            <?php if (empty($topBooks)): ?>
                                <div class="widget-empty" style="margin: 22px; border: none;">ยังไม่มีข้อมูลการขายหนังสือในระยะเวลานี้</div>
                            <?php else: ?>
                                <?php foreach ($topBooks as $index => $book): 
                                    $rank = $index + 1;
                                    $rankClass = $rank <= 3 ? "top-{$rank}" : "";
                                ?>
                                    <div class="rank-item <?= $rankClass ?>">
                                        <div class="rank-number"><?= $rank ?></div>
                                        <div class="rank-shop">
                                            <img src="<?= htmlspecialchars($book['image_cover'] ?: '/assets/images/default_book.png') ?>" alt="" class="rank-avatar" style="border-radius: 4px;">
                                            <div class="rank-name-wrap">
                                                <span class="rank-name"><?= htmlspecialchars($book['title']) ?></span>
                                                <div class="rank-meta">
                                                    <span><?= htmlspecialchars($book['author'] ?: 'ไม่ระบุผู้แต่ง') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="rank-col-sales">
                                            <div class="value">฿<?= number_format($book['total_sales']) ?></div>
                                            <div class="growth"><?= number_format($book['total_sold']) ?> เล่ม ↗</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Categories & System Health -->
                <div style="display: flex; flex-direction: column; gap: 18px;">
                    <!-- 1. Top Performing Book Categories -->
                    <div class="widget-card" style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
                        <div class="lb-header-row" style="padding: 16px 20px; border-bottom: 1px solid #f4f9f7; margin-bottom: 0;">
                            <div class="lb-title" style="display: flex; align-items: center;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: #8b5cf6;"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg> 
                            Top Categories
                        </div>
                        </div>
                        <div class="chart-container" style="flex: 1; min-height: 180px; display: flex; justify-content: center; align-items: center; padding: 10px;">
                            <canvas id="categoriesChart"></canvas>
                        </div>
                    </div>

                    <!-- 2. Exchange System Health -->
                    <div class="widget-card" style="display: flex; flex-direction: column; flex: 1;">
                        <div class="widget-header" style="margin-bottom: 16px;">
                            <span style="display: flex; align-items: center;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: #10b981;"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg> 
                            Exchange System Health
                        </span>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 12px; flex: 1; justify-content: center;">
                            <div style="background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 12px; color: #64748b; font-weight: 600;">Success Rate</div>
                                    <div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">Completed / Failed</div>
                                </div>
                                <div style="font-size: 24px; font-weight: 800; color: <?= $exchangeSuccessRate >= 70 ? '#16a34a' : '#d97706' ?>;"><?= $exchangeSuccessRate ?>%</div>
                            </div>
                            <div style="background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 12px; color: #64748b; font-weight: 600;">Inventory Value</div>
                                    <div style="font-size: 10px; color: #94a3b8; margin-top: 2px;"><?= number_format($openExchangesCount) ?> books in system</div>
                                </div>
                                <div style="font-size: 20px; font-weight: 800; color: #0f172a;">฿<?= number_format($exchangeInventoryValue) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Shop & Publisher Health -->
                    <div class="widget-card" style="display: flex; flex-direction: column; flex: 1;">
                        <div class="widget-header" style="margin-bottom: 16px;">
                            <span style="display: flex; align-items: center;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px; color: #ef4444;"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg> 
                            Shop & Publisher Health
                        </span>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 12px; flex: 1; justify-content: center;">
                            <div style="background: #fef2f2; padding: 14px; border-radius: 12px; border: 1px solid #fee2e2; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 12px; color: #ef4444; font-weight: 600;">Inactive Shops (30d)</div>
                                    <div style="font-size: 10px; color: #f87171; margin-top: 2px;">Needs attention</div>
                                </div>
                                <div style="font-size: 24px; font-weight: 800; color: #b91c1c;"><?= number_format($inactiveShopsCount) ?></div>
                            </div>
                            <div style="background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-size: 12px; color: #64748b; font-weight: 600;">Complaint/Return Rate</div>
                                    <div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">Estimated average</div>
                                </div>
                                <div style="font-size: 24px; font-weight: 800; color: #d97706;"><?= $complaintRate ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Widgets Row -->
            <div class="widgets-row">
                <div class="widget-card">
                    <div class="widget-header">
                        <span>รายการรอตรวจสอบสลิป</span>
                    </div>
                    <ul class="widget-list">
                        <?php foreach($pendingOrders as $order): ?>
                           <li class="widget-item">
                               <div class="widget-item-info">
                                   <span class="widget-item-title">คำสั่งซื้อ #<?= $order['order_number'] ?></span>
                                   <span class="widget-item-time"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?> (฿<?= number_format($order['total_amount'],2) ?>)</span>
                               </div>
                               <a href="escrow.php" class="widget-item-action">ตรวจสอบ</a>
                           </li>
                        <?php endforeach; ?>
                        <?php if(empty($pendingOrders)): ?>
                            <div class="widget-empty">ไม่มีรายการรอตรวจสอบ</div>
                        <?php endif; ?>
                    </ul>
                    <div class="widget-footer">
                        <a href="escrow.php" class="widget-view-all">ดูทั้งหมด &rarr;</a>
                    </div>
                </div>
                
                <div class="widget-card">
                    <div class="widget-header">
                        <span>ร้านค้า/สำนักพิมพ์ รออนุมัติ</span>
                    </div>
                    <ul class="widget-list">
                         <?php foreach($pendingSellers as $seller): ?>
                           <li class="widget-item">
                               <div class="widget-item-info">
                                   <span class="widget-item-title"><?= htmlspecialchars($seller['name']) ?></span>
                                   <span class="widget-item-time">
                                       <?= $seller['type'] === 'shop' ? 'ร้านมือ 2' : 'สำนักพิมพ์' ?> • <?= date('d/m/Y H:i', strtotime($seller['created_at'])) ?>
                                   </span>
                               </div>
                               <a href="approvals.php" class="widget-item-action">ตรวจสอบ</a>
                           </li>
                        <?php endforeach; ?>
                        <?php if(empty($pendingSellers)): ?>
                            <div class="widget-empty">ไม่มีรายการรอตรวจสอบ</div>
                        <?php endif; ?>
                    </ul>
                    <div class="widget-footer">
                        <a href="approvals.php" class="widget-view-all">ดูทั้งหมด &rarr;</a>
                    </div>
                </div>
                
                <div class="widget-card">
                    <div class="widget-header">
                        <span>สมาชิกล่าสุด (Users)</span>
                    </div>
                    <ul class="widget-list">
                         <?php foreach($latestUsers as $u): ?>
                           <li class="widget-item">
                               <div class="widget-item-info">
                                   <span class="widget-item-title"><?= htmlspecialchars($u['display_name'] ?: 'ผู้ใช้ใหม่') ?></span>
                                   <span class="widget-item-time">สมัครเมื่อ <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></span>
                               </div>
                           </li>
                        <?php endforeach; ?>
                        <?php if(empty($latestUsers)): ?>
                            <div class="widget-empty">ยังไม่มีสมาชิกใหม่</div>
                        <?php endif; ?>
                    </ul>
                    <div class="widget-footer">
                        <a href="#" class="widget-view-all" onclick="alert('ไปที่หน้าจัดการผู้ใช้'); return false;">ดูทั้งหมด &rarr;</a>
                    </div>
                </div>
            </div>

            <div class="widgets-row-2">
                <div class="widget-card">
                    <div class="widget-header">
                        <span>QC สินค้าที่ลูกค้าส่งมา</span>
                    </div>
                    <ul class="widget-list">
                         <?php foreach($pendingQC as $qc): ?>
                           <li class="widget-item">
                               <div class="widget-item-info">
                                   <span class="widget-item-title">รายการแลกเปลี่ยน #<?= $qc['request_id'] ?></span>
                                   <span class="widget-item-time">รอตรวจสอบสภาพหนังสือจริง • <?= date('d/m/Y H:i', strtotime($qc['created_at'])) ?></span>
                               </div>
                               <a href="qc_exchanges.php?tab=physical" class="widget-item-action">ตรวจสอบ</a>
                           </li>
                        <?php endforeach; ?>
                        <?php if(empty($pendingQC)): ?>
                            <div class="widget-empty">ไม่มีสินค้าที่รอ QC</div>
                        <?php endif; ?>
                    </ul>
                    <div class="widget-footer">
                        <a href="qc_exchanges.php?tab=physical" class="widget-view-all">ดูทั้งหมด &rarr;</a>
                    </div>
                </div>
                
                <div class="widget-card">
                    <div class="widget-header">
                        <span>การจัดการโพสต์ (รายงาน/โปรไฟล์)</span>
                    </div>
                    <ul class="widget-list">
                         <?php foreach($pendingReports as $rep): ?>
                           <li class="widget-item">
                               <div class="widget-item-info">
                                   <span class="widget-item-title"><?= htmlspecialchars($rep['title']) ?></span>
                                   <span class="widget-item-time"><?= date('d/m/Y H:i', strtotime($rep['created_at'])) ?></span>
                               </div>
                               <a href="#" class="widget-item-action">ตรวจสอบ</a>
                           </li>
                        <?php endforeach; ?>
                        <?php if(empty($pendingReports)): ?>
                            <div class="widget-empty">ไม่มีรายงานใหม่</div>
                        <?php endif; ?>
                    </ul>
                    <div class="widget-footer">
                        <a href="#" class="widget-view-all" onclick="alert('ไปที่หน้าจัดการรายงาน'); return false;">ดูทั้งหมด &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const chartLabels = <?= json_encode($labels) ?>;
        
        // --- Sales Chart ---
        const ctxSales = document.getElementById('salesChart').getContext('2d');
        new Chart(ctxSales, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'ยอดขาย (บาท)',
                    data: <?= json_encode($salesSeries) ?>,
                    borderColor: '#2F6E63',
                    backgroundColor: 'rgba(47, 110, 99, 0.12)',
                    borderWidth: 2,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#2F6E63',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: { 
                        beginAtZero: true, 
                        border: { display: false },
                        grid: { color: '#e8f3ef' },
                        ticks: { callback: function(value) { return '฿' + value; } } 
                    }
                }
            }
        });

        // --- Users Chart ---
        const ctxUsers = document.getElementById('usersChart').getContext('2d');
        new Chart(ctxUsers, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'ผู้ใช้ทั่วไป (Users)',
                        data: <?= json_encode($userSeries) ?>,
                        backgroundColor: '#408575',
                        borderRadius: 4
                    },
                    {
                        label: 'ร้านมือ 2 (Shops)',
                        data: <?= json_encode($shopSeries) ?>,
                        backgroundColor: '#E6A86A',
                        borderRadius: 4
                    },
                    {
                        label: 'สำนักพิมพ์ (Publishers)',
                        data: <?= json_encode($pubSeries) ?>,
                        backgroundColor: '#D9534F',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }
                },
                scales: {
                    x: { 
                        stacked: true,
                        grid: { display: false }
                    },
                    y: { 
                        stacked: true, 
                        beginAtZero: true, 
                        border: { display: false },
                        grid: { color: '#e8f3ef' },
                        ticks: { stepSize: 1 } 
                    }
                }
            }
        });
        
        // --- Categories Pie Chart ---
        const catLabels = <?= json_encode($catLabels) ?>;
        const catData = <?= json_encode($catData) ?>;
        
        if (catLabels.length > 0) {
            const ctxCat = document.getElementById('categoriesChart').getContext('2d');
            new Chart(ctxCat, {
                type: 'doughnut',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catData,
                        backgroundColor: [
                            '#2B5E55', '#408575', '#58B39B', '#83CDBB', '#B4E1D4'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right', labels: { font: { family: "'Sarabun', sans-serif" } } },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed !== null) {
                                        label += new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(context.parsed);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
