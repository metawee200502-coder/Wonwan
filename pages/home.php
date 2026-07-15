<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// ─── Breadcrumb ───────────────────────────────────────────
$breadcrumbs = [
    ['label' => 'หน้าหลัก', 'url' => '']
];

// ─── Tab หนังสือแนะนำ ─────────────────────────────────────
$active_tab = $_GET['tab'] ?? 'new';

// ─── Pagination ───────────────────────────────────────────
$per_page     = 8;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

// ─── Map tab → condition_level ────────────────────────────
$condition_map = [
    'new'      => 'new',
    'used'     => 'good',
    'exchange' => 'exchange',
];
$condition = $condition_map[$active_tab] ?? 'new';

// ─── ดึงข้อมูลหนังสือตาม tab ──────────────────────────────
try {
    // นับจำนวนทั้งหมดสำหรับ pagination
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM books 
        WHERE condition_level = :cond
    ");
    $count_stmt->execute([':cond' => $condition]);
    $total_books = (int)$count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_books / $per_page));

    // ดึงข้อมูลหนังสือ
    $stmt = $pdo->prepare("
        SELECT 
            b.book_id,
            b.title,
            b.author,
            b.condition_level,
            b.price,
            b.image_cover,
            b.stock_quantity
        FROM books b
        WHERE b.condition_level = :cond
        ORDER BY b.book_id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':cond',   $condition,  PDO::PARAM_STR);
    $stmt->bindValue(':limit',  $per_page,   PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─── สถิติ ─────────────────────────────────────────────
    $stat_books = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
    $stat_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

} catch (PDOException $e) {
    $books       = [];
    $total_pages = 1;
    $stat_books  = 0;
    $stat_users  = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วนวรรณ – หน้าหลัก</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
    /* =========================================================
       HOME PAGE STYLES — ใช้ CSS Variables จาก styles.css [1]
       ========================================================= */
    *, *::before, *::after { box-sizing: border-box; }

    /* ── Layout ── */
    .page-wrap {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 40px;
    }

    /* =========================================================
       NAV MENU BAR (เลือกอ่านเล่มใหม่ / ตามหาเล่มโปรด / ลานแลก)
       ========================================================= */
    /* =========================================================
       HERO SECTION
       ========================================================= */
    .hero-section {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 40px;
        padding: 48px 0 44px;
    }
    .hero-content { flex: 1; max-width: 520px; }
    .hero-content h1 {
        font-size: 40px;
        font-weight: 600;
        color: var(--secondary-color);
        margin: 0 0 12px;
        font-family: var(--font-family);
    }
    .hero-content .hero-quote {
        font-size: var(--text-md);
        color: var(--text-muted);
        font-weight: 300;
        line-height: 1.7;
        margin: 0 0 28px;
    }
    .hero-stats { display: flex; gap: 48px; }
    .hero-stat .num {
        display: block;
        font-size: 34px;
        font-weight: 600;
        color: var(--secondary-color);
        font-family: var(--font-family);
        line-height: 1.1;
    }
    .hero-stat .lbl {
        font-size: var(--text-sm);
        color: var(--text-muted);
        font-weight: 300;
    }
    .hero-img-box {
        flex: 1;
        max-width: 380px;
        min-height: 220px;
        background-color: var(--border-color);
        border-radius: var(--radius-lg);
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .hero-img-box img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* =========================================================
       DIGITAL TWIN SECTION
       ========================================================= */
    .dt-section {
        background-color: var(--bg-color);
        padding: 56px 0 52px;
        text-align: center;
    }
    .dt-section h2 {
        font-size: 28px;
        font-weight: 600;
        color: var(--text-dark);
        margin: 0 0 10px;
        font-family: var(--font-family);
    }
    .dt-section .dt-sub {
        font-size: var(--text-md);
        color: var(--text-muted);
        font-weight: 300;
        line-height: 1.7;
        margin: 0 0 32px;
    }
    /* Search bar */
    .dt-search {
        display: flex;
        align-items: center;
        background-color: var(--card-bg);
        border: 1.5px solid var(--border-color);
        border-radius: 32px;
        padding: 12px 16px;
        max-width: 540px;
        margin: 0 auto 40px;
        gap: 10px;
        box-shadow: var(--shadow-card);
    }
    .dt-search input {
        flex: 1;
        border: none;
        outline: none;
        font-family: var(--font-family);
        font-size: var(--text-sm);
        color: var(--text-dark);
        background: transparent;
    }
    .dt-search input::placeholder { color: var(--text-muted); }
    .dt-search .ico { color: var(--text-muted); display: flex; align-items: center; }
    .dt-search .send-btn {
        background-color: var(--primary-color);
        border: none;
        border-radius: 50%;
        width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        color: var(--text-light);
        flex-shrink: 0;
        transition: background-color 0.2s;
    }
    .dt-search .send-btn:hover { background-color: var(--primary-hover); }
    /* Feature cards */
    .dt-cards {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        max-width: 700px;
        margin: 0 auto;
    }
    .dt-card {
        background-color: var(--card-bg);
        border: 1.5px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 22px 22px 18px;
        text-align: left;
        transition: box-shadow 0.2s, border-color 0.2s;
        cursor: pointer;
    }
    .dt-card:hover {
        border-color: var(--primary-color);
        box-shadow: 0 6px 16px rgba(201,123,139,0.13);
    }
    .dt-card-ico {
        width: 42px; height: 42px;
        border-radius: var(--radius-md);
        display: flex; align-items: center; justify-content: center;
        font-size: 20px;
        margin-bottom: 12px;
    }
    .dt-card-ico.pink { background-color: var(--accent-pink-light); }
    .dt-card-ico.teal { background-color: #D6EAE8; }
    .dt-card h3 {
        font-size: var(--text-md);
        font-weight: 600;
        color: var(--text-dark);
        margin: 0 0 6px;
        font-family: var(--font-family);
    }
    .dt-card p {
        font-size: var(--text-sm);
        color: var(--text-muted);
        margin: 0 0 14px;
        line-height: 1.6;
        font-weight: 300;
    }
    .dt-card a {
        font-size: var(--text-sm);
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 500;
    }
    .dt-card a:hover { color: var(--primary-hover); }

    /* =========================================================
       BOOK SECTION
       ========================================================= */
    .book-section {
        padding: 48px 0;
        scroll-margin-top: 110px;
    }
    .book-section__title {
        text-align: center;
        font-size: var(--text-xl);
        font-weight: 600;
        color: var(--text-dark);
        margin: 0 0 24px;
        font-family: var(--font-family);
    }

    /* Tabs */
    .book-tabs {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .book-tab {
        padding: 8px 26px;
        border-radius: 24px;
        border: 1.5px solid var(--accent-pink-light);
        background-color: var(--accent-pink-light);
        color: var(--text-dark);
        font-family: var(--font-family);
        font-size: var(--text-sm);
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s;
    }
    .book-tab:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }
    .book-tab.active {
        background-color: var(--secondary-color);
        color: var(--text-light);
        border-color: var(--secondary-color);
    }
    .book-tab.exchange-tab {
        background-color: var(--accent-pink-light);
        color: var(--text-dark);
        border-color: var(--accent-pink-light);
    }
    .book-tab.exchange-tab:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }
    .book-tab.exchange-tab.active {
        background-color: var(--secondary-color);
        color: var(--text-light);
        border-color: var(--secondary-color);
    }

    /* See all */
    .see-all-row {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 16px;
    }
    .see-all-row a {
        font-size: var(--text-sm);
        color: var(--text-dark);
        font-weight: 500;
        text-decoration: none;
    }
    .see-all-row a:hover { color: var(--primary-color); }

    /* Book Grid */
    .book-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
    }

    /* =========================================================
       PAGINATION
       ========================================================= */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        padding: 36px 0 52px;
    }
    .pagination a, .pagination span {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 36px; height: 36px;
        border-radius: var(--radius-md);
        font-family: var(--font-family);
        font-size: var(--text-sm);
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s;
    }
    .pagination a {
        border: 1.5px solid var(--border-color);
        background-color: var(--card-bg);
        color: var(--text-dark);
    }
    .pagination a:hover { border-color: var(--primary-color); color: var(--primary-color); }
    .pagination a.active {
        background-color: var(--primary-color);
        color: var(--text-light);
        border-color: var(--primary-color);
    }
    .pagination span.dots { color: var(--text-muted); border: none; }

    /* =========================================================
       EMPTY STATE
       ========================================================= */
    .empty-state {
        grid-column: 1 / -1;
        text-align: center;
        padding: 48px 0;
        color: var(--text-muted);
        font-family: var(--font-family);
    }
    .empty-state svg { margin-bottom: 12px; opacity: 0.3; }
    .empty-state p { font-size: var(--text-md); margin: 0; }

    /* =========================================================
       RESPONSIVE
       ========================================================= */
    @media (max-width: 1024px) {
        .book-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 768px) {
        .page-wrap { padding: 0 16px; }
        .hero-section { flex-direction: column; }
        .hero-img-box { max-width: 100%; width: 100%; }
        .book-grid { grid-template-columns: repeat(2, 1fr); }
        .dt-cards { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .book-grid { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>

<!-- ── HEADER [3] ─────────────────────────────────────────── -->
<div class="home-sticky-header">
<?php require_once __DIR__ . '/../components/header.php'; ?>

<!-- ── NAV MENU BAR ─────────────────────────────────────── -->
<?php require __DIR__ . '/../components/home_nav.php'; ?>

<!-- ── MAIN ─────────────────────────────────────────────── -->
</div>

<main>
    <div class="page-wrap">

        <!-- ── HERO ───────────────────────────────────────── -->
        <section class="hero-section">
            <div class="hero-content">
                <h1>วนวรรณ</h1>
                <p class="hero-quote">
                    "เชื่อมโยงทุกหน้ากระดาษ ยึดอายุทุกตัวอักษร<br>
                    เพื่อมาตุคแห่งการเรียนรู้ที่ยั่งยืนอย่างแท้จริง"
                </p>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <span class="num"><?= number_format((int)$stat_books) ?></span>
                        <span class="lbl">หนังสือในระบบ</span>
                    </div>
                    <div class="hero-stat">
                        <span class="num"><?= number_format((int)$stat_users) ?></span>
                        <span class="lbl">ผู้ใช้งาน</span>
                    </div>
                </div>
            </div>
            <div class="hero-img-box">
                <img src="/assets/image/hero_banner.jpg"
                     alt="Hero"
                     onerror="this.style.display='none'">
            </div>
        </section>

    </div><!-- /.page-wrap -->

    <!-- ── DIGITAL TWIN SECTION ───────────────────────────── -->
    <section class="dt-section">
        <div class="page-wrap">
            <h2>"ค้นพบเล่มที่ใช่ ในแบบที่เป็นคุณ"</h2>
            <p class="dt-sub">
                ถาม–ตอบ สรุป และอธิบายเนื้อหา<br>
                ช่วยให้คุณเข้าใจหนังสือแต่ละเล่มได้ง่ายและลึกซึ้งยิ่งขึ้น
            </p>

            <!-- AI Search -->
            <div class="dt-search">
                <span class="ico">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                        <line x1="12" y1="19" x2="12" y2="23"/>
                        <line x1="8"  y1="23" x2="16" y2="23"/>
                    </svg>
                </span>
                <input type="text" placeholder="พูดคุยกับหนังสือผ่าน Digital Twin...">
                <span class="ico">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                        <line x1="12" y1="19" x2="12" y2="23"/>
                        <line x1="8"  y1="23" x2="16" y2="23"/>
                    </svg>
                </span>
                <button class="send-btn" aria-label="ส่ง">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                </button>
            </div>

            <!-- Feature Cards -->
            <div class="dt-cards">
                <div class="dt-card">
                    <div class="dt-card-ico pink">📖</div>
                    <h3>Mood Match</h3>
                    <p>ระบบจะค้นหาหนังสือที่เข้ากับบรรยากาศและความสนุกของคุณ
                       เพื่อให้การค้นหาหนังสือเป็นเรื่องง่ายและตรงความต้องการยิ่งขึ้น</p>
                    <a href="#">เริ่มต้นตอนนี้ ›</a>
                </div>
                <div class="dt-card">
                    <div class="dt-card-ico teal">📚</div>
                    <h3>Book Genome</h3>
                    <p>ค้นหาหนังสือการอ่านที่เป็นเอกลักษณ์ของคุณ
                       แล้วพบกับระบบแนะนำหนังสือที่ใช้เทคโนโลยีความสนใจ</p>
                    <a href="#">เริ่มต้นวิเคราะห์ ›</a>
                </div>
            </div>
        </div>
    </section>

    <!-- ── BOOK RECOMMEND SECTION ─────────────────────────── -->
    <section class="book-section" id="book-section">
        <div class="page-wrap">

            <h2 class="book-section__title">หนังสือแนะนำ</h2>

            <!-- Tabs -->
            <div class="book-tabs">
                <a href="?tab=new#book-section"
                   class="book-tab <?= $active_tab === 'new'  ? 'active' : '' ?>">
                   หนังสือมือ 1
                </a>
                <a href="?tab=used#book-section"
                   class="book-tab <?= $active_tab === 'used' ? 'active' : '' ?>">
                   หนังสือมือ 2
                </a>
                <a href="?tab=exchange#book-section"
                   class="book-tab exchange-tab <?= $active_tab === 'exchange' ? 'active' : '' ?>">
                   แลกเปลี่ยน
                </a>
            </div>

            <!-- See All -->
            <div class="see-all-row">
                <a href="/pages/catalog.php?tab=<?= htmlspecialchars($active_tab) ?>">ดูทั้งหมด ›</a>
            </div>

            <!-- Book Grid — ใช้ component book_card.php [5] -->
            <div class="book-grid">
                <?php if (empty($books)): ?>
                    <div class="empty-state">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.2">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M3 9h18M9 21V9"/>
                        </svg>
                        <p>ไม่พบหนังสือในหมวดหมู่นี้</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($books as $row):
                        // ✅ แก้: ตรวจสอบค่า default ทั้งหมด
                        $book = [
                            'id'     => (int)($row['book_id'] ?? 0),
                            'title'  => (string)($row['title'] ?? ''),
                            'author' => (string)($row['author'] ?? ''),
                            'price'  => (float)($row['price'] ?? 0),
                            'badge'  => 'มาใหม่',
                            'image'  => !empty($row['image_cover'])
                                            ? '/assets/image/books/' . htmlspecialchars($row['image_cover'])
                                            : null,
                        ];
                        // กำหนด card_type ตาม condition_level จาก DB
                        $card_type = match($row['condition_level'] ?? 'new') {
                            'new'      => 'new',
                            'good'     => 'used',
                            'exchange' => 'exchange',
                            default    => 'new',
                        };
                        require __DIR__ . '/../components/book_card.php';
                    endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                // แสดงหน้า 1, 2, 3 และ ... และหน้าสุดท้าย
                $show = array_unique([1, 2, 3, $current_page - 1,
                                      $current_page, $current_page + 1,
                                      $total_pages]);
                sort($show);
                $prev = 0;
                foreach ($show as $p):
                    if ($p < 1 || $p > $total_pages) continue;
                    if ($prev > 0 && $p - $prev > 1): ?>
                        <span class="dots">…</span>
                    <?php endif; ?>
                    <a href="?tab=<?= htmlspecialchars($active_tab) ?>&page=<?= $p ?>#book-section"
                       class="<?= $p === $current_page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                    <?php $prev = $p;
                endforeach; ?>
            </div>
            <?php endif; ?>

        </div><!-- /.page-wrap -->
    </section>

</main>

<!-- ── FOOTER ───────────────────────────────────────────── -->
<?php require __DIR__ . '/../components/footer.php'; ?>

<script>
// ── Add to Cart ──────────────────────────────────────────
function addToCart(bookId) {
    fetch('/pages/handlers/cart_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ book_id: bookId, action: 'add' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('เพิ่มหนังสือลงตะกร้าเรียบร้อยแล้ว');
        } else {
            alert(data.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่');
        }
    })
    .catch(() => alert('เกิดข้อผิดพลาด กรุณาลองใหม่'));
}
</script>
</body>
</html>
