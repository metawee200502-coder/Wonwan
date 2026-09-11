<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/book_merchandising.php';

$active_zone = 'new';
$breadcrumbs = [
    ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'],
    ['label' => 'เลือกซื้อเล่มใหม่', 'url' => ''],
];

$keyword   = trim($_GET['q'] ?? '');
$category  = trim($_GET['category'] ?? '');
$publisher = trim($_GET['publisher'] ?? '');
$author    = trim($_GET['author'] ?? '');
$maxPrice  = is_numeric($_GET['price'] ?? null) ? (float)$_GET['price'] : 0;
$minRating = is_numeric($_GET['rating'] ?? null) ? (int)$_GET['rating'] : 0;

$where = ["b.status = 'available'", "(b.book_type = 'new' OR b.condition_level = 'new')", "(b.seller_type='user' OR (b.seller_type='publisher' AND p.status='approved') OR (b.seller_type='shop' AND s.status='approved'))"];
$params = [];
if ($keyword !== '') { $where[] = '(b.title LIKE :keyword OR b.author LIKE :keyword OR b.isbn LIKE :keyword OR b.isbn_13 LIKE :keyword)'; $params[':keyword'] = "%$keyword%"; }
if ($category !== '') { $where[] = 'b.category = :category'; $params[':category'] = $category; }
if ($publisher !== '') { $where[] = 'COALESCE(NULLIF(b.publisher_name, \'\'), p.company_name, s.shop_name) = :publisher'; $params[':publisher'] = $publisher; }
if ($author !== '') { $where[] = 'b.author = :author'; $params[':author'] = $author; }
if ($maxPrice > 0) { $where[] = 'b.price <= :max_price'; $params[':max_price'] = $maxPrice; }
if ($minRating > 0) { $where[] = 'COALESCE((SELECT AVG(r.rating) FROM book_reviews r WHERE r.book_id = b.book_id), 0) >= :min_rating'; $params[':min_rating'] = $minRating; }
$whereSql = implode(' AND ', $where);

$baseSelect = "SELECT b.*,
    COALESCE(NULLIF(b.publisher_name, ''), p.company_name, s.shop_name) AS resolved_publisher_name,
    CASE WHEN b.seller_type='publisher' THEN p.status='approved' WHEN b.seller_type='shop' THEN s.status='approved' ELSE 0 END AS seller_verified,
    COALESCE((SELECT AVG(r.rating) FROM book_reviews r WHERE r.book_id=b.book_id),0) AS avg_rating,
    COALESCE((SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON o.order_id=oi.order_id WHERE oi.book_id=b.book_id AND o.payment_status='paid'),0) AS sold_count
    FROM books b
    LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id
    LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id
    WHERE $whereSql";
$latestStmt = $pdo->prepare($baseSelect . ' ORDER BY b.created_at DESC, b.book_id DESC LIMIT 4');
$latestStmt->execute($params);
$latestBooks = $latestStmt->fetchAll(PDO::FETCH_ASSOC);
$latestBooks = wanawanAnnotateBooks($pdo, $latestBooks);
$bestStmt = $pdo->prepare($baseSelect . ' ORDER BY sold_count DESC, b.book_id DESC LIMIT 4');
$bestStmt->execute($params);
$bestBooks = $bestStmt->fetchAll(PDO::FETCH_ASSOC);
$bestBooks = wanawanSortBestsellerBooks(wanawanAnnotateBooks($pdo, $bestBooks));
$topStmt = $pdo->query("SELECT b.book_id,b.title,b.author,b.price,b.image_cover,b.seller_type,
    COALESCE(p.company_name,s.shop_name,NULLIF(u.display_name,''),u.email,'ผู้ขายวนวรรณ') seller_name,
    SUM(oi.quantity) sold_count
    FROM order_items oi INNER JOIN orders o ON o.order_id=oi.order_id
    INNER JOIN books b ON b.book_id=oi.book_id
    LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id
    LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id
    LEFT JOIN users u ON b.seller_type='user' AND u.user_id=b.seller_id
    WHERE o.payment_status NOT IN('cancelled','failed') AND b.status='available'
      AND (b.book_type='new' OR b.condition_level='new') AND (b.seller_type='user' OR (b.seller_type='publisher' AND p.status='approved') OR (b.seller_type='shop' AND s.status='approved'))
    GROUP BY b.book_id,b.title,b.author,b.price,b.image_cover,b.seller_type,
             p.company_name,s.shop_name,u.display_name,u.email
    ORDER BY sold_count DESC,b.book_id DESC LIMIT 10");
$topBooks = $topStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($topBooks as &$topBook) {
    $topBook['seller_name'] = wanawanSellerName($topBook['seller_name'], $topBook['seller_type']);
    $topBook['image_url'] = zoneImage($topBook['image_cover'] ?? null) ?: '/assets/image/minilogo.png';
}
unset($topBook);

$categories = $pdo->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL AND category <> '' AND (book_type='new' OR condition_level='new') ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$publishers = $pdo->query("SELECT DISTINCT COALESCE(NULLIF(b.publisher_name,''), p.company_name, s.shop_name) AS publisher_name
    FROM books b
    LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id
    LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id
    WHERE b.status='available' AND (b.book_type='new' OR b.condition_level='new') AND (b.seller_type='user' OR (b.seller_type='publisher' AND p.status='approved') OR (b.seller_type='shop' AND s.status='approved'))
      AND COALESCE(NULLIF(b.publisher_name,''), p.company_name, s.shop_name) IS NOT NULL
    ORDER BY publisher_name")->fetchAll(PDO::FETCH_COLUMN);
$authors = $pdo->query("SELECT DISTINCT author FROM books WHERE author IS NOT NULL AND author <> '' AND (book_type='new' OR condition_level='new') ORDER BY author LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
$partnerPublishers = $pdo->query("SELECT p.publisher_id,p.company_name,p.profile_image,p.created_at,
    COUNT(DISTINCT b.book_id) books_count,COALESCE(AVG(r.rating),0) rating,COUNT(DISTINCT r.review_id) review_count,
    COALESCE((SELECT SUM(oi.quantity) FROM order_items oi INNER JOIN orders o ON o.order_id=oi.order_id
        INNER JOIN books sb ON sb.book_id=oi.book_id WHERE sb.seller_type='publisher' AND sb.seller_id=p.publisher_id
        AND (sb.book_type='new' OR sb.condition_level='new') AND o.payment_status NOT IN('cancelled','failed')),0) sold_count
    FROM publishers p
    INNER JOIN books b ON b.seller_type='publisher' AND b.seller_id=p.publisher_id
        AND b.status='available' AND (b.book_type='new' OR b.condition_level='new') AND p.status='approved'
    LEFT JOIN book_reviews r ON r.book_id=b.book_id
    GROUP BY p.publisher_id,p.company_name,p.profile_image,p.created_at,p.followers_count
    ORDER BY sold_count DESC,rating DESC,p.followers_count DESC,p.publisher_id DESC")->fetchAll(PDO::FETCH_ASSOC);
$publisherSalesRank = 0;
foreach ($partnerPublishers as &$partner) {
    $rank = (int)$partner['sold_count'] > 0 ? ++$publisherSalesRank : 0;
    if (strtotime((string)$partner['created_at']) >= strtotime('-1 month')) $partner['store_badge'] = 'สำนักพิมพ์มาใหม่';
    elseif ($rank > 0 && $rank <= 10) $partner['store_badge'] = 'TOP '.$rank;
    elseif ((int)$partner['review_count'] > 0 && (float)$partner['rating'] >= 4) $partner['store_badge'] = 'ร้านแนะนำ';
    else $partner['store_badge'] = '';
}
unset($partner);
$statBooks = (int)$pdo->query("SELECT COUNT(*) FROM books b LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id WHERE b.status='available' AND (b.book_type='new' OR b.condition_level='new') AND (b.seller_type='user' OR (b.seller_type='publisher' AND p.status='approved') OR (b.seller_type='shop' AND s.status='approved'))")->fetchColumn();
$statPublishers = (int)$pdo->query("SELECT COUNT(DISTINCT b.seller_id) FROM books b LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id WHERE b.seller_type='publisher' AND b.status='available' AND (b.book_type='new' OR b.condition_level='new') AND p.status='approved'")->fetchColumn();

function zoneImage(?string $value): ?string {
    if (!$value) return null;
    if (preg_match('#^https?://#i', $value)) return $value;
    $value = ltrim(str_replace('\\', '/', $value), '/');
    return str_starts_with($value, 'uploads/') ? '/pages/' . $value : '/assets/image/books/' . $value;
}

function renderZoneBooks(array $rows): void {
    if (!$rows) { echo '<p class="zone-empty">ไม่พบหนังสือตามตัวกรองที่เลือก</p>'; return; }
    foreach ($rows as $row) {
        $book = [
            'id' => (int)$row['book_id'], 'title' => (string)$row['title'], 'author' => (string)($row['author'] ?? ''),
            'price' => (float)$row['price'], 'badge' => (string)($row['badge'] ?? ''), 'image' => zoneImage($row['image_cover'] ?? null),
            'seller_type' => (string)($row['seller_type'] ?? 'user'), 'seller_verified' => !empty($row['seller_verified']),
            'stock_quantity' => $row['stock_quantity'] ?? 1,
        ];
        $card_type = 'new';
        require __DIR__ . '/../components/book_card.php';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกซื้อเล่มใหม่ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
    * { box-sizing: border-box; }
    .zone-wrap { max-width: 1200px; margin: 0 auto; padding: 0 40px; }
    .zone-hero { display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center; padding: 38px 0 46px; }
    .zone-title { display: flex; gap: 14px; align-items: center; color: var(--secondary-color); font-size: 34px; margin: 0 0 12px; }
    .zone-title svg { width: 38px; height: 38px; fill: currentColor; }
    .zone-quote { color: var(--text-muted); font-size: var(--text-md); line-height: 1.65; max-width: 520px; }
    .zone-stats { display: flex; gap: 70px; margin-top: 28px; }
    .zone-stat strong { display: block; color: #183f37; font-size: 30px; }
    .zone-stat span { color: var(--text-dark); }
    .hero-img-box { width:100%; max-width:500px; height:275px; justify-self:end; background:linear-gradient(135deg,#f5e3e5,#fff7f2); border-radius:var(--radius-lg); overflow:hidden; position:relative; border:1px solid #ead4d8; box-shadow:0 12px 34px rgba(91,58,67,.12); }
    .top-book-slide{position:absolute;inset:0;display:grid;grid-template-columns:48% 52%;color:inherit;text-decoration:none;opacity:0;visibility:hidden;transform:translateX(12px);transition:opacity .55s ease,transform .55s ease,visibility .55s}
    .top-book-slide.active{opacity:1;visibility:visible;transform:none}.top-book-cover{display:grid;place-items:center;padding:22px;background:linear-gradient(145deg,#edd7db,#faede7)}
    .top-book-cover img{width:145px;height:205px;object-fit:cover;border-radius:8px;box-shadow:0 10px 25px rgba(48,33,37,.2)}
    .top-book-detail{position:relative;padding:28px 24px 34px;display:flex;flex-direction:column;justify-content:center;min-width:0;background:#fffaf7}
    .top-book-rank{position:absolute;top:17px;right:18px;padding:4px 9px;border-radius:15px;background:#f6dce6;color:#ae5275;font-size:11px;font-weight:700}
    .top-book-detail h3{margin:0 0 7px;color:var(--secondary-color);font-size:20px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .top-book-author,.top-book-store{margin:0;color:var(--text-muted);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.top-book-store{margin-top:5px}
    .top-book-price{margin-top:17px;color:#c56689;font-size:23px;font-weight:700}.top-book-sold{font-size:11px;color:#9a858d;margin-top:3px}
    .top-book-dots{position:absolute;z-index:4;right:20px;bottom:15px;display:flex;gap:6px}.top-book-dot{width:6px;height:6px;border:0;border-radius:50%;padding:0;background:#dfc8cf;cursor:pointer;transition:.25s}.top-book-dot.active{width:18px;border-radius:6px;background:#c66f90}.top-book-empty{height:100%;display:grid;place-items:center;color:var(--text-muted)}
    .filter-box { border: 1px solid #d8ddd4; border-radius: 14px; padding: 22px 24px; box-shadow: 0 3px 9px rgba(0,0,0,.08); background: #fff; }
    .filter-grid { display: grid; grid-template-columns: 1.5fr repeat(5, 1fr); gap: 12px; align-items: end; }
    .filter-field label { display: block; color: var(--secondary-color); font-weight: 600; font-size: var(--text-sm); margin-bottom: 7px; }
    .filter-field input, .filter-field select { width: 100%; height: 40px; padding: 0 11px; border: 1px solid var(--border-color); border-radius: 8px; background: #fff; font-family: var(--font-family); color: var(--text-dark); }
    .filter-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 14px; }
    .filter-btn { min-width: 120px; height: 38px; border-radius: 8px; border: 1px solid var(--secondary-color); font-family: var(--font-family); cursor: pointer; }
    .filter-submit { background: var(--secondary-color); color: #fff; }
    .filter-reset { background: #fff; color: var(--secondary-color); }
    .zone-section { padding: 34px 0 8px; }
    .section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .section-head h2 { margin: 0; color: var(--secondary-color); font-size: 27px; }
    .section-head a { color: var(--secondary-color); text-decoration: none; font-weight: 600; }
    .publisher-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
    .publisher-card { min-height: 118px; border: 1px solid #d8ddd4; border-radius: 12px; display: flex; align-items: center; gap: 14px; padding: 16px 18px; text-decoration: none; color: var(--secondary-color); font-weight: 600; box-shadow: 0 2px 5px rgba(0,0,0,.07); }
    .publisher-rank { display:inline-flex;align-items:center;width:max-content;margin-top:6px;background:#f6dce6;color:#ae5275;border-radius:12px;padding:3px 9px;font-size:10px;font-weight:700;line-height:1.2; }
    .publisher-logo { width: 44px; height: 44px; border-radius: 8px; background: var(--accent-pink-light); display: grid; place-items: center; overflow: hidden; }
    .publisher-logo img { width: 100%; height: 100%; object-fit: cover; }
    .publisher-info { min-width:0;display:flex;flex-direction:column;align-items:flex-start; }
    .publisher-info strong { display: -webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.35; }
    .publisher-meta { display: block; margin-top: 7px; color: #c49300; font-size: 11px; line-height:1.45; font-weight: 500; }
    .zone-book-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
    .zone-empty { grid-column: 1/-1; text-align: center; padding: 50px; color: var(--text-muted); }
    .zone-content { padding-bottom: 70px; }
    @media (max-width: 1000px) { .filter-grid { grid-template-columns: repeat(3, 1fr); } .zone-book-grid { grid-template-columns: repeat(2, 1fr); } .publisher-grid { grid-template-columns: repeat(3, 1fr); } .publisher-card:nth-child(n+4) { display: none; } }
    @media (max-width: 700px) { .zone-wrap { padding: 0 16px; } .zone-hero { grid-template-columns: 1fr; } .hero-img-box { max-width:none;height:245px; } .top-book-slide{grid-template-columns:43% 57%}.top-book-cover{padding:14px}.top-book-cover img{width:105px;height:155px}.top-book-detail{padding:24px 15px 30px}.top-book-detail h3{font-size:16px}.top-book-price{font-size:19px}.top-book-rank{top:10px;right:10px} .filter-grid { grid-template-columns: 1fr; } .zone-book-grid { grid-template-columns: repeat(2, 1fr); } .publisher-grid { grid-template-columns: repeat(3, 1fr); } .zone-title { font-size: 28px; } }
    </style>
</head>
<body>
<div class="home-sticky-header">
    <?php require __DIR__ . '/../components/header.php'; ?>

</div>

<main class="zone-content">
    <div class="zone-wrap">
        <section class="zone-hero">
            <div><h1 class="zone-title"><svg viewBox="0 0 24 24"><path d="m12 2.7 2.8 5.7 6.3.9-4.6 4.4 1.1 6.3-5.6-3-5.6 3 1.1-6.3L2.9 9.3l6.3-.9L12 2.7Z"/></svg>เลือกซื้อเล่มใหม่</h1><p class="zone-quote">“ให้ทุกเรื่องราวเริ่มต้นที่นี่... หนังสือมือหนึ่งที่คัดสรรมาอย่างดี ส่งตรงความภูมิใจจากสำนักพิมพ์ถึงมือคุณโดยเฉพาะ”</p><div class="zone-stats"><div class="zone-stat"><strong><?= number_format($statBooks) ?></strong><span>หนังสือในระบบ</span></div><div class="zone-stat"><strong><?= number_format($statPublishers) ?></strong><span>สำนักพิมพ์</span></div></div></div>
            <div class="hero-img-box">
                <?php if($topBooks): foreach($topBooks as $index=>$topBook): ?>
                <a class="top-book-slide <?=$index===0?'active':''?>" href="/pages/book_detail.php?id=<?=(int)$topBook['book_id']?>">
                    <div class="top-book-cover"><img src="<?=htmlspecialchars($topBook['image_url'])?>" alt="ปกหนังสือ <?=htmlspecialchars($topBook['title'])?>"></div>
                    <div class="top-book-detail"><span class="top-book-rank">TOP <?=$index+1?></span><h3><?=htmlspecialchars($topBook['title'])?></h3><p class="top-book-author"><?=htmlspecialchars($topBook['author']?:'ไม่ระบุผู้เขียน')?></p><p class="top-book-store"><?=htmlspecialchars($topBook['seller_name'])?></p><strong class="top-book-price">฿<?=number_format((float)$topBook['price'],2)?></strong><span class="top-book-sold">สั่งซื้อแล้ว <?=number_format((int)$topBook['sold_count'])?> เล่ม</span></div>
                </a>
                <?php endforeach; ?><div class="top-book-dots"><?php foreach($topBooks as $index=>$topBook): ?><button class="top-book-dot <?=$index===0?'active':''?>" type="button" data-slide="<?=$index?>" aria-label="แสดงอันดับ <?=$index+1?>"></button><?php endforeach; ?></div>
                <?php else: ?><div class="top-book-empty">ยังไม่มีข้อมูลหนังสือขายดี</div><?php endif; ?>
            </div>
        </section>

        <form class="filter-box" method="get" action="/pages/catalog.php">
            <input type="hidden" name="type" value="new"><input type="hidden" name="section" value="latest">
            <div class="filter-grid">
                <div class="filter-field"><label for="q">ค้นหาตามชื่อ/ผู้แต่ง</label><input id="q" name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="พิมพ์ชื่อหนังสือที่คุณกำลังตามหา..."></div>
                <div class="filter-field"><label for="category">หมวดหมู่</label><select id="category" name="category"><option value="">ทั้งหมด</option><?php foreach($categories as $value): ?><option <?= $category===$value?'selected':'' ?>><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
                <div class="filter-field"><label for="publisher">สำนักพิมพ์</label><select id="publisher" name="publisher"><option value="">ทั้งหมด</option><?php foreach($publishers as $value): ?><option <?= $publisher===$value?'selected':'' ?>><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
                <div class="filter-field"><label for="author">ผู้เขียน</label><select id="author" name="author"><option value="">ทั้งหมด</option><?php foreach($authors as $value): ?><option <?= $author===$value?'selected':'' ?>><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
                <div class="filter-field"><label for="price">ราคาสูงสุด</label><select id="price" name="price"><option value="">ทั้งหมด</option><?php foreach([200,300,500,1000] as $value): ?><option value="<?= $value ?>" <?= $maxPrice===$value?'selected':'' ?>>ไม่เกิน ฿<?= $value ?></option><?php endforeach; ?></select></div>
                <div class="filter-field"><label for="rating">ให้คะแนน</label><select id="rating" name="rating"><option value="">ทั้งหมด</option><?php foreach([5,4,3] as $value): ?><option value="<?= $value ?>" <?= $minRating===$value?'selected':'' ?>><?= $value ?> ดาวขึ้นไป</option><?php endforeach; ?></select></div>
            </div><div class="filter-actions"><button class="filter-btn filter-submit">กรองข้อมูล</button><a class="filter-btn filter-reset" href="/pages/new_books.php" style="display:grid;place-items:center;text-decoration:none">ล้าง</a></div>
        </form>

        <?php if ($partnerPublishers): ?><section class="zone-section"><div class="section-head"><h2>สำนักพิมพ์พันธมิตร</h2><a href="/pages/partners.php?type=publisher">ดูทั้งหมด ›</a></div><div class="publisher-grid"><?php foreach($partnerPublishers as $partner): ?><a class="publisher-card" href="/pages/partner_detail.php?type=publisher&amp;id=<?= (int)$partner['publisher_id'] ?>"><span class="publisher-logo"><?php if($img=zoneImage($partner['profile_image'])): ?><img src="<?= htmlspecialchars($img) ?>" alt=""><?php else: ?><?= htmlspecialchars(mb_substr($partner['company_name'],0,1)) ?><?php endif; ?></span><span class="publisher-info"><strong><?= htmlspecialchars(wanawanSellerName($partner['company_name'],'publisher')) ?></strong><?php if($partner['store_badge']!==''):?><span class="publisher-rank"><?=htmlspecialchars($partner['store_badge'])?></span><?php endif;?><span class="publisher-meta">ขายแล้ว <?=number_format((int)$partner['sold_count'])?> เล่ม · ★ <?= number_format((float)$partner['rating'],1) ?> · <?= number_format((int)$partner['books_count']) ?> รายการ</span></span></a><?php endforeach; ?></div></section><?php endif; ?>

        <section class="zone-section"><div class="section-head"><h2>มาใหม่ล่าสุด</h2><a href="/pages/catalog.php?type=new&section=latest">ดูทั้งหมด ›</a></div><div class="zone-book-grid"><?php renderZoneBooks($latestBooks); ?></div></section>
        <section class="zone-section"><div class="section-head"><h2>หนังสือขายดี</h2><a href="/pages/catalog.php?type=new&section=best">ดูทั้งหมด ›</a></div><div class="zone-book-grid"><?php renderZoneBooks($bestBooks); ?></div></section>
    </div>
</main>

<?php require __DIR__ . '/../components/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const box=document.querySelector('.hero-img-box');
    const slides=Array.from(document.querySelectorAll('.top-book-slide'));
    const dots=Array.from(document.querySelectorAll('.top-book-dot'));
    if(!box||slides.length<2)return;
    let current=0,timer;
    const show=index=>{current=(index+slides.length)%slides.length;slides.forEach((slide,i)=>slide.classList.toggle('active',i===current));dots.forEach((dot,i)=>dot.classList.toggle('active',i===current))};
    const start=()=>{clearInterval(timer);timer=setInterval(()=>show(current+1),1750)};
    dots.forEach(dot=>dot.addEventListener('click',event=>{event.preventDefault();show(Number(dot.dataset.slide));start()}));
    box.addEventListener('mouseenter',()=>clearInterval(timer));
    box.addEventListener('mouseleave',start);
    document.addEventListener('visibilitychange',()=>document.hidden?clearInterval(timer):start());
    start();
});
</script>
</body>
</html>
