<?php
$active_tab = $active_tab ?? 'new';
?>

<style>
.home-sticky-header {
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: var(--shadow-card);
}
.home-sticky-header .site-header {
    position: static;
    box-shadow: none;
}
.nav-menu-bar {
    background-color: var(--accent-pink-light);
    border-bottom: 1px solid var(--border-color);
}
.nav-menu-bar__inner {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.nav-menu-bar a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 13px 32px;
    font-family: var(--font-family);
    font-size: var(--text-sm);
    font-weight: 500;
    color: var(--text-dark);
    text-decoration: none;
    transition: color 0.2s, background-color 0.2s;
    white-space: nowrap;
}
.nav-menu-bar a:hover,
.nav-menu-bar a.active {
    color: var(--primary-color);
    background-color: rgba(201, 123, 139, 0.08);
}
.nav-menu-bar .nav-ico {
    width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.nav-menu-bar .nav-ico svg {
    width: 100%;
    height: 100%;
    display: block;
}
@media (max-width: 768px) {
    .nav-menu-bar__inner {
        justify-content: flex-start;
        padding: 0 8px;
        overflow-x: auto;
        scrollbar-width: none;
    }
    .nav-menu-bar__inner::-webkit-scrollbar { display: none; }
}
</style>

<nav class="nav-menu-bar" aria-label="เมนูหลักหน้าแรก">
    <div class="nav-menu-bar__inner">
        <a href="?tab=new" class="<?= $active_tab === 'new' ? 'active' : '' ?>">
            <span class="nav-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M12 3v18M3 12h18"/>
                    <path d="m6.5 6.5 11 11M17.5 6.5l-11 11" opacity=".45"/>
                </svg>
            </span>
            เลือกอ่านเล่มใหม่
        </a>
        <a href="?tab=used" class="<?= $active_tab === 'used' ? 'active' : '' ?>">
            <span class="nav-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>
                </svg>
            </span>
            ตามหาเล่มโปรด
        </a>
        <a href="?tab=exchange" class="<?= $active_tab === 'exchange' ? 'active' : '' ?>">
            <span class="nav-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 7h11l-3-3M18 7l-3 3"/>
                    <path d="M17 17H6l3 3M6 17l3-3"/>
                </svg>
            </span>
            ลานแลกเปลี่ยนหนังสือ
        </a>
    </div>
</nav>
