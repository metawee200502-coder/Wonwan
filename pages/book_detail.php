<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/book_merchandising.php';
require_once __DIR__ . '/../config/promotions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$source = ($_GET['source'] ?? 'book') === 'exchange' ? 'exchange' : 'book';
if (!$id) {
    http_response_code(404);
    exit('ไม่พบรายการหนังสือ');
}

function bookImageUrl(?string $value): ?string {
    if (!$value) return null;
    if (preg_match('#^https?://#i', $value)) return $value;
    $value = ltrim(str_replace('\\', '/', $value), '/');
    if (str_starts_with($value, 'pages/')) return '/' . $value;
    if (str_starts_with($value, 'uploads/')) return '/pages/' . $value;
    return '/assets/image/books/' . $value;
}
function reviewerImageUrl(?string $value): ?string {
    if (!$value) return null;
    if (preg_match('#^https?://#i', $value)) return $value;
    $value = ltrim(str_replace('\\', '/', $value), '/');
    return str_starts_with($value, 'uploads/') ? '/pages/' . $value : '/assets/image/' . $value;
}

if ($source === 'exchange') {
    $stmt = $pdo->prepare('SELECT e.*, u.display_name AS seller_name, u.email AS seller_email FROM exchanges e LEFT JOIN users u ON u.user_id = e.owner_id WHERE e.exchange_id = ? AND e.status <> "completed"');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    $isExchange = true;
} else {
    $stmt = $pdo->prepare('SELECT * FROM books WHERE book_id = ? AND status <> "hidden"');
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    $isExchange = false;
    if ($item) {
        if ($item['seller_type'] === 'publisher') {
            $sellerStmt = $pdo->prepare('SELECT company_name AS seller_name, description AS seller_description, profile_image, status AS seller_status FROM publishers WHERE publisher_id = ?');
        } elseif ($item['seller_type'] === 'shop') {
            $sellerStmt = $pdo->prepare('SELECT shop_name AS seller_name, description AS seller_description, profile_image, status AS seller_status FROM shops WHERE shop_id = ?');
        } else {
            $sellerStmt = $pdo->prepare('SELECT COALESCE(NULLIF(display_name,\'\'),email) AS seller_name, bio AS seller_description, profile_image FROM users WHERE user_id = ?');
        }
        $sellerStmt->execute([$item['seller_id']]);
        $item = array_merge($item, $sellerStmt->fetch(PDO::FETCH_ASSOC) ?: []);
    }
}

if (!$item) {
    http_response_code(404);
    exit('ไม่พบรายการหนังสือนี้ หรือรายการถูกปิดแล้ว');
}

$title = $item['title'];
$images = array_values(array_filter([
    bookImageUrl($item['image_cover'] ?? null),
    bookImageUrl($item['image_back'] ?? null),
    bookImageUrl($item['image_inside'] ?? null),
    bookImageUrl($item['image_edge'] ?? null),
]));
$cover = $images[0] ?? null;
$sellerName = $item['seller_name'] ?? ($isExchange ? 'สมาชิกวนวรรณ' : 'ผู้ขายวนวรรณ');
$conditionLabels = ['new' => 'มือหนึ่ง', 'good' => 'สภาพดี', 'fair' => 'สภาพพอใช้', 'old' => 'หนังสือเก่า'];

// ตรวจสอบว่าผู้ใช้ที่ล็อกอินเป็นเจ้าของโพสต์หรือไม่
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$isOwner = false;
if ($currentUserId > 0) {
    if ($isExchange) {
        $isOwner = $currentUserId === (int)($item['owner_id'] ?? 0);
    } else {
        $isOwner = ($item['seller_type'] ?? '') === 'user' && $currentUserId === (int)($item['seller_id'] ?? 0);
    }
}
$condition = $isExchange ? (($item['condition_percent'] ?? 0) . '%') : ($conditionLabels[$item['condition_level']] ?? 'หนังสือ');
$activePromotion=$isExchange?null:wanawanActiveBookPromotion($pdo,(int)$id);

// ข้อมูลสต็อกสินค้า
$stockQuantity = $isExchange ? 1 : (int)($item['stock_quantity'] ?? 0);
$isOutOfStock = !$isExchange && $stockQuantity <= 0;

$sellerName=wanawanSellerName($sellerName,$isExchange?'user':($item['seller_type']??'user'));
$productSellerType=$isExchange?'user':($item['seller_type']??'user');
$productSellerVerified=($item['seller_status']??'')==='approved';
$productSourceTag=match($productSellerType){
    'publisher'=>[$productSellerVerified?'สำนักพิมพ์ยืนยันแล้ว':'สินค้าจากสำนักพิมพ์','publisher'],
    'shop'=>[$productSellerVerified?'ร้านหนังสือยืนยันแล้ว':'สินค้าจากร้านหนังสือ','shop'],
    default=>['ผู้ขายทั่วไป','user'],
};

$chatUrl = (!$isExchange && in_array($item['seller_type'] ?? '', ['publisher', 'shop', 'user'], true))
    ? '/pages/chat.php?' . http_build_query([
        'seller_type' => $item['seller_type'],
        'seller_id' => (int)$item['seller_id'],
        'book_id' => $id,
    ])
    : '#';

$reviewSummary = ['average' => 0, 'total' => 0];
$previewReviews = [];
if (!$isExchange) {
    $reviewSummaryStmt = $pdo->prepare('SELECT COALESCE(AVG(rating),0) average,COUNT(*) total FROM book_reviews WHERE book_id=?');
    $reviewSummaryStmt->execute([$id]);
    $reviewSummary = $reviewSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: $reviewSummary;
    $reviewStmt = $pdo->prepare("SELECT r.*,COALESCE(NULLIF(u.display_name,''),u.email,'สมาชิกวนวรรณ') reviewer_name,u.profile_image FROM book_reviews r LEFT JOIN users u ON u.user_id=r.user_id WHERE r.book_id=? ORDER BY r.created_at DESC LIMIT 3");
    $reviewStmt->execute([$id]);
    $previewReviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
}
ob_start(function(string $html) use($previewReviews):string{$index=0;return preg_replace_callback('#(<article class="review-card">.*?)(</article>)#s',function($match)use(&$index,$previewReviews){$review=$previewReviews[$index++]??null;if(!$review||empty($review['seller_reply']))return$match[0];$reply='<div style="grid-column:2;margin-top:10px;padding:12px 15px;border-left:3px solid #d586a4;border-radius:8px;background:#fff5f8"><strong style="color:#a34f70">คำตอบจากร้าน</strong><p style="margin:6px 0">'.nl2br(htmlspecialchars($review['seller_reply'])).'</p><small style="color:#998782">'.date('d/m/Y H:i',strtotime($review['seller_reply_at'])).'</small></div>';return$match[1].$reply.$match[2];},$html)??$html;});

if ($isExchange) {
    $recStmt = $pdo->prepare('SELECT exchange_id AS id, title, author, image_cover FROM exchanges WHERE exchange_id <> ? AND status = "open" ORDER BY created_at DESC LIMIT 4');
    $recStmt->execute([$id]);
} else {
    $recStmt = $pdo->prepare('SELECT book_id AS id, title, author, price, image_cover, condition_level FROM books WHERE book_id <> ? AND status = "available" AND category <=> ? ORDER BY created_at DESC LIMIT 4');
    $recStmt->execute([$id, $item['category'] ?? null]);
}
$recommended = $recStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$isExchange) $recommended = wanawanSortRecommendedBooks(wanawanAnnotateBooks($pdo, $recommended));

$breadcrumbs = [
    ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'],
    ['label' => $isExchange ? 'ลานแลกเปลี่ยนหนังสือ' : 'หนังสือแนะนำ', 'url' => '/pages/home.php?tab=' . ($isExchange ? 'exchange' : (($item['book_type'] ?? 'used') === 'new' ? 'new' : 'used')) . '#book-section'],
    ['label' => $title, 'url' => ''],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
    * { box-sizing: border-box; }
    .detail-wrap { max-width: 1180px; margin: 0 auto; padding: 36px 40px 70px; }
    .product-layout { display: grid; grid-template-columns: minmax(300px, 500px) 1fr; gap: 54px; align-items: start; }
    .gallery-main { min-height: 520px; background: #f7f3f0; border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
    .gallery-main img { width: 100%; height: 520px; object-fit: contain; }
    .cover-placeholder { color: var(--primary-color); opacity: .45; text-align: center; }
    .cover-placeholder svg { width: 72px; height: 72px; }
    .thumbs { display: flex; gap: 12px; margin-top: 14px; }
    .thumb { width: 74px; height: 74px; padding: 0; border: 2px solid transparent; border-radius: 9px; overflow: hidden; background: #f7f3f0; cursor: pointer; }
    .thumb.active { border-color: var(--secondary-color); }
    .thumb img { width: 100%; height: 100%; object-fit: cover; }
    .type-pill { display: inline-block; padding: 6px 14px; border-radius: 20px; background: #efe2d8; color: var(--text-muted); font-size: var(--text-sm); font-weight: 600; }
    .product-source-tag{display:inline-flex;align-items:center;gap:5px;margin-left:7px;padding:6px 11px;border-radius:6px;font-size:11px;font-weight:700;vertical-align:middle}.product-source-tag::before{content:'✓';width:15px;height:15px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:9px}.product-source-tag--publisher{color:#a14468;background:#f9dce7;border:1px solid #edbfd0}.product-source-tag--publisher::before{background:#c65f86}.product-source-tag--shop{color:#176272;background:#dff2f3;border:1px solid #b9dde0}.product-source-tag--shop::before{background:#268091}.product-source-tag--user{color:#756a6e;background:#f2eeee;border:1px solid #e1d9dc}.product-source-tag--user::before{content:'บุคคล';width:auto;height:auto;background:none;color:#8c7e83;font-size:9px}
    .product-title { color: var(--secondary-color); font-size: 30px; line-height: 1.35; margin: 14px 0 8px; }
    .meta { color: var(--text-muted); font-size: var(--text-sm); line-height: 1.8; }
    .price { color: #d3948b; font-size: 32px; font-weight: 700; margin: 22px 0; }
    .promotion-box{margin:12px 0 20px;padding:15px 17px;border:1px solid #efb6c8;border-radius:12px;background:linear-gradient(135deg,#fff0f5,#fff8f3);display:flex;gap:12px;align-items:center}.promotion-icon{width:42px;height:42px;border-radius:50%;background:#c94f75;color:#fff;display:grid;place-items:center;font-size:20px;flex:0 0 42px}.promotion-box strong{display:block;color:#a83f61;font-size:16px}.promotion-box span{display:block;color:#765f68;font-size:13px;margin-top:3px}.promotion-name{font-weight:700}
    .condition-box, .seller-box, .exchange-box { border: 1px solid #d8ddd4; border-radius: 12px; padding: 20px; margin: 18px 0; background: #fff; }
    .condition-head { display: flex; justify-content: space-between; color: var(--secondary-color); font-weight: 600; }
    .stock-indicator{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;font-size:14px;font-weight:600;margin:12px 0}.stock-low{background:#fff0f0;color:#c0392b;border:1px solid #f5c6cb}.stock-normal{background:#f5f5f5;color:#888;border:1px solid #e0e0e0}.stock-out{background:#fde8e8;color:#a33;border:1px solid #e8b4b4;font-weight:700}
    .action-btn:disabled,.action-btn.disabled{opacity:.45;cursor:not-allowed;pointer-events:none}
    .condition-bar { height: 6px; background: #e8e5e2; border-radius: 5px; margin: 14px 0; overflow: hidden; }
    .condition-bar span { display: block; height: 100%; background: #d5978d; }
    .defects { color: var(--text-muted); font-size: var(--text-sm); line-height: 1.7; white-space: pre-line; }
    .seller-box { display: flex; align-items: center; gap: 12px; }
    .seller-box-link { color: inherit; text-decoration: none; transition: border-color .2s, box-shadow .2s; }
    .seller-box-link:hover { border-color: var(--primary-color); box-shadow: 0 4px 12px rgba(201,123,139,.12); }
    .seller-avatar { width: 46px; height: 46px; border-radius: 50%; background: var(--accent-pink-light); display: grid; place-items: center; color: var(--primary-color); font-weight: 700; overflow: hidden; }
    .seller-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .seller-name { font-weight: 600; color: var(--text-dark); }
    .seller-box .seller-name{display:inline-block;vertical-align:middle}.seller-box .product-source-tag{padding:4px 8px;margin-left:7px;font-size:10px;vertical-align:middle}.seller-box .product-source-tag::before{width:13px;height:13px;font-size:8px}.seller-box .seller-inline-source-tag.product-source-tag--user::before{display:none}
    .seller-sub { font-size: var(--text-xs); color: var(--text-muted); }
    .seller-profile-label { margin-left: auto; color: var(--primary-color); font-size: var(--text-sm); font-weight: 600; }
    .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0; }
    .action-btn { min-height: 50px; border-radius: 9px; border: 1px solid var(--primary-color); font-family: var(--font-family); font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
    .action-primary { background: #d2948a; color: #fff; border-color: #d2948a; }
    .action-outline { background: #fff; color: #d2948a; }
    .action-wide { grid-column: 1 / -1; }
    .exchange-actions { display: flex; flex-direction: column; gap: 12px; }
    .exchange-actions .action-btn { width: 100%; }
    .exchange-actions .twin { background: #d56f95; border-color: #d56f95; color: #fff; }
    .shipping-methods { display: flex; flex-wrap: wrap; gap: 9px; margin-top: 14px; }
    .shipping-method { display: inline-flex; align-items: center; gap: 7px; padding: 8px 13px; border-radius: 20px; background: #f9e8e2; color: #765f56; font-size: var(--text-sm); }
    .shipping-method::before { content: '✓'; width: 19px; height: 19px; display: grid; place-items: center; border-radius: 50%; background: #d3948b; color: #fff; font-size: 11px; font-weight: 700; }
    .detail-tabs { margin-top: 48px; border-bottom: 1px solid #d8ddd4; display: flex; gap: 36px; }
    .detail-tab { padding: 12px 0; color: var(--text-muted); border: 0; border-bottom: 3px solid transparent; background: transparent; font-family: var(--font-family); font-size: var(--text-sm); cursor: pointer; }
    .detail-tab.active { color: var(--secondary-color); border-bottom-color: var(--secondary-color); }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
    .description { padding: 24px 0 50px; color: var(--text-dark); line-height: 1.9; white-space: pre-line; }
    .tech-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px 24px; margin-top: 18px; }
    .tech-item { font-size: var(--text-sm); color: var(--text-muted); }
    .tech-item strong { color: var(--text-dark); }
    .recommend-head { display: flex; justify-content: space-between; align-items: center; margin: 25px 0 20px; }
    .recommend-head h2 { color: var(--secondary-color); margin: 0; font-size: 24px; }
    .recommend-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
    .review-section{margin-top:48px;padding:28px;border:1px solid #eadbd4;border-radius:18px;background:#fff}.review-title-row{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:18px}.review-title-row h2{margin:0;color:var(--secondary-color)}.review-score{display:flex;gap:8px;align-items:baseline}.review-score strong{font-size:27px;color:#d99a27}.review-list{display:grid;gap:12px}.review-card{display:flex;gap:13px;padding:17px;border-radius:13px;background:#fff8f4}.review-avatar{width:45px;height:45px;flex:0 0 45px;border-radius:50%;object-fit:cover;background:#f5dce5;display:grid;place-items:center;color:#b85e7e;font-weight:700}.review-meta{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.review-stars{color:#dda027;letter-spacing:1px}.review-date{font-size:12px;color:#988a84}.review-card p{margin:8px 0 0;line-height:1.65;white-space:pre-wrap}.all-reviews{color:var(--primary-color);font-weight:700;text-decoration:none}.no-reviews{padding:28px;text-align:center;color:var(--text-muted);background:#fff8f4;border-radius:12px}
    @media (max-width: 800px) { .detail-wrap { padding: 24px 18px 50px; } .product-layout { grid-template-columns: 1fr; gap: 28px; } .gallery-main, .gallery-main img { min-height: 380px; height: 380px; } .recommend-grid { grid-template-columns: repeat(2, 1fr); } .tech-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 480px) { .actions { grid-template-columns: 1fr; } .action-wide { grid-column: auto; } .recommend-grid { grid-template-columns: 1fr; } .tech-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php require __DIR__ . '/../components/header.php'; ?>

<main class="detail-wrap">
    <section class="product-layout">
        <div>
            <div class="gallery-main" id="mainGallery">
                <?php if ($cover): ?>
                    <img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($title) ?>" id="mainImage">
                <?php else: ?>
                    <div class="cover-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8"/></svg><div>ยังไม่มีรูปหนังสือ</div></div>
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?><div class="thumbs">
                <?php foreach ($images as $index => $image): ?><button type="button" class="thumb <?= $index === 0 ? 'active' : '' ?>" onclick="selectImage(this, '<?= htmlspecialchars($image, ENT_QUOTES) ?>')"><img src="<?= htmlspecialchars($image) ?>" alt="ภาพหนังสือ <?= $index + 1 ?>"></button><?php endforeach; ?>
            </div><?php endif; ?>
        </div>

        <div>
            <span class="type-pill"><?= htmlspecialchars($isExchange ? 'แลกเปลี่ยน' : $condition) ?></span>
            <span class="product-source-tag product-source-tag--<?=htmlspecialchars($productSourceTag[1])?>"><?=htmlspecialchars($productSourceTag[0])?></span>
            <h1 class="product-title"><?= htmlspecialchars($title) ?></h1>
            <div class="meta">ผู้เขียน: <strong><?= htmlspecialchars($item['author'] ?: '-') ?></strong><?php if (!empty($item['category'])): ?> &nbsp;•&nbsp; หมวดหมู่: <?= htmlspecialchars($item['category']) ?><?php endif; ?></div>

            <?php if ($isExchange): ?>
                <a class="seller-box seller-box-link" href="/pages/exchange_profile.php?user_id=<?= (int)$item['owner_id'] ?>"><div class="seller-avatar"><?= htmlspecialchars(mb_substr($sellerName, 0, 1)) ?></div><div><div class="seller-name"><?= htmlspecialchars($sellerName) ?></div><div class="seller-sub">เจ้าของหนังสือ • พร้อมแลกเปลี่ยน</div></div><span class="seller-profile-label">ดูโปรไฟล์ ›</span></a>
                <div class="exchange-actions">
                    <?php $follow_type='user';$follow_id=(int)$item['owner_id'];$follow_class='action-btn action-outline';require __DIR__.'/../components/follow_button.php';?>
                    <?php if (!$isOwner): ?>
                    <a class="action-btn action-primary" href="/pages/exchange_request.php?exchange_id=<?= $id ?>">ยื่นคำขอแลกเปลี่ยน</a>
                    <a class="action-btn action-outline" href="/pages/chat.php?seller_type=user&amp;seller_id=<?= (int)$item['owner_id'] ?>&amp;exchange_id=<?= $id ?>">คุยกับเจ้าของหนังสือ</a>
                    <a class="action-btn twin" href="/pages/digital_twin.php?source=exchange&amp;id=<?= $id ?>">คุยกับ Digital Twin ของหนังสือเล่มนี้</a>
                    <?php else: ?>
                    <div class="owner-notice" style="padding:16px 20px;border-radius:10px;background:#fff5f0;border:1px solid #ead4c8;color:#8c6a5d;text-align:center;font-weight:600">นี่คือโพสต์แลกเปลี่ยนของคุณ</div>
                    <?php endif; ?>
                </div>
                <div class="exchange-box"><strong>ความต้องการแลกเปลี่ยน</strong><div class="description"><?= nl2br(htmlspecialchars($item['wanted_detail'] ?: 'เปิดรับข้อเสนอหนังสือที่น่าสนใจ')) ?></div></div>
                <div class="exchange-box"><strong>วิธีจัดส่งที่รองรับ</strong><div class="shipping-methods"><?php $shippingMethods=array_values(array_filter(array_map('trim',explode(',',(string)($item['shipping_methods']??''))))); if($shippingMethods): foreach($shippingMethods as $method): ?><span class="shipping-method"><?=htmlspecialchars($method)?></span><?php endforeach; else: ?><span class="seller-sub">ผู้โพสต์ยังไม่ได้ระบุวิธีจัดส่ง</span><?php endif; ?></div></div>
            <?php else: ?>
                <div class="price">฿<?= number_format((float)$item['price'], 2) ?></div>
                <?php if($activePromotion):?><div class="promotion-box"><div class="promotion-icon">%</div><div><strong><?=htmlspecialchars($activePromotion['display_discount'])?> · <?=htmlspecialchars($activePromotion['promotion_name'])?></strong><span><?=htmlspecialchars($activePromotion['display_condition'])?><?php if(!empty($activePromotion['maximum_discount'])):?> · ลดสูงสุด ฿<?=number_format($activePromotion['maximum_discount'],0)?><?php endif;?> · ถึง <?=date('d/m/Y H:i',strtotime($activePromotion['ends_at']))?></span></div></div><?php endif;?>
                <?php if (($item['book_type'] ?? 'used') === 'used'): ?>
                <div class="condition-box"><div class="condition-head"><span>สภาพหนังสือ</span><span><?= htmlspecialchars($condition) ?></span></div><div class="condition-bar"><span style="width:<?= match($item['condition_level']) { 'good' => 90, 'fair' => 70, 'old' => 50, default => 100 } ?>%"></span></div><div class="defects"><?= htmlspecialchars($item['defects_detail'] ?: 'ไม่พบตำหนิที่ผู้ขายระบุ') ?></div></div>
                <?php endif; ?>
                <a class="seller-box seller-box-link" href="/pages/partner_detail.php?type=<?= urlencode($item['seller_type']) ?>&amp;id=<?= (int)$item['seller_id'] ?>"><div class="seller-avatar"><?php if (!empty($item['profile_image'])): ?><img src="<?= htmlspecialchars(bookImageUrl($item['profile_image'])) ?>" alt=""><?php else: ?><?= htmlspecialchars(mb_substr($sellerName, 0, 1)) ?><?php endif; ?></div><div><div class="seller-name"><?= htmlspecialchars($sellerName) ?></div><div class="seller-sub">มีสินค้า <?= $stockQuantity ?> เล่ม</div></div><span class="seller-profile-label">ดูโปรไฟล์ ›</span></a>
                <?php if ($isOutOfStock): ?>
                <div class="stock-indicator stock-out">✕ สินค้าหมดแล้ว</div>
                <?php elseif ($stockQuantity < 10): ?>
                <div class="stock-indicator stock-low">⚠ เหลือเพียง <?= $stockQuantity ?> เล่ม รีบสั่งก่อนหมด!</div>
                <?php else: ?>
                <div class="stock-indicator stock-normal">คลัง <?= number_format($stockQuantity) ?> เล่ม</div>
                <?php endif; ?>
                <?php if (!$isOwner): ?>
                <?php if ($isOutOfStock): ?>
                <div class="actions"><button class="action-btn action-primary" disabled>สินค้าหมด</button><button class="action-btn action-outline" disabled>สินค้าหมด</button><a class="action-btn action-outline action-wide" href="<?=htmlspecialchars($chatUrl)?>">แชทกับคนขาย</a><a class="action-btn action-primary action-wide" href="/pages/digital_twin.php?source=book&amp;id=<?= $id ?>">Digital Twin ของหนังสือเล่มนี้</a></div>
                <?php else: ?>
                <div class="actions"><button class="action-btn action-primary" onclick="addToCart(<?= $id ?>)">เพิ่มลงรถเข็น</button><button class="action-btn action-outline" onclick="addToCart(<?= $id ?>)">ซื้อเลย</button><a class="action-btn action-outline action-wide" href="<?=htmlspecialchars($chatUrl)?>">แชทกับคนขาย</a><a class="action-btn action-primary action-wide" href="/pages/digital_twin.php?source=book&amp;id=<?= $id ?>">Digital Twin ของหนังสือเล่มนี้</a></div>
                <?php endif; ?>
                <?php else: ?>
                <div class="owner-notice" style="padding:16px 20px;border-radius:10px;background:#fff5f0;border:1px solid #ead4c8;color:#8c6a5d;text-align:center;font-weight:600">นี่คือสินค้าที่คุณลงขาย</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <div class="detail-tabs" role="tablist">
        <button type="button" class="detail-tab active" data-tab="summary" role="tab">เรื่องย่อ</button>
        <button type="button" class="detail-tab" data-tab="technical" role="tab">ข้อมูลเทคนิค</button>
        <?php if (!$isExchange): ?><button type="button" class="detail-tab" data-tab="seller-note" role="tab">หมายเหตุจากคนขาย</button><?php endif; ?>
    </div>
    <section class="tab-panel active description" id="tab-summary" role="tabpanel">
        <?= nl2br(htmlspecialchars(($isExchange ? ($item['story_reason'] ?? null) : ($item['synopsis'] ?? null)) ?: 'ยังไม่มีรายละเอียดสำหรับหนังสือเล่มนี้')) ?>
    </section>
    <section class="tab-panel description" id="tab-technical" role="tabpanel">
        <div class="tech-grid">
            <?php
            $technicalData = $isExchange
                ? ['ISBN' => $item['isbn'], 'หมวดหมู่' => $item['category'], 'สภาพหนังสือ' => ($item['condition_percent'] ?? null) ? $item['condition_percent'] . '%' : null, 'รูปแบบที่ต้องการ' => $item['wanted_type']]
                : ['ISBN' => $item['isbn_13'] ?: $item['isbn'], 'สำนักพิมพ์' => $item['publisher_name'], 'รูปแบบ' => $item['format'], 'จำนวนหน้า' => $item['pages'], 'ภาษา' => $item['language'], 'ขนาด' => $item['dimensions'], 'น้ำหนัก' => $item['weight_grams'] ? $item['weight_grams'] . ' กรัม' : null, 'วันที่พิมพ์' => $item['publish_date']];
            foreach ($technicalData as $label => $value): if (!$value) continue; ?>
                <div class="tech-item"><strong><?= htmlspecialchars($label) ?>:</strong> <?= htmlspecialchars((string)$value) ?></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php if (!$isExchange): ?>
    <section class="tab-panel description" id="tab-seller-note" role="tabpanel">
        <?= nl2br(htmlspecialchars($item['defects_detail'] ?: 'ผู้ขายไม่ได้ระบุหมายเหตุเพิ่มเติม')) ?>
    </section>
    <?php endif; ?>

    <?php if (!$isExchange): ?><section class="review-section" id="reviews"><div class="review-title-row"><div><h2>รีวิวจากผู้ซื้อจริง</h2><div class="review-score"><strong><?=number_format((float)$reviewSummary['average'],1)?> ★</strong><span><?=number_format((int)$reviewSummary['total'])?> รีวิว</span></div></div><?php if((int)$reviewSummary['total']>3):?><a class="all-reviews" href="/pages/book_reviews.php?id=<?=$id?>">ดูทั้งหมด ›</a><?php endif;?></div><?php if(!$previewReviews):?><div class="no-reviews">สินค้านี้ยังไม่มีรีวิว เป็นคนแรกที่บอกเล่าความประทับใจได้หลังรับสินค้าสำเร็จ</div><?php else:?><div class="review-list"><?php foreach($previewReviews as $review):?><article class="review-card"><?php $avatar=reviewerImageUrl($review['profile_image']??null);if($avatar):?><img class="review-avatar" src="<?=htmlspecialchars($avatar)?>" alt=""><?php else:?><div class="review-avatar"><?=htmlspecialchars(mb_substr($review['reviewer_name'],0,1))?></div><?php endif;?><div><div class="review-meta"><strong><?=htmlspecialchars($review['reviewer_name'])?></strong><span class="review-stars"><?=str_repeat('★',(int)$review['rating']).str_repeat('☆',5-(int)$review['rating'])?></span><span class="review-date"><?=date('d/m/Y',strtotime($review['created_at']))?></span></div><p><?=htmlspecialchars($review['review_text']?:'ให้คะแนนสินค้าโดยไม่ได้เขียนข้อความเพิ่มเติม')?></p></div></article><?php endforeach;?></div><?php if((int)$reviewSummary['total']>3):?><div style="margin-top:18px;text-align:center"><a class="all-reviews" href="/pages/book_reviews.php?id=<?=$id?>">ดูรีวิวทั้งหมด <?=number_format((int)$reviewSummary['total'])?> รีวิว</a></div><?php endif;?><?php endif;?></section><?php endif; ?>

    <?php if ($recommended): ?><section><div class="recommend-head"><h2>หนังสือที่คุณอาจสนใจ</h2><a href="/pages/home.php#book-section">ดูทั้งหมด</a></div><div class="recommend-grid">
        <?php foreach ($recommended as $rec):
            $book = [
                'id'     => (int)$rec['id'],
                'title'  => (string)$rec['title'],
                'author' => (string)($rec['author'] ?? ''),
                'price'  => (float)($rec['price'] ?? 0),
                'badge'  => $isExchange ? 'แลกเปลี่ยน' : (string)($rec['badge'] ?? ''),
                'image'  => bookImageUrl($rec['image_cover'] ?? null),
            ];
            $card_type = $isExchange
                ? 'exchange'
                : match($rec['condition_level'] ?? 'new') {
                    'new' => 'new',
                    default => 'used',
                };
            require __DIR__ . '/../components/book_card.php';
        endforeach; ?>
    </div></section><?php endif; ?>
</main>

<?php require __DIR__ . '/../components/footer.php'; ?>
<script>
function selectImage(button, src) { const image = document.getElementById('mainImage'); if (image) image.src = src; document.querySelectorAll('.thumb').forEach(el => el.classList.remove('active')); button.classList.add('active'); }
document.querySelectorAll('.detail-tab').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('.detail-tab, .tab-panel').forEach(element => element.classList.remove('active'));
    button.classList.add('active');
    document.getElementById('tab-' + button.dataset.tab)?.classList.add('active');
}));
const productSourceTag=document.querySelector('.product-source-tag');
const sellerNameElement=document.querySelector('.seller-box .seller-name');
if(productSourceTag&&sellerNameElement){
    const sellerTag=productSourceTag.cloneNode(true);
    sellerTag.classList.add('seller-inline-source-tag');
    if(sellerTag.classList.contains('product-source-tag--publisher')) sellerTag.textContent='สำนักพิมพ์';
    else if(sellerTag.classList.contains('product-source-tag--shop')) sellerTag.textContent='ร้านหนังสือ';
    else sellerTag.textContent='ผู้ขายทั่วไป';
    sellerNameElement.insertAdjacentElement('afterend',sellerTag);
}
</script>
</body>
</html>
