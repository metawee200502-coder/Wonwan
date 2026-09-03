<?php
$active_menu = $active_menu ?? '';
$admin_name = $_SESSION['display_name'] ?? 'แอดมินระบบ';
$admin_avatar = $_SESSION['avatar'] ?? '';

// You can query for pending badges if desired, or skip it for now.
$shopPendingBadge = 0;
$slipPendingBadge = 0;
$disputeBadge = 0;
$qcBadge = 0;

if (!empty($_SESSION['user_id']) && isset($pdo)) {
    try {
        $shopPendingBadge = (int)$pdo->query("SELECT COUNT(*) FROM shops WHERE status = 'pending'")->fetchColumn() + (int)$pdo->query("SELECT COUNT(*) FROM publishers WHERE status = 'pending'")->fetchColumn();
        $slipPendingBadge = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'reviewing'")->fetchColumn();
        $disputeBadge = (int)$pdo->query("SELECT COUNT(*) FROM disputes WHERE status = 'open'")->fetchColumn();
        $qcBadge = (int)$pdo->query("SELECT COUNT(*) FROM exchange_requests WHERE status = 'in_progress' AND (admin_qc_requester_status = 'pending' OR admin_qc_offer_status = 'pending')")->fetchColumn() + (int)$pdo->query("SELECT COUNT(*) FROM exchanges WHERE qc_status = 'pending'")->fetchColumn();
    } catch (Throwable $e) {}
}
?>
<link rel="stylesheet" href="/assets/css/admin-sidebar.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin-sidebar.css') ?>">
<style>
/* CSS overrides for main content wrapper when sidebar is used */
.admin-main-wrap {
    margin-left: 260px;
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
</style>
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="/assets/image/minilogo.png" alt="Logo">
        <div>
            <div class="sidebar-logo-text">วนวรรณ</div>
            <div class="sidebar-logo-sub">ระบบผู้ดูแล (Super Admin)</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="/admin/index.php" class="nav-item <?= $active_menu === 'dashboard' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            แผงควบคุมระบบ
        </a>
        <a href="/admin/approvals.php" class="nav-item <?= $active_menu === 'approvals' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            ตรวจสอบร้านค้า
            <?php if($shopPendingBadge):?><span class="admin-order-badge"><?=$shopPendingBadge>99?'99+':$shopPendingBadge?></span><?php endif;?>
        </a>
        <a href="/admin/escrow.php" class="nav-item <?= $active_menu === 'escrow' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
            ระบบเงินกลาง (Escrow)
            <?php if($slipPendingBadge):?><span class="admin-order-badge"><?=$slipPendingBadge>99?'99+':$slipPendingBadge?></span><?php endif;?>
        </a>
        <a href="/admin/disputes.php" class="nav-item <?= $active_menu === 'disputes' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            ข้อพิพาท & คืนเงิน
            <?php if($disputeBadge):?><span class="admin-order-badge"><?=$disputeBadge>99?'99+':$disputeBadge?></span><?php endif;?>
        </a>
        <a href="/admin/qc_exchanges.php" class="nav-item <?= $active_menu === 'qc_exchanges' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"/><path d="M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10"/><path d="M3.51 15A9 9 0 0 0 18.36 18.36L23 14"/></svg>
            แลกเปลี่ยน & QC
            <?php if($qcBadge):?><span class="admin-order-badge"><?=$qcBadge>99?'99+':$qcBadge?></span><?php endif;?>
        </a>
    </nav>
    <a class="sidebar-profile sidebar-profile-link" href="#" title="โปรไฟล์ผู้ดูแลระบบ">
        <div class="profile-avatar">
            <?php if ($admin_avatar !== ''): ?>
                <img src="<?= htmlspecialchars($admin_avatar) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(mb_substr($admin_name, 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div>
            <div class="profile-role">Super Admin</div>
            <div class="profile-seller"><?= htmlspecialchars($admin_name) ?></div>
        </div>
    </a>
    <form class="sidebar-logout-form" action="/process/logout.php" method="post">
        <button class="sidebar-logout" type="submit">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            ออกจากระบบ
        </button>
    </form>
</aside>
