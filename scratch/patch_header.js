const fs = require('fs');
let header = fs.readFileSync('components/header.php', 'utf8');
const sidebarCode = fs.readFileSync('scratch/recover5.txt', 'utf8');

// 1. Add CSS
const cssToAdd = `
.mobile-menu-btn {
    display: none;
    background: none;
    border: none;
    color: var(--secondary-color);
    cursor: pointer;
    padding: 4px;
}
@media (max-width: 768px) {
    .mobile-menu-btn { display: block; }
}
</style>`;
header = header.replace('</style>', cssToAdd);

// 2. Add Hamburger Button
const btnCode = `
        <!-- Mobile Menu Toggle -->
        <button class="mobile-menu-btn" id="openMobileMenuBtn" aria-label="เปิดเมนู">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <!-- Logo -->`;
header = header.replace('<!-- Logo -->', btnCode);

// 3. Add Category Tabs and Sidebar
const bottomCode = `
    <!-- ===== CATEGORY TAB BAR ===== -->
    <?php require __DIR__ . '/category_tabs.php'; ?>

` + sidebarCode + `

<script>
document.addEventListener('DOMContentLoaded', function() {
    var openBtn = document.getElementById('openMobileMenuBtn');
    var closeBtn = document.getElementById('closeMobileMenuBtn');
    var sidebar = document.getElementById('mobileSidebar');
    var overlay = document.getElementById('mobileSidebarOverlay');
    if (sidebar && overlay) {
        if (openBtn) {
            openBtn.addEventListener('click', function(e) {
                e.preventDefault();
                sidebar.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        }
        function closeSidebar() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);
    }
});
</script>

</header>`;
header = header.replace('</header>', bottomCode);

// Remove the "</header>" from inside the sidebarCode if it exists because recover5.txt had it
header = header.replace('</aside>\n\n</header>\n\n<script>', '</aside>\n\n<script>');

fs.writeFileSync('components/header.php', header);
console.log('Patched');
