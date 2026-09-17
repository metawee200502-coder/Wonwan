<?php
session_start();
require_once __DIR__ . '/../config/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php');
    exit;
}

// --- POST: Resolve Dispute ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['dispute_id'], $_POST['order_id'])) {
    $action     = $_POST['action'];
    $dispute_id = (int)$_POST['dispute_id'];
    $order_id   = (int)$_POST['order_id'];
    $admin_note = $_POST['admin_note'] ?? '';

    if ($action === 'refund') {
        $pdo->prepare("UPDATE disputes SET status='resolved',resolution='refunded',admin_note=? WHERE dispute_id=?")->execute([$admin_note, $dispute_id]);
        $pdo->prepare("UPDATE orders SET payout_status='refunded',shipping_status='returned' WHERE order_id=?")->execute([$order_id]);
        $order_number = $pdo->query("SELECT order_number FROM orders WHERE order_id=".(int)$order_id)->fetchColumn() ?: '#'.$order_id;
        logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'RESOLVE_DISPUTE_REFUND', (string)$dispute_id, "คืนเงินลูกค้า ออเดอร์ {$order_number}. หมายเหตุ: {$admin_note}");
        $_SESSION['flash_msg'] = ['type'=>'success','text'=>'บันทึกการคืนเงินให้ลูกค้าเรียบร้อยแล้ว'];
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE disputes SET status='resolved',resolution='rejected',admin_note=? WHERE dispute_id=?")->execute([$admin_note, $dispute_id]);
        $pdo->prepare("UPDATE orders SET payout_status='pending' WHERE order_id=?")->execute([$order_id]);
        $order_number = $pdo->query("SELECT order_number FROM orders WHERE order_id=".(int)$order_id)->fetchColumn() ?: '#'.$order_id;
        logAdminAction($pdo, $_SESSION['email'] ?? 'unknown', 'RESOLVE_DISPUTE_REJECT', (string)$dispute_id, "ปฏิเสธข้อพิพาท ออเดอร์ {$order_number}. หมายเหตุ: {$admin_note}");
        $_SESSION['flash_msg'] = ['type'=>'info','text'=>'ปฏิเสธข้อพิพาทเรียบร้อย'];
    }
    header("Location: disputes.php#disputes");
    exit;
}

// --- Disputes (open) ---
$disputes = $pdo->query("
    SELECT d.*, o.order_number, o.total_amount, o.shipping_name, o.shipping_phone, u.email AS user_email
    FROM disputes d
    JOIN orders o ON d.order_id = o.order_id
    JOIN users  u ON d.user_id  = u.user_id
    WHERE d.status = 'open'
    ORDER BY d.created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

// --- Disputes (resolved) ---
$resolvedDisputes = $pdo->query("
    SELECT d.*, o.order_number, o.total_amount, u.email AS user_email
    FROM disputes d
    JOIN orders o ON d.order_id = o.order_id
    JOIN users  u ON d.user_id  = u.user_id
    WHERE d.status = 'resolved'
    ORDER BY d.updated_at DESC LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// --- Return Requests (pending_super_admin) ---
if (empty($_SESSION['superadmin_return_csrf'])) {
    $_SESSION['superadmin_return_csrf'] = bin2hex(random_bytes(24));
}

// Filter params
$filterReason = $_GET['reason'] ?? '';
$filterSearch = trim($_GET['search'] ?? '');

$rWhere = "rr.status = 'pending_super_admin'";
$rParams = [];
if ($filterReason) { $rWhere .= " AND rr.reason = :reason"; $rParams['reason'] = $filterReason; }
if ($filterSearch) {
    $rWhere .= " AND (o.order_number LIKE :s1 OR rr.return_tracking LIKE :s2 OR u.email LIKE :s3)";
    $rParams['s1'] = '%'.$filterSearch.'%';
    $rParams['s2'] = '%'.$filterSearch.'%';
    $rParams['s3'] = '%'.$filterSearch.'%';
}

$rStmt = $pdo->prepare("
    SELECT rr.*, o.order_number, o.total_amount, o.shipping_fee,
        COALESCE(NULLIF(u.display_name,''), u.email) buyer_name,
        u.email buyer_email,
        COALESCE(s.shop_name, p.company_name) seller_name
    FROM return_requests rr
    LEFT JOIN orders     o ON o.order_id      = rr.order_id
    LEFT JOIN users      u ON u.user_id       = rr.user_id
    LEFT JOIN shops      s ON rr.seller_type='shop'      AND s.shop_id      = rr.seller_id
    LEFT JOIN publishers p ON rr.seller_type='publisher' AND p.publisher_id = rr.seller_id
    WHERE $rWhere
    ORDER BY rr.created_at ASC
");
$rStmt->execute($rParams);
$returnRequests = $rStmt->fetchAll(PDO::FETCH_ASSOC);

// Escrow summary
$escrowTotal = 0;
foreach ($returnRequests as $r) { $escrowTotal += (float)$r['refund_amount']; }

$reasonMap = [
    'damaged'          => ['label' => 'สินค้าชำรุด/เสียหาย',        'icon' => '🔧', 'color' => '#dc2626'],
    'not_as_described' => ['label' => 'สินค้าไม่ตรงตามโฆษณา',       'icon' => '📷', 'color' => '#d97706'],
    'wrong_item'       => ['label' => 'ได้รับสินค้าผิดรายการ',        'icon' => '📦', 'color' => '#2563eb'],
];

$activeTab = $_GET['tab'] ?? ($_REQUEST['_tab'] ?? 'returns');
$active_menu = 'disputes';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disputes &amp; คำขอคืนสินค้า — วนวรรณ Admin</title>
    <meta name="description" content="พิจารณาข้อพิพาทและคำขอส่งคืนสินค้า เพื่อการตัดสินใจและคุ้มครองผู้ซื้อ-ผู้ขายผ่านระบบ Escrow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
    /* ===== RESET / BASE ===== */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Noto Sans Thai', var(--font-family, system-ui), sans-serif; background: #f4f6fb; color: #1a1a2e; }

    /* ===== LAYOUT WRAPPERS ===== */
    .dsp-wrap { max-width: 1280px; margin: 0 auto; padding: 0 28px 60px; }

    /* ===== PAGE HEADER ===== */
    .dsp-header {
        display: flex; justify-content: space-between; align-items: flex-start;
        flex-wrap: wrap; gap: 20px;
        padding: 28px 0 22px;
        border-bottom: 1.5px solid #e8e4f3;
        margin-bottom: 0;
    }
    .dsp-header-left {}
    .dsp-breadcrumb { font-size: 12px; color: #9ca3af; margin-bottom: 8px; }
    .dsp-breadcrumb a { color: #7c3aed; text-decoration: none; }
    .dsp-breadcrumb a:hover { text-decoration: underline; }
    .dsp-title {
        font-size: 26px; font-weight: 800; color: #1e1b3a;
        display: flex; align-items: center; gap: 10px;
    }
    .dsp-title-icon {
        width: 36px; height: 36px; background: #fee2e2; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; font-size: 18px;
    }
    .dsp-subtitle { font-size: 13px; color: #6b7280; margin-top: 5px; line-height: 1.5; }
    .dsp-stats { display: flex; gap: 16px; flex-wrap: wrap; }
    .dsp-stat-box {
        background: #fff; border: 1.5px solid #e8e4f3; border-radius: 14px;
        padding: 14px 22px; text-align: right; min-width: 150px;
    }
    .dsp-stat-label { font-size: 11px; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }
    .dsp-stat-value { font-size: 22px; font-weight: 800; color: #1e1b3a; }
    .dsp-stat-value.green { color: #059669; }

    /* ===== TAB NAV ===== */
    .tab-nav {
        display: flex; gap: 0;
        border-bottom: 2px solid #e8e4f3;
        margin-bottom: 24px; margin-top: 6px;
        overflow-x: auto; scrollbar-width: none;
    }
    .tab-nav::-webkit-scrollbar { display: none; }
    .tab-btn {
        padding: 14px 24px; font-size: 13.5px; font-weight: 700; color: #9ca3af;
        background: none; border: none; border-bottom: 3px solid transparent;
        margin-bottom: -2px; cursor: pointer; transition: all .2s;
        font-family: inherit; display: inline-flex; align-items: center; gap: 7px;
        white-space: nowrap; flex-shrink: 0;
    }
    .tab-btn.active { color: #7c3aed; border-bottom-color: #7c3aed; }
    .tab-btn:hover:not(.active) { color: #6b7280; }
    .tab-badge {
        font-size: 11px; font-weight: 800; border-radius: 20px;
        padding: 2px 9px; line-height: 1.5;
        background: #e8e4f3; color: #7c3aed;
    }
    .tab-badge.red   { background: #fee2e2; color: #dc2626; }
    .tab-badge.amber { background: #fef3c7; color: #d97706; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* ===== SEARCH / FILTER BAR ===== */
    .filter-bar {
        display: flex; align-items: center; gap: 10px;
        flex-wrap: wrap; margin-bottom: 20px;
        background: #fff; border: 1.5px solid #e8e4f3;
        border-radius: 14px; padding: 12px 18px;
    }
    .filter-search {
        flex: 1; min-width: 180px; height: 38px;
        border: 1.5px solid #e8e4f3; border-radius: 10px;
        padding: 0 14px 0 38px; font: inherit; font-size: 13px; outline: none;
        background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' stroke='%239ca3af' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 12px center #fafafa;
        transition: border-color .2s;
    }
    .filter-search:focus { border-color: #7c3aed; background-color: #fff; }
    .filter-select {
        height: 38px; border: 1.5px solid #e8e4f3; border-radius: 10px;
        padding: 0 32px 0 12px; font: inherit; font-size: 13px; outline: none;
        background: #fafafa url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='none' stroke='%236b7280' stroke-width='2' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4'/%3E%3C/svg%3E") no-repeat right 10px center;
        appearance: none; cursor: pointer; color: #374151;
        transition: border-color .2s;
    }
    .filter-select:focus { border-color: #7c3aed; }
    .filter-btn {
        height: 38px; padding: 0 18px; border: 1.5px solid #7c3aed;
        border-radius: 10px; font: inherit; font-size: 13px; font-weight: 700;
        background: #7c3aed; color: #fff; cursor: pointer; transition: opacity .2s;
        white-space: nowrap;
    }
    .filter-btn:hover { opacity: .88; }
    .filter-refresh {
        width: 38px; height: 38px; border: 1.5px solid #e8e4f3;
        border-radius: 10px; background: #fafafa; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: #6b7280; font-size: 16px; transition: all .2s; flex-shrink: 0;
    }
    .filter-refresh:hover { border-color: #7c3aed; color: #7c3aed; }

    /* ===== RETURN REQUEST CARD ===== */
    .return-list { display: flex; flex-direction: column; gap: 20px; }
    .return-card {
        background: #fff; border: 1.5px solid #e8e4f3;
        border-radius: 18px; overflow: hidden;
        box-shadow: 0 2px 16px rgba(124,58,237,.06);
        transition: box-shadow .25s;
    }
    .return-card:hover { box-shadow: 0 6px 28px rgba(124,58,237,.12); }

    /* Card top strip */
    .rc-strip {
        height: 4px;
        background: linear-gradient(90deg, #7c3aed, #a78bfa);
    }

    /* Card inner 2-col */
    .rc-inner { display: flex; gap: 0; align-items: stretch; }
    .rc-main { flex: 1 1 0; padding: 22px 24px; min-width: 0; }
    .rc-panel {
        width: 300px; flex-shrink: 0;
        border-left: 1.5px solid #f0ebff;
        padding: 22px 22px;
        background: linear-gradient(180deg, #faf9ff 0%, #fff 100%);
        display: flex; flex-direction: column; gap: 14px;
    }

    /* Order header */
    .rc-order-row {
        display: flex; align-items: center; gap: 10px;
        flex-wrap: wrap; margin-bottom: 14px;
    }
    .rc-order-num { font-size: 17px; font-weight: 800; color: #1e1b3a; font-family: monospace; }
    .rc-badge {
        font-size: 11px; font-weight: 700; border-radius: 20px;
        padding: 3px 10px; border: 1px solid transparent;
    }
    .rc-badge.super   { background: #fef3c7; color: #92400e; border-color: #fde68a; }
    .rc-badge.shop    { background: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
    .rc-badge.verified { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .rc-time { font-size: 12px; color: #9ca3af; margin-left: auto; }

    /* Parties */
    .rc-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
    .rc-party-box {
        background: #f9f8ff; border: 1px solid #ede9fe;
        border-radius: 10px; padding: 12px 14px;
        display: flex; align-items: center; gap: 10px;
    }
    .rc-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; font-weight: 800; flex-shrink: 0;
    }
    .rc-avatar.buyer  { background: #dbeafe; color: #1e40af; }
    .rc-avatar.seller { background: #fce7f3; color: #9d174d; }
    .rc-party-info {}
    .rc-party-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; }
    .rc-party-name  { font-size: 13px; font-weight: 700; color: #1e1b3a; }
    .rc-party-sub   { font-size: 11px; color: #6b7280; }

    /* Reason */
    .rc-reason-box {
        border-radius: 10px; padding: 14px 16px; margin-bottom: 14px;
        border: 1px solid transparent;
    }
    .rc-reason-box.damaged          { background: #fff5f5; border-color: #fecaca; }
    .rc-reason-box.not_as_described { background: #fffbeb; border-color: #fde68a; }
    .rc-reason-box.wrong_item       { background: #eff6ff; border-color: #bfdbfe; }
    .rc-reason-title { font-size: 13px; font-weight: 700; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
    .rc-reason-desc  { font-size: 13px; color: #374151; line-height: 1.6; }

    /* Tracking */
    .rc-tracking {
        display: flex; align-items: center; gap: 10px;
        background: #f9fafb; border: 1px solid #e5e7eb;
        border-radius: 10px; padding: 10px 14px; margin-bottom: 14px;
        font-size: 13px; flex-wrap: wrap;
    }
    .rc-tracking-num { font-family: monospace; font-size: 14px; font-weight: 700; color: #1e1b3a; }
    .rc-tracking-carrier {
        font-size: 11px; font-weight: 700; background: #fee2e2;
        color: #dc2626; border-radius: 6px; padding: 2px 8px;
    }
    .rc-tracking-status { font-size: 12px; color: #059669; margin-left: auto; display: flex; align-items: center; gap: 4px; }

    /* Evidence */
    .rc-evidence-section { margin-bottom: 14px; }
    .rc-evidence-header  { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .rc-evidence-label   { font-size: 12px; font-weight: 700; color: #4b5563; display: flex; align-items: center; gap: 5px; }
    .rc-evidence-dl      { font-size: 12px; color: #7c3aed; text-decoration: none; font-weight: 600; }
    .rc-evidence-dl:hover { text-decoration: underline; }
    .rc-evidence-grid    { display: flex; gap: 10px; flex-wrap: wrap; }
    .rc-evidence-thumb {
        width: 90px; height: 90px; border-radius: 10px;
        object-fit: cover; cursor: zoom-in;
        border: 1.5px solid #e8e4f3; transition: all .2s;
        background: #f9f8ff;
    }
    .rc-evidence-thumb:hover { border-color: #7c3aed; transform: scale(1.04); }

    /* Product summary */
    .rc-product {
        display: flex; align-items: center; gap: 12px;
        background: #f9fafb; border: 1px solid #e5e7eb;
        border-radius: 10px; padding: 10px 14px; font-size: 13px;
    }
    .rc-product-cover {
        width: 44px; height: 56px; border-radius: 6px;
        object-fit: cover; background: #e8e4f3; flex-shrink: 0;
    }
    .rc-product-cover.placeholder {
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
    }
    .rc-product-name { font-weight: 700; color: #1e1b3a; margin-bottom: 2px; }
    .rc-product-meta { font-size: 12px; color: #6b7280; }
    .rc-product-price { font-size: 17px; font-weight: 800; color: #059669; margin-left: auto; white-space: nowrap; }

    /* ===== RIGHT PANEL (action) ===== */
    .rp-escrow-box {
        background: linear-gradient(135deg, #f0fdf4, #dcfce7);
        border: 1.5px solid #86efac; border-radius: 12px; padding: 14px 16px;
        text-align: center;
    }
    .rp-escrow-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #166534; margin-bottom: 4px; }
    .rp-escrow-amt   { font-size: 26px; font-weight: 900; color: #059669; letter-spacing: -.02em; }

    .rp-section-title { font-size: 13px; font-weight: 800; color: #1e1b3a; display: flex; align-items: center; gap: 6px; }

    .rp-warning {
        background: #fffbeb; border: 1px solid #fde68a;
        border-radius: 10px; padding: 12px; font-size: 12px; color: #92400e; line-height: 1.5;
    }
    .rp-warning strong { color: #b45309; }

    .rp-note {
        width: 100%; border: 1.5px solid #e8e4f3; border-radius: 10px;
        padding: 10px 12px; font: inherit; font-size: 13px;
        resize: vertical; min-height: 90px; outline: none; color: #374151;
        transition: border-color .2s;
    }
    .rp-note:focus { border-color: #7c3aed; }

    .rp-note-counter { font-size: 11px; color: #9ca3af; text-align: right; margin-top: 2px; }

    .rp-quick-inserts { display: flex; flex-wrap: wrap; gap: 6px; }
    .rp-insert-chip {
        font-size: 11px; font-weight: 600; color: #7c3aed;
        background: #f5f3ff; border: 1px solid #ddd6fe; border-radius: 20px;
        padding: 3px 10px; cursor: pointer; transition: all .15s;
        white-space: nowrap;
    }
    .rp-insert-chip:hover { background: #ede9fe; border-color: #c4b5fd; }

    .btn-approve-r {
        width: 100%; padding: 13px 14px; border: none; border-radius: 11px;
        font: inherit; font-size: 14px; font-weight: 800;
        background: linear-gradient(135deg, #059669, #10b981);
        color: #fff; cursor: pointer;
        box-shadow: 0 4px 14px rgba(5,150,105,.35);
        transition: opacity .2s, transform .15s;
    }
    .btn-approve-r:hover { opacity: .9; transform: translateY(-1px); }
    .btn-reject-r {
        width: 100%; padding: 13px 14px; border: none; border-radius: 11px;
        font: inherit; font-size: 14px; font-weight: 800;
        background: linear-gradient(135deg, #dc2626, #ef4444);
        color: #fff; cursor: pointer;
        box-shadow: 0 4px 14px rgba(220,38,38,.25);
        transition: opacity .2s, transform .15s;
    }
    .btn-reject-r:hover { opacity: .9; transform: translateY(-1px); }
    .btn-info-r {
        width: 100%; padding: 11px 14px; border: 1.5px solid #e8e4f3;
        border-radius: 11px; font: inherit; font-size: 13px; font-weight: 700;
        background: #fff; color: #6b7280; cursor: pointer; transition: all .2s;
    }
    .btn-info-r:hover { border-color: #7c3aed; color: #7c3aed; }
    .btn-approve-r:disabled, .btn-reject-r:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    .rp-audit-note { font-size: 11px; color: #9ca3af; text-align: center; padding-top: 4px; }

    /* ===== DISPUTE CARDS (existing style modernized) ===== */
    .dispute-list { display: flex; flex-direction: column; gap: 16px; }
    .dispute-card {
        background: #fff; border: 1.5px solid #fee2e2;
        border-left: 4px solid #e8546a; border-radius: 16px; padding: 20px 22px;
        display: flex; gap: 22px; align-items: stretch;
        box-shadow: 0 2px 10px rgba(232,84,106,.06);
    }
    .dispute-info { flex: 2; display: flex; flex-direction: column; gap: 10px; }
    .dispute-reason-box { background: #fff5f5; padding: 14px; border-radius: 10px; border: 1px solid #fecaca; }
    .dispute-reason-title { font-size: 13px; font-weight: 700; color: #991b1b; margin-bottom: 5px; }
    .dispute-reason-text { font-size: 14px; color: #1e1b3a; line-height: 1.6; }
    .order-meta { font-size: 13px; color: #6b7280; line-height: 1.7; }
    .proof-img { width: 110px; height: 110px; border-radius: 10px; object-fit: cover; cursor: zoom-in; border: 1.5px solid #fecaca; transition: opacity .2s; }
    .proof-img:hover { opacity: .85; }
    .dispute-actions { flex: 1; border-left: 1.5px solid #f5cdd8; padding-left: 22px; display: flex; flex-direction: column; gap: 10px; }
    .dispute-note { width: 100%; border: 1.5px solid #e8e4f3; border-radius: 10px; padding: 10px 12px; font-family: inherit; font-size: 13px; resize: vertical; min-height: 80px; outline: none; }
    .dispute-note:focus { border-color: #e8546a; }
    .btn-dispute-refund { padding: 11px; border: none; border-radius: 10px; font: inherit; font-size: 13px; font-weight: 700; background: linear-gradient(135deg,#e8546a,#f43f5e); color: #fff; cursor: pointer; transition: opacity .2s; }
    .btn-dispute-refund:hover { opacity: .88; }
    .btn-dispute-reject { padding: 11px; border: none; border-radius: 10px; font: inherit; font-size: 13px; font-weight: 700; background: linear-gradient(135deg,#10b981,#059669); color: #fff; cursor: pointer; transition: opacity .2s; }
    .btn-dispute-reject:hover { opacity: .88; }

    /* ===== RESOLVED TABLE ===== */
    .resolved-table-wrap { background: #fff; border: 1.5px solid #e8e4f3; border-radius: 16px; overflow: hidden; }
    .resolved-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .resolved-table thead { background: #f9f8ff; }
    .resolved-table th { padding: 12px 16px; text-align: left; font-weight: 700; color: #5b21b6; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
    .resolved-table td { padding: 14px 16px; border-bottom: 1px solid #f3f0fd; color: #374151; }
    .resolved-table tr:last-child td { border-bottom: none; }
    .resolved-table tr:hover td { background: #faf9ff; }
    .res-badge { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    .res-badge.refunded { background: #dcfce7; color: #166534; }
    .res-badge.rejected { background: #fee2e2; color: #991b1b; }

    /* ===== SOP SECTION ===== */
    .sop-section {
        background: linear-gradient(135deg, #134e4a, #0f766e);
        border-radius: 18px; padding: 28px 32px;
        display: flex; align-items: center; gap: 24px;
        margin-top: 32px; flex-wrap: wrap;
    }
    .sop-logo { font-size: 24px; flex-shrink: 0; }
    .sop-body { flex: 1; min-width: 0; }
    .sop-tag { font-size: 11px; font-weight: 700; letter-spacing: .1em; color: #5eead4; text-transform: uppercase; margin-bottom: 4px; }
    .sop-title { font-size: 18px; font-weight: 800; color: #fff; margin-bottom: 6px; }
    .sop-desc { font-size: 13px; color: #ccfbf1; line-height: 1.6; }
    .sop-btn {
        padding: 12px 22px; background: #fff; color: #0f766e;
        border: none; border-radius: 12px; font: inherit; font-size: 13px;
        font-weight: 800; cursor: pointer; white-space: nowrap;
        flex-shrink: 0; text-decoration: none; display: inline-block;
        transition: opacity .2s;
    }
    .sop-btn:hover { opacity: .88; }

    /* ===== EMPTY STATE ===== */
    .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
    .empty-icon { font-size: 52px; margin-bottom: 14px; }
    .empty-state h3 { font-size: 16px; font-weight: 700; color: #374151; margin-bottom: 6px; }

    /* ===== IMAGE MODAL ===== */
    .img-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.88); z-index: 3000; cursor: zoom-out; align-items: center; justify-content: center; }
    .img-modal.open { display: flex; }
    .img-modal img { max-width: 92vw; max-height: 88vh; border-radius: 10px; object-fit: contain; }
    .img-modal-close { position: absolute; top: 16px; right: 24px; color: #fff; font-size: 38px; cursor: pointer; line-height: 1; opacity: .8; }
    .img-modal-close:hover { opacity: 1; }

    /* ===== TOAST ===== */
    .toast-msg { display: none; position: fixed; bottom: 28px; right: 28px; border-radius: 12px; padding: 14px 22px; font-size: 14px; font-weight: 700; z-index: 9999; box-shadow: 0 8px 32px rgba(0,0,0,.2); max-width: 380px; }
    .toast-msg.show { display: block; animation: fadeup .3s; }
    @keyframes fadeup { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }

    /* ===== FLASH ===== */
    .flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; font-weight: 600; }
    .flash.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .flash.info    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 900px) {
        .rc-inner { flex-direction: column; }
        .rc-panel { width: 100%; border-left: none; border-top: 1.5px solid #f0ebff; }
        .rc-parties { grid-template-columns: 1fr; }
        .dispute-card { flex-direction: column; }
        .dispute-actions { border-left: none; border-top: 1.5px solid #f5cdd8; padding-left: 0; padding-top: 14px; }
        .dsp-stats { width: 100%; }
        .dsp-stat-box { flex: 1; }
    }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
<div class="admin-main-wrap">
<main>
<div class="dsp-wrap">

    <!-- ===== PAGE HEADER ===== -->
    <div class="dsp-header">
        <div class="dsp-header-left">
            <div class="dsp-breadcrumb">
                <a href="/admin/index.php">หน้าหลัก</a> /
                <a href="#">ผู้จัดการ (Admin)</a> /
                <span>ข้อพิพาทและคำขอคืนสินค้า (Disputes &amp; Returns)</span>
            </div>
            <div class="dsp-title">
                <div class="dsp-title-icon">&#9888;</div>
                Disputes &amp; คำขอคืนสินค้า
            </div>
            <div class="dsp-subtitle">พิจารณาข้อพิพาทและคำขอส่งคืนสินค้า เพื่อการตัดสินใจและคุ้มครองผู้ซื้อ-ผู้ขายผ่านระบบ Escrow</div>
        </div>
        <div class="dsp-stats">
            <div class="dsp-stat-box">
                <div class="dsp-stat-label">ยอดรวมจัดเก็บใน Escrow</div>
                <div class="dsp-stat-value green">&#3647;<?= number_format($escrowTotal, 2) ?></div>
            </div>
            <div class="dsp-stat-box">
                <div class="dsp-stat-label">คำร้องดำเนินการ</div>
                <div class="dsp-stat-value"><?= count($returnRequests) + count($disputes) ?> รายการ</div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['flash_msg'])): $f = $_SESSION['flash_msg']; unset($_SESSION['flash_msg']); ?>
    <div class="flash <?= $f['type'] ?>"><?= htmlspecialchars($f['text']) ?></div>
    <?php endif; ?>

    <!-- ===== TAB NAV ===== -->
    <div class="tab-nav" id="tabNav" role="tablist">
        <button class="tab-btn" id="tab-disputes-btn" onclick="switchTab('disputes')" role="tab">
            &#9878; Dispute Tickets
            <?php if ($disputes): ?><span class="tab-badge red"><?= count($disputes) ?></span><?php else: ?><span class="tab-badge">0</span><?php endif; ?>
        </button>
        <button class="tab-btn" id="tab-returns-btn" onclick="switchTab('returns')" role="tab">
            &#128230; คำขอคืนสินค้า &mdash; รอพิจารณา
            <?php if ($returnRequests): ?><span class="tab-badge amber"><?= count($returnRequests) ?></span><?php else: ?><span class="tab-badge">0</span><?php endif; ?>
        </button>
        <button class="tab-btn" id="tab-resolved-btn" onclick="switchTab('resolved')" role="tab">
            &#10003; ประวัติการตัดสินแยกเงินแล้ว (Resolved)
        </button>
    </div>

    <!-- ============================= TAB: DISPUTES ============================= -->
    <div class="tab-content" id="tab-disputes" role="tabpanel">
        <?php if (empty($disputes)): ?>
        <div class="empty-state"><div class="empty-icon">&#10003;</div><h3>ไม่มีข้อพิพาทที่รอจัดการ</h3><p>ทุกอย่างเรียบร้อยดี</p></div>
        <?php else: ?>
        <div class="dispute-list">
            <?php foreach ($disputes as $d): ?>
            <div class="dispute-card">
                <div class="dispute-info">
                    <div style="font-size:15px;font-weight:700;color:#1e1b3a;">ออเดอร์: <?= htmlspecialchars($d['order_number']) ?></div>
                    <div class="order-meta">
                        ผู้เปิดข้อพิพาท: <?= htmlspecialchars($d['shipping_name'] ?? '') ?> (<?= htmlspecialchars($d['user_email']) ?>)<br>
                        วันที่เปิด: <?= htmlspecialchars($d['created_at']) ?> &nbsp;|&nbsp; ยอดเงิน: <strong>&#3647;<?= number_format((float)$d['total_amount'], 2) ?></strong>
                    </div>
                    <div class="dispute-reason-box">
                        <div class="dispute-reason-title">สาเหตุข้อพิพาท:</div>
                        <div class="dispute-reason-text"><?= nl2br(htmlspecialchars($d['reason'])) ?></div>
                    </div>
                    <?php if (!empty($d['proof_image'])): ?>
                    <div>
                        <div style="font-size:12px;color:#6b7280;margin-bottom:6px;">&#128247; หลักฐาน (คลิกขยาย):</div>
                        <img src="<?= htmlspecialchars($d['proof_image']) ?>" alt="Proof" class="proof-img" onclick="openImgModal(this.src)">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="dispute-actions">
                    <form method="POST" onsubmit="return confirm('ยืนยันการตัดสิน?');" style="display:flex;flex-direction:column;gap:10px;height:100%;">
                        <input type="hidden" name="dispute_id" value="<?= $d['dispute_id'] ?>">
                        <input type="hidden" name="order_id"   value="<?= $d['order_id'] ?>">
                        <div style="font-size:13px;font-weight:700;color:#374151;">การตัดสินของ Super Admin:</div>
                        <textarea name="admin_note" class="dispute-note" placeholder="บันทึกข้อความ (เหตุผลในการตัดสิน)..." required></textarea>
                        <div style="margin-top:auto;display:flex;flex-direction:column;gap:8px;">
                            <button type="submit" name="action" value="refund" class="btn-dispute-refund">&#10006; ยกเลิกออเดอร์และคืนเงินให้ผู้ซื้อ</button>
                            <button type="submit" name="action" value="reject" class="btn-dispute-reject">&#10003; ปฏิเสธข้อพิพาท (โอนให้ผู้ขาย)</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================= TAB: RETURN REQUESTS ============================= -->
    <div class="tab-content" id="tab-returns" role="tabpanel">

        <!-- Filter Bar -->
        <form class="filter-bar" method="get" action="">
            <input type="hidden" name="tab" value="returns">
            <input class="filter-search" type="text" name="search"
                   value="<?= htmlspecialchars($filterSearch) ?>"
                   placeholder="ค้นหา #เลขออเดอร์ / เลขพัสดุ / อีเมล">
            <select class="filter-select" name="reason">
                <option value="">สินค้าทุกประเภท</option>
                <option value="damaged"          <?= $filterReason==='damaged'?'selected':'' ?>>สินค้าชำรุด / เสียหาย</option>
                <option value="not_as_described" <?= $filterReason==='not_as_described'?'selected':'' ?>>สินค้าไม่ตรงโฆษณา</option>
                <option value="wrong_item"       <?= $filterReason==='wrong_item'?'selected':'' ?>>ได้รับสินค้าผิดรายการ</option>
            </select>
            <button class="filter-btn" type="submit">&#128269; ค้นหา</button>
            <a href="?tab=returns" class="filter-refresh" title="รีเซ็ต">&#8635;</a>
        </form>

        <?php if (empty($returnRequests)): ?>
        <div class="empty-state">
            <div class="empty-icon">&#128235;</div>
            <h3>ไม่มีคำขอคืนสินค้าที่รอพิจารณา</h3>
            <p>เมื่อลูกค้ายื่นคำขอ จะปรากฏที่นี่พร้อมหลักฐาน</p>
        </div>
        <?php else: ?>
        <div class="return-list" id="return-list">
            <?php foreach ($returnRequests as $r):
                $ri         = $reasonMap[$r['reason']] ?? ['label'=>$r['reason'],'icon'=>'📌','color'=>'#374151'];
                $buyerInitial = mb_strtoupper(mb_substr($r['buyer_name'] ?? 'U', 0, 1));
                $sellerInitial= mb_strtoupper(mb_substr($r['seller_name'] ?? 'S', 0, 1));
                $created = strtotime($r['created_at'] ?? 'now');
                $diffMin = round((time() - $created) / 60);
                $diffLabel = $diffMin < 60 ? $diffMin.' นาทีที่แล้ว' : round($diffMin/60).' ชั่วโมงที่แล้ว';
            ?>
            <div class="return-card" id="rcard-<?= (int)$r['return_id'] ?>">
                <div class="rc-strip"></div>
                <div class="rc-inner">

                    <!-- LEFT MAIN -->
                    <div class="rc-main">
                        <!-- Order row -->
                        <div class="rc-order-row">
                            <span class="rc-order-num">#<?= htmlspecialchars($r['order_number'] ?? $r['order_id']) ?></span>
                            <span class="rc-badge super">&#128680; ร้อง Super Admin พิจารณา</span>
                            <span class="rc-time">&#128344; <?= date('d/m/Y H:i', $created) ?> &nbsp;&middot;&nbsp; <?= $diffLabel ?></span>
                        </div>

                        <!-- Parties -->
                        <div class="rc-parties">
                            <div class="rc-party-box">
                                <div class="rc-avatar buyer"><?= htmlspecialchars($buyerInitial) ?></div>
                                <div class="rc-party-info">
                                    <div class="rc-party-label">ข้อมูลผู้ซื้อ / ผู้ซื้อ:</div>
                                    <div class="rc-party-name"><?= htmlspecialchars($r['buyer_name'] ?? 'ลูกค้า') ?></div>
                                    <div class="rc-party-sub"><?= htmlspecialchars($r['buyer_email'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="rc-party-box">
                                <div class="rc-avatar seller"><?= htmlspecialchars($sellerInitial) ?></div>
                                <div class="rc-party-info">
                                    <div class="rc-party-label">ร้านค้า/สำนักพิมพ์:</div>
                                    <div class="rc-party-name"><?= htmlspecialchars($r['seller_name'] ?? ($r['seller_type'].' #'.$r['seller_id'])) ?></div>
                                    <?php if (!empty($r['seller_verified'])): ?>
                                    <span class="rc-badge verified" style="font-size:10px;padding:1px 7px;">&#10003; Verified Store</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div class="rc-reason-box <?= htmlspecialchars($r['reason']) ?>">
                            <div class="rc-reason-title" style="color:<?= $ri['color'] ?>">
                                <?= $ri['icon'] ?> สาเหตุการคืนสินค้า: <?= htmlspecialchars($ri['label']) ?>
                                <span style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;">(<?= strtoupper(str_replace('_',' ',$r['reason'])) ?>)</span>
                            </div>
                        </div>

                        <!-- Tracking -->
                        <div class="rc-tracking">
                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#6b7280" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                            <span style="font-size:12px;color:#6b7280;">เลขพัสดุที่ส่งคืน:</span>
                            <span class="rc-tracking-num"><?= htmlspecialchars($r['return_tracking']) ?></span>
                            <div class="rc-tracking-status">
                                <span style="width:7px;height:7px;background:#059669;border-radius:50%;display:inline-block;"></span>
                                พัสดุถูกส่งแล้ว &mdash; รอตรวจสอบ
                            </div>
                        </div>

                        <!-- Evidence -->
                        <?php
                            // Build correct URL: uploads/returns/file.jpg → /pages/uploads/returns/file.jpg
                            $ev = $r['evidence_url'] ?? '';
                            $evUrl = '';
                            if ($ev) {
                                $ev = ltrim(str_replace('\\','/',$ev),'/');
                                $evUrl = str_starts_with($ev,'uploads/') ? '/pages/'.$ev : '/'.$ev;
                            }
                        ?>
                        <?php if ($evUrl): ?>
                        <div class="rc-evidence-section">
                            <div class="rc-evidence-header">
                                <div class="rc-evidence-label">&#128247; รูปหลักฐาน (คลิกเพื่อขยาย)</div>
                                <a href="<?= htmlspecialchars($evUrl) ?>" download target="_blank" class="rc-evidence-dl">&#11015; ดาวน์โหลด</a>
                            </div>
                            <div class="rc-evidence-grid">
                                <img src="<?= htmlspecialchars($evUrl) ?>"
                                     alt="หลักฐาน"
                                     class="rc-evidence-thumb"
                                     onclick="openImgModal(this.src)"
                                     onerror="this.onerror=null;this.src='';this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div style="display:none;width:90px;height:90px;border-radius:10px;background:#f5f3ff;border:1.5px dashed #c4b5fd;align-items:center;justify-content:center;flex-direction:column;gap:4px;">
                                    <span style="font-size:22px;">&#128247;</span>
                                    <span style="font-size:10px;color:#9ca3af;text-align:center;">โหลดรูปไม่ได้</span>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="rc-evidence-section">
                            <div class="rc-evidence-label" style="color:#9ca3af;">&#128247; ไม่มีรูปหลักฐานแนบ</div>
                        </div>
                        <?php endif; ?>

                        <!-- Product summary -->
                        <div class="rc-product">
                            <div class="rc-product-cover placeholder">&#128218;</div>
                            <div>
                                <div class="rc-product-name">คำสั่งซื้อ #<?= htmlspecialchars($r['order_number'] ?? $r['order_id']) ?></div>
                                <div class="rc-product-meta">ชำระผ่าน PromptPay &nbsp;&middot;&nbsp; จัดเก็บโดย Escrow</div>
                            </div>
                            <div class="rc-product-price">&#3647;<?= number_format((float)$r['refund_amount'], 2) ?></div>
                        </div>
                    </div><!-- /.rc-main -->

                    <!-- RIGHT PANEL -->
                    <div class="rc-panel">
                        <!-- Escrow amount -->
                        <div class="rp-escrow-box">
                            <div class="rp-escrow-label">ยอดเงินจัดเก็บ (ESCROW HOLD)</div>
                            <div class="rp-escrow-amt">&#3647;<?= number_format((float)$r['refund_amount'], 2) ?></div>
                        </div>

                        <!-- Section title -->
                        <div class="rp-section-title">&#9679; การพิจารณาของ Super Admin:</div>

                        <!-- Warning -->
                        <div class="rp-warning">
                            <strong>เมื่ออนุมัติ</strong> แอดมินต้องสั่งให้ระบบนำเงินจาก Escrow คืนให้ผู้ซื้อ โดยยืนยันว่าร้านค้าได้รับสินค้าคืนก่อนโอนเงิน
                        </div>

                        <!-- Remarks -->
                        <div>
                            <div style="font-size:12px;font-weight:700;color:#4b5563;margin-bottom:6px;">หมายเหตุสำหรับการตัดสิน (Decision Remarks):</div>
                            <textarea class="rp-note"
                                      id="note-<?= (int)$r['return_id'] ?>"
                                      maxlength="500"
                                      placeholder="หมายเหตุถึงลูกค้า เช่น กรุณาส่งสินค้าคืนภายใน 7 วัน หรือ ยืนยันหลักฐานจากร้านค้าแล้ว... (ไม่บังคับ)"
                                      oninput="updateCounter(this, 'ctr-<?= (int)$r['return_id'] ?>')"></textarea>
                            <div class="rp-note-counter"><span id="ctr-<?= (int)$r['return_id'] ?>">0</span>/500 ตัวอักษร</div>
                        </div>

                        <!-- Quick inserts -->
                        <div>
                            <div style="font-size:11px;font-weight:700;color:#9ca3af;margin-bottom:6px;">ข้อความด่วนสำเร็จรูป:</div>
                            <div class="rp-quick-inserts">
                                <span class="rp-insert-chip" onclick="insertNote('note-<?= (int)$r['return_id'] ?>', 'ตรวจสอบหลักฐานครบถ้วน')">+ ตรวจสอบหลักฐานครบถ้วน</span>
                                <span class="rp-insert-chip" onclick="insertNote('note-<?= (int)$r['return_id'] ?>', 'สินค้าอยู่ในสภาพชำรุดจากการขนส่ง')">+ สินค้าอยู่ในสภาพชำรุดจากการขนส่ง</span>
                                <span class="rp-insert-chip" onclick="insertNote('note-<?= (int)$r['return_id'] ?>', 'ขอหลักฐานเพิ่มเติม')">+ ขอหลักฐานเพิ่มเติม</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:auto;">
                            <button class="btn-approve-r"
                                    id="btn-approve-<?= (int)$r['return_id'] ?>"
                                    onclick="reviewReturn(<?= (int)$r['return_id'] ?>, 'approve')">
                                &#10003; อนุมัติ &mdash; ส่งให้ร้านค้ายืนยัน
                            </button>
                            <button class="btn-reject-r"
                                    id="btn-reject-<?= (int)$r['return_id'] ?>"
                                    onclick="reviewReturn(<?= (int)$r['return_id'] ?>, 'reject')">
                                &#10007; ปฏิเสธคำขอ
                            </button>
                            <button class="btn-info-r"
                                    onclick="insertNote('note-<?= (int)$r['return_id'] ?>', 'ขอหลักฐานเพิ่มเติม (Request More Info): ')">
                                &#9711; ขอหลักฐานเพิ่มเติม (Request More Info)
                            </button>
                        </div>

                        <div class="rp-audit-note">บันทึกการตัดสินทุกครั้งอยู่ใน Admin Audit Log ตลอดเวลา</div>
                    </div><!-- /.rc-panel -->

                </div><!-- /.rc-inner -->
            </div><!-- /.return-card -->
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================= TAB: RESOLVED ============================= -->
    <div class="tab-content" id="tab-resolved" role="tabpanel">
        <?php if (empty($resolvedDisputes)): ?>
        <div class="empty-state"><div class="empty-icon">&#128203;</div><h3>ยังไม่มีประวัติการตัดสิน</h3></div>
        <?php else: ?>
        <div class="resolved-table-wrap">
            <table class="resolved-table">
                <thead>
                    <tr>
                        <th>ออเดอร์</th>
                        <th>ลูกค้า</th>
                        <th>ยอดเงิน</th>
                        <th>ผลตัดสิน</th>
                        <th>หมายเหตุ</th>
                        <th>วันที่</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resolvedDisputes as $d): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($d['order_number']) ?></strong></td>
                    <td style="font-size:12px;"><?= htmlspecialchars($d['user_email']) ?></td>
                    <td><strong>&#3647;<?= number_format((float)$d['total_amount'], 2) ?></strong></td>
                    <td>
                        <span class="res-badge <?= $d['resolution'] === 'refunded' ? 'refunded' : 'rejected' ?>">
                            <?= $d['resolution'] === 'refunded' ? '&#9166; คืนเงินแล้ว' : '&#10007; ปฏิเสธ' ?>
                        </span>
                    </td>
                    <td style="font-size:12px;color:#6b7280;max-width:200px;"><?= htmlspecialchars(mb_strimwidth($d['admin_note'] ?? '', 0, 60, '...')) ?></td>
                    <td style="font-size:12px;color:#9ca3af;"><?= date('d/m/Y H:i', strtotime($d['updated_at'] ?? $d['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- ===== SOP SECTION ===== -->
    <div class="sop-section">
        <div class="sop-logo">&#128737;</div>
        <div class="sop-body">
            <div class="sop-tag">Wanawan Standard Operating Procedure (SOP)</div>
            <div class="sop-title">แนวทางปฏิบัติการตัดสินข้อพิพาท Wanawan Escrow</div>
            <div class="sop-desc">การพิจารณาคดีสินค้าต้องอิงหลักฐานและตรวจสอบภาพถ่ายสภาพสินค้าจริงเป็นสำคัญ หากตรวจสอบพบว่าสินค้าชำรุดก่อนส่ง หรือระหว่างขนส่ง ระบบ Escrow จะทำการรักษาเงินและคืนเงินให้กับผู้ซื้อเต็มจำนวน 100% ภายในระยะเวลา 24-48 ชั่วโมง</div>
        </div>
        <a href="/admin/escrow.php" class="sop-btn">อ่านคู่มือระเบียบ Escrow &#8594;</a>
    </div>

</div><!-- /.dsp-wrap -->
</main>
</div>

<!-- Image Modal -->
<div class="img-modal" id="imgModal" onclick="closeImgModal()">
    <span class="img-modal-close" onclick="closeImgModal()">&times;</span>
    <img id="imgModalSrc" src="" alt="หลักฐาน" onclick="event.stopPropagation()">
</div>

<div class="toast-msg" id="toast" role="alert"></div>

<script>
// ── Tab ──
var INITIAL_TAB = '<?= htmlspecialchars($activeTab) ?>';
function switchTab(tab) {
    ['disputes','returns','resolved'].forEach(function(t) {
        document.getElementById('tab-'+t).classList.toggle('active', t===tab);
        document.getElementById('tab-'+t+'-btn').classList.toggle('active', t===tab);
    });
    history.replaceState(null,'','?tab='+tab);
}
(function(){
    var hash = location.hash.replace('#','');
    var tab  = hash || INITIAL_TAB || 'returns';
    switchTab(tab);
})();

// ── Image Modal ──
function openImgModal(src) {
    document.getElementById('imgModalSrc').src = src;
    document.getElementById('imgModal').classList.add('open');
}
function closeImgModal() { document.getElementById('imgModal').classList.remove('open'); }

// ── Quick insert ──
function insertNote(id, text) {
    var ta = document.getElementById(id);
    if (!ta) return;
    ta.value = (ta.value ? ta.value + ' ' : '') + text;
    ta.focus();
    var ctrId = id.replace('note-','ctr-');
    updateCounter(ta, ctrId);
}
function updateCounter(ta, ctrId) {
    var el = document.getElementById(ctrId);
    if (el) el.textContent = ta.value.length;
}

// ── Return Request Review ──
var CSRF = <?= json_encode($_SESSION['superadmin_return_csrf']) ?>;

function reviewReturn(returnId, action) {
    var msg = action === 'approve'
        ? 'ยืนยันการอนุมัติ? คำขอจะถูกส่งไปให้ร้านค้ายืนยันรับสินค้าคืน'
        : 'ยืนยันการปฏิเสธคำขอนี้?';
    if (!confirm(msg)) return;

    var note       = (document.getElementById('note-'+returnId)||{value:''}).value.trim();
    var btnApprove = document.getElementById('btn-approve-'+returnId);
    var btnReject  = document.getElementById('btn-reject-'+returnId);

    [btnApprove, btnReject].forEach(function(b){ if(b){ b.disabled=true; b.textContent='กำลังดำเนินการ...'; }});

    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('return_id',  returnId);
    fd.append('action',     action);
    fd.append('admin_note', note);

    fetch('/api/superadmin_review_return.php', { method:'POST', body:fd })
        .then(function(res){ if(!res.ok) throw new Error('HTTP '+res.status); return res.json(); })
        .then(function(data){
            if (data.ok) {
                showToast(data.message, false);
                var card = document.getElementById('rcard-'+returnId);
                if (card) {
                    card.style.transition = 'opacity .4s, transform .4s';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(24px)';
                    setTimeout(function(){
                        card.remove();
                        var cnt = document.querySelectorAll('.return-card').length;
                        var badge = document.querySelector('#tab-returns-btn .tab-badge');
                        if (badge) badge.textContent = cnt > 0 ? cnt : '0';
                        if (cnt === 0) {
                            var list = document.getElementById('return-list');
                            if (list) list.innerHTML = '<div class="empty-state"><div class="empty-icon">&#128235;</div><h3>ไม่มีคำขอคืนสินค้าที่รอพิจารณา</h3></div>';
                        }
                    }, 440);
                }
            } else {
                showToast(data.message || 'เกิดข้อผิดพลาด', true);
                resetBtns(returnId);
            }
        })
        .catch(function(){
            showToast('เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ กรุณาลองใหม่', true);
            resetBtns(returnId);
        });
}

function resetBtns(returnId) {
    var btnA = document.getElementById('btn-approve-'+returnId);
    var btnR = document.getElementById('btn-reject-'+returnId);
    [btnA, btnR].forEach(function(b){ if(b) b.disabled = false; });
    if (btnA) btnA.textContent = '\u2713 อนุมัติ \u2014 ส่งให้ร้านค้ายืนยัน';
    if (btnR) btnR.textContent = '\u2717 ปฏิเสธคำขอ';
}

function showToast(msg, isError) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.style.color = '#fff';
    t.style.background = isError ? '#dc2626' : '#059669';
    t.classList.add('show');
    setTimeout(function(){ t.classList.remove('show'); }, 4500);
}
</script>
<?php require_once __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
