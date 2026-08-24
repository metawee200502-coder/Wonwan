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
require_once __DIR__ . '/breadcrumb_history.php';
$headerUnreadCount = 0;
if (!empty($_SESSION['user_id'])) {
    try {
        if (!isset($pdo)) require_once __DIR__ . '/../config/config.php';
        $headerNotificationStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');
        $headerNotificationStmt->execute([(int)$_SESSION['user_id']]);
        $headerUnreadCount = (int)$headerNotificationStmt->fetchColumn();
    } catch (Throwable $e) { $headerUnreadCount = 0; }
}
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
    cursor: text;
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
    width: 100%;
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

/* ---------- Autocomplete ---------- */
.ac-wrap{position:relative;flex:1;min-width:0;width:100%}
.ac-dropdown{display:none;position:absolute;top:calc(100% + 8px);left:-16px;right:-16px;background:#fff;border:1.5px solid var(--border-color);border-radius:14px;box-shadow:0 8px 28px rgba(76,46,40,.12);z-index:2000;overflow:hidden;font-family:var(--font-family)}
.ac-dropdown.open{display:block}
.ac-item{display:flex;align-items:center;gap:11px;padding:9px 14px;cursor:pointer;text-decoration:none;color:var(--text-dark);transition:background .15s;border-bottom:1px solid #f6ede9}
.ac-item:last-child{border-bottom:0}
.ac-item:hover,.ac-item.focused{background:#fdf2ee}
.ac-img{width:36px;height:50px;object-fit:cover;border-radius:5px;flex-shrink:0;background:var(--card-peach)}
.ac-texts{min-width:0;flex:1}
.ac-title{font-size:13px;font-weight:600;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ac-title mark{background:none;color:var(--primary-color);font-weight:700}
.ac-author{font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ac-badge{font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;flex-shrink:0;white-space:nowrap}
.ac-badge--new{background:#dff2f3;color:#176272}
.ac-badge--used{background:#f2eeee;color:#756a6e}
.ac-badge--exchange{background:var(--accent-pink-light);color:var(--text-dark)}
.ac-footer{display:block;text-align:center;padding:9px;font-size:12px;color:var(--primary-color);font-weight:600;border-top:1px solid #f0e6e2;text-decoration:none;transition:background .15s}
.ac-footer:hover{background:#fdf2ee}

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

/* ปุ่มโพสต์หนังสือแบบลัด */
.quick-post-wrapper {
    position: relative;
    display: inline-block;
}
.quick-post-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background-color: var(--primary-color);
    color: var(--text-light, #fff);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: var(--text-sm);
    font-weight: 600;
    text-decoration: none;
    transition: background-color 0.2s, transform 0.2s;
    border: none;
    cursor: pointer;
    font-family: inherit;
}
.quick-post-btn:hover {
    background-color: var(--primary-hover);
    color: var(--text-light, #fff);
    transform: translateY(-1px);
}
.quick-post-btn svg {
    display: block;
}

/* Dropdown เมนูเพิ่มโพสต์ */
.quick-post-dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    min-width: 260px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: opacity 0.2s, transform 0.2s, visibility 0.2s;
    z-index: 1000;
    overflow: hidden;
    border: 1px solid var(--border-color);
}
.quick-post-wrapper:hover .quick-post-dropdown,
.quick-post-wrapper:focus-within .quick-post-dropdown {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.quick-post-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    color: var(--text-dark);
    text-decoration: none;
    font-size: var(--text-sm);
    font-weight: 600;
    transition: background-color 0.2s;
}
.quick-post-item:hover {
    background-color: #fef0f3;
}
.quick-post-item:not(:last-child) {
    border-bottom: 1px solid var(--border-color);
}
.quick-post-icon {
    color: var(--primary-color);
    flex-shrink: 0;
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
@media (max-width: 520px) {
    .header-topbar { padding: 8px 12px; gap: 8px; }
    .header-logo { gap: 6px; }
    .header-logo img { width: 58px; height: 38px; }
    .header-logo__name { font-size: 17px; }
    .header-logo__tagline { display: none; }
    .header-actions { margin-left: auto; gap: 0; }
    .icon-btn { width: 36px; height: 36px; }
    .header-avatar { width: 36px; height: 36px; }
    .header-search form { min-height: 42px; padding: 7px 13px; }
    .header-breadcrumb { padding: 7px 12px; flex-wrap: nowrap; overflow-x: auto; font-size: 12px; }
    .header-breadcrumb__current { max-width: 48vw; overflow: hidden; text-overflow: ellipsis; }
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
            <form action="/pages/search.php" method="GET" role="search" onclick="document.getElementById('headerSearchInput').focus()">
                <div class="ac-wrap">
                    <input
                        type="text"
                        name="q"
                        id="headerSearchInput"
                        placeholder="ค้นหาตามชื่อเรื่อง ผู้เขียน หรือ ISBN..."
                        value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                        aria-label="ค้นหาหนังสือ"
                        autocomplete="off"
                    >
                    <div class="ac-dropdown" id="acDropdown" role="listbox"></div>
                </div>
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

            <!-- ปุ่มโพสต์หนังสือแบบลัด -->
            <?php if (!empty($_SESSION['user_id'])): ?>
            <div class="quick-post-wrapper">
                <button class="quick-post-btn" aria-label="เพิ่มโพสต์">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    เพิ่มโพสต์
                </button>
                <div class="quick-post-dropdown">
                    <a href="/pages/sell_used_book.php" class="quick-post-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="quick-post-icon">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>
                        </svg>
                        โพสต์สินค้ามือ 2
                    </a>
                    <a href="/pages/post_exchange.php" class="quick-post-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="quick-post-icon">
                            <path d="M17 2l4 4-4 4"></path>
                            <path d="M3 11v-1a4 4 0 0 1 4-4h14"></path>
                            <path d="M7 22l-4-4 4-4"></path>
                            <path d="M21 13v1a4 4 0 0 1-4 4H3"></path>
                        </svg>
                        โพสต์สำหรับแลกเปลี่ยนสินค้า
                    </a>
                </div>
            </div>
            <?php else: ?>
            <a href="#" class="quick-post-btn" aria-label="เพิ่มโพสต์" onclick="event.preventDefault(); requireLoginPopup('เข้าสู่ระบบเพื่อโพสต์หนังสือ', 'คุณต้องเข้าสู่ระบบก่อนเพื่อเพิ่มโพสต์ใหม่')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                เพิ่มโพสต์
            </a>
            <?php endif; ?>

            <!-- ตะกร้าสินค้า -->
            <?php if (!empty($_SESSION['user_id'])): ?>
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
            <?php else: ?>
            <a href="#" class="icon-btn" aria-label="ตะกร้าสินค้า" onclick="event.preventDefault(); requireLoginPopup('เข้าสู่ระบบเพื่อดูตะกร้า', 'คุณต้องเข้าสู่ระบบก่อนเพื่อดูตะกร้าสินค้า')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72
                             a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
            </a>
            <?php endif; ?>

            <!-- รายการโปรด -->
            <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="/pages/wishlist.php" class="icon-btn" aria-label="รายการโปรด">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67
                             l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06
                             L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </a>
            <?php else: ?>
            <a href="#" class="icon-btn" aria-label="รายการโปรด" onclick="event.preventDefault(); requireLoginPopup('เข้าสู่ระบบเพื่อดูรายการโปรด', 'คุณต้องเข้าสู่ระบบก่อนเพื่อดูรายการโปรด')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67
                             l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06
                             L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </a>
            <?php endif; ?>

            <!-- การแจ้งเตือน -->
            <?php if (!empty($_SESSION['user_id'])): ?>
            <a href="/pages/notifications.php" class="icon-btn" aria-label="การแจ้งเตือน">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                </svg>
                <!-- แสดง badge ถ้ามีการแจ้งเตือน -->
                <?php if ($headerUnreadCount > 0): ?><span class="icon-btn__badge" title="<?=$headerUnreadCount?> รายการใหม่"></span><?php endif; ?>
            </a>
            <?php else: ?>
            <a href="#" class="icon-btn" aria-label="การแจ้งเตือน" onclick="event.preventDefault(); requireLoginPopup('เข้าสู่ระบบเพื่อดูการแจ้งเตือน', 'คุณต้องเข้าสู่ระบบก่อนเพื่อดูการแจ้งเตือน')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                    <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
                </svg>
            </a>
            <?php endif; ?>

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
<script src="/assets/js/shop-actions.js?v=<?= filemtime(__DIR__ . '/../assets/js/shop-actions.js') ?>" defer></script>
<script src="/assets/js/followers-link.js?v=<?= filemtime(__DIR__ . '/../assets/js/followers-link.js') ?>" defer></script>
<script src="/assets/js/announcement-drag.js?v=<?= filemtime(__DIR__ . '/../assets/js/announcement-drag.js') ?>" defer></script>
<?php if(!empty($_SESSION['user_id'])):?><script src="/assets/js/notification-live.js?v=<?=filemtime(__DIR__.'/../assets/js/notification-live.js')?>" defer></script><?php endif;?>
<script>
(function(){
  var inp=document.getElementById('headerSearchInput'),drop=document.getElementById('acDropdown');
  if(!inp||!drop)return;
  var timer,lastQ='',focusIdx=-1,items=[];
  function escRe(s){return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');}
  function hi(t,q){return q?t.replace(new RegExp('('+escRe(q)+')','gi'),'<mark>$1</mark>'):t;}
  function bc(c){return c==='exchange'?'ac-badge--exchange':c==='used'?'ac-badge--used':'ac-badge--new';}
  function bl(c){return c==='exchange'?'\u0e41\u0e25\u0e01\u0e40\u0e1b\u0e25\u0e35\u0e48\u0e22\u0e19':c==='used'?'\u0e21\u0e37\u0e2d\u0e2a\u0e2d\u0e07':'\u0e21\u0e37\u0e2d\u0e2b\u0e19\u0e36\u0e48\u0e07';}
  function detUrl(it){return '/pages/book_detail.php?source='+(it.type==='exchange'?'exchange':'book')+'&id='+it.id;}
  function close(){drop.classList.remove('open');focusIdx=-1;}
  function render(data,q){
    items=data;focusIdx=-1;
    if(!data.length){close();return;}
    var html='';
    data.forEach(function(it,i){
      html+='<a class="ac-item" href="'+detUrl(it)+'" data-idx="'+i+'">'+'<img class="ac-img" src="'+it.image+'" alt="" loading="lazy">'+'<div class="ac-texts"><div class="ac-title">'+hi(it.title,q)+'</div>'+(it.author?'<div class="ac-author">'+hi(it.author,q)+'</div>':'')+'</div>'+'<span class="ac-badge '+bc(it.cond)+'">'+bl(it.cond)+'</span></a>';
    });
    html+='<a class="ac-footer" href="/pages/search.php?q='+encodeURIComponent(q)+'">\u0e14\u0e39\u0e1c\u0e25\u0e01\u0e32\u0e23\u0e04\u0e49\u0e19\u0e2b\u0e32\u0e17\u0e31\u0e49\u0e07\u0e2b\u0e21\u0e14 \u2192</a>';
    drop.innerHTML=html;
    drop.classList.add('open');
  }
  function doFetch(q){
    if(q===lastQ)return;lastQ=q;
    clearTimeout(timer);
    if(q.length<1){close();return;}
    timer=setTimeout(function(){
      fetch('/pages/handlers/search_autocomplete.php?q='+encodeURIComponent(q))
        .then(function(r){return r.json();})
        .then(function(data){if(inp.value.trim()===q)render(data,q);})
        .catch(function(){});
    },180);
  }
  inp.addEventListener('input',function(){doFetch(inp.value.trim());});
  inp.addEventListener('focus',function(){if(inp.value.trim())doFetch(inp.value.trim());});
  inp.addEventListener('keydown',function(e){
    var rows=drop.querySelectorAll('.ac-item');
    if(!drop.classList.contains('open'))return;
    if(e.key==='ArrowDown'){e.preventDefault();focusIdx=Math.min(focusIdx+1,rows.length-1);}
    else if(e.key==='ArrowUp'){e.preventDefault();focusIdx=Math.max(focusIdx-1,-1);}
    else if(e.key==='Enter'&&focusIdx>=0){e.preventDefault();rows[focusIdx].click();return;}
    else if(e.key==='Escape'){close();return;}
    rows.forEach(function(r,i){r.classList.toggle('focused',i===focusIdx);});
    if(focusIdx>=0&&items[focusIdx])inp.value=items[focusIdx].title;
  });
  document.addEventListener('click',function(e){if(!inp.closest('form').contains(e.target))close();});
  inp.closest('form').addEventListener('submit',function(){close();});
})();
</script>
