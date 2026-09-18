<?php
session_start();
require_once __DIR__ . '/../config/config.php';
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: /pages/login.php'); exit;
}
if (empty($_SESSION['superadmin_return_csrf'])) {
    $_SESSION['superadmin_return_csrf'] = bin2hex(random_bytes(24));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['dispute_id'], $_POST['order_id'])) {
    $action = $_POST['action']; $dispute_id = (int)$_POST['dispute_id']; $order_id = (int)$_POST['order_id']; $admin_note = $_POST['admin_note'] ?? '';
    if ($action === 'refund') {
        $pdo->prepare("UPDATE disputes SET status='resolved',resolution='refunded',admin_note=? WHERE dispute_id=?")->execute([$admin_note,$dispute_id]);
        $pdo->prepare("UPDATE orders SET payout_status='refunded',shipping_status='returned' WHERE order_id=?")->execute([$order_id]);
        $on = $pdo->query("SELECT order_number FROM orders WHERE order_id=".(int)$order_id)->fetchColumn() ?: '#'.$order_id;
        logAdminAction($pdo,$_SESSION['email']??'unknown','RESOLVE_DISPUTE_REFUND',(string)$dispute_id,"คืนเงินลูกค้า ออเดอร์ {$on}. หมายเหตุ: {$admin_note}");
        $_SESSION['flash_msg'] = ['type'=>'success','text'=>'บันทึกการคืนเงินให้ลูกค้าเรียบร้อยแล้ว'];
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE disputes SET status='resolved',resolution='rejected',admin_note=? WHERE dispute_id=?")->execute([$admin_note,$dispute_id]);
        $pdo->prepare("UPDATE orders SET payout_status='pending' WHERE order_id=?")->execute([$order_id]);
        $on = $pdo->query("SELECT order_number FROM orders WHERE order_id=".(int)$order_id)->fetchColumn() ?: '#'.$order_id;
        logAdminAction($pdo,$_SESSION['email']??'unknown','RESOLVE_DISPUTE_REJECT',(string)$dispute_id,"ปฏิเสธข้อพิพาท ออเดอร์ {$on}. หมายเหตุ: {$admin_note}");
        $_SESSION['flash_msg'] = ['type'=>'info','text'=>'ปฏิเสธข้อพิพาทเรียบร้อย'];
    }
    header("Location: disputes.php?tab=all"); exit;
}
$activeTab = $_GET['tab'] ?? 'pending'; $filterSearch = trim($_GET['search'] ?? ''); $filterReason = $_GET['reason'] ?? '';
$validTabs = ['pending','reviewed','decided','rejected','all'];
if (!in_array($activeTab, $validTabs, true)) $activeTab = 'pending';
$tabStatusMap = [
    'pending'=>['pending_super_admin'],
    'reviewed'=>['waiting_for_shop','shop_received'],
    'decided'=>['refunded_to_wallet'],
    'rejected'=>['rejected'],
    'all'=>['pending_super_admin','waiting_for_shop','shop_received','refunded_to_wallet','rejected'],
];
$sf = $tabStatusMap[$activeTab]; $ph = implode(',', array_fill(0, count($sf), '?'));
$rWhere = "1=1 AND rr.status IN ($ph)"; $rParams = $sf;
if ($filterReason) { $rWhere .= ' AND rr.reason = ?'; $rParams[] = $filterReason; }
if ($filterSearch) { $rWhere .= ' AND (o.order_number LIKE ? OR rr.return_tracking LIKE ? OR u.email LIKE ?)'; $rParams[] = '%'.$filterSearch.'%'; $rParams[] = '%'.$filterSearch.'%'; $rParams[] = '%'.$filterSearch.'%'; }
$rStmt = $pdo->prepare("SELECT rr.*, o.order_number, o.total_amount, COALESCE(NULLIF(u.display_name,''), u.email) buyer_name, u.email buyer_email, COALESCE(s.shop_name, p.company_name) seller_name FROM return_requests rr LEFT JOIN orders o ON o.order_id=rr.order_id LEFT JOIN users u ON u.user_id=rr.user_id LEFT JOIN shops s ON rr.seller_type='shop' AND s.shop_id=rr.seller_id LEFT JOIN publishers p ON rr.seller_type='publisher' AND p.publisher_id=rr.seller_id WHERE $rWhere ORDER BY rr.created_at DESC");
$rStmt->execute($rParams); $returnRequests = $rStmt->fetchAll(PDO::FETCH_ASSOC);
$disputes = [];
if (in_array($activeTab,['pending','all'],true)) { $disputes = $pdo->query("SELECT d.*, o.order_number, o.total_amount, o.shipping_name, u.email AS user_email FROM disputes d JOIN orders o ON d.order_id=o.order_id JOIN users u ON d.user_id=u.user_id WHERE d.status='open' ORDER BY d.created_at ASC")->fetchAll(PDO::FETCH_ASSOC); }
$resolvedDisputes = [];
if (in_array($activeTab,['decided','all'],true)) { $resolvedDisputes = $pdo->query("SELECT d.*, o.order_number, o.total_amount, u.email AS user_email FROM disputes d JOIN orders o ON d.order_id=o.order_id JOIN users u ON d.user_id=u.user_id WHERE d.status='resolved' ORDER BY d.updated_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); }
$statsRow = $pdo->query("SELECT SUM(status='pending_super_admin') cnt_pending, SUM(status IN ('waiting_for_shop','shop_received')) cnt_reviewed, SUM(status='refunded_to_wallet') cnt_decided, SUM(status='rejected') cnt_rejected, COUNT(*) cnt_all FROM return_requests")->fetch(PDO::FETCH_ASSOC);
$openDisputeCount = (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status='open'")->fetchColumn();
$escrowTotal = (float)$pdo->query("SELECT COALESCE(SUM(refund_amount),0) FROM return_requests WHERE status IN ('pending_super_admin','waiting_for_shop','shop_received')")->fetchColumn();
$reasonMap=['damaged'=>['label'=>'สินค้าชำรุด/เสียหาย','icon'=>'<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>','color'=>'#dc2626'],'not_as_described'=>['label'=>'สินค้าไม่ตรงตามโฆษณา','icon'=>'<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>','color'=>'#d97706'],'wrong_item'=>['label'=>'ได้รับสินค้าผิดรายการ','icon'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>','color'=>'#2563eb']];
$statusLabelMap=['pending_super_admin'=>['label'=>'รอพิจารณา','cls'=>'badge-pending'],'waiting_for_shop'=>['label'=>'รอร้านค้ายืนยัน','cls'=>'badge-waiting'],'shop_received'=>['label'=>'ร้านค้ารับแล้ว','cls'=>'badge-received'],'refunded_to_wallet'=>['label'=>'คืนเงินแล้ว','cls'=>'badge-refunded'],'rejected'=>['label'=>'ปฏิเสธแล้ว','cls'=>'badge-rejected']];
$active_menu = 'disputes';
?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>คำขอคืนสินค้า — วนวรรณ Admin</title>
<meta name="description" content="พิจารณาคำขอคืนสินค้าและข้อพิพาท">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/styles.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans Thai',var(--font-family,system-ui),sans-serif;background:#f4f6fb;color:#1a1a2e}
.dsp-wrap{max-width:1280px;margin:0 auto;padding:0 28px 60px}
.dsp-header{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:20px;padding:28px 0 22px;border-bottom:1.5px solid #e5e7eb;margin-bottom:0}
.dsp-breadcrumb{font-size:12px;color:#9ca3af;margin-bottom:8px}
.dsp-breadcrumb a{color:#115e59;text-decoration:none}
.dsp-title{font-size:26px;font-weight:800;color:#1e1b3a;display:flex;align-items:center;gap:10px}
.dsp-title-icon{width:36px;height:36px;background:#ccfbf1;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px}
.dsp-subtitle{font-size:13px;color:#6b7280;margin-top:5px;line-height:1.5}
.dsp-stats{display:flex;gap:16px;flex-wrap:wrap}
.dsp-stat-box{background:#fff;border:1.5px solid #e5e7eb;border-radius:14px;padding:14px 22px;text-align:right;min-width:140px}
.dsp-stat-label{font-size:11px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px}
.dsp-stat-value{font-size:22px;font-weight:800;color:#1e1b3a}
.dsp-stat-value.green{color:#059669}
.tab-nav{display:flex;border-bottom:2px solid #e5e7eb;margin:6px 0 24px;overflow-x:auto;scrollbar-width:none}
.tab-nav::-webkit-scrollbar{display:none}
.tab-btn{padding:14px 20px;font-size:13.5px;font-weight:700;color:#9ca3af;background:none;border:none;border-bottom:3px solid transparent;margin-bottom:-2px;cursor:pointer;transition:all .2s;font-family:inherit;display:inline-flex;align-items:center;gap:7px;white-space:nowrap;flex-shrink:0;text-decoration:none}
.tab-btn.active{color:#115e59;border-bottom-color:#115e59}
.tab-btn:hover:not(.active){color:#6b7280}
.tab-badge{font-size:11px;font-weight:800;border-radius:20px;padding:2px 9px;line-height:1.5;background:#e5e7eb;color:#115e59}
.tab-badge.red{background:#fee2e2;color:#dc2626}
.tab-badge.amber{background:#fef3c7;color:#d97706}
.tab-badge.green{background:#dcfce7;color:#166534}
.tab-badge.gray{background:#f3f4f6;color:#6b7280}
.filter-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:20px;background:#fff;border:1.5px solid #e5e7eb;border-radius:14px;padding:12px 18px}
.filter-search{flex:1;min-width:180px;height:38px;border:1.5px solid #e5e7eb;border-radius:10px;padding:0 14px 0 38px;font:inherit;font-size:13px;outline:none;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' stroke='%239ca3af' stroke-width='2' viewBox='0 0 24 24'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E") no-repeat 12px center #fafafa;transition:border-color .2s}
.filter-search:focus{border-color:#115e59;background-color:#fff}
.filter-select{height:38px;border:1.5px solid #e5e7eb;border-radius:10px;padding:0 32px 0 12px;font:inherit;font-size:13px;outline:none;background:#fafafa url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' fill='none' stroke='%236b7280' stroke-width='2' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4'/%3E%3C/svg%3E") no-repeat right 10px center;appearance:none;cursor:pointer;color:#374151;transition:border-color .2s}
.filter-select:focus{border-color:#115e59}
.filter-btn{height:38px;padding:0 18px;border:1.5px solid #115e59;border-radius:10px;font:inherit;font-size:13px;font-weight:700;background:#115e59;color:#fff;cursor:pointer;transition:opacity .2s;white-space:nowrap}
.filter-btn:hover{opacity:.88}
.filter-refresh{width:38px;height:38px;border:1.5px solid #e5e7eb;border-radius:10px;background:#fafafa;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#6b7280;font-size:16px;transition:all .2s;flex-shrink:0;text-decoration:none}
.filter-refresh:hover{border-color:#115e59;color:#115e59}
.return-list{display:flex;flex-direction:column;gap:20px}
.return-card{background:#fff;border:1.5px solid #e5e7eb;border-radius:18px;overflow:hidden;box-shadow:0 2px 16px rgba(124,58,237,.06);transition:box-shadow .25s}
.return-card:hover{box-shadow:0 6px 28px rgba(124,58,237,.12)}
.rc-strip{height:4px;background:linear-gradient(90deg,#115e59,#14b8a6)}
.rc-strip.reviewed{background:linear-gradient(90deg,#2563eb,#60a5fa)}
.rc-strip.decided{background:linear-gradient(90deg,#059669,#34d399)}
.rc-strip.rejected{background:linear-gradient(90deg,#dc2626,#f87171)}
.rc-inner{display:flex;align-items:stretch}
.rc-main{flex:1 1 0;padding:22px 24px;min-width:0}
.rc-panel{width:300px;flex-shrink:0;border-left:1.5px solid #f3f4f6;padding:22px;background:linear-gradient(180deg,#f9fafb 0%,#fff 100%);display:flex;flex-direction:column;gap:14px}
.rc-order-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.rc-order-num{font-size:17px;font-weight:800;color:#1e1b3a;font-family:monospace}
.rc-time{font-size:12px;color:#9ca3af;margin-left:auto}
.rc-parties{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px}
.rc-party-box{background:#f9f8ff;border:1px solid #ccfbf1;border-radius:10px;padding:12px 14px;display:flex;align-items:center;gap:10px}
.rc-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;flex-shrink:0}
.rc-avatar.buyer{background:#dbeafe;color:#1e40af}.rc-avatar.seller{background:#fce7f3;color:#9d174d}
.rc-party-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af}
.rc-party-name{font-size:13px;font-weight:700;color:#1e1b3a}
.rc-party-sub{font-size:11px;color:#6b7280}
.rc-reason-box{border-radius:10px;padding:14px 16px;margin-bottom:14px;border:1px solid transparent}
.rc-reason-box.damaged{background:#fff5f5;border-color:#fecaca}
.rc-reason-box.not_as_described{background:#fffbeb;border-color:#fde68a}
.rc-reason-box.wrong_item{background:#eff6ff;border-color:#bfdbfe}
.rc-reason-title{font-size:13px;font-weight:700;display:flex;align-items:center;gap:6px}
.rc-tracking{display:flex;align-items:center;gap:10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px;flex-wrap:wrap}
.rc-tracking-num{font-family:monospace;font-size:14px;font-weight:700;color:#1e1b3a}
.rc-evidence-section{margin-bottom:14px}
.rc-evidence-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.rc-evidence-label{font-size:12px;font-weight:700;color:#4b5563}
.rc-evidence-dl{font-size:12px;color:#115e59;text-decoration:none;font-weight:600}
.rc-evidence-dl:hover{text-decoration:underline}
.rc-evidence-thumb{width:90px;height:90px;border-radius:10px;object-fit:cover;cursor:zoom-in;border:1.5px solid #e5e7eb;transition:all .2s}
.rc-evidence-thumb:hover{border-color:#115e59;transform:scale(1.04)}
.rc-admin-note{background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:10px 14px;font-size:13px;color:#166534;margin-bottom:14px}
.rc-admin-note.rejected{background:#fff5f5;border-color:#fecaca;color:#991b1b}
.rc-product{display:flex;align-items:center;gap:12px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:10px 14px;font-size:13px}
.rc-product-cover{width:44px;height:56px;border-radius:6px;background:#e5e7eb;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:22px}
.rc-product-name{font-weight:700;color:#1e1b3a;margin-bottom:2px}
.rc-product-meta{font-size:12px;color:#6b7280}
.rc-product-price{font-size:17px;font-weight:800;color:#059669;margin-left:auto;white-space:nowrap}
.rp-escrow-box{background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #86efac;border-radius:12px;padding:14px 16px;text-align:center}
.rp-escrow-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#166534;margin-bottom:4px}
.rp-escrow-amt{font-size:26px;font-weight:900;color:#059669;letter-spacing:-.02em}
.rp-section-title{font-size:13px;font-weight:800;color:#1e1b3a}
.rp-warning{background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px;font-size:12px;color:#92400e;line-height:1.5}
.rp-note{width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:10px 12px;font:inherit;font-size:13px;resize:vertical;min-height:90px;outline:none;color:#374151;transition:border-color .2s}
.rp-note:focus{border-color:#115e59}
.rp-note-counter{font-size:11px;color:#9ca3af;text-align:right;margin-top:2px}
.rp-quick-inserts{display:flex;flex-wrap:wrap;gap:6px}
.rp-insert-chip{font-size:11px;font-weight:600;color:#115e59;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:20px;padding:3px 10px;cursor:pointer;transition:all .15s;white-space:nowrap}
.rp-insert-chip:hover{background:#ccfbf1;border-color:#86efac}
.btn-approve-r{width:100%;padding:13px 14px;border:none;border-radius:11px;font:inherit;font-size:14px;font-weight:800;background:linear-gradient(135deg,#059669,#10b981);color:#fff;cursor:pointer;box-shadow:0 4px 14px rgba(5,150,105,.35);transition:opacity .2s,transform .15s}
.btn-approve-r:hover{opacity:.9;transform:translateY(-1px)}
.btn-reject-r{width:100%;padding:13px 14px;border:none;border-radius:11px;font:inherit;font-size:14px;font-weight:800;background:linear-gradient(135deg,#dc2626,#ef4444);color:#fff;cursor:pointer;box-shadow:0 4px 14px rgba(220,38,38,.25);transition:opacity .2s,transform .15s}
.btn-reject-r:hover{opacity:.9;transform:translateY(-1px)}
.btn-refund-r{width:100%;padding:14px;border:none;border-radius:11px;font:inherit;font-size:14px;font-weight:800;background:linear-gradient(135deg,#0d9488,#059669);color:#fff;cursor:pointer;box-shadow:0 4px 18px rgba(5,150,105,.4);transition:opacity .2s,transform .15s;display:flex;align-items:center;justify-content:center;gap:8px}
.btn-refund-r:hover{opacity:.9;transform:translateY(-1px)}
.btn-approve-r:disabled,.btn-reject-r:disabled,.btn-refund-r:disabled{opacity:.5;cursor:not-allowed;transform:none}
.rp-audit-note{font-size:11px;color:#9ca3af;text-align:center;padding-top:4px}
.status-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;border-radius:20px;padding:3px 10px;border:1px solid transparent}
.badge-pending{background:#fef3c7;color:#92400e;border-color:#fde68a}
.badge-waiting{background:#dbeafe;color:#1e40af;border-color:#bfdbfe}
.badge-received{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
.badge-refunded{background:#dcfce7;color:#166534;border-color:#86efac}
.badge-rejected{background:#fee2e2;color:#991b1b;border-color:#fecaca}
.dispute-list{display:flex;flex-direction:column;gap:16px}
.dispute-card{background:#fff;border:1.5px solid #fee2e2;border-left:4px solid #e8546a;border-radius:16px;padding:20px 22px;display:flex;gap:22px;align-items:stretch;box-shadow:0 2px 10px rgba(232,84,106,.06)}
.dispute-info{flex:2;display:flex;flex-direction:column;gap:10px}
.dispute-reason-box{background:#fff5f5;padding:14px;border-radius:10px;border:1px solid #fecaca}
.dispute-reason-title{font-size:13px;font-weight:700;color:#991b1b;margin-bottom:5px}
.dispute-reason-text{font-size:14px;color:#1e1b3a;line-height:1.6}
.order-meta{font-size:13px;color:#6b7280;line-height:1.7}
.proof-img{width:110px;height:110px;border-radius:10px;object-fit:cover;cursor:zoom-in;border:1.5px solid #fecaca;transition:opacity .2s}
.proof-img:hover{opacity:.85}
.dispute-actions{flex:1;border-left:1.5px solid #f5cdd8;padding-left:22px;display:flex;flex-direction:column;gap:10px}
.dispute-note{width:100%;border:1.5px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-family:inherit;font-size:13px;resize:vertical;min-height:80px;outline:none}
.dispute-note:focus{border-color:#e8546a}
.btn-dispute-refund{padding:11px;border:none;border-radius:10px;font:inherit;font-size:13px;font-weight:700;background:linear-gradient(135deg,#e8546a,#f43f5e);color:#fff;cursor:pointer;transition:opacity .2s}
.btn-dispute-refund:hover{opacity:.88}
.btn-dispute-reject{padding:11px;border:none;border-radius:10px;font:inherit;font-size:13px;font-weight:700;background:linear-gradient(135deg,#10b981,#059669);color:#fff;cursor:pointer;transition:opacity .2s}
.btn-dispute-reject:hover{opacity:.88}
.history-section{margin-top:32px}
.history-section-title{font-size:16px;font-weight:800;color:#1e1b3a;margin-bottom:14px;display:flex;align-items:center;gap:8px;padding-bottom:10px;border-bottom:2px solid #e5e7eb}
.resolved-table-wrap{background:#fff;border:1.5px solid #e5e7eb;border-radius:16px;overflow:hidden}
.resolved-table{width:100%;border-collapse:collapse;font-size:13px}
.resolved-table thead{background:#f9f8ff}
.resolved-table th{padding:12px 16px;text-align:left;font-weight:700;color:#0f5132;font-size:12px;text-transform:uppercase;letter-spacing:.05em}
.resolved-table td{padding:14px 16px;border-bottom:1px solid #f3f4f6;color:#374151}
.resolved-table tr:last-child td{border-bottom:none}
.resolved-table tr:hover td{background:#f9fafb}
.res-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700}
.res-badge.refunded{background:#dcfce7;color:#166534}
.res-badge.rejected{background:#fee2e2;color:#991b1b}
.empty-state{text-align:center;padding:60px 20px;color:#9ca3af}
.empty-icon{font-size:52px;margin-bottom:14px}
.empty-state h3{font-size:16px;font-weight:700;color:#374151;margin-bottom:6px}
.img-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:3000;cursor:zoom-out;align-items:center;justify-content:center}
.img-modal.open{display:flex}
.img-modal img{max-width:92vw;max-height:88vh;border-radius:10px;object-fit:contain}
.img-modal-close{position:absolute;top:16px;right:24px;color:#fff;font-size:38px;cursor:pointer;line-height:1;opacity:.8}
.img-modal-close:hover{opacity:1}
.toast-msg{display:none;position:fixed;bottom:28px;right:28px;border-radius:12px;padding:14px 22px;font-size:14px;font-weight:700;z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,.2);max-width:380px}
.toast-msg.show{display:block;animation:fadeup .3s}
@keyframes fadeup{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.flash{padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:14px;font-weight:600}
.flash.success{background:#dcfce7;color:#166534;border:1px solid #86efac}
.flash.info{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd}
@media(max-width:900px){.rc-inner{flex-direction:column}.rc-panel{width:100%;border-left:none;border-top:1.5px solid #f3f4f6}.rc-parties{grid-template-columns:1fr}.dispute-card{flex-direction:column}.dispute-actions{border-left:none;border-top:1.5px solid #f5cdd8;padding-left:0;padding-top:14px}.dsp-stats{width:100%}.dsp-stat-box{flex:1}}
</style>
</head>
<body>
<?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>
<div class="admin-main-wrap"><main><div class="dsp-wrap">
<div class="dsp-header">
  <div>
    <div class="dsp-breadcrumb"><a href="/admin/index.php">หน้าหลัก</a> / <span>คำขอคืนสินค้า &amp; ข้อพิพาท</span></div>
    <div class="dsp-title"><div class="dsp-title-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></div>คำขอคืนสินค้า &amp; ข้อพิพาท</div>
    <div class="dsp-subtitle">พิจารณาคำขอคืนสินค้าจากลูกค้า ตรวจสอบหลักฐาน และดูประวัติการตัดสินทั้งหมด</div>
  </div>
  <div class="dsp-stats">
    <div class="dsp-stat-box"><div class="dsp-stat-label">Escrow ทั้งหมด</div><div class="dsp-stat-value green">&#3647;<?= number_format($escrowTotal,2) ?></div></div>
    <div class="dsp-stat-box"><div class="dsp-stat-label">รอพิจารณา</div><div class="dsp-stat-value"><?= (int)($statsRow['cnt_pending']??0)+$openDisputeCount ?> รายการ</div></div>
  </div>
</div>
<?php if (isset($_SESSION['flash_msg'])): $f=$_SESSION['flash_msg'];unset($_SESSION['flash_msg']); ?>
<div class="flash <?= $f['type'] ?>"><?= htmlspecialchars($f['text']) ?></div>
<?php endif; ?>
<div class="tab-nav" role="tablist">
<?php $tabs=['pending'=>['label'=>'คำขอสินค้า','icon'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>','count'=>(int)($statsRow['cnt_pending']??0)+$openDisputeCount,'cls'=>'red'],'reviewed'=>['label'=>'พิจารณาแล้ว','icon'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>','count'=>(int)($statsRow['cnt_reviewed']??0),'cls'=>'amber'],'decided'=>['label'=>'ตัดสินแล้ว','icon'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>','count'=>(int)($statsRow['cnt_decided']??0)+count($resolvedDisputes),'cls'=>'green'],'rejected'=>['label'=>'ปฏิเสธแล้ว','icon'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>','count'=>(int)($statsRow['cnt_rejected']??0),'cls'=>'red'],'all'=>['label'=>'ทั้งหมด','icon'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>','count'=>(int)($statsRow['cnt_all']??0),'cls'=>'gray']];
foreach($tabs as $k=>$t): ?>
<a href="?tab=<?=$k?>" class="tab-btn <?=$activeTab===$k?'active':''?>" role="tab">
  <?=$t['icon']?> <?=$t['label']?> <span class="tab-badge <?=$t['cls']?>"><?=$t['count']?></span>
</a>
<?php endforeach; ?>
</div>
<form class="filter-bar" method="get" action="">
  <input type="hidden" name="tab" value="<?=htmlspecialchars($activeTab)?>">
  <input class="filter-search" type="text" name="search" value="<?=htmlspecialchars($filterSearch)?>" placeholder="ค้นหา #เลขออเดอร์ / เลขพัสดุ / อีเมล">
  <select class="filter-select" name="reason">
    <option value="">สินค้าทุกประเภท</option>
    <option value="damaged" <?=$filterReason==='damaged'?'selected':''?>>สินค้าชำรุด / เสียหาย</option>
    <option value="not_as_described" <?=$filterReason==='not_as_described'?'selected':''?>>สินค้าไม่ตรงโฆษณา</option>
    <option value="wrong_item" <?=$filterReason==='wrong_item'?'selected':''?>>ได้รับสินค้าผิดรายการ</option>
  </select>
  <button class="filter-btn" type="submit"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> ค้นหา</button>
  <a href="?tab=<?=htmlspecialchars($activeTab)?>" class="filter-refresh" title="รีเซ็ต"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px"><polyline points="1 4 1 10 7 10"></polyline><polyline points="23 20 23 14 17 14"></polyline><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"></path></svg></a>
</form>
<?php if(in_array($activeTab,['pending','all'],true)&&!empty($disputes)): ?>
<div class="dispute-list" style="margin-bottom:24px">
  <div style="font-size:14px;font-weight:800;color:#dc2626;margin-bottom:10px;display:flex;align-items:center;gap:8px">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg> Dispute Tickets <span class="tab-badge red"><?=count($disputes)?></span>
  </div>
  <?php foreach($disputes as $d): ?>
  <div class="dispute-card">
    <div class="dispute-info">
      <div style="font-size:15px;font-weight:700;color:#1e1b3a">ออเดอร์: <?=htmlspecialchars($d['order_number'])?></div>
      <div class="order-meta">ผู้เปิดข้อพิพาท: <?=htmlspecialchars($d['shipping_name']??'')?> (<?=htmlspecialchars($d['user_email'])?>)<br>วันที่เปิด: <?=htmlspecialchars($d['created_at'])?> &nbsp;|&nbsp; ยอดเงิน: <strong>&#3647;<?=number_format((float)$d['total_amount'],2)?></strong></div>
      <div class="dispute-reason-box"><div class="dispute-reason-title">สาเหตุข้อพิพาท:</div><div class="dispute-reason-text"><?=nl2br(htmlspecialchars($d['reason']))?></div></div>
      <?php if(!empty($d['proof_image'])): ?><div><div style="font-size:12px;color:#6b7280;margin-bottom:6px"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg> หลักฐาน:</div><img src="<?=htmlspecialchars($d['proof_image'])?>" alt="Proof" class="proof-img" onclick="openImgModal(this.src)"></div><?php endif; ?>
    </div>
    <div class="dispute-actions">
      <form method="POST" onsubmit="event.preventDefault(); var form = this; showConfirmModal('ยืนยันการตัดสินข้อพิพาทนี้ใช่หรือไม่?', function(){ form.submit(); })" style="display:flex;flex-direction:column;gap:10px;height:100%">
        <input type="hidden" name="dispute_id" value="<?=$d['dispute_id']?>">
        <input type="hidden" name="order_id" value="<?=$d['order_id']?>">
        <div style="font-size:13px;font-weight:700;color:#374151">การตัดสินของ Super Admin:</div>
        <textarea name="admin_note" class="dispute-note" placeholder="บันทึกเหตุผลในการตัดสิน..." required></textarea>
        <div style="margin-top:auto;display:flex;flex-direction:column;gap:8px">
          <button type="submit" name="action" value="refund" class="btn-dispute-refund"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> ยกเลิกออเดอร์และคืนเงินให้ผู้ซื้อ</button>
          <button type="submit" name="action" value="reject" class="btn-dispute-reject"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> ปฏิเสธข้อพิพาท (โอนให้ผู้ขาย)</button>
        </div>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if(empty($returnRequests)&&empty($disputes)&&empty($resolvedDisputes)): ?>
<div class="empty-state">
  <div class="empty-icon"><?php echo['pending'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>','reviewed'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>','decided'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>','rejected'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>','all'=>'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>'][$activeTab]??'<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>'?></div>
  <h3>ไม่มีรายการในหมวดนี้</h3>
  <p><?php echo['pending'=>'เมื่อลูกค้ายื่นคำขอ จะปรากฏที่นี่','reviewed'=>'รายการที่อนุมัติแล้วจะแสดงที่นี่','decided'=>'ประวัติการตัดสินจะแสดงที่นี่','rejected'=>'รายการที่ปฏิเสธจะแสดงที่นี่','all'=>'ยังไม่มีรายการ'][$activeTab]??''?></p>
</div>
<?php else: ?>
<?php if(!empty($returnRequests)): ?>
<div class="return-list" id="return-list">
<?php foreach($returnRequests as $r):
  $ri=$reasonMap[$r['reason']]??['label'=>$r['reason'],'icon'=>'<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>','color'=>'#374151'];
  $bi=mb_strtoupper(mb_substr($r['buyer_name']??'U',0,1));
  $si=mb_strtoupper(mb_substr($r['seller_name']??'S',0,1));
  $ct=strtotime($r['created_at']??'now');
  $dm=round((time()-$ct)/60);
  $dl=$dm<60?$dm.' นาทีที่แล้ว':(round($dm/60)<24?round($dm/60).' ชั่วโมงที่แล้ว':round($dm/1440).' วันที่แล้ว');
  $st=$statusLabelMap[$r['status']]??['label'=>$r['status'],'cls'=>''];
  $ip=$r['status']==='pending_super_admin';
  $sc=match($r['status']){'pending_super_admin'=>'','waiting_for_shop'=>'reviewed','shop_received'=>'reviewed','refunded_to_wallet'=>'decided','rejected'=>'rejected',default=>''};
  $ev=$r['evidence_url']??'';$evUrl='';
  if($ev){$ev=ltrim(str_replace('\\','/',$ev),'/');$evUrl=str_starts_with($ev,'uploads/')?'/pages/'.$ev:'/'.$ev;}
?>
<div class="return-card" id="rcard-<?=(int)$r['return_id']?>">
  <div class="rc-strip <?=$sc?>"></div>
  <div class="rc-inner">
    <div class="rc-main">
      <div class="rc-order-row">
        <span class="rc-order-num">#<?=htmlspecialchars($r['order_number']??$r['order_id'])?></span>
        <span class="status-badge <?=$st['cls']?>"><?=$st['label']?></span>
        <span class="rc-time"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> <?=date('d/m/Y H:i',$ct)?> &middot; <?=$dl?></span>
      </div>
      <div class="rc-parties">
        <div class="rc-party-box"><div class="rc-avatar buyer"><?=htmlspecialchars($bi)?></div><div><div class="rc-party-label">ผู้ซื้อ</div><div class="rc-party-name"><?=htmlspecialchars($r['buyer_name']??'ลูกค้า')?></div><div class="rc-party-sub"><?=htmlspecialchars($r['buyer_email']??'')?></div></div></div>
        <div class="rc-party-box"><div class="rc-avatar seller"><?=htmlspecialchars($si)?></div><div><div class="rc-party-label">ร้านค้า/สำนักพิมพ์</div><div class="rc-party-name"><?=htmlspecialchars($r['seller_name']??($r['seller_type'].' #'.$r['seller_id']))?></div></div></div>
      </div>
      <div class="rc-reason-box <?=htmlspecialchars($r['reason'])?>"><div class="rc-reason-title" style="color:<?=$ri['color']?>"><?=$ri['icon']?> สาเหตุ: <?=htmlspecialchars($ri['label'])?></div></div>
      <div class="rc-tracking"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#6b7280" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg><span style="font-size:12px;color:#6b7280">เลขพัสดุที่ส่งคืน:</span><span class="rc-tracking-num"><?=htmlspecialchars($r['return_tracking'])?></span></div>
      <?php if($evUrl): ?>
      <div class="rc-evidence-section"><div class="rc-evidence-header"><div class="rc-evidence-label"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg> รูปหลักฐาน</div><a href="<?=htmlspecialchars($evUrl)?>" download target="_blank" class="rc-evidence-dl"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> ดาวน์โหลด</a></div><img src="<?=htmlspecialchars($evUrl)?>" alt="หลักฐาน" class="rc-evidence-thumb" onclick="openImgModal(this.src)" onerror="this.style.display='none'"></div>
      <?php endif; ?>
      <?php if(!empty($r['admin_note'])): ?>
      <div class="rc-admin-note <?=$r['status']==='rejected'?'rejected':''?>"><strong><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> หมายเหตุ Admin:</strong> <?=htmlspecialchars($r['admin_note'])?></div>
      <?php endif; ?>
      <div class="rc-product"><div class="rc-product-cover"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg></div><div><div class="rc-product-name">คำสั่งซื้อ #<?=htmlspecialchars($r['order_number']??$r['order_id'])?></div><div class="rc-product-meta">จัดเก็บโดย Escrow</div></div><div class="rc-product-price">&#3647;<?=number_format((float)$r['refund_amount'],2)?></div></div>
    </div>
    <div class="rc-panel">
      <div class="rp-escrow-box"><div class="rp-escrow-label">ยอดเงิน Escrow</div><div class="rp-escrow-amt">&#3647;<?=number_format((float)$r['refund_amount'],2)?></div></div>
      <?php if($ip): ?>
      <div class="rp-section-title">&#9679; การพิจารณาของ Super Admin:</div>
      <div class="rp-warning"><strong>เมื่ออนุมัติ</strong> ระบบจะแจ้งร้านค้าให้รอรับสินค้าคืน</div>
      <div>
        <div style="font-size:12px;font-weight:700;color:#4b5563;margin-bottom:6px">หมายเหตุ (ไม่บังคับ):</div>
        <textarea class="rp-note" id="note-<?=(int)$r['return_id']?>" maxlength="500" placeholder="หมายเหตุถึงลูกค้า..." oninput="updateCounter(this,'ctr-<?=(int)$r['return_id']?>')"></textarea>
        <div class="rp-note-counter"><span id="ctr-<?=(int)$r['return_id']?>">0</span>/500</div>
      </div>
      <div class="rp-quick-inserts">
        <span class="rp-insert-chip" onclick="insertNote('note-<?=(int)$r['return_id']?>','ตรวจสอบหลักฐานครบถ้วน')">+ ตรวจสอบหลักฐานครบถ้วน</span>
        <span class="rp-insert-chip" onclick="insertNote('note-<?=(int)$r['return_id']?>','สินค้าชำรุดจากการขนส่ง')">+ สินค้าชำรุดจากการขนส่ง</span>
        <span class="rp-insert-chip" onclick="insertNote('note-<?=(int)$r['return_id']?>','ขอหลักฐานเพิ่มเติม')">+ ขอหลักฐานเพิ่มเติม</span>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;margin-top:auto">
        <button class="btn-approve-r" id="btn-approve-<?=(int)$r['return_id']?>" onclick="reviewReturn(<?=(int)$r['return_id']?>,'approve')"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> อนุมัติ &mdash; ส่งให้ร้านค้ายืนยัน</button>
        <button class="btn-reject-r"  id="btn-reject-<?=(int)$r['return_id']?>"  onclick="reviewReturn(<?=(int)$r['return_id']?>,'reject')"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> ปฏิเสธคำขอ</button>
      </div>
      <div class="rp-audit-note">บันทึกการตัดสินอยู่ใน Admin Audit Log</div>
      <?php else: ?>
      <?php if($r['status']==='waiting_for_shop'): ?>
      <div style="background:#f9f8ff;border:1.5px solid #ccfbf1;border-radius:12px;padding:14px;font-size:13px;color:#4b5563;line-height:1.7">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg> <strong>รอร้านค้ายืนยันรับสินค้าคืน</strong><br><small>ระบบแจ้งร้านค้าแล้ว</small>
      </div>
      <?php elseif($r['status']==='shop_received'): ?>
      <div style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1.5px solid #6ee7b7;border-radius:12px;padding:14px;font-size:13px;color:#065f46;font-weight:600;margin-bottom:4px">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> ร้านค้ายืนยันรับสินค้าคืนแล้ว<?php if(!empty($r['shop_received_at'])): ?><br><small style="font-weight:400;color:#059669">รับเมื่อ <?=htmlspecialchars(date('d/m/Y H:i',strtotime($r['shop_received_at'])))?></small><?php endif; ?>
      </div>
      <div>
        <div style="font-size:12px;font-weight:700;color:#4b5563;margin-bottom:6px">หมายเหตุการโอนเงิน (ไม่บังคับ):</div>
        <textarea class="rp-note" id="rnote-<?=(int)$r['return_id']?>" maxlength="300" placeholder="เช่น คืนเงินตามคำร้อง #<?=(int)$r['return_id']?>..." style="min-height:70px" oninput="updateCounter(this,'rctr-<?=(int)$r['return_id']?>')"></textarea>
        <div class="rp-note-counter"><span id="rctr-<?=(int)$r['return_id']?>">0</span>/300</div>
      </div>
      <div style="margin-top:auto">
        <button class="btn-refund-r" id="btn-refund-<?=(int)$r['return_id']?>"
                onclick="processRefund(<?=(int)$r['return_id']?>,<?=htmlspecialchars(json_encode(number_format((float)$r['refund_amount'],2)),ENT_QUOTES)?>)">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> โอนเงิน &#3647;<?=number_format((float)$r['refund_amount'],2)?> เข้า Wallet ลูกค้า
        </button>
      </div>
      <div class="rp-audit-note">บันทึกการโอนเงินอยู่ใน Admin Audit Log</div>
      <?php elseif($r['status']==='refunded_to_wallet'): ?>
      <div style="background:#f0fdf4;border:1.5px solid #86efac;border-radius:12px;padding:14px;font-size:13px;color:#166534;line-height:1.7">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> <strong>คืนเงินเข้า Wallet แล้ว</strong><?php if(!empty($r['refunded_at'])): ?><br><small>เมื่อ <?=htmlspecialchars(date('d/m/Y H:i',strtotime($r['refunded_at'])))?></small><?php endif; ?>
      </div>
      <?php elseif($r['status']==='rejected'): ?>
      <div style="background:#fff5f5;border:1.5px solid #fecaca;border-radius:12px;padding:14px;font-size:13px;color:#991b1b;line-height:1.7">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> <strong>ปฏิเสธคำขอแล้ว</strong>
      </div>
      <?php endif; ?>
      <?php if(!empty($r['super_admin_reviewed_at'])): ?>
      <div style="font-size:11px;color:#9ca3af;text-align:center">พิจารณาเมื่อ <?=htmlspecialchars(date('d/m/Y H:i',strtotime($r['super_admin_reviewed_at'])))?></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php if(!empty($resolvedDisputes)): ?>
<div class="history-section">
  <div class="history-section-title">📜 ประวัติการตัดสินข้อพิพาท <span class="tab-badge gray"><?=count($resolvedDisputes)?></span></div>
  <div class="resolved-table-wrap">
    <table class="resolved-table">
      <thead><tr><th>ออเดอร์</th><th>ลูกค้า</th><th>ยอดเงิน</th><th>ผลตัดสิน</th><th>หมายเหตุ</th><th>วันที่ตัดสิน</th></tr></thead>
      <tbody>
      <?php foreach($resolvedDisputes as $d): ?>
      <tr>
        <td><strong><?=htmlspecialchars($d['order_number'])?></strong></td>
        <td style="font-size:12px"><?=htmlspecialchars($d['user_email'])?></td>
        <td><strong>&#3647;<?=number_format((float)$d['total_amount'],2)?></strong></td>
        <td><span class="res-badge <?=$d['resolution']==='refunded'?'refunded':'rejected'?>"><?=$d['resolution']==='refunded'?'<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 0 0-4-4H4"></path></svg> คืนเงินแล้ว':'<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> ปฏิเสธ'?></span></td>
        <td style="font-size:12px;color:#6b7280;max-width:200px"><?=htmlspecialchars(mb_strimwidth($d['admin_note']??'',0,60,'...'))?></td>
        <td style="font-size:12px;color:#9ca3af"><?=date('d/m/Y H:i',strtotime($d['updated_at']??$d['created_at']))?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
</div></main></div>
<div class="img-modal" id="imgModal" onclick="closeImgModal()">
  <span class="img-modal-close" onclick="closeImgModal()">&times;</span>
  <img id="imgModalSrc" src="" alt="หลักฐาน" onclick="event.stopPropagation()">
</div>
<style>
.custom-confirm-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.custom-confirm-modal.open { display:flex; animation: fadeIn 0.2s ease; }
.custom-confirm-box { background:#fff; width:400px; max-width:90%; border-radius:16px; padding:24px; box-shadow:0 10px 40px rgba(0,0,0,0.2); text-align:center; transform:scale(0.95); transition:transform 0.2s ease; }
.custom-confirm-modal.open .custom-confirm-box { transform:scale(1); }
.custom-confirm-icon { width:64px; height:64px; background:#ccfbf1; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; color:#0f5132; }
.custom-confirm-title { font-size:18px; font-weight:800; color:#1e1b3a; margin-bottom:10px; }
.custom-confirm-actions { display:flex; gap:12px; margin-top:24px; }
.custom-confirm-btn { flex:1; padding:12px; border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; transition:opacity 0.2s; }
.custom-confirm-cancel { background:#f3f4f6; color:#4b5563; }
.custom-confirm-ok { background:#115e59; color:#fff; }
.custom-confirm-btn:hover { opacity:0.85; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
</style>
<div class="custom-confirm-modal" id="customConfirmModal">
  <div class="custom-confirm-box">
    <div class="custom-confirm-icon"><svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"></path><path d="M12 8v4"></path><path d="M12 16h.01"></path></svg></div>
    <div class="custom-confirm-title" id="customConfirmMsg">ยืนยันการดำเนินการ?</div>
    <div class="custom-confirm-actions">
      <button class="custom-confirm-btn custom-confirm-cancel" onclick="closeConfirmModal()">ยกเลิก</button>
      <button class="custom-confirm-btn custom-confirm-ok" id="customConfirmOkBtn">ยืนยัน</button>
    </div>
  </div>
</div>
<div class="toast-msg" id="toast" role="alert"></div>
<script>
function openImgModal(s){document.getElementById('imgModalSrc').src=s;document.getElementById('imgModal').classList.add('open')}
function closeImgModal(){document.getElementById('imgModal').classList.remove('open')}
function insertNote(id,t){var ta=document.getElementById(id);if(!ta)return;ta.value=(ta.value?ta.value+' ':'')+t;ta.focus();updateCounter(ta,id.replace('note-','ctr-'))}
function updateCounter(ta,c){var el=document.getElementById(c);if(el)el.textContent=ta.value.length}
var confirmCallback = null;
function showConfirmModal(msg, callback) {
    document.getElementById('customConfirmMsg').innerText = msg;
    confirmCallback = callback;
    document.getElementById('customConfirmModal').classList.add('open');
}
function closeConfirmModal() {
    document.getElementById('customConfirmModal').classList.remove('open');
    confirmCallback = null;
}
document.getElementById('customConfirmOkBtn').addEventListener('click', function() {
    if(confirmCallback) confirmCallback();
    closeConfirmModal();
});
var CSRF=<?=json_encode($_SESSION['superadmin_return_csrf'])?>;
function reviewReturn(id,action){
  var msg=action==='approve'?'ยืนยันการอนุมัติ? คำขอจะถูกส่งให้ร้านค้ายืนยันรับสินค้าคืน':'ยืนยันการปฏิเสธคำขอนี้?';
  showConfirmModal(msg, function() {
      var note=(document.getElementById('note-'+id)||{value:''}).value.trim();
      var btnA=document.getElementById('btn-approve-'+id),btnR=document.getElementById('btn-reject-'+id);
      [btnA,btnR].forEach(function(b){if(b){b.disabled=true;b.textContent='กำลังดำเนินการ...'}});
      var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('return_id',id);fd.append('action',action);fd.append('admin_note',note);
      fetch('/api/superadmin_review_return.php',{method:'POST',body:fd})
        .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
        .then(function(d){
          if(d.ok){showToast(d.message,false);var c=document.getElementById('rcard-'+id);
            if(c){c.style.transition='opacity .4s,transform .4s';c.style.opacity='0';c.style.transform='translateX(24px)';
              setTimeout(function(){c.remove();var cnt=document.querySelectorAll('.return-card').length;
                if(cnt===0){var l=document.getElementById('return-list');if(l)l.innerHTML='<div class="empty-state"><div class="empty-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div><h3>ไม่มีคำขอที่รอพิจารณา</h3></div>'}},440)}}
          else{showToast(d.message||'เกิดข้อผิดพลาด',true);resetBtns(id)}})
        .catch(function(){showToast('เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ',true);resetBtns(id)});
  });
}
function resetBtns(id){var a=document.getElementById('btn-approve-'+id),r=document.getElementById('btn-reject-'+id);if(a){a.disabled=false;a.textContent='\u2713 อนุมัติ \u2014 ส่งให้ร้านค้ายืนยัน'}if(r){r.disabled=false;r.textContent='\u2717 ปฏิเสธคำขอ'}}
function processRefund(id,amtLabel){
  showConfirmModal('ยืนยันการโอนเงิน ฿'+amtLabel+' เข้า Wonwan Wallet ของลูกค้า? การดำเนินการนี้ไม่สามารถยกเลิกได้', function() {
      var btn=document.getElementById('btn-refund-'+id);
      if(btn){btn.disabled=true;btn.textContent='⏳ กำลังโอนเงิน...'}
      var note=(document.getElementById('rnote-'+id)||{value:''}).value.trim();
      var fd=new FormData();fd.append('csrf_token',CSRF);fd.append('return_id',id);fd.append('refund_note',note);
      fetch('/api/superadmin_process_refund.php',{method:'POST',body:fd})
        .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json()})
        .then(function(d){
          if(d.ok){
            showToast('<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> '+d.message,false);
            var card=document.getElementById('rcard-'+id);
            if(card){card.style.transition='opacity .5s,transform .5s';card.style.opacity='0';card.style.transform='translateX(28px)';
              setTimeout(function(){card.remove();var cnt=document.querySelectorAll('.return-card').length;
                if(cnt===0){var l=document.getElementById('return-list');if(l)l.innerHTML='<div class="empty-state"><div class="empty-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div><h3>ดำเนินการโอนเงินครบทุกรายการแล้ว</h3></div>'}},520)}
          }else{showToast('<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> '+(d.message||'เกิดข้อผิดพลาด'),true);if(btn){btn.disabled=false;btn.textContent='<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> โอนเงิน ฿'+amtLabel+' เข้า Wallet ลูกค้า'}}
        })
        .catch(function(){showToast('<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> เชื่อมต่อเซิร์ฟเวอร์ไม่สำเร็จ กรุณาลองใหม่',true);if(btn){btn.disabled=false;btn.textContent='<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> โอนเงิน ฿'+amtLabel+' เข้า Wallet ลูกค้า'}});
  });
}
function showToast(msg,err){var t=document.getElementById('toast');t.textContent=msg;t.style.color='#fff';t.style.background=err?'#dc2626':'#059669';t.classList.add('show');setTimeout(function(){t.classList.remove('show')},4500)}
</script>
</body>
</html>