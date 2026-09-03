<?php
session_start();
require_once '../config/config.php';
$userId=(int)($_SESSION['user_id']??0);$role=$_SESSION['role']??'';
if(!$userId||!in_array($role,['publisher_admin','shop_admin'],true)){header('Location:/pages/login.php');exit;}
$sellerType=$role==='publisher_admin'?'publisher':'shop';
$sellerStmt=$sellerType==='publisher'?$pdo->prepare('SELECT p.publisher_id id,p.company_name name FROM publisher_staff ps JOIN publishers p ON p.publisher_id=ps.publisher_id WHERE ps.user_id=? LIMIT 1'):$pdo->prepare('SELECT s.shop_id id,s.shop_name name FROM shop_staff ss JOIN shops s ON s.shop_id=ss.shop_id WHERE ss.user_id=? LIMIT 1');
$sellerStmt->execute([$userId]);$dashboardSeller=$sellerStmt->fetch(PDO::FETCH_ASSOC);if(!$dashboardSeller){header('Location:/pages/login.php');exit;}
$sellerId=(int)$dashboardSeller['id'];$seller_name=(string)$dashboardSeller['name'];
$salesStmt=$pdo->prepare("SELECT COALESCE(SUM(oi.price_at_purchase*oi.quantity),0) FROM order_items oi JOIN orders o ON o.order_id=oi.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type=? AND b.seller_id=? AND o.payment_status='paid'");$salesStmt->execute([$sellerType,$sellerId]);$dashboardSales=(float)$salesStmt->fetchColumn();
$stockStmt=$pdo->prepare("SELECT COALESCE(SUM(stock_quantity),0) FROM books WHERE seller_type=? AND seller_id=? AND status='available'");$stockStmt->execute([$sellerType,$sellerId]);$dashboardStock=(int)$stockStmt->fetchColumn();
$pendingStmt=$pdo->prepare("SELECT COUNT(DISTINCT o.order_id) FROM orders o JOIN order_items oi ON oi.order_id=o.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type=? AND b.seller_id=? AND o.payment_status='paid' AND o.shipping_status IN('pending','packing')");$pendingStmt->execute([$sellerType,$sellerId]);$dashboardPending=(int)$pendingStmt->fetchColumn();
$dashboardOrderAlerts=0;
$monthly=array_fill(1,12,0.0);$monthlyStmt=$pdo->prepare("SELECT MONTH(o.created_at) month,SUM(oi.price_at_purchase*oi.quantity) total FROM order_items oi JOIN orders o ON o.order_id=oi.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type=? AND b.seller_id=? AND o.payment_status='paid' AND YEAR(o.created_at)=YEAR(CURDATE()) GROUP BY MONTH(o.created_at)");$monthlyStmt->execute([$sellerType,$sellerId]);foreach($monthlyStmt as $row)$monthly[(int)$row['month']]=(float)$row['total'];
$currentMonth=(int)date('n');$currentSales=$monthly[$currentMonth]??0;$previousSales=$currentMonth>1?($monthly[$currentMonth-1]??0):0;$salesGrowth=$previousSales>0?(($currentSales-$previousSales)/$previousSales*100):($currentSales>0?100:0);
$yearStmt=$pdo->prepare("SELECT DISTINCT YEAR(o.created_at) sales_year FROM orders o JOIN order_items oi ON oi.order_id=o.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type=? AND b.seller_id=? ORDER BY sales_year DESC");$yearStmt->execute([$sellerType,$sellerId]);$salesYears=array_map('intval',$yearStmt->fetchAll(PDO::FETCH_COLUMN));if(!in_array((int)date('Y'),$salesYears,true))array_unshift($salesYears,(int)date('Y'));
$categoryStmt=$pdo->prepare("SELECT COALESCE(NULLIF(category,''),'อื่นๆ') category,COUNT(*) total FROM books WHERE seller_type=? AND seller_id=? AND status<>'hidden' GROUP BY COALESCE(NULLIF(category,''),'อื่นๆ') ORDER BY total DESC");$categoryStmt->execute([$sellerType,$sellerId]);$dashboardCategories=$categoryStmt->fetchAll(PDO::FETCH_ASSOC);$categoryTotal=array_sum(array_column($dashboardCategories,'total'));
$activityStmt=$pdo->prepare("SELECT DISTINCT o.order_number,o.payment_status,o.shipping_status,o.created_at FROM orders o JOIN order_items oi ON oi.order_id=o.order_id JOIN books b ON b.book_id=oi.book_id WHERE b.seller_type=? AND b.seller_id=? ORDER BY o.created_at DESC LIMIT 5");$activityStmt->execute([$sellerType,$sellerId]);$activities=[];foreach($activityStmt as $order){$label=match($order['payment_status']){'paid'=>match($order['shipping_status']){'delivered'=>'จัดส่งสำเร็จแล้ว','shipped'=>'กำลังจัดส่งคำสั่งซื้อ','packing'=>'กำลังเตรียมสินค้า',default=>'ยืนยันการชำระเงินแล้ว'},'cancelled'=>'คำสั่งซื้อถูกยกเลิก',default=>'มีคำสั่งซื้อใหม่'};$activities[]=[$label,'คำสั่งซื้อ #'.$order['order_number'],date('d/m/Y H:i',strtotime($order['created_at']))];}
$chatStmt=$pdo->prepare("SELECT COUNT(m.message_id) FROM chat_conversations c JOIN chat_messages m ON m.conversation_id=c.conversation_id AND m.is_read=0 AND m.sender_user_id=c.customer_user_id WHERE c.seller_type=? AND c.seller_id=?");$chatStmt->execute([$sellerType,$sellerId]);$dashboardUnreadChats=(int)$chatStmt->fetchColumn();
$dashboardTodos=[];if($dashboardPending)$dashboardTodos[]=['มีคำสั่งซื้อรอจัดส่ง '.$dashboardPending.' รายการ','เตรียมสินค้าและระบุข้อมูลพัสดุ','/pages/admin_orders.php?status=packing','จัดการ'];if($dashboardUnreadChats)$dashboardTodos[]=['มีข้อความที่ยังไม่อ่าน '.$dashboardUnreadChats.' ข้อความ','ตอบกลับคำถามจากลูกค้า','/pages/admin_chat.php','ตอบกลับ'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แผงควบคุมระบบ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-color);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
        }

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: 220px;
            min-height: 100vh;
            background-color: var(--card-bg);
            display: flex;
            flex-direction: column;
            padding: 24px 0;
            border-right: 1px solid var(--border-color);
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 20px 28px;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-logo img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .sidebar-logo-text {
            font-size: 20px;
            font-weight: 700;
            color: var(--secondary-color);
            line-height: 1.1;
        }

        .sidebar-logo-sub {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 300;
        }

        .sidebar-nav {
            flex: 1;
            padding: 20px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            font-size: var(--text-sm);
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.2s, color 0.2s;
            cursor: pointer;
        }

        .nav-item:hover {
            background-color: var(--accent-pink-light);
            color: var(--primary-color);
        }

        .nav-item.active {
            background-color: var(--accent-pink-light);
            color: var(--primary-color);
            font-weight: 600;
        }

        .nav-item svg {
            flex-shrink: 0;
            width: 18px;
            height: 18px;
        }

        .sidebar-profile {
            padding: 16px 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: var(--accent-pink-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-weight: 700;
            font-size: 16px;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-name {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-dark);
        }

        .profile-role {
            font-size: var(--text-xs);
            color: var(--primary-color);
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            margin-left: 220px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            background-color: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
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
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
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

        .search-box svg {
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .btn-export {
            background-color: var(--primary-color);
            color: var(--text-light);
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

        .btn-export:hover {
            background-color: var(--primary-hover);
        }

        .page-body {
            padding: 28px;
            flex: 1;
        }

        .page-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 22px;
        }

        /* ========== STAT CARDS ========== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 20px 22px;
            border: 1px solid var(--border-color);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .stat-icon {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-md);
            background-color: var(--accent-pink-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }

        .stat-badge {
            font-size: var(--text-xs);
            font-weight: 600;
            color: var(--accent-green);
            background-color: #E8F5E9;
            border-radius: 20px;
            padding: 2px 8px;
        }

        .stat-badge.urgent {
            color: var(--accent-red);
            background-color: #FBE4E1;
        }

        .stat-label {
            font-size: var(--text-xs);
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--secondary-color);
        }

        /* ========== CHART & DONUT ========== */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 18px;
            margin-bottom: 22px;
        }

        .chart-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 22px;
            border: 1px solid var(--border-color);
        }

        .chart-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .chart-title {
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-dark);
        }

        .chart-filters {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .chart-filter-btn {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: var(--text-xs);
            font-family: var(--font-family);
            cursor: pointer;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            color: var(--text-muted);
            transition: all 0.2s;
        }

        .chart-filter-btn.active {
            background-color: var(--primary-color);
            color: var(--text-light);
            border-color: var(--primary-color);
        }

        .chart-year-select,.chart-month-select,.chart-week-select {
            padding: 4px 10px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            font-family: var(--font-family);
            font-size: var(--text-xs);
            color: var(--text-dark);
            background: var(--card-bg);
            outline: none;
        }

        /* Donut */
        .donut-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .donut-canvas-wrap {
            position: relative;
            width: 160px;
            height: 160px;
        }

        .donut-center {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .donut-center-num {
            font-size: 26px;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .donut-center-label {
            font-size: var(--text-xs);
            color: var(--text-muted);
        }

        .donut-legend {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: var(--text-sm);
        }

        .legend-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .legend-pct {
            font-weight: 600;
            color: var(--text-dark);
        }

        /* ========== BOTTOM ROW ========== */
        .bottom-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .activity-card, .todo-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 22px;
            border: 1px solid var(--border-color);
        }

        .card-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .card-title {
            font-size: var(--text-md);
            font-weight: 600;
            color: var(--text-dark);
        }

        .see-all-link {
            font-size: var(--text-xs);
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        /* Activity */
        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background-color: var(--accent-pink-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            flex-shrink: 0;
        }

        .activity-text {
            font-size: var(--text-sm);
            color: var(--text-dark);
            line-height: 1.4;
        }

        .activity-text strong { color: var(--secondary-color); }

        .activity-time {
            font-size: var(--text-xs);
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Todo */
        .todo-badge {
            background-color: var(--primary-color);
            color: var(--text-light);
            font-size: var(--text-xs);
            font-weight: 600;
            border-radius: 20px;
            padding: 2px 8px;
        }

        .todo-item {
            background-color: var(--bg-color);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .todo-left {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .todo-flag {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            background-color: #FBE4E1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-red);
            flex-shrink: 0;
        }

        .todo-title {
            font-size: var(--text-sm);
            font-weight: 600;
            color: var(--text-dark);
        }

        .todo-desc {
            font-size: var(--text-xs);
            color: var(--text-muted);
            margin-top: 2px;
        }

        .btn-todo {
            padding: 6px 14px;
            border-radius: var(--radius-md);
            border: none;
            font-family: var(--font-family);
            font-size: var(--text-xs);
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-check { background-color: var(--secondary-color); color: var(--text-light); }
        .btn-reply { background-color: var(--text-dark); color: var(--text-light); }

        /* Footer */
        .dashboard-footer {
            border-top: 1px solid var(--border-color);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: var(--text-xs);
            color: var(--text-muted);
            background-color: var(--card-bg);
        }

        .footer-links {
            display: flex;
            gap: 16px;
        }

        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }
    </style>
</head>
<body>

<!-- ========== SIDEBAR ========== -->
<?php if(false): ?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="../assets/image/minilogo.png" alt="Logo">
        <div>
            <div class="sidebar-logo-text">วนวรรณ</div>
            <div class="sidebar-logo-sub">Buy · Sell · Exchange</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item active">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
            </svg>
            แผงควบคุม
        </a>

        <a href="inventory.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            การจัดการร้านค้า
        </a>

        <a href="support.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            แชทและการสนับสนุน
        </a>

        <a href="admin_orders.php" class="nav-item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>

            ติดตามคำสั่งซื้อ
        </a>
    </nav>

    <div class="sidebar-profile">
        <div class="profile-avatar">ผ</div>
        <div>
            <div class="profile-name">โปรไฟล์ผู้ดูแล</div>
            <div class="profile-role">ซูเปอร์แอดมิน</div>
        </div>
    </div>
</aside>
<?php endif; $active_menu='dashboard'; include __DIR__.'/../components/sidebar.php'; ?>

<!-- ========== MAIN CONTENT ========== -->
<div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <div class="search-box">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" placeholder="ค้นหาบันทึกข้อมูล...">
        </div>
        <button class="btn-export">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            ส่งออกข้อมูล
        </button>
    </div>

    <!-- Body -->
    <div class="page-body">
        <h1 class="page-title">แผงควบคุมระบบ</h1>

        <!-- Stat Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                    </div>
                    <span class="stat-badge <?=$salesGrowth<0?'urgent':''?>"><?=$salesGrowth>=0?'+':''?><?=number_format($salesGrowth,1)?>%</span>
                </div>
                <div class="stat-label">ยอดขายทั้งหมด</div>
                <div class="stat-value">฿<?=number_format($dashboardSales,2)?></div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="17 1 21 5 17 9"></polyline>
                            <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                            <polyline points="7 23 3 19 7 15"></polyline>
                            <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                        </svg>
                    </div>
                </div>
                <div class="stat-label">จำนวนหนังสือคงเหลือ</div>
                <div class="stat-value"><?=number_format($dashboardStock)?> <span style="font-size:14px;font-weight:400;color:var(--text-muted)">เล่ม</span></div>
            </div>

            <div class="stat-card">
                <div class="stat-card-header">
                    <div class="stat-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                    </div>
                    <?php if($dashboardPending):?><span class="stat-badge urgent">รอดำเนินการ</span><?php endif;?>
                </div>
                <div class="stat-label">รายการคำสั่งซื้อรอจัดส่ง</div>
                <div class="stat-value"><?=number_format($dashboardPending)?> <span style="font-size:14px;font-weight:400;color:var(--text-muted)">รายการ</span></div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <!-- Bar Chart -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <span class="chart-title">การเติบโตของยอดขาย</span>
                    <div class="chart-filters">
                        <select class="chart-year-select" aria-label="เลือกปีของยอดขาย">
                            <?php foreach($salesYears as $salesYear):?><option value="<?=$salesYear?>" <?=$salesYear===(int)date('Y')?'selected':''?>><?=$salesYear?></option><?php endforeach;?>
                        </select>
                        <select class="chart-month-select" aria-label="เลือกเดือน" hidden><?php foreach(['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'] as $monthIndex=>$monthName):?><option value="<?=$monthIndex+1?>" <?=$monthIndex+1===(int)date('n')?'selected':''?>><?=$monthName?></option><?php endforeach;?></select>
                        <select class="chart-week-select" aria-label="เลือกสัปดาห์" hidden></select>
                        <button class="chart-filter-btn" data-period="weekly">รายสัปดาห์</button>
                        <button class="chart-filter-btn active" data-period="monthly">รายเดือน</button>
                        <button class="chart-filter-btn" data-period="yearly">รายปี</button>
                    </div>
                </div>
                <canvas id="salesChart" height="90"></canvas>
            </div>

            <!-- Donut Chart -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <span class="chart-title">หมวดหมู่หนังสือ</span>
                </div>
                <div class="donut-wrapper">
                    <div class="donut-canvas-wrap">
                        <canvas id="donutChart"></canvas>
                        <div class="donut-center">
                            <div class="donut-center-num"><?=number_format($categoryTotal)?></div>
                            <div class="donut-center-label">รวมทั้งหมด</div>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <div class="legend-item">
                            <div class="legend-left">
                                <div class="legend-dot" style="background:#C97B8B"></div>
                                <span>การ์ตูน/มังงะ</span>
                            </div>
                            <span class="legend-pct">45%</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-left">
                                <div class="legend-dot" style="background:#175564"></div>
                                <span>จิตวิทยา</span>
                            </div>
                            <span class="legend-pct">25%</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-left">
                                <div class="legend-dot" style="background:#4A3B35"></div>
                                <span>การศึกษา</span>
                            </div>
                            <span class="legend-pct">15%</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-left">
                                <div class="legend-dot" style="background:#F7D9DE"></div>
                                <span>อื่นๆ</span>
                            </div>
                            <span class="legend-pct">15%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row -->
        <div class="bottom-row">
            <!-- Activity -->
            <div class="activity-card">
                <div class="card-header-row">
                    <span class="card-title">กิจกรรมล่าสุด</span>
                    <a href="#" class="see-all-link">ดูทั้งหมด</a>
                </div>

                <?php
                if(!$activities):?><div style="padding:35px 10px;text-align:center;color:var(--text-muted)">ยังไม่มีกิจกรรมคำสั่งซื้อ</div><?php endif;
                foreach ($activities as $a): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                    </div>
                    <div>
                        <div class="activity-text"><?= $a[0] ?> <strong><?= $a[1] ?></strong></div>
                        <div class="activity-time"><?= $a[2] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Todo -->
            <div class="todo-card">
                <div class="card-header-row">
                    <span class="card-title">สิ่งที่ต้องดำเนินการ</span>
                    <span class="todo-badge">2 รายการ</span>
                </div>

                <div class="todo-item">
                    <div class="todo-left">
                        <div class="todo-flag">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                                <line x1="4" y1="22" x2="4" y2="15"></line>
                            </svg>
                        </div>
                        <div>
                            <div class="todo-title">โพสต์ที่ถูกรายงาน: #8812</div>
                            <div class="todo-desc">อาจละเมิดโนบายในรายสะเอียดสินค้า</div>
                        </div>
                    </div>
                    <button class="btn-todo btn-check">ตรวจสอบ</button>
                </div>

                <div class="todo-item">
                    <div class="todo-left">
                        <div class="todo-flag" style="background:#E8F5E9;color:var(--accent-green)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <div class="todo-title">ติดต่อสอบถาม</div>
                            <div class="todo-desc">ผู้ใช้ "Silas_W" ขอความช่วยเหลือเรื่องสินค้า</div>
                        </div>
                    </div>
                    <button class="btn-todo btn-reply">ตอบกลับ</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="dashboard-footer">
        <span>© 2024 Wanawan Forest Archive. สงวนลิขสิทธิ์ทั้งหมด</span>
        <div class="footer-links">
            <a href="#">นโยบายความเป็นส่วนตัว</a>
            <a href="#">เอกสารภายใน</a>
        </div>
    </div>
</div>

<script>
const dashboardCategories = <?=json_encode(array_map(fn($row)=>['name'=>$row['category'],'value'=>(int)$row['total']],$dashboardCategories),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const dashboardColors = ['#C97B8B','#175564','#4A3B35','#E3A77C','#7D9A83','#9A7FB5','#D6B45F','#8EAFC1'];
const legend = document.querySelector('.donut-legend');
if (legend) {
    legend.replaceChildren();
    const total = dashboardCategories.reduce((sum,item)=>sum+item.value,0);
    dashboardCategories.forEach((item,index)=>{const row=document.createElement('div');row.className='legend-item';const left=document.createElement('div');left.className='legend-left';const dot=document.createElement('div');dot.className='legend-dot';dot.style.background=dashboardColors[index%dashboardColors.length];const name=document.createElement('span');name.textContent=item.name;left.append(dot,name);const pct=document.createElement('span');pct.className='legend-pct';pct.textContent=total?`${Math.round(item.value/total*100)}%`:'0%';row.append(left,pct);legend.append(row)});
    if(!dashboardCategories.length)legend.innerHTML='<div style="color:#8a7b74">ยังไม่มีข้อมูลหมวดหมู่</div>';
}
const todoCard=document.querySelector('.todo-card');
const dashboardTodos=<?=json_encode($dashboardTodos,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
if(todoCard){todoCard.replaceChildren();const head=document.createElement('div');head.className='card-header-row';const title=document.createElement('span');title.className='card-title';title.textContent='สิ่งที่ต้องดำเนินการ';const badge=document.createElement('span');badge.className='todo-badge';badge.textContent=`${dashboardTodos.length} รายการ`;head.append(title,badge);todoCard.append(head);if(!dashboardTodos.length){const empty=document.createElement('div');empty.style.cssText='padding:45px 10px;text-align:center;color:var(--text-muted)';empty.textContent='ไม่มีรายการที่ต้องดำเนินการ';todoCard.append(empty)}dashboardTodos.forEach(item=>{const row=document.createElement('div');row.className='todo-item';const left=document.createElement('div');left.className='todo-left';const flag=document.createElement('div');flag.className='todo-flag';flag.textContent='!';const copy=document.createElement('div');const itemTitle=document.createElement('div');itemTitle.className='todo-title';itemTitle.textContent=item[0];const desc=document.createElement('div');desc.className='todo-desc';desc.textContent=item[1];copy.append(itemTitle,desc);left.append(flag,copy);const action=document.createElement('a');action.className='btn-todo btn-check';action.href=item[2];action.textContent=item[3];action.style.textDecoration='none';row.append(left,action);todoCard.append(row)})}
// Bar Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(salesCtx, {
    type: 'bar',
    data: {
        labels: ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'],
        datasets: [{
            data: <?=json_encode(array_values($monthly),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
            backgroundColor: (ctx) => {
                return ctx.dataIndex === <?=max(0,$currentMonth-1)?> ? '#C97B8B' : '#F7D9DE';
            },
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { family: 'Prompt', size: 11 }, color: '#8A7B74' } },
            y: { display: false, grid: { display: false } }
        }
    }
});

// Donut Chart
const donutCtx = document.getElementById('donutChart').getContext('2d');
new Chart(donutCtx, {
    type: 'doughnut',
    data: {
        datasets: [{
            data: dashboardCategories.length?dashboardCategories.map(item=>item.value):[1],
            backgroundColor: dashboardCategories.length?dashboardCategories.map((_,index)=>dashboardColors[index%dashboardColors.length]):['#F1E8E5'],
            borderWidth: 0,
            hoverOffset: 4
        }]
    },
    options: {
        cutout: '70%',
        plugins: { legend: { display: false }, tooltip: { enabled: true } }
    }
});

// Active nav highlight
document.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', function() {
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
    });
});

// ตัวกรองกราฟยอดขายจากฐานข้อมูลจริง
const salesYearSelect=document.querySelector('.chart-year-select');
const salesMonthSelect=document.querySelector('.chart-month-select');
const salesWeekSelect=document.querySelector('.chart-week-select');
const salesFilterButtons=[...document.querySelectorAll('.chart-filter-btn')];
let activeSalesPeriod='monthly';
async function loadSalesChart(){
    const year=salesYearSelect.value;
    const month=salesMonthSelect.value;
    const week=salesWeekSelect.value;
    salesFilterButtons.forEach(button=>button.disabled=true);
    try{
        const response=await fetch(`/pages/handlers/dashboard_sales.php?year=${encodeURIComponent(year)}&period=${encodeURIComponent(activeSalesPeriod)}&month=${encodeURIComponent(month)}&week=${encodeURIComponent(week)}`,{headers:{Accept:'application/json'}});
        if(!response.ok)throw new Error('load failed');
        const result=await response.json();
        if(!result.success)throw new Error('invalid result');
        if(activeSalesPeriod==='weekly'){
            const previousValue=salesWeekSelect.value;
            salesWeekSelect.replaceChildren(...result.weeks.map((range,index)=>{const option=document.createElement('option');option.value=index;option.textContent=`สัปดาห์ ${index+1} · ${range.label}`;return option}));
            salesWeekSelect.value=String(result.week??previousValue??0);
        }
        salesChart.data.labels=result.labels;
        salesChart.data.datasets[0].data=result.data.map(Number);
        salesChart.data.datasets[0].backgroundColor=(context)=>{const values=result.data.map(Number),max=Math.max(...values);return max>0&&Number(context.raw)===max?'#C97B8B':'#F7D9DE'};
        salesChart.options.scales.x.ticks.maxRotation=0;
        salesChart.options.scales.x.ticks.autoSkip=true;
        salesChart.update();
    }catch(error){console.error('ไม่สามารถโหลดข้อมูลยอดขายได้',error)}finally{salesFilterButtons.forEach(button=>button.disabled=false)}
}
salesFilterButtons.forEach(button=>button.addEventListener('click',()=>{salesFilterButtons.forEach(item=>item.classList.remove('active'));button.classList.add('active');activeSalesPeriod=button.dataset.period;const weekly=activeSalesPeriod==='weekly';salesMonthSelect.hidden=!weekly;salesWeekSelect.hidden=!weekly;if(weekly)salesWeekSelect.value='';loadSalesChart()}));
salesYearSelect.addEventListener('change',loadSalesChart);
salesMonthSelect.addEventListener('change',()=>{salesWeekSelect.value='';loadSalesChart()});
salesWeekSelect.addEventListener('change',loadSalesChart);
</script>

</body>
</html>
