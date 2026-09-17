<?php
/**
 * admin/returns.php
 * Super Admin — Return & Refund Management Dashboard
 *
 * หน้านี้จัดการ 3 actions หลัก:
 *   1. Approve / Reject คำขอ (pending_super_admin)
 *   2. ดูรายการที่รอร้านค้ายืนยัน (waiting_for_shop)
 *   3. โอนเงินคืน (shop_received)  ← action ที่ต้องทำ
 */
session_start();
require_once __DIR__ . '/../config/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

// --- CSRF ---
if (empty($_SESSION['superadmin_return_csrf'])) {
    $_SESSION['superadmin_return_csrf'] = bin2hex(random_bytes(24));
}

// --- Filter ---
$filterStatus = $_GET['status'] ?? 'pending_super_admin';
$search       = trim($_GET['search'] ?? '');

$allStatuses = [
    'pending_super_admin' => ['label' => 'รอพิจารณา',          'icon' => '⏳', 'color' => '#d97706'],
    'waiting_for_shop'    => ['label' => 'รอร้านค้ายืนยัน',    'icon' => '📦', 'color' => '#2563eb'],
    'shop_received'       => ['label' => 'รอโอนเงิน',           'icon' => '💰', 'color' => '#7c3aed'],
    'refunded_to_wallet'  => ['label' => 'คืนเงินแล้ว',         'icon' => '✅', 'color' => '#059669'],
    'rejected'            => ['label' => 'ปฏิเสธแล้ว',          'icon' => '❌', 'color' => '#dc2626'],
];

$where  = '1=1';
$params = [];

if ($filterStatus !== 'all') {
    $where   .= ' AND rr.status = :status';
    $params['status'] = $filterStatus;
}
if ($search !== '') {
    $where  .= ' AND (o.order_number LIKE :s1 OR rr.return_tracking LIKE :s2 OR u.email LIKE :s3)';
    $params['s1'] = '%' . $search . '%';
    $params['s2'] = '%' . $search . '%';
    $params['s3'] = '%' . $search . '%';
}

$listStmt = $pdo->prepare(
    "SELECT rr.*,
        o.order_number,
        COALESCE(NULLIF(u.display_name,''), u.email) buyer_name,
        u.email buyer_email,
        COALESCE(s.shop_name, p.company_name) seller_name
    FROM return_requests rr
    LEFT JOIN orders   o ON o.order_id  = rr.order_id
    LEFT JOIN users    u ON u.user_id   = rr.user_id
    LEFT JOIN shops    s ON rr.seller_type = 'shop'      AND s.shop_id       = rr.seller_id
    LEFT JOIN publishers p ON rr.seller_type = 'publisher' AND p.publisher_id = rr.seller_id
    WHERE $where
    ORDER BY
        CASE rr.status
            WHEN 'shop_received'       THEN 1
            WHEN 'pending_super_admin' THEN 2
            WHEN 'waiting_for_shop'    THEN 3
            ELSE 4
        END,
        rr.created_at DESC"
);
$listStmt->execute($params);
$returns = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// --- Stats ---
$statsRaw = $pdo->query(
    "SELECT status, COUNT(*) cnt FROM return_requests GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$reasonMap = [
    'damaged'          => '🔧 สินค้าชำรุด/เสียหาย',
    'not_as_described' => '📷 สินค้าไม่ตรงตามโฆษณา',
    'wrong_item'       => '📦 ได้รับสินค้าผิดรายการ',
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคืนสินค้า — Super Admin วนวรรณ</title>
    <meta name="description" content="Super Admin Dashboard สำหรับตรวจสอบและอนุมัติคำขอคืนสินค้าและขอคืนเงิน">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand:      #7c3aed;
            --brand-dark: #5b21b6;
            --brand-soft: #ede9fe;
            --green:  #059669; --green-soft:  #d1fae5;
            --amber:  #d97706; --amber-soft:  #fef3c7;
            --blue:   #2563eb; --blue-soft:   #dbeafe;
            --purple: #7c3aed; --purple-soft: #ede9fe;
            --red:    #dc2626; --red-soft:    #fee2e2;
            --surface: #f8f7ff;
            --card:    #ffffff;
            --border:  #e8e4f3;
            --text:    #1e1b3a;
            --muted:   #6b7280;
            --font: 'Noto Sans Thai', system-ui, sans-serif;
            --radius: 16px;
            --shadow: 0 4px 24px rgba(124,58,237,.08);
        }

        body {
            font-family: var(--font);
            background: var(--surface);
            color: var(--text);
            min-height: 100vh;
        }

        /* ───── Layout ───── */
        .layout { display: flex; min-height: 100vh; }

        /* ───── Sidebar ───── */
        .sidebar {
            width: 240px; flex-shrink: 0;
            background: linear-gradient(180deg, #1e1b3a 0%, #2d1b69 100%);
            padding: 28px 0;
            position: sticky; top: 0; height: 100vh;
            overflow-y: auto;
        }
        .sidebar-logo {
            padding: 0 24px 28px;
            font-size: 20px; font-weight: 800; color: #fff;
            letter-spacing: -.02em;
            display: flex; align-items: center; gap: 10px;
        }
        .sidebar-logo span { color: #a78bfa; }
        .sidebar-section { padding: 8px 16px 4px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #6d6a8a; }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 24px;
            font-size: 14px; font-weight: 600; color: #c4b9f5;
            text-decoration: none; transition: all .2s;
            border-radius: 0;
        }
        .nav-item:hover, .nav-item.active {
            background: rgba(124,58,237,.25);
            color: #fff;
        }
        .nav-item.active { border-right: 3px solid #a78bfa; }

        /* ───── Main ───── */
        .main { flex: 1; padding: 40px 48px; min-width: 0; overflow-x: hidden; }
        @media (max-width: 900px) { .main { padding: 24px 16px; } .sidebar { display: none; } }

        /* ───── Page Header ───── */
        .page-head { margin-bottom: 32px; }
        .page-head h1 { font-size: 28px; font-weight: 800; color: var(--text); }
        .page-head p  { font-size: 14px; color: var(--muted); margin-top: 6px; }
        .breadcrumb { font-size: 13px; color: var(--muted); margin-bottom: 10px; }
        .breadcrumb a { color: var(--brand); text-decoration: none; }

        /* ───── Stats Grid ───── */
        .stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px; margin-bottom: 28px;
        }
        @media (max-width: 1100px) { .stats { grid-template-columns: repeat(3,1fr); } }
        @media (max-width: 650px)  { .stats { grid-template-columns: repeat(2,1fr); } }

        .stat-card {
            background: var(--card);
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            padding: 20px 18px;
            cursor: pointer; transition: all .22s;
            text-decoration: none; display: block;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
        .stat-card.active-tab { border-color: currentColor; box-shadow: 0 0 0 2px currentColor; }
        .stat-val { font-size: 28px; font-weight: 800; line-height: 1; margin-bottom: 4px; }
        .stat-lbl { font-size: 12px; color: var(--muted); font-weight: 600; }

        /* ───── Alert Banner (shop_received needs action) ───── */
        .action-alert {
            background: linear-gradient(135deg, #4c1d95, #6d28d9);
            color: #fff; border-radius: var(--radius);
            padding: 16px 22px; margin-bottom: 22px;
            display: flex; align-items: center; gap: 14px;
            font-size: 14px; font-weight: 600;
        }
        .action-alert .alert-count {
            background: #fff; color: #6d28d9;
            border-radius: 20px; padding: 4px 12px;
            font-size: 15px; font-weight: 800;
        }
        .action-alert a { color: #d8b4fe; text-decoration: underline; cursor: pointer; }

        /* ───── Toolbar ───── */
        .toolbar {
            display: flex; justify-content: space-between;
            align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 18px;
        }
        .tab-filters { display: flex; gap: 6px; flex-wrap: wrap; }
        .tab-btn {
            padding: 8px 16px; border-radius: 30px;
            border: 1.5px solid var(--border);
            background: var(--card); font: inherit; font-size: 13px; font-weight: 600;
            color: var(--muted); cursor: pointer; transition: all .2s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
        }
        .tab-btn.active { background: var(--brand); color: #fff; border-color: var(--brand); }
        .tab-btn:hover:not(.active) { border-color: var(--brand); color: var(--brand); }

        .search-form { display: flex; gap: 8px; }
        .search-input {
            height: 40px; border: 1.5px solid var(--border); border-radius: 20px;
            padding: 0 16px 0 38px; font: inherit; font-size: 14px;
            outline: none; width: 260px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' stroke='%236b7280' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 12px center var(--card);
        }
        .search-input:focus { border-color: var(--brand); }
        .btn-sm {
            height: 40px; padding: 0 18px; border: none;
            border-radius: 20px; font: inherit; font-size: 13px; font-weight: 700;
            cursor: pointer; background: var(--brand); color: #fff; transition: opacity .2s;
        }
        .btn-sm:hover { opacity: .88; }

        /* ───── Table ───── */
        .table-wrap {
            background: var(--card); border: 1.5px solid var(--border);
            border-radius: var(--radius); overflow: hidden;
        }
        .rtable { width: 100%; border-collapse: collapse; min-width: 860px; }
        .rtable thead { background: linear-gradient(135deg, var(--brand-soft), #f5f3ff); }
        .rtable th { padding: 14px 18px; text-align: left; font-size: 12px; font-weight: 700; color: var(--brand-dark); text-transform: uppercase; letter-spacing: .05em; }
        .rtable td { padding: 16px 18px; border-bottom: 1px solid #f3f0fd; font-size: 14px; vertical-align: middle; }
        .rtable tr:last-child td { border-bottom: none; }
        .rtable tr:hover td { background: #faf9ff; }

        /* ───── Badges ───── */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 30px; font-size: 12px; font-weight: 700;
        }
        .badge-pending  { background: var(--amber-soft);  color: var(--amber);  }
        .badge-waiting  { background: var(--blue-soft);   color: var(--blue);   }
        .badge-received { background: var(--purple-soft); color: var(--purple); }
        .badge-refunded { background: var(--green-soft);  color: var(--green);  }
        .badge-rejected { background: var(--red-soft);    color: var(--red);    }

        .btn-action {
            padding: 8px 14px; background: var(--card);
            border: 1.5px solid var(--border);
            border-radius: 10px; font: inherit; font-size: 13px; font-weight: 700;
            color: var(--brand); cursor: pointer; transition: all .2s; white-space: nowrap;
        }
        .btn-action:hover { background: var(--brand); color: #fff; border-color: var(--brand); }
        .btn-action.urgent {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            color: #fff; border-color: transparent;
            box-shadow: 0 3px 12px rgba(124,58,237,.35);
        }
        .btn-action.urgent:hover { opacity: .88; }

        /* ───── Empty State ───── */
        .empty-state { text-align: center; padding: 80px 20px; color: var(--muted); }
        .empty-state .e-icon { font-size: 60px; margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; font-weight: 700; color: #374151; margin-bottom: 8px; }

        /* ───── Modal ───── */
        .modal {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.55);
            display: none; align-items: center; justify-content: center;
            padding: 20px; z-index: 700; backdrop-filter: blur(6px);
        }
        .modal.open { display: flex; }
        .modal-box {
            background: var(--card); border-radius: 24px;
            width: min(760px, 100%); max-height: 90vh; overflow-y: auto;
            box-shadow: 0 32px 100px rgba(0,0,0,.25);
            scrollbar-width: none;
        }
        .modal-box::-webkit-scrollbar { display: none; }

        .modal-head {
            padding: 24px 28px;
            background: linear-gradient(135deg, var(--brand-soft), #f5f3ff);
            border-bottom: 1px solid var(--border);
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0;
        }
        .modal-head h2 { font-size: 18px; color: var(--brand-dark); font-weight: 800; }
        .close-btn { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted); transition: color .2s; }
        .close-btn:hover { color: var(--brand); }
        .modal-body { padding: 28px; }

        /* ───── Modal Detail Grid ───── */
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 22px; }
        @media (max-width: 540px) { .detail-grid { grid-template-columns: 1fr; } }
        .detail-field { display: flex; flex-direction: column; gap: 4px; }
        .detail-field label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); }
        .detail-field span { font-size: 14px; color: var(--text); font-weight: 600; }
        .detail-field span.mono { font-family: monospace; font-size: 14px; letter-spacing: .04em; }

        .evidence-wrap { margin-bottom: 22px; }
        .evidence-wrap > label { font-size: 13px; font-weight: 700; color: #4b5563; display: block; margin-bottom: 10px; }
        .evidence-wrap img { max-width: 100%; max-height: 300px; border-radius: 12px; border: 1px solid var(--border); object-fit: contain; }

        .note-wrap { margin-bottom: 22px; }
        .note-wrap > label { display: block; font-size: 13px; font-weight: 700; color: #4b5563; margin-bottom: 8px; }
        .note-wrap textarea, .note-wrap input[type=text] {
            width: 100%; padding: 12px 16px; border: 1.5px solid var(--border);
            border-radius: 12px; font: inherit; font-size: 14px;
            resize: vertical; min-height: 70px; outline: none;
        }
        .note-wrap textarea:focus, .note-wrap input:focus { border-color: var(--brand); }

        /* ───── Action Row ───── */
        .action-row { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-approve {
            flex: 1; padding: 14px; border: none; border-radius: 12px;
            font: inherit; font-size: 15px; font-weight: 700;
            background: linear-gradient(135deg, #059669, #10b981); color: #fff;
            cursor: pointer; box-shadow: 0 4px 16px rgba(5,150,105,.3); transition: opacity .2s;
        }
        .btn-approve:hover { opacity: .88; }
        .btn-reject {
            flex: 1; padding: 14px; border: none; border-radius: 12px;
            font: inherit; font-size: 15px; font-weight: 700;
            background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff;
            cursor: pointer; box-shadow: 0 4px 16px rgba(220,38,38,.25); transition: opacity .2s;
        }
        .btn-reject:hover { opacity: .88; }
        .btn-refund {
            flex: 1; padding: 14px; border: none; border-radius: 12px;
            font: inherit; font-size: 15px; font-weight: 700;
            background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff;
            cursor: pointer; box-shadow: 0 4px 16px rgba(124,58,237,.35); transition: opacity .2s;
        }
        .btn-refund:hover { opacity: .88; }
        .btn-approve:disabled, .btn-reject:disabled, .btn-refund:disabled { opacity: .5; cursor: not-allowed; }

        .status-badge-large {
            text-align: center; padding: 18px; border-radius: 12px;
            font-size: 15px; font-weight: 700;
        }
        .status-badge-large.waiting  { background: var(--blue-soft);   color: var(--blue); }
        .status-badge-large.refunded { background: var(--green-soft);  color: var(--green); }
        .status-badge-large.rejected { background: var(--red-soft);    color: var(--red); }

        /* ───── Toast ───── */
        .toast-msg {
            display: none; position: fixed; bottom: 28px; right: 28px;
            background: var(--text); color: #fff; border-radius: 12px;
            padding: 14px 24px; font-size: 14px; font-weight: 600;
            z-index: 9999; box-shadow: 0 8px 32px rgba(0,0,0,.2);
            max-width: 360px;
        }
        .toast-msg.show { display: block; animation: fadeup .3s; }
        @keyframes fadeup { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body>
<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            📚 <span>วนวรรณ</span>
        </div>
        <div class="sidebar-section">เมนูหลัก</div>
        <a href="/admin/index.php"          class="nav-item">🏠 Dashboard</a>
        <a href="/admin/approvals.php"      class="nav-item">✅ อนุมัติร้านค้า</a>
        <a href="/admin/manage_users.php"   class="nav-item">👥 จัดการผู้ใช้</a>
        <a href="/admin/escrow.php"         class="nav-item">💳 Escrow</a>
        <a href="/admin/revenue.php"        class="nav-item">📊 รายได้</a>
        <div class="sidebar-section">การคืนสินค้า</div>
        <a href="/admin/returns.php"        class="nav-item active">📦 คืนสินค้า &amp; คืนเงิน</a>
        <div class="sidebar-section">ระบบ</div>
        <a href="/admin/disputes.php"       class="nav-item">⚖️ Disputes</a>
        <a href="/admin/action_logs.php"    class="nav-item">📋 Action Logs</a>
        <a href="/admin/manage_admins.php"  class="nav-item">🔑 จัดการ Admin</a>
    </aside>

    <main class="main">
        <div class="breadcrumb"><a href="/admin/index.php">Dashboard</a> / จัดการคืนสินค้า</div>

        <div class="page-head">
            <h1>📦 Return &amp; Refund Management</h1>
            <p>ตรวจสอบหลักฐาน อนุมัติคำขอ และดำเนินการคืนเงินเข้า Wonwan Wallet</p>
        </div>

        <!-- Stats -->
        <div class="stats">
            <?php foreach ($allStatuses as $st => $info):
                $cnt = (int)($statsRaw[$st] ?? 0);
                $isActive = ($filterStatus === $st);
            ?>
            <a href="?status=<?= $st ?>"
               class="stat-card <?= $isActive ? 'active-tab' : '' ?>"
               style="color:<?= $info['color'] ?>">
                <div class="stat-val"><?= $cnt ?></div>
                <div class="stat-lbl"><?= $info['icon'] ?> <?= $info['label'] ?></div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (($statsRaw['shop_received'] ?? 0) > 0 && $filterStatus !== 'shop_received'): ?>
        <div class="action-alert">
            <span>⚡</span>
            <div>
                มีคำขอ <span class="alert-count"><?= (int)$statsRaw['shop_received'] ?></span>
                รายการที่ <strong>รอโอนเงินคืน</strong> — ร้านค้ายืนยันรับสินค้าแล้ว
            </div>
            <a href="?status=shop_received">ดูและดำเนินการ →</a>
        </div>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="tab-filters">
                <a href="?status=all<?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="tab-btn <?= $filterStatus === 'all' ? 'active' : '' ?>">ทั้งหมด</a>
                <?php foreach ($allStatuses as $st => $info): ?>
                <a href="?status=<?= $st ?><?= $search ? '&search='.urlencode($search) : '' ?>"
                   class="tab-btn <?= $filterStatus === $st ? 'active' : '' ?>">
                    <?= $info['icon'] ?> <?= $info['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
            <form class="search-form" method="get">
                <?php if ($filterStatus !== 'all'): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
                <?php endif; ?>
                <input class="search-input" type="text" name="search"
                       value="<?= htmlspecialchars($search) ?>"
                       placeholder="ค้นหาเลขคำสั่งซื้อ / พัสดุ / อีเมล">
                <button class="btn-sm" type="submit">ค้นหา</button>
            </form>
        </div>

        <!-- Table -->
        <div class="table-wrap" style="overflow:auto">
            <?php if (!$returns): ?>
            <div class="empty-state">
                <div class="e-icon">📭</div>
                <h3>ไม่พบรายการในสถานะนี้</h3>
                <p>เปลี่ยน filter หรือรอคำขอใหม่จากลูกค้า</p>
            </div>
            <?php else: ?>
            <table class="rtable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ลูกค้า</th>
                        <th>ร้านค้า</th>
                        <th>สาเหตุ</th>
                        <th>เลขพัสดุคืน</th>
                        <th>ยอดคืน</th>
                        <th>สถานะ</th>
                        <th>วันที่ยื่น</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($returns as $r):
                    $badgeClass = [
                        'pending_super_admin' => 'badge-pending',
                        'waiting_for_shop'    => 'badge-waiting',
                        'shop_received'       => 'badge-received',
                        'refunded_to_wallet'  => 'badge-refunded',
                        'rejected'            => 'badge-rejected',
                    ][$r['status']] ?? '';
                    $statusLabel = $allStatuses[$r['status']]['icon'] . ' ' . ($allStatuses[$r['status']]['label'] ?? $r['status']);
                    $btnClass = $r['status'] === 'shop_received' ? 'btn-action urgent' : 'btn-action';
                ?>
                <tr>
                    <td><strong>#<?= htmlspecialchars($r['order_number'] ?? $r['order_id']) ?></strong></td>
                    <td>
                        <?= htmlspecialchars($r['buyer_name'] ?? 'ลูกค้า') ?><br>
                        <small style="color:var(--muted)"><?= htmlspecialchars($r['buyer_email'] ?? '') ?></small>
                    </td>
                    <td style="font-size:13px"><?= htmlspecialchars($r['seller_name'] ?? ($r['seller_type'].' #'.$r['seller_id'])) ?></td>
                    <td><?= $reasonMap[$r['reason']] ?? htmlspecialchars($r['reason']) ?></td>
                    <td style="font-family:monospace;font-size:13px"><?= htmlspecialchars($r['return_tracking']) ?></td>
                    <td><strong style="color:var(--green)">฿<?= number_format((float)$r['refund_amount'], 2) ?></strong></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span></td>
                    <td style="font-size:12px;color:var(--muted)"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                    <td>
                        <button class="<?= $btnClass ?>"
                                id="open-<?= (int)$r['return_id'] ?>"
                                onclick="openModal(<?= htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                            <?php if ($r['status'] === 'shop_received'): ?>💰 โอนเงินคืน
                            <?php elseif ($r['status'] === 'pending_super_admin'): ?>🔍 ตรวจสอบ
                            <?php else: ?>ดูรายละเอียด<?php endif; ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>
</div><!-- /.layout -->

<!-- ─────────────── Modal ─────────────── -->
<div class="modal" id="modal">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="m-title">รายละเอียดคำขอคืนสินค้า</h2>
            <button class="close-btn" id="close-modal" aria-label="ปิด">✕</button>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-field"><label>คำสั่งซื้อ</label><span id="m-order"></span></div>
                <div class="detail-field"><label>ลูกค้า</label><span id="m-buyer"></span></div>
                <div class="detail-field"><label>ร้านค้า</label><span id="m-seller"></span></div>
                <div class="detail-field"><label>สาเหตุ</label><span id="m-reason"></span></div>
                <div class="detail-field"><label>เลขพัสดุที่ส่งคืน</label><span class="mono" id="m-tracking"></span></div>
                <div class="detail-field"><label>ยอดคืนเงิน</label><span id="m-amount" style="color:var(--green);font-size:18px"></span></div>
                <div class="detail-field"><label>วันที่ยื่นคำขอ</label><span id="m-date"></span></div>
                <div class="detail-field"><label>Super Admin รีวิวเมื่อ</label><span id="m-sa-date" style="font-size:13px"></span></div>
            </div>

            <div class="evidence-wrap">
                <label>📸 รูปภาพหลักฐาน</label>
                <img id="m-evidence" src="" alt="หลักฐาน" style="display:none">
                <p id="m-no-evidence" style="color:var(--muted);font-size:14px">ไม่มีรูปภาพ</p>
            </div>

            <!-- Zone 1: Pending Super Admin — Approve / Reject -->
            <div id="zone-review" style="display:none">
                <div class="note-wrap">
                    <label>หมายเหตุถึงลูกค้า (ไม่บังคับ)</label>
                    <textarea id="m-note" rows="3" placeholder="เช่น กรุณาส่งคืนสินค้าภายใน 7 วัน..."></textarea>
                </div>
                <div class="action-row">
                    <button class="btn-approve" id="btn-approve">✅ อนุมัติ — ส่งให้ร้านค้ายืนยัน</button>
                    <button class="btn-reject"  id="btn-reject">❌ ปฏิเสธคำขอ</button>
                </div>
            </div>

            <!-- Zone 2: Shop Received — Process Refund -->
            <div id="zone-refund" style="display:none">
                <div style="background:var(--purple-soft);border-radius:12px;padding:16px;margin-bottom:18px;font-size:14px;color:#4c1d95">
                    <strong>💰 ขั้นตอนสุดท้าย:</strong> ร้านค้ายืนยันรับสินค้าคืนแล้ว กรุณาโอนเงินคืนเข้า Wonwan Wallet ของลูกค้า
                </div>
                <div class="note-wrap" style="margin-bottom:16px">
                    <label>หมายเหตุการโอนเงิน (ไม่บังคับ)</label>
                    <textarea id="m-refund-note" rows="2" placeholder="เช่น คืนเงินครบถ้วนตามยอดที่ลูกค้าชำระ..."></textarea>
                </div>
                <div class="action-row">
                    <button class="btn-refund" id="btn-refund">💚 โอนเงินเข้า Wonwan Wallet</button>
                </div>
            </div>

            <!-- Zone 3: Other statuses — read-only -->
            <div id="zone-readonly" style="display:none">
                <div class="status-badge-large" id="readonly-msg"></div>
            </div>
        </div>
    </div>
</div>

<div class="toast-msg" id="toast" role="alert"></div>

<script>
const CSRF    = <?= json_encode($_SESSION['superadmin_return_csrf']) ?>;
const REASONS = {
    damaged: '🔧 สินค้าชำรุด/เสียหาย',
    not_as_described: '📷 สินค้าไม่ตรงตามโฆษณา',
    wrong_item: '📦 ได้รับสินค้าผิดรายการ',
};

let currentReturnId = null;

// ─────────── Open Modal ───────────
function openModal(r) {
    currentReturnId = r.return_id;

    document.getElementById('m-title').textContent    = 'คำขอคืนสินค้า — คำสั่งซื้อ #' + (r.order_number || r.order_id);
    document.getElementById('m-order').textContent    = '#' + (r.order_number || r.order_id);
    document.getElementById('m-buyer').textContent    = (r.buyer_name || 'ลูกค้า') + '\n' + (r.buyer_email || '');
    document.getElementById('m-seller').textContent   = r.seller_name || (r.seller_type + ' #' + r.seller_id);
    document.getElementById('m-reason').textContent   = REASONS[r.reason] || r.reason;
    document.getElementById('m-tracking').textContent = r.return_tracking;
    document.getElementById('m-amount').textContent   = '฿' + Number(r.refund_amount).toLocaleString('th-TH', {minimumFractionDigits:2});
    document.getElementById('m-date').textContent     = r.created_at;
    document.getElementById('m-sa-date').textContent  = r.super_admin_reviewed_at
        ? '✅ ' + r.super_admin_reviewed_at
        : '— ยังไม่ได้รีวิว';

    // Evidence image — uploads/x.jpg → /pages/uploads/x.jpg (Laragon routing)
    const img = document.getElementById('m-evidence');
    const noEv = document.getElementById('m-no-evidence');
    if (r.evidence_url) {
        const ev = r.evidence_url.replace(/^\/+/, '');
        img.src = ev.startsWith('uploads/') ? '/pages/' + ev : '/' + ev;
        img.onerror = () => { img.style.display='none'; noEv.style.display='block'; noEv.textContent='โหลดรูปไม่ได้ — ลองดาวน์โหลดแทน'; };
        img.style.display = 'block';
        noEv.style.display = 'none';
    } else {
        img.style.display = 'none';
        noEv.style.display = 'block';
    }

    // Reset
    ['m-note','m-refund-note'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    enableButtons();

    // Zones
    const zReview   = document.getElementById('zone-review');
    const zRefund   = document.getElementById('zone-refund');
    const zReadonly = document.getElementById('zone-readonly');

    zReview.style.display = zRefund.style.display = zReadonly.style.display = 'none';

    if (r.status === 'pending_super_admin') {
        zReview.style.display = '';
    } else if (r.status === 'shop_received') {
        zRefund.style.display = '';
    } else {
        zReadonly.style.display = '';
        const statusInfo = {
            waiting_for_shop:   { cls: 'waiting',  msg: '📦 รอร้านค้ายืนยันรับสินค้าคืน — แจ้งร้านค้าแล้ว' },
            refunded_to_wallet: { cls: 'refunded', msg: '✅ คืนเงินเข้า Wonwan Wallet เรียบร้อยแล้ว' + (r.refunded_at ? ' เมื่อ ' + r.refunded_at : '') },
            rejected:           { cls: 'rejected', msg: '❌ ปฏิเสธแล้ว' + (r.admin_note ? ' — ' + r.admin_note : '') },
        };
        const info = statusInfo[r.status] || {cls:'', msg: r.status};
        const el = document.getElementById('readonly-msg');
        el.className = 'status-badge-large ' + info.cls;
        el.textContent = info.msg;
    }

    document.getElementById('modal').classList.add('open');
}

// ─────────── Close ───────────
document.getElementById('close-modal').onclick = closeModal;
document.getElementById('modal').onclick = e => { if (e.target === document.getElementById('modal')) closeModal(); };
function closeModal() { document.getElementById('modal').classList.remove('open'); }

// ─────────── Busy state ───────────
function enableButtons() {
    ['btn-approve','btn-reject','btn-refund'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = false;
    });
    document.getElementById('btn-approve') && (document.getElementById('btn-approve').textContent = '✅ อนุมัติ — ส่งให้ร้านค้ายืนยัน');
    document.getElementById('btn-reject')  && (document.getElementById('btn-reject').textContent  = '❌ ปฏิเสธคำขอ');
    document.getElementById('btn-refund')  && (document.getElementById('btn-refund').textContent  = '💚 โอนเงินเข้า Wonwan Wallet');
}
function setBusy(busy) {
    ['btn-approve','btn-reject','btn-refund'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.disabled = busy; if (busy) el.textContent = '⏳ กำลังดำเนินการ...'; }
    });
}

// ─────────── Review (Approve / Reject) ───────────
document.getElementById('btn-approve').onclick = () => {
    if (!currentReturnId) return;
    if (!confirm('ยืนยันการอนุมัติ? คำขอจะถูกส่งไปให้ร้านค้ายืนยันรับสินค้าคืน')) return;
    callApi('/api/superadmin_review_return.php', {
        return_id:  currentReturnId,
        action:     'approve',
        admin_note: document.getElementById('m-note').value.trim(),
    });
};

document.getElementById('btn-reject').onclick = () => {
    if (!currentReturnId) return;
    if (!confirm('ยืนยันการปฏิเสธคำขอนี้?')) return;
    callApi('/api/superadmin_review_return.php', {
        return_id:  currentReturnId,
        action:     'reject',
        admin_note: document.getElementById('m-note').value.trim(),
    });
};

// ─────────── Process Refund ───────────
document.getElementById('btn-refund').onclick = () => {
    if (!currentReturnId) return;
    if (!confirm('ยืนยันการโอนเงินคืนเข้า Wonwan Wallet ของลูกค้า? ไม่สามารถย้อนกลับได้')) return;
    callApi('/api/superadmin_process_refund.php', {
        return_id:   currentReturnId,
        refund_note: document.getElementById('m-refund-note').value.trim(),
    });
};

// ─────────── Generic API Call ───────────
async function callApi(url, fields) {
    setBusy(true);
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.entries(fields).forEach(([k, v]) => fd.append(k, v));

    try {
        const res = await fetch(url, { method: 'POST', body: fd });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();

        if (data.ok) {
            showToast('✅ ' + data.message, false);
            closeModal();
            setTimeout(() => location.reload(), 1400);
        } else {
            showToast('❌ ' + (data.message || 'เกิดข้อผิดพลาด'), true);
            enableButtons();
        }
    } catch (err) {
        // Network error / Timeout → reset state ทันที (ไม่ค้างอยู่)
        showToast('❌ เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ กรุณาลองใหม่', true);
        enableButtons();
    }
}

// ─────────── Toast ───────────
function showToast(msg, isError = false) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.background = isError ? '#dc2626' : '#059669';
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 4000);
}
</script>
</body>
</html>
