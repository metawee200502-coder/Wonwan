<?php
/**
 * Sidebar component (เมนูฝั่งซ้าย)
 *
 * ตัวแปรที่ต้องเตรียมไว้ก่อน include ไฟล์นี้:
 * - $active_menu (string)  หน้าไหนกำลังเปิดอยู่: 'dashboard' | 'inventory' | 'support' | 'orders'
 * - $role        (string)  จาก $_SESSION['role']
 * - $seller_name (string)  ชื่อสำนักพิมพ์ หรือ ชื่อร้านค้า ของผู้ที่ล็อกอินอยู่
 */
$active_menu = $active_menu ?? '';
$seller_name = $seller_name ?? '';
$seller_profile_image = $seller_profile_image ?? '';
$seller_profile_id = $seller_profile_id ?? 0;
$role = $role ?? ($_SESSION['role'] ?? '');
$staff_role = 'sub_admin';
if (($seller_name === '' || $seller_profile_id === 0) && !empty($_SESSION['user_id']) && isset($pdo)) {
    try {
        if ($role === 'publisher_admin') {
            $sellerStmt = $pdo->prepare('SELECT p.company_name,p.profile_image,p.publisher_id,ps.staff_role FROM publisher_staff ps JOIN publishers p ON p.publisher_id=ps.publisher_id WHERE ps.user_id=? LIMIT 1');
        } else {
            $sellerStmt = $pdo->prepare('SELECT s.shop_name,s.profile_image,s.shop_id,ss.staff_role FROM shop_staff ss JOIN shops s ON s.shop_id=ss.shop_id WHERE ss.user_id=? LIMIT 1');
        }
        $sellerStmt->execute([$_SESSION['user_id']]);
        $sellerRow = $sellerStmt->fetch(PDO::FETCH_NUM);
        $seller_name = (string)($sellerRow[0] ?? '');
        $seller_profile_image = (string)($sellerRow[1] ?? '');
        $seller_profile_id = (int)($sellerRow[2] ?? 0);
        $staff_role = (string)($sellerRow[3] ?? 'sub_admin');
    } catch (Throwable $e) {}
}
$admin_order_badge = 0;
$admin_chat_badge = 0;
if (!empty($_SESSION['user_id']) && isset($pdo)) {
    try {
        $badgeStmt = $pdo->prepare("SELECT COUNT(DISTINCT o.order_id) FROM orders o JOIN order_items oi ON oi.order_id=o.order_id JOIN books b ON b.book_id=oi.book_id WHERE o.payment_status='paid' AND o.shipping_status IN ('pending','packing') AND ((b.seller_type='publisher' AND b.seller_id IN (SELECT publisher_id FROM publisher_staff WHERE user_id=?)) OR (b.seller_type='shop' AND b.seller_id IN (SELECT shop_id FROM shop_staff WHERE user_id=?)))");
        $badgeStmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
        $admin_order_badge = (int)$badgeStmt->fetchColumn();
        if($seller_profile_id){$sellerType=$role==='publisher_admin'?'publisher':'shop';$chatBadgeStmt=$pdo->prepare("SELECT COUNT(m.message_id) FROM chat_conversations c JOIN chat_messages m ON m.conversation_id=c.conversation_id AND m.is_read=0 AND m.sender_user_id=c.customer_user_id WHERE c.seller_type=? AND c.seller_id=?");$chatBadgeStmt->execute([$sellerType,$seller_profile_id]);$admin_chat_badge=(int)$chatBadgeStmt->fetchColumn();}
    } catch (Throwable $e) {}
}
?>
<link rel="stylesheet" href="../assets/css/admin-sidebar.css">
<style>
/* Seller Admin Pastel Pink Theme Overrides */
.sidebar { background: #fce1e6 !important; border-right: 1px solid #eabac2 !important; }
.sidebar-logo { border-bottom: 1px solid #f2e1e3 !important; }
.sidebar-logo-text { color: #6e404a !important; }
.sidebar-logo-sub { color: #a1747e !important; }
.sidebar .nav-item { color: #704850 !important; }
.sidebar .nav-item:hover, .sidebar .nav-item.active { background: #C97B8B !important; color: #fff !important; font-weight: 600 !important; }
.sidebar-profile { border-top: 1px solid #f2e1e3 !important; }
.profile-avatar { color: #C97B8B !important; }
.profile-seller, .profile-name { color: #6e404a !important; }
.profile-role { color: #a1747e !important; }
.sidebar-logout { border: 1px solid #C97B8B !important; color: #C97B8B !important; }
.sidebar-logout:hover { background: #C97B8B !important; color: #fff !important; }
.profile-admin-name { font-size: 11px !important; color: #a1747e !important; margin-top: 3px !important; font-weight: 500 !important; line-height: 1.2 !important; }
</style>
<aside class="sidebar">
    <div class="sidebar-logo">
        <img src="../assets/image/minilogo.png" alt="Logo">
        <div>
            <div class="sidebar-logo-text">วนวรรณ</div>
            <div class="sidebar-logo-sub">Buy · Sell · Exchange</div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $active_menu === 'dashboard' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            แผงควบคุม
        </a>
        <a href="inventory.php" class="nav-item <?= $active_menu === 'inventory' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            จัดการคลังสินค้า
        </a>
        <a href="admin_chat.php" class="nav-item <?= $active_menu === 'support' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            แชทและการสนับสนุน
            <?php if($admin_chat_badge):?><span class="admin-order-badge admin-chat-unread-badge"><?=$admin_chat_badge>99?'99+':$admin_chat_badge?></span><?php endif;?>
        </a>
        <a href="admin_orders.php" class="nav-item <?= $active_menu === 'orders' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            ติดตามคำสั่งซื้อ
            <?php if ($admin_order_badge > 0): ?><span class="admin-order-badge"><?= $admin_order_badge ?></span><?php endif; ?>
        </a>
        <a href="seller_wallet.php" class="nav-item <?= $active_menu === 'wallet' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            รายได้ทั้งหมด
        </a>
        <?php
        // Badge: คำขอคืนสินค้าที่รอพิจารณา
        $admin_return_badge = 0;
        if ($seller_profile_id && isset($pdo)) {
            try {
                $sellerTypeForReturn = $role === 'publisher_admin' ? 'publisher' : 'shop';
                $retBadge = $pdo->prepare("SELECT COUNT(*) FROM return_requests WHERE seller_type = ? AND seller_id = ? AND status = 'pending'");
                $retBadge->execute([$sellerTypeForReturn, $seller_profile_id]);
                $admin_return_badge = (int)$retBadge->fetchColumn();
            } catch (Throwable $e) {}
        }
        ?>
        <a href="admin_returns.php" class="nav-item <?= $active_menu === 'returns' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.5"/></svg>
            คำขอคืนสินค้า
            <?php if ($admin_return_badge > 0): ?><span class="admin-order-badge" style="background:#d97706"><?= $admin_return_badge ?></span><?php endif; ?>
        </a>
        <a href="admin_promotions.php" class="nav-item <?= $active_menu === 'promotions' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12v8H4v-8"/><path d="M2 7h20v5H2z"/><path d="M12 7v13"/><path d="M12 7H7.5a2.5 2.5 0 1 1 2.2-3.7L12 7zm0 0h4.5a2.5 2.5 0 1 0-2.2-3.7L12 7z"/></svg>
            จัดการโปรโมชั่น
        </a>
        <a href="/pages/seller_review_management.php" class="nav-item <?= $active_menu === 'reviews' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 7 .9-5 4.8 1.3 6.8L12 17.3l-6.3 3.2L7 13.7 2 8.9 9 8z"/></svg>
            รีวิวจากลูกค้า
        </a>
        <a href="admin_notifications.php" class="nav-item <?= $active_menu === 'notifications' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
            การแจ้งเตือน
            <?php
            $adminUnread = 0;
            if (!empty($_SESSION['user_id']) && isset($pdo)) {
                try {
                    $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
                    $unreadStmt->execute([$_SESSION['user_id']]);
                    $adminUnread = (int)$unreadStmt->fetchColumn();
                } catch (Throwable $e) {}
            }
            ?>
            <?php if($adminUnread): ?><span class="admin-order-badge"><?=$adminUnread?></span><?php endif; ?>
        </a>
        <?php if ($staff_role === 'owner'): ?>
        <a href="manage_seller_admins.php" class="nav-item <?= $active_menu === 'manage_seller_admins' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
            จัดการผู้ดูแลระบบ
        </a>
        <a href="seller_action_logs.php" class="nav-item <?= $active_menu === 'seller_action_logs' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            ประวัติการทำรายการ
        </a>
        <?php endif; ?>
    </nav>
    <a class="sidebar-profile sidebar-profile-link" href="/pages/admin_profile.php" title="<?= $role==='publisher_admin'?'แก้ไขโปรไฟล์สำนักพิมพ์':'แก้ไขโปรไฟล์ร้านหนังสือ' ?>">
        <div class="profile-avatar">
            <?php if ($seller_profile_image !== ''): ?>
                <img src="<?= htmlspecialchars(preg_match('#^https?://#i',$seller_profile_image)?$seller_profile_image:'/pages/'.ltrim(str_replace('\\','/',$seller_profile_image),'/')) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(mb_substr($_SESSION['display_name'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div>
            <div class="profile-role"><?= $role === 'publisher_admin' ? 'สำนักพิมพ์' : 'ร้านหนังสือ' ?></div>
            <div class="profile-seller"><?= htmlspecialchars($seller_name ?: '-') ?></div>
            <div class="profile-admin-name">แอดมิน <?= htmlspecialchars($_SESSION['display_name'] ?? '') ?></div>
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
<script>
document.addEventListener("DOMContentLoaded", function() {
    var nav = document.querySelector('.sidebar-nav');
    if (nav) {
        var pos = sessionStorage.getItem('sellerSidebarScroll');
        if (pos) nav.scrollTop = parseInt(pos, 10);
        nav.addEventListener('scroll', function() {
            sessionStorage.setItem('sellerSidebarScroll', nav.scrollTop);
        });
    }
});
</script>
<script src="/assets/js/notification-live.js?v=<?=filemtime(__DIR__.'/../assets/js/notification-live.js')?>" defer></script>
<?php if($active_menu==='promotions'):?>
<script>window.wanawanPromotionOptions=<?=json_encode(['categories'=>$categories??[],'books'=>$sellerBooks??[],'promotions'=>$promotions??[]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;</script>
<script src="/assets/js/admin-promotions.js?v=<?=filemtime(__DIR__.'/../assets/js/admin-promotions.js')?>" defer></script>
<?php endif;?>
