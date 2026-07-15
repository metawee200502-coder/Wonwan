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
?>
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
        <a href="support.php" class="nav-item <?= $active_menu === 'support' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            แชทและการสนับสนุน
        </a>
        <a href="orders.php" class="nav-item <?= $active_menu === 'orders' ? 'active' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            ติดตามคำสั่งซื้อ
        </a>
    </nav>
    <div class="sidebar-profile">
        <div class="profile-avatar">
            <?php if (!empty($_SESSION['profile_image'])): ?>
                <img src="<?= htmlspecialchars($_SESSION['profile_image']) ?>" alt="">
            <?php else: ?>
                <?= htmlspecialchars(mb_substr($_SESSION['display_name'] ?? 'U', 0, 1)) ?>
            <?php endif; ?>
        </div>
        <div>
            <div class="profile-seller"><?= htmlspecialchars($seller_name ?: '-') ?></div>
            <div class="profile-name"><?= htmlspecialchars($_SESSION['display_name'] ?? 'ผู้ใช้') ?></div>
            <div class="profile-role"><?= $role === 'publisher_admin' ? 'สำนักพิมพ์' : 'ร้านค้า' ?></div>
        </div>
    </div>
</aside>
