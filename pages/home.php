<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/book_merchandising.php';

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
function homeBookImage(?string $value):string{
    if(!$value)return '/assets/image/minilogo.png';
    if(preg_match('#^https?://#i',$value))return $value;
    $value=ltrim(str_replace('\\','/',$value),'/');
    if(str_starts_with($value,'pages/'))return '/'.$value;
    if(str_starts_with($value,'uploads/'))return '/pages/'.$value;
    if(str_starts_with($value,'assets/'))return '/'.$value;
    return '/assets/image/books/'.$value;
}

// ─── ดึงข้อมูลหนังสือตาม tab ──────────────────────────────
try {
    if ($active_tab === 'exchange') {
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM exchanges WHERE status <> 'hidden'");
        $total_books = (int)$count_stmt->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT exchange_id AS book_id, title, author, status,
                   'exchange' AS condition_level, 0 AS price,
                   image_cover, 1 AS stock_quantity,
                   owner_lat AS seller_lat, owner_lng AS seller_lng, owner_province AS seller_province
            FROM exchanges
            WHERE status <> 'hidden'
            ORDER BY exchange_id DESC
            LIMIT :limit OFFSET :offset
        ");
    } else {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM books b LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id WHERE b.condition_level = :cond AND b.status = 'available' AND (b.seller_type='user' OR (b.seller_type='publisher' AND p.status='approved') OR (b.seller_type='shop' AND s.status='approved'))");
        $count_stmt->execute([':cond' => $condition]);
        $total_books = (int)$count_stmt->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT b.book_id, b.title, b.author, b.condition_level, b.category, b.created_at,
                   b.price, b.image_cover, b.stock_quantity,
                   b.seller_lat, b.seller_lng, b.seller_province,
                   (COALESCE((SELECT SUM(oi.quantity) FROM order_items oi INNER JOIN orders o ON o.order_id=oi.order_id WHERE oi.book_id=b.book_id AND o.payment_status NOT IN ('cancelled','failed')),0)*10
                    + (SELECT COUNT(*) FROM wishlists w WHERE w.book_id=b.book_id)*3
                    + COALESCE((SELECT SUM(ci.quantity) FROM cart_items ci WHERE ci.book_id=b.book_id),0)*2
                    + (SELECT COUNT(*) FROM chat_conversations cc WHERE cc.referenced_book_id=b.book_id)*2) recommendation_score
            FROM books b
            LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id
            LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id
            WHERE b.condition_level = :cond AND b.status = 'available' AND (b.seller_type='user' OR (b.seller_type='publisher' AND p.status='approved') OR (b.seller_type='shop' AND s.status='approved'))
            ORDER BY recommendation_score DESC, b.book_id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':cond', $condition, PDO::PARAM_STR);
    }
    $total_pages = max(1, ceil($total_books / $per_page));
    $stmt->bindValue(':limit',  $per_page,   PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,     PDO::PARAM_INT);
    $stmt->execute();
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($active_tab !== 'exchange') {
        $books = wanawanSortRecommendedBooks(wanawanAnnotateBooks($pdo, $books));
    }

    // ─── สถิติ ─────────────────────────────────────────────
    $stat_books = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
    $stat_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

    // ─── หนังสือมาใหม่ (newest 12) ───────────────────────────
    $newestStmt = $pdo->query("
        SELECT b.book_id, b.title, b.author, b.condition_level, b.price,
               b.image_cover, b.stock_quantity, b.seller_type,
               b.seller_lat, b.seller_lng, b.seller_province
        FROM books b
        LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id
        LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id
        WHERE b.status = 'available' AND (b.seller_type='user' OR (b.seller_type='publisher' AND p.status='approved') OR (b.seller_type='shop' AND s.status='approved'))
        ORDER BY b.book_id DESC
        LIMIT 12
    ");
    $newestBooks = $newestStmt->fetchAll(PDO::FETCH_ASSOC);

    // หนังสือเข้าใหม่ล่าสุด 10 อันดับ: รวมทุกหมวดหมู่และทุกประเภทหนังสือที่พร้อมขาย
    $topStmt=$pdo->query("SELECT b.book_id,b.title,b.author,b.price,b.image_cover,b.seller_type,b.seller_id,
        COALESCE((SELECT SUM(oi.quantity) FROM order_items oi INNER JOIN orders o ON o.order_id=oi.order_id WHERE oi.book_id=b.book_id AND o.payment_status NOT IN ('cancelled','failed')),0) AS sold_count,
        COALESCE(p.company_name,s.shop_name,NULLIF(u.display_name,''),u.email,'ผู้ขายวนวรรณ') AS seller_name
        FROM books b
        LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id
        LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id
        LEFT JOIN users u ON b.seller_type='user' AND u.user_id=b.seller_id
        WHERE b.status='available' AND (b.seller_type='user' OR (b.seller_type='publisher' AND p.status='approved') OR (b.seller_type='shop' AND s.status='approved'))
        ORDER BY b.created_at DESC,b.book_id DESC
        LIMIT 10");
    $topBooks=$topStmt->fetchAll(PDO::FETCH_ASSOC);foreach($topBooks as &$topBook){$topBook['seller_name']=wanawanSellerName($topBook['seller_name'],$topBook['seller_type']);$topBook['image_url']=homeBookImage($topBook['image_cover']);}unset($topBook);

} catch (PDOException $e) {
    $books       = [];
    $total_pages = 1;
    $stat_books  = 0;
    $stat_users  = 0;
    $topBooks    = [];
    $newestBooks = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>วนวรรณ – หน้าหลัก</title>
    <link rel="stylesheet" href="/assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
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
        max-width: 500px;
        height: 275px;
        background:linear-gradient(135deg,#f5e3e5,#fff7f2);
        border-radius: var(--radius-lg);
        overflow: hidden;
        position:relative;
        border:1px solid #ead4d8;
        box-shadow:0 12px 34px rgba(91,58,67,.12);
    }
    .top-book-slide{position:absolute;inset:0;display:grid;grid-template-columns:48% 52%;color:inherit;text-decoration:none;opacity:0;visibility:hidden;transform:translateX(12px);transition:opacity .55s ease,transform .55s ease,visibility .55s}
    .top-book-slide.active{opacity:1;visibility:visible;transform:none}
    .top-book-cover{display:grid;place-items:center;padding:22px;background:linear-gradient(145deg,#edd7db,#faede7)}
    .top-book-cover img{width:145px;height:205px;object-fit:cover;border-radius:8px;box-shadow:0 10px 25px rgba(48,33,37,.2)}
    .top-book-detail{position:relative;padding:28px 24px 34px;display:flex;flex-direction:column;justify-content:center;min-width:0;background:#fffaf7}
    .top-book-rank{position:absolute;top:17px;right:18px;padding:4px 9px;border-radius:15px;background:#f6dce6;color:#ae5275;font-size:11px;font-weight:700}
    .top-book-detail h3{margin:0 0 7px;color:var(--secondary-color);font-size:20px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .top-book-author,.top-book-store{margin:0;color:var(--text-muted);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .top-book-store{margin-top:5px}
    .top-book-price{margin-top:17px;color:#c56689;font-size:23px;font-weight:700}
    .top-book-sold{font-size:11px;color:#9a858d;margin-top:3px}
    .top-book-dots{position:absolute;z-index:4;right:20px;bottom:15px;display:flex;gap:6px}
    .top-book-dot{width:6px;height:6px;border:0;border-radius:50%;padding:0;background:#dfc8cf;cursor:pointer;transition:.25s}
    .top-book-dot.active{width:18px;border-radius:6px;background:#c66f90}
    .top-book-empty{height:100%;display:grid;place-items:center;color:var(--text-muted)}

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
        border: 1px solid #d8ddd4;
        border-radius: 9px;
        padding: 6px;
        margin: 0 auto 24px;
        background: #fff;
        gap: 0;
        max-width: max-content;
    }
    .book-tab {
        flex: 1;
        padding: 10px 20px;
        text-align: center;
        border-radius: 7px;
        color: var(--text-dark);
        text-decoration: none;
        font-weight: normal;
        transition: 0.2s;
        background: transparent;
        font-size: var(--text-sm);
        border: none;
        white-space: nowrap;
    }
    .book-tab:hover, .book-tab.exchange-tab:hover {
        background: #f4f5f3;
        color: var(--text-dark);
        border-color: transparent;
    }
    .book-tab.active, .book-tab.exchange-tab.active {
        background: var(--secondary-color);
        color: #fff;
        border-color: transparent;
    }
    .book-tab.active:hover, .book-tab.exchange-tab.active:hover {
        background: var(--secondary-color);
        color: #fff;
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
       NEWEST BOOKS SECTION
       ========================================================= */
    .newest-section {
        padding: 0 0 60px;
    }
    .newest-section__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }
    .newest-section__title {
        font-size: var(--text-xl);
        font-weight: 600;
        color: var(--text-dark);
        margin: 0;
        font-family: var(--font-family);
    }
    .newest-section__title span {
        display: inline-block;
        width: 10px;
        height: 10px;
        background: var(--primary-color);
        border-radius: 50%;
        margin-right: 10px;
        vertical-align: middle;
        position: relative;
        top: -2px;
    }
    .newest-view-all {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 22px;
        background: var(--secondary-color);
        color: #fff;
        font-family: var(--font-family);
        font-size: var(--text-sm);
        font-weight: 500;
        border-radius: 24px;
        text-decoration: none;
        transition: background-color 0.2s, transform 0.2s;
        white-space: nowrap;
    }
    .newest-view-all:hover {
        background: #124453;
        transform: translateY(-1px);
    }
    .newest-view-all svg {
        flex-shrink: 0;
    }
    .newest-divider {
        border: none;
        border-top: 1.5px solid var(--border-color);
        margin: 0 0 52px;
    }

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
        .book-tabs {
            max-width: 100%;
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            justify-content: flex-start;
        }
        .book-tab {
            min-width: max-content;
            flex: 0 0 auto;
            padding: 10px 16px;
        }
    }
    @media (max-width: 480px) {
        .book-grid { grid-template-columns: repeat(2, 1fr); }
        .hero-img-box{height:245px}.top-book-slide{grid-template-columns:43% 57%}.top-book-cover{padding:14px}.top-book-cover img{width:105px;height:155px}.top-book-detail{padding:24px 15px 30px}.top-book-detail h3{font-size:16px}.top-book-price{font-size:19px}.top-book-rank{top:10px;right:10px}
    }
    </style>
</head>
<body>

<!-- ── HEADER [3] ─────────────────────────────────────────── -->
<div class="home-sticky-header">
<?php require_once __DIR__ . '/../components/header.php'; ?>
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
            <div class="hero-img-box" aria-label="10 อันดับหนังสือเข้าใหม่">
                <?php if ($topBooks): ?>
                    <?php foreach ($topBooks as $index => $topBook): ?>
                    <a class="top-book-slide <?= $index === 0 ? 'active' : '' ?>"
                       href="/pages/book_detail.php?id=<?= (int)$topBook['book_id'] ?>">
                        <div class="top-book-cover">
                            <img src="<?= htmlspecialchars($topBook['image_url']) ?>"
                                 alt="ปกหนังสือ <?= htmlspecialchars($topBook['title']) ?>">
                        </div>
                        <div class="top-book-detail">
                            <span class="top-book-rank">เข้าใหม่ #<?= $index + 1 ?></span>
                            <h3><?= htmlspecialchars($topBook['title']) ?></h3>
                            <p class="top-book-author"><?= htmlspecialchars($topBook['author'] ?: 'ไม่ระบุผู้เขียน') ?></p>
                            <p class="top-book-store"><?= htmlspecialchars($topBook['seller_name']) ?></p>
                            <strong class="top-book-price">฿<?= number_format((float)$topBook['price'], 2) ?></strong>
                            <span class="top-book-sold">สั่งซื้อแล้ว <?= number_format((int)$topBook['sold_count']) ?> เล่ม</span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <div class="top-book-dots" aria-label="เลือกหนังสือเข้าใหม่">
                        <?php foreach ($topBooks as $index => $topBook): ?>
                        <button class="top-book-dot <?= $index === 0 ? 'active' : '' ?>" type="button"
                                data-slide="<?= $index ?>" aria-label="แสดงหนังสือเข้าใหม่อันดับ <?= $index + 1 ?>"></button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="top-book-empty">ยังไม่มีหนังสือเข้าใหม่</div>
                <?php endif; ?>
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
            <form class="dt-search" method="GET" action="/pages/digital_twin.php">
                <input type="hidden" name="discover" value="1">
                <input type="text" name="message" placeholder="พูดคุยกับหนังสือผ่าน Digital Twin...">
                <button class="send-btn" type="submit" aria-label="ส่ง">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"/>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                </button>
            </form>

            <!-- Feature Cards -->
            <div class="dt-cards">
                <div class="dt-card" onclick="location.href='/pages/mood_match.php'">
                    <div class="dt-card-ico pink">📖</div>
                    <h3>Mood Match</h3>
                    <p>ระบบจะค้นหาหนังสือที่เข้ากับบรรยากาศและความสนุกของคุณ
                       เพื่อให้การค้นหาหนังสือเป็นเรื่องง่ายและตรงความต้องการยิ่งขึ้น</p>
                    <a href="/pages/mood_match.php">เริ่มต้นตอนนี้ ›</a>
                </div>
                <div class="dt-card" onclick="location.href='/pages/book_genome.php'">
                    <div class="dt-card-ico teal">📚</div>
                    <h3>Book Genome</h3>
                    <p>ค้นหาหนังสือการอ่านที่เป็นเอกลักษณ์ของคุณ
                       แล้วพบกับระบบแนะนำหนังสือที่ใช้เทคโนโลยีความสนใจ</p>
                    <a href="/pages/book_genome.php">เริ่มต้นวิเคราะห์ ›</a>
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
                <a href="?tab=new"
                   class="book-tab <?= $active_tab === 'new'  ? 'active' : '' ?>">
                   หนังสือมือ 1
                </a>
                <a href="?tab=used"
                   class="book-tab <?= $active_tab === 'used' ? 'active' : '' ?>">
                   หนังสือมือ 2
                </a>
                <a href="?tab=exchange"
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
                            'badge'  => $active_tab === 'exchange' ? 'แลกเปลี่ยน' : (string)($row['badge'] ?? ''),
                            'image'  => !empty($row['image_cover'])
                                            ? (preg_match('#^https?://#i', $row['image_cover'])
                                                ? $row['image_cover']
                                                : '/pages/' . ltrim($row['image_cover'], '/'))
                                            : null,
                            'seller_province' => $row['seller_province'] ?? '',
                            'seller_lat'      => $row['seller_lat'] ?? null,
                            'seller_lng'      => $row['seller_lng'] ?? null,
                            'stock_quantity'  => $row['stock_quantity'] ?? 1,
                            'status'          => $row['status'] ?? 'open',
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
                    <a href="?tab=<?= htmlspecialchars($active_tab) ?>&page=<?= $p ?>"
                       class="<?= $p === $current_page ? 'active' : '' ?>">
                        <?= $p ?>
                    </a>
                    <?php $prev = $p;
                endforeach; ?>
            </div>
            <?php endif; ?>

        </div><!-- /.page-wrap -->
    </section>

    <?php if (!empty($newestBooks)): ?>
    <!-- ── NEWEST BOOKS SECTION ──────────────────────────── -->
    <section class="newest-section">
        <div class="page-wrap">
            <hr class="newest-divider">
            <div class="newest-section__head">
                <h2 class="newest-section__title">
                    <span aria-hidden="true"></span>หนังสือมาใหม่จากร้านค้า
                </h2>
                <a class="newest-view-all" href="/pages/catalog.php?sort=latest">
                    ดูทั้งหมด
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            <div class="book-grid">
                <?php foreach ($newestBooks as $row):
                    $book = [
                        'id'     => (int)($row['book_id'] ?? 0),
                        'title'  => (string)($row['title'] ?? ''),
                        'author' => (string)($row['author'] ?? ''),
                        'price'  => (float)($row['price'] ?? 0),
                        'badge'  => '',
                        'image'  => !empty($row['image_cover'])
                                        ? (preg_match('#^https?://#i', $row['image_cover'])
                                            ? $row['image_cover']
                                            : '/pages/' . ltrim($row['image_cover'], '/'))
                                        : null,
                        'seller_type' => (string)($row['seller_type'] ?? ''),
                        'seller_province' => $row['seller_province'] ?? '',
                        'seller_lat'      => $row['seller_lat'] ?? null,
                        'seller_lng'      => $row['seller_lng'] ?? null,
                        'stock_quantity'  => $row['stock_quantity'] ?? 1,
                    ];
                    $card_type = match($row['condition_level'] ?? 'new') {
                        'new'    => 'new',
                        'good'   => 'used',
                        default  => 'new',
                    };
                    require __DIR__ . '/../components/book_card.php';
                endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

</main>

<!-- ── FOOTER ───────────────────────────────────────────── -->
<?php require __DIR__ . '/../components/footer.php'; ?>

<script>
// Restore scroll position instantly
(function() {
    var scrollPos = sessionStorage.getItem('homeScrollPos');
    if (scrollPos) {
        // Use timeout 0 to ensure it overrides browser's default scroll restoration
        setTimeout(function() {
            window.scrollTo(0, parseInt(scrollPos));
        }, 0);
        sessionStorage.removeItem('homeScrollPos');
    }
})();

document.addEventListener('DOMContentLoaded', () => {
    const box = document.querySelector('.hero-img-box'),
          slides = Array.from(document.querySelectorAll('.top-book-slide')),
          dots = Array.from(document.querySelectorAll('.top-book-dot'));
    if (box && slides.length >= 2) {
        let current = 0, timer;
        const show = index => {
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, i) => slide.classList.toggle('active', i === current));
            dots.forEach((dot, i) => dot.classList.toggle('active', i === current));
        };
        const start = () => { clearInterval(timer); timer = setInterval(() => show(current + 1), 1750); };
        dots.forEach(dot => dot.addEventListener('click', event => { event.preventDefault(); show(Number(dot.dataset.slide)); start(); }));
        box.addEventListener('mouseenter', () => clearInterval(timer));
        box.addEventListener('mouseleave', start);
        document.addEventListener('visibilitychange', () => document.hidden ? clearInterval(timer) : start());
        start();
    }

    // Save scroll position when clicking tabs or pagination
    document.querySelectorAll('.book-tab, .pagination a').forEach(link => {
        link.addEventListener('click', () => {
            sessionStorage.setItem('homeScrollPos', Math.round(window.scrollY));
        });
    });
});
</script>
</body>
</html>
