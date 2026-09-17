<?php
/**
 * pages/admin_returns.php
 * Dashboard คำขอคืนสินค้า — ฝั่งร้านค้า (Shop Admin / Publisher Admin)
 *
 * v2 Flow: ร้านค้าไม่ Approve/Reject อีกต่อไป
 *          มีหน้าที่เดียว = ยืนยันรับสินค้าคืน เมื่อได้รับพัสดุจริง
 */
session_start();
require_once __DIR__ . '/../config/config.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = $_SESSION['role'] ?? '';
if (!$userId || !in_array($role, ['shop_admin', 'publisher_admin'], true)) {
    header('Location:/pages/login.php'); exit;
}

$sellerType = $role === 'publisher_admin' ? 'publisher' : 'shop';
if ($sellerType === 'publisher') {
    $sq = $pdo->prepare('SELECT p.publisher_id id, p.company_name name FROM publisher_staff ps JOIN publishers p ON p.publisher_id = ps.publisher_id WHERE ps.user_id = ? LIMIT 1');
} else {
    $sq = $pdo->prepare('SELECT s.shop_id id, s.shop_name name FROM shop_staff ss JOIN shops s ON s.shop_id = ss.shop_id WHERE ss.user_id = ? LIMIT 1');
}
$sq->execute([$userId]);
$seller = $sq->fetch(PDO::FETCH_ASSOC);
if (!$seller) { header('Location:/pages/login.php'); exit; }

$sellerId    = (int)$seller['id'];
$seller_name = (string)$seller['name'];
$active_menu = 'returns';

// CSRF
if (empty($_SESSION['admin_return_csrf'])) {
    $_SESSION['admin_return_csrf'] = bin2hex(random_bytes(24));
}

// Filters — ร้านค้าเห็นเฉพาะ 2 สถานะ
$filterStatus = $_GET['status'] ?? 'waiting_for_shop';
$search       = trim($_GET['search'] ?? '');

// ร้านค้าเห็นเฉพาะ waiting_for_shop และ shop_received
$shopStatuses = ['waiting_for_shop', 'shop_received'];
if (!in_array($filterStatus, array_merge($shopStatuses, ['all']), true)) {
    $filterStatus = 'waiting_for_shop';
}

$where  = 'rr.seller_type = :type AND rr.seller_id = :seller';
$params = ['type' => $sellerType, 'seller' => $sellerId];

if ($filterStatus !== 'all') {
    $where .= ' AND rr.status = :status';
    $params['status'] = $filterStatus;
} else {
    // แสดงเฉพาะสถานะที่ร้านค้าเกี่ยวข้อง
    $where .= " AND rr.status IN ('waiting_for_shop', 'shop_received')";
}

if ($search !== '') {
    $where .= ' AND (o.order_number LIKE :search OR rr.return_tracking LIKE :search2)';
    $params['search']  = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
}

$listStmt = $pdo->prepare(
    "SELECT rr.*, o.order_number,
        COALESCE(NULLIF(u.display_name,''), u.email) buyer_name, u.email buyer_email,
        MIN(b.title) first_book
    FROM return_requests rr
    LEFT JOIN orders o ON o.order_id = rr.order_id
    LEFT JOIN users  u ON u.user_id  = rr.user_id
    LEFT JOIN order_items oi ON oi.order_id = rr.order_id
    LEFT JOIN books b ON b.book_id = oi.book_id
    WHERE $where
    GROUP BY rr.return_id
    ORDER BY
        CASE rr.status WHEN 'waiting_for_shop' THEN 1 ELSE 2 END,
        rr.created_at DESC"
);
$listStmt->execute($params);
$returns = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Stats (เฉพาะสถานะที่ร้านเกี่ยวข้อง)
$statsStmt = $pdo->prepare(
    "SELECT
        SUM(status='waiting_for_shop') waiting_count,
        SUM(status='shop_received')    received_count
     FROM return_requests WHERE seller_type = ? AND seller_id = ?"
);
$statsStmt->execute([$sellerType, $sellerId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

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
    <title>สินค้าคืนที่รอยืนยัน — วนวรรณ</title>
    <meta name="description" content="ยืนยันการรับสินค้าคืนจากลูกค้า">
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: var(--font-family); background: #fdf8f9; color: #1a1a2e; display: flex; min-height: 100vh; }
        .main { margin-left: 220px; flex: 1; padding: 44px 48px; min-width: 0; overflow-y: auto; }
        @media (max-width: 900px) { body { display: block; } .main { margin-left: 0; padding: 24px 16px; } }

        .page-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px; flex-wrap: wrap; gap: 16px; }
        .page-head h1 { font-size: 26px; color: var(--secondary-color); font-weight: 800; }
        .page-head p  { font-size: 14px; color: #6b7280; margin-top: 5px; }

        /* Info Banner */
        .info-banner {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1.5px solid #bfdbfe; border-radius: 16px;
            padding: 16px 20px; margin-bottom: 24px;
            display: flex; align-items: flex-start; gap: 12px;
            font-size: 14px; color: #1e40af;
        }
        .info-banner strong { display: block; margin-bottom: 4px; font-size: 15px; }

        /* Stats */
        .stats { display: grid; grid-template-columns: repeat(2,1fr); gap: 18px; margin-bottom: 28px; max-width: 500px; }
        .stat { background: #fff; border: 1px solid #f0e4ea; border-radius: 18px; padding: 22px; }
        .stat .s-val { font-size: 30px; font-weight: 800; margin-bottom: 4px; }
        .stat .s-lbl { font-size: 13px; color: #6b7280; }
        .stat.waiting  .s-val { color: #2563eb; }
        .stat.received .s-val { color: #059669; }

        /* Toolbar */
        .toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px; }
        .filters { display: flex; gap: 6px; flex-wrap: wrap; }
        .filter-btn { padding: 8px 16px; border-radius: 30px; border: 1.5px solid #e9d8de; background: #fff; font: inherit; font-size: 13px; font-weight: 600; color: #6b7280; cursor: pointer; transition: all .2s; text-decoration: none; display: inline-block; }
        .filter-btn.active { background: var(--secondary-color); color: #fff; border-color: var(--secondary-color); }
        .filter-btn:hover:not(.active) { border-color: var(--secondary-color); color: var(--secondary-color); }
        .search-form { display: flex; gap: 8px; }
        .search-input { height: 40px; border: 1.5px solid #e9d8de; border-radius: 20px; padding: 0 16px 0 36px; font: inherit; font-size: 14px; outline: none; width: 240px; background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' stroke='%236b7280' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 12px center #fff; }
        .search-input:focus { border-color: var(--secondary-color); }
        .btn-sm { height: 40px; padding: 0 16px; border: none; border-radius: 20px; font: inherit; font-size: 13px; font-weight: 700; cursor: pointer; background: var(--secondary-color); color: #fff; }

        /* Table */
        .table-wrap { background: #fff; border: 1px solid #f0e4ea; border-radius: 20px; overflow: hidden; }
        .rtable { width: 100%; border-collapse: collapse; min-width: 780px; }
        .rtable thead { background: linear-gradient(135deg, #fdf2f8, #fce7f3); }
        .rtable th { padding: 14px 18px; text-align: left; font-size: 13px; font-weight: 700; color: #6b4a56; }
        .rtable td { padding: 16px 18px; border-bottom: 1px solid #f9f0f3; font-size: 14px; vertical-align: middle; }
        .rtable tr:last-child td { border-bottom: none; }
        .rtable tr:hover td { background: #fdf8f9; }

        .badge { display: inline-flex; align-items: center; gap: 5px; padding: 5px 12px; border-radius: 30px; font-size: 12px; font-weight: 700; }
        .badge-waiting  { background: #dbeafe; color: #2563eb; border: 1px solid #bfdbfe; }
        .badge-received { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }

        .btn-confirm {
            padding: 9px 16px; background: linear-gradient(135deg,#2563eb,#1d4ed8);
            border: none; border-radius: 10px; font: inherit; font-size: 13px; font-weight: 700;
            color: #fff; cursor: pointer; box-shadow: 0 3px 12px rgba(37,99,235,.3); transition: opacity .2s;
            white-space: nowrap;
        }
        .btn-confirm:hover { opacity: .88; }
        .btn-detail { padding: 9px 14px; background: #fff; border: 1.5px solid #e9d8de; border-radius: 10px; font: inherit; font-size: 13px; font-weight: 700; color: var(--secondary-color); cursor: pointer; transition: all .2s; }
        .btn-detail:hover { background: var(--secondary-color); color: #fff; border-color: var(--secondary-color); }

        .empty-state { text-align: center; padding: 70px 20px; color: #9ca3af; }
        .empty-state .e-icon { font-size: 56px; margin-bottom: 16px; }
        .empty-state h3 { font-size: 18px; font-weight: 700; color: #374151; margin-bottom: 8px; }

        /* Modal */
        .modal { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; padding: 20px; z-index: 600; backdrop-filter: blur(4px); }
        .modal.open { display: flex; }
        .modal-box { background: #fff; border-radius: 24px; width: min(700px, 100%); max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 80px rgba(0,0,0,.2); scrollbar-width: none; }
        .modal-box::-webkit-scrollbar { display: none; }
        .modal-head { padding: 24px 28px; background: linear-gradient(135deg, #fdf2f8, #fce7f3); border-bottom: 1px solid #f5cdd8; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; }
        .modal-head h2 { font-size: 18px; color: var(--secondary-color); font-weight: 800; }
        .close-btn { background: none; border: none; font-size: 22px; cursor: pointer; color: #9ca3af; transition: color .2s; }
        .close-btn:hover { color: var(--secondary-color); }
        .modal-body { padding: 28px; }

        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 22px; }
        @media (max-width: 540px) { .detail-grid { grid-template-columns: 1fr; } }
        .detail-field { display: flex; flex-direction: column; gap: 4px; }
        .detail-field label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #9ca3af; }
        .detail-field span { font-size: 14px; color: #1a1a2e; font-weight: 600; }
        .detail-field span.mono { font-family: monospace; font-size: 15px; }

        .evidence-wrap { margin-bottom: 22px; }
        .evidence-wrap label { font-size: 13px; font-weight: 700; color: #4b5563; display: block; margin-bottom: 10px; }
        .evidence-wrap img { max-width: 100%; max-height: 280px; border-radius: 12px; border: 1px solid #e5e7eb; object-fit: contain; }

        /* Confirm area */
        .confirm-area { background: linear-gradient(135deg, #eff6ff, #dbeafe); border-radius: 16px; padding: 20px; margin-bottom: 0; }
        .confirm-area p { font-size: 14px; color: #1e40af; margin-bottom: 16px; line-height: 1.7; }
        .btn-confirm-large {
            width: 100%; padding: 16px; border: none; border-radius: 12px;
            font: inherit; font-size: 16px; font-weight: 800;
            background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff;
            cursor: pointer; box-shadow: 0 6px 20px rgba(37,99,235,.35); transition: opacity .2s;
        }
        .btn-confirm-large:hover { opacity: .88; }
        .btn-confirm-large:disabled { opacity: .5; cursor: not-allowed; }
        .received-badge { text-align: center; padding: 18px; background: #d1fae5; border-radius: 12px; font-size: 15px; font-weight: 700; color: #059669; }

        .toast-msg { display: none; position: fixed; bottom: 28px; right: 28px; background: #1a1a2e; color: #fff; border-radius: 12px; padding: 14px 24px; font-size: 14px; font-weight: 600; z-index: 9999; box-shadow: 0 8px 30px rgba(0,0,0,.2); }
        .toast-msg.show { display: block; animation: fadeup .3s; }
        @keyframes fadeup { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
    </style>
</head>
<body>
<?php include __DIR__ . '/../components/sidebar.php'; ?>

<main class="main">
    <div class="page-head">
        <div>
            <h1>📦 สินค้าที่รอการยืนยันรับคืน</h1>
            <p>ยืนยันรับสินค้าเมื่อพัสดุมาถึงร้านค้าแล้ว ระบบจะโอนเงินคืนให้ลูกค้าอัตโนมัติ</p>
        </div>
        <a href="shop_return_address.php" class="btn-sm" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            ที่อยู่รับคืน
        </a>
    </div>

    <!-- Info Banner -->
    <div class="info-banner">
        <span style="font-size:22px">ℹ️</span>
        <div>
            <strong>กระบวนการคืนสินค้าใหม่ (v2)</strong>
            Super Admin ตรวจสอบและอนุมัติคำขอก่อน → ลูกค้าส่งพัสดุมาให้ท่าน →
            <strong>ท่านกดยืนยันรับสินค้า</strong> → Super Admin โอนเงินคืนให้ลูกค้าทันที
        </div>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat waiting">
            <div class="s-val"><?= (int)($stats['waiting_count'] ?? 0) ?></div>
            <div class="s-lbl">📦 รอรับสินค้าคืน</div>
        </div>
        <div class="stat received">
            <div class="s-val"><?= (int)($stats['received_count'] ?? 0) ?></div>
            <div class="s-lbl">✅ ยืนยันรับแล้ว</div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
        <div class="filters">
            <a href="?status=all<?= $search ? '&search='.urlencode($search) : '' ?>"
               class="filter-btn <?= $filterStatus === 'all' ? 'active' : '' ?>">ทั้งหมด</a>
            <a href="?status=waiting_for_shop<?= $search ? '&search='.urlencode($search) : '' ?>"
               class="filter-btn <?= $filterStatus === 'waiting_for_shop' ? 'active' : '' ?>">📦 รอรับสินค้า</a>
            <a href="?status=shop_received<?= $search ? '&search='.urlencode($search) : '' ?>"
               class="filter-btn <?= $filterStatus === 'shop_received' ? 'active' : '' ?>">✅ ยืนยันรับแล้ว</a>
        </div>
        <form class="search-form" method="get">
            <?php if ($filterStatus !== 'all'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>"><?php endif; ?>
            <input class="search-input" type="text" name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   placeholder="ค้นหาเลขคำสั่งซื้อ / พัสดุ">
            <button class="btn-sm" type="submit">ค้นหา</button>
        </form>
    </div>

    <!-- Table -->
    <div class="table-wrap" style="overflow:auto">
        <?php if (!$returns): ?>
        <div class="empty-state">
            <div class="e-icon">📭</div>
            <h3>ไม่มีรายการคืนสินค้าที่รอการยืนยัน</h3>
            <p>เมื่อ Super Admin อนุมัติคำขอจากลูกค้า รายการจะปรากฏที่นี่</p>
        </div>
        <?php else: ?>
        <table class="rtable">
            <thead>
                <tr>
                    <th>คำสั่งซื้อ</th>
                    <th>ลูกค้า</th>
                    <th>สาเหตุ</th>
                    <th>เลขพัสดุคืน</th>
                    <th>ยอดคืนเงิน</th>
                    <th>วันที่ยื่น</th>
                    <th>สถานะ</th>
                    <th>ดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($returns as $r):
                $isWaiting = $r['status'] === 'waiting_for_shop';
            ?>
            <tr>
                <td><strong>#<?= htmlspecialchars($r['order_number'] ?? $r['order_id']) ?></strong></td>
                <td>
                    <?= htmlspecialchars($r['buyer_name'] ?? 'ลูกค้า') ?><br>
                    <small style="color:#9ca3af"><?= htmlspecialchars($r['buyer_email'] ?? '') ?></small>
                </td>
                <td><?= $reasonMap[$r['reason']] ?? htmlspecialchars($r['reason']) ?></td>
                <td style="font-family:monospace;font-size:13px"><?= htmlspecialchars($r['return_tracking']) ?></td>
                <td><strong style="color:var(--secondary-color)">฿<?= number_format((float)$r['refund_amount'], 2) ?></strong></td>
                <td style="font-size:12px;color:#6b7280"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                <td>
                    <span class="badge <?= $isWaiting ? 'badge-waiting' : 'badge-received' ?>">
                        <?= $isWaiting ? '📦 รอรับสินค้า' : '✅ ยืนยันรับแล้ว' ?>
                    </span>
                </td>
                <td>
                    <?php if ($isWaiting): ?>
                    <button class="btn-confirm"
                            onclick="openModal(<?= htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                        ✅ ยืนยันรับสินค้า
                    </button>
                    <?php else: ?>
                    <button class="btn-detail"
                            onclick="openModal(<?= htmlspecialchars(json_encode($r, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
                        ดูรายละเอียด
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>

<!-- Modal -->
<div class="modal" id="modal">
    <div class="modal-box">
        <div class="modal-head">
            <h2 id="m-title">รายละเอียดการคืนสินค้า</h2>
            <button class="close-btn" id="close-modal" aria-label="ปิด">✕</button>
        </div>
        <div class="modal-body">
            <div class="detail-grid">
                <div class="detail-field"><label>คำสั่งซื้อ</label><span id="m-order"></span></div>
                <div class="detail-field"><label>ลูกค้า</label><span id="m-buyer"></span></div>
                <div class="detail-field"><label>สาเหตุ</label><span id="m-reason"></span></div>
                <div class="detail-field"><label>เลขพัสดุที่ส่งคืน</label><span class="mono" id="m-tracking"></span></div>
                <div class="detail-field"><label>ยอดที่จะคืนลูกค้า</label><span id="m-amount"></span></div>
                <div class="detail-field"><label>วันที่ยื่นคำขอ</label><span id="m-date"></span></div>
            </div>

            <div class="evidence-wrap">
                <label>📸 รูปภาพหลักฐานจากลูกค้า</label>
                <img id="m-evidence" src="" alt="หลักฐาน" style="display:none">
                <p id="m-no-evidence" style="color:#9ca3af;font-size:14px;display:none">ไม่มีรูปภาพ</p>
            </div>

            <!-- Confirm zone (waiting_for_shop) -->
            <div id="zone-confirm">
                <div class="confirm-area">
                    <p>
                        🚚 <strong>กรุณากดยืนยันเมื่อได้รับพัสดุคืนจริงแล้วเท่านั้น</strong><br>
                        เมื่อกดยืนยัน ระบบจะแจ้ง Super Admin ทันที และจะดำเนินการโอนเงิน
                        <strong>฿<span id="m-confirm-amount"></span></strong>
                        เข้า Wonwan Wallet ของลูกค้าโดยไม่ต้องดำเนินการใด ๆ เพิ่มเติมจากท่าน
                    </p>
                    <button class="btn-confirm-large" id="btn-confirm-received">
                        ✅ ยืนยันรับสินค้าเคลมเรียบร้อยแล้ว
                    </button>
                </div>
            </div>

            <!-- Already received -->
            <div id="zone-received" style="display:none">
                <div class="received-badge">
                    ✅ ยืนยันรับสินค้าคืนแล้ว เมื่อ <span id="m-received-at"></span><br>
                    <small style="font-weight:400;font-size:13px">Super Admin กำลังดำเนินการโอนเงินคืนให้ลูกค้า</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast-msg" id="toast" role="alert"></div>

<script>
const CSRF = <?= json_encode($_SESSION['admin_return_csrf']) ?>;
const REASONS = {
    damaged: '🔧 สินค้าชำรุด/เสียหาย',
    not_as_described: '📷 สินค้าไม่ตรงตามโฆษณา',
    wrong_item: '📦 ได้รับสินค้าผิดรายการ',
};

let currentReturnId = null;

function openModal(r) {
    currentReturnId = r.return_id;
    document.getElementById('m-title').textContent    = 'สินค้าคืน — คำสั่งซื้อ #' + (r.order_number || r.order_id);
    document.getElementById('m-order').textContent    = '#' + (r.order_number || r.order_id);
    document.getElementById('m-buyer').textContent    = (r.buyer_name || 'ลูกค้า') + ' · ' + (r.buyer_email || '');
    document.getElementById('m-reason').textContent   = REASONS[r.reason] || r.reason;
    document.getElementById('m-tracking').textContent = r.return_tracking;
    document.getElementById('m-amount').textContent   = '฿' + Number(r.refund_amount).toLocaleString('th-TH', {minimumFractionDigits:2});
    document.getElementById('m-confirm-amount').textContent = Number(r.refund_amount).toLocaleString('th-TH', {minimumFractionDigits:2});
    document.getElementById('m-date').textContent     = r.created_at;

    // Evidence
    const img  = document.getElementById('m-evidence');
    const noEv = document.getElementById('m-no-evidence');
    if (r.evidence_url) {
        img.src = '/' + r.evidence_url.replace(/^\//, '');
        img.style.display = 'block'; noEv.style.display = 'none';
    } else {
        img.style.display = 'none'; noEv.style.display = 'block';
    }

    // Zones
    const isWaiting = r.status === 'waiting_for_shop';
    document.getElementById('zone-confirm').style.display  = isWaiting ? '' : 'none';
    document.getElementById('zone-received').style.display = isWaiting ? 'none' : '';
    if (!isWaiting) {
        document.getElementById('m-received-at').textContent = r.shop_received_at || 'ไม่ทราบวันที่';
    }

    // Reset button
    const btn = document.getElementById('btn-confirm-received');
    btn.disabled = false;
    btn.textContent = '✅ ยืนยันรับสินค้าเคลมเรียบร้อยแล้ว';

    document.getElementById('modal').classList.add('open');
}

document.getElementById('close-modal').onclick = () => document.getElementById('modal').classList.remove('open');
document.getElementById('modal').onclick = e => { if (e.target === document.getElementById('modal')) document.getElementById('modal').classList.remove('open'); };

// Confirm received
document.getElementById('btn-confirm-received').onclick = () => {
    if (!currentReturnId) return;
    if (!confirm('ยืนยันว่าได้รับสินค้าคืนจากลูกค้าเรียบร้อยแล้ว?\n\nระบบจะแจ้ง Super Admin เพื่อดำเนินการโอนเงินคืนให้ลูกค้าทันที')) return;

    const btn = document.getElementById('btn-confirm-received');
    btn.disabled = true;
    btn.textContent = '⏳ กำลังบันทึก...';

    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('return_id', currentReturnId);

    (async () => {
        try {
            const res = await fetch('../api/shop_confirm_received.php', { method: 'POST', body: fd });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();

            if (data.ok) {
                showToast('✅ ' + data.message, false);
                document.getElementById('modal').classList.remove('open');
                setTimeout(() => location.reload(), 1400);
            } else {
                showToast('❌ ' + (data.message || 'เกิดข้อผิดพลาด'), true);
                btn.disabled = false;
                btn.textContent = '✅ ยืนยันรับสินค้าเคลมเรียบร้อยแล้ว';
            }
        } catch (err) {
            // Network error / Timeout — reset state ทันที
            showToast('❌ เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ กรุณาลองใหม่', true);
            btn.disabled = false;
            btn.textContent = '✅ ยืนยันรับสินค้าเคลมเรียบร้อยแล้ว';
        }
    })();
};

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
