<?php
// components/header.php
// รับค่า $breadcrumbs จากหน้าที่เรียกใช้
// ตัวอย่าง:
// $breadcrumbs = [
//     ['label' => 'หน้าหลัก',              'url' => '/pages/home.php'],
//     ['label' => 'ลานแลกเปลี่ยนหนังสือ',  'url' => '/pages/exchange.php'],
//     ['label' => 'meowmeow book',          'url' => ''],  // หน้าปัจจุบัน ไม่มี url
// ];
$breadcrumbs = $breadcrumbs ?? [];
?>

<style>
/* =====================================================
   HEADER COMPONENT — ใช้ CSS Variables จาก styles.css
   ===================================================== */

/* ---------- Wrapper ---------- */
.site-header {
    background: linear-gradient(90deg, #fdeee2 0%, #f9e3d4 100%);
    box-shadow: var(--shadow-card);
    position: sticky;
    top: 0;
    width: 100%;
    z-index: 1000;
    font-family: var(--font-family);
    box-sizing: border-box;
}

/* ---------- Top Bar ---------- */
.header-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 48px;
    gap: 24px;
}

/* ---------- Logo ---------- */
.header-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    flex-shrink: 0;
}
.header-logo img {
    height: 48px;
    width: auto;
    object-fit: contain;
}
.header-logo__text {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
}
.header-logo__name {
    font-size: 20px;
    font-weight: 600;
    color: var(--secondary-color);
    font-family: var(--font-family);
}
.header-logo__tagline {
    font-size: var(--text-xs);
    color: var(--text-muted);
    font-weight: 300;
    letter-spacing: 0.3px;
}

/* ---------- Search ---------- */
.header-search {
    flex: 1;
    max-width: 460px;
}
.header-search form {
    display: flex;
    align-items: center;
    background-color: var(--card-bg);
    border: 1.5px solid var(--border-color);
    border-radius: 24px;
    padding: 8px 16px;
    gap: 8px;
    transition: border-color 0.2s;
}
.header-search form:focus-within {
    border-color: var(--primary-color);
}
.header-search input {
    flex: 1;
    border: none;
    outline: none;
    font-family: var(--font-family);
    font-size: var(--text-sm);
    color: var(--text-dark);
    background: transparent;
    min-width: 0;
}
.header-search input::placeholder {
    color: var(--text-muted);
}
.header-search button {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    padding: 0;
    flex-shrink: 0;
    transition: color 0.2s;
}
.header-search button:hover {
    color: var(--primary-color);
}

/* ---------- Icons + Avatar ---------- */
.header-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: transparent;
    color: var(--text-dark);
    text-decoration: none;
    transition: background-color 0.2s, color 0.2s;
    position: relative;
}
.icon-btn:hover {
    background-color: rgba(201, 123, 139, 0.10);
    color: var(--primary-color);
}
.icon-btn svg {
    display: block;
}

/* Badge แจ้งเตือน */
.icon-btn__badge {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: var(--primary-color);
    border: 1.5px solid #fdeee2;
}

/* Avatar ผู้ใช้ */
.header-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid var(--border-color);
    transition: border-color 0.2s;
    display: block;
    flex-shrink: 0;
}
.header-avatar:hover {
    border-color: var(--primary-color);
}

/* ---------- Breadcrumb Bar ---------- */
.header-breadcrumb {
    background-color: var(--card-bg);
    border-top: 1px solid var(--border-color);
    padding: 9px 48px;
    font-family: var(--font-family);
    font-size: var(--text-sm);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
}
.header-breadcrumb a {
    color: var(--text-muted);
    text-decoration: none;
    transition: color 0.2s;
    white-space: nowrap;
}
.header-breadcrumb a:hover {
    color: var(--primary-color);
}
.header-breadcrumb__sep {
    color: var(--text-muted);
    font-size: var(--text-xs);
    margin: 0 2px;
    user-select: none;
}
/* ข้อความหน้าปัจจุบัน (ตัวสุดท้าย) */
.header-breadcrumb__current {
    color: var(--primary-color);
    font-weight: 500;
    white-space: nowrap;
}

/* ---------- Responsive ---------- */
@media (max-width: 768px) {
    .header-topbar {
        padding: 10px 16px;
        flex-wrap: wrap;
    }
    .header-search {
        max-width: 100%;
        order: 3;
        flex-basis: 100%;
    }
    .header-breadcrumb {
        padding: 8px 16px;
    }
}
</style>

<!-- ===== TOP BAR ===== -->
<header class="site-header">
    <div class="header-topbar">

        <!-- Logo -->
        <a href="/pages/home.php" class="header-logo">
            <img
                src="/assets/image/logo.png"
                alt="โลโก้ วนวรรณ"
                onerror="this.style.display='none'"
            >
            <div class="header-logo__text">
                <span class="header-logo__name">วนวรรณ</span>
                <span class="header-logo__tagline">Buy • Sell • Exchange</span>
            </div>
        </a>

        <!-- Search Bar -->
        <div class="header-search">
            <form action="/pages/search.php" method="GET" role="search">
                <input
                    type="text"
                    name="q"
                    placeholder="ค้นหาตามชื่อเรื่อง ผู้เขียน หรือ ISBN..."
                    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                    aria-label="ค้นหาหนังสือ"
                >
                <button type="submit" aria-label="ค้นหา">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </button>
            </form>
        </div>

        <!-- Action Icons + Avatar -->
        <div class="header-actions">

            <!-- ตะกร้าสินค้า -->
            <a href="/pages/cart.php" class="icon-btn" aria-label="ตะกร้าสินค้า">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72
                             a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
            </a>

            <!-- รายการโปรด -->
            <a href="/pages/wishlist.php" class="icon-btn" aria-label="รายการโปรด">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67
                             l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06
                             L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </a>

            <!-- การแจ้งเตือน -->
            <a href="#" class="icon-btn" aria-label="การแจ้งเตือน">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                </svg>
                <!-- แสดง badge ถ้ามีการแจ้งเตือน -->
                <span class="icon-btn__badge"></span>
            </a>

            <!-- Avatar / บัญชีผู้ใช้ -->
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="/pages/profile.php" aria-label="โปรไฟล์ของฉัน">
                    <img
                        src="<?= htmlspecialchars($_SESSION['avatar'] ?? '') ?>"
                        alt="<?= htmlspecialchars($_SESSION['email'] ?? 'User') ?>"
                        class="header-avatar"
                        onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['email'] ?? 'U') ?>&background=C97B8B&color=fff&size=40&rounded=true'"
                    >
                </a>
            <?php else: ?>
                <a href="/pages/login.php" class="icon-btn" aria-label="เข้าสู่ระบบ">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </a>
            <?php endif; ?>

        </div>
    </div>

    <!-- ===== BREADCRUMB BAR ===== -->
    <?php if (!empty($breadcrumbs)): ?>
    <nav class="header-breadcrumb" aria-label="Breadcrumb">
        <?php foreach ($breadcrumbs as $i => $crumb):
            $isLast = ($i === count($breadcrumbs) - 1);
        ?>
            <?php if ($i > 0): ?>
                <span class="header-breadcrumb__sep">›</span>
            <?php endif; ?>

            <?php if (!$isLast && !empty($crumb['url'])): ?>
                <a href="<?= htmlspecialchars($crumb['url']) ?>">
                    <?= htmlspecialchars($crumb['label']) ?>
                </a>
            <?php else: ?>
                <span class="header-breadcrumb__current">
                    <?= htmlspecialchars($crumb['label']) ?>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

</header>