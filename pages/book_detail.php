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
    $stmt = $pdo->prepare('SELECT e.*, u.display_name AS seller_name, u.email AS seller_email, u.latitude, u.longitude FROM exchanges e LEFT JOIN users u ON u.user_id = e.owner_id WHERE e.exchange_id = ?');
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
            $sellerStmt = $pdo->prepare('SELECT company_name AS seller_name, description AS seller_description, profile_image, status AS seller_status, NULL as latitude, NULL as longitude FROM publishers WHERE publisher_id = ?');
        } elseif ($item['seller_type'] === 'shop') {
            $sellerStmt = $pdo->prepare('SELECT shop_name AS seller_name, description AS seller_description, profile_image, status AS seller_status, latitude, longitude FROM shops WHERE shop_id = ?');
        } else {
            $sellerStmt = $pdo->prepare('SELECT COALESCE(NULLIF(display_name,\'\'),email) AS seller_name, bio AS seller_description, profile_image, latitude, longitude FROM users WHERE user_id = ?');
        }
        $sellerStmt->execute([$item['seller_id']]);
        $item = array_merge($item, $sellerStmt->fetch(PDO::FETCH_ASSOC) ?: []);
    }
}

if (!$item) {
    http_response_code(404);
    exit('ไม่พบรายการหนังสือนี้ หรือรายการถูกปิดแล้ว');
}

$buyerLat = (float)($_COOKIE['buyer_lat'] ?? 0);
$buyerLng = (float)($_COOKIE['buyer_lng'] ?? 0);
if(empty($buyerLat) && !empty($_SESSION['user_id'])){
    $userLoc=$pdo->prepare('SELECT latitude,longitude FROM users WHERE user_id=?');$userLoc->execute([$_SESSION['user_id']]);$u=$userLoc->fetch(PDO::FETCH_ASSOC);
    if($u&&$u['latitude']){$buyerLat=(float)$u['latitude'];$buyerLng=(float)$u['longitude'];}
}
$bookLat = (float)($isExchange ? ($item['owner_lat'] ?? $item['latitude'] ?? 0) : ($item['seller_lat'] ?? $item['latitude'] ?? 0));
$bookLng = (float)($isExchange ? ($item['owner_lng'] ?? $item['longitude'] ?? 0) : ($item['seller_lng'] ?? $item['longitude'] ?? 0));

$distance = null;
if ($buyerLat != 0 && $buyerLng != 0 && $bookLat != 0 && $bookLng != 0) {
    $lat1 = deg2rad($buyerLat); $lng1 = deg2rad($buyerLng);
    $lat2 = deg2rad($bookLat); $lng2 = deg2rad($bookLng);
    $distance = 6371 * acos(cos($lat1)*cos($lat2)*cos($lng2-$lng1)+sin($lat1)*sin($lat2));
}

function distanceLabelPHP(float $km): string {
    if ($km < 1) return round($km * 1000) . ' ม.';
    if ($km < 10) return number_format($km, 1) . ' กม.';
    return number_format(round($km)) . ' กม.';
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

// ── BookPodcaster Data ─────────────────────────────────────────────────────
$podcastSynopsis = $isExchange ? trim($item['story_reason'] ?? '') : trim($item['synopsis'] ?? '');
if (empty($podcastSynopsis)) { $podcastSynopsis = 'A book available on Wanawan book exchange platform.'; }
$dnaParts = array_filter([ !$isExchange ? ($item['mood'] ?? null) : null, $item['category'] ?? null, !$isExchange ? (($item['format'] ?? null) ? $item['format'] . ' format' : null) : null ]);
$podcastBookDNA = !empty($dnaParts) ? implode(', ', $dnaParts) : (!$isExchange ? 'book' : 'exchange book');
$flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$podcastJsonBlock = json_encode([ 'title' => (string)($item['title'] ?? ''), 'synopsis' => $podcastSynopsis, 'dna' => $podcastBookDNA, 'cover' => (string)($cover ?? '') ], $flags);

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
    'publisher'=>[$productSellerVerified?'สำนักพิมพ์ยืนยันแล้ว':'สำนักพิมพ์','publisher'],
    'shop'=>[$productSellerVerified?'ร้านหนังสือยืนยันแล้ว':'ร้านหนังสือ','shop'],
    default=>['ผู้ขายทั่วไป','user'],
};

$sellerBookCount = 0;
if ($isExchange) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM exchanges WHERE owner_id = ? AND status <> 'completed'");
    $countStmt->execute([$item['owner_id']]);
    $sellerBookCount = (int)$countStmt->fetchColumn();
} else {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE seller_type = ? AND seller_id = ? AND status = 'available'");
    $countStmt->execute([$item['seller_type'], $item['seller_id']]);
    $sellerBookCount = (int)$countStmt->fetchColumn();
}

$chatUrl = (!$isExchange && in_array($item['seller_type'] ?? '', ['publisher', 'shop', 'user'], true))
    ? '/pages/chat.php?' . http_build_query([
        'seller_type' => $item['seller_type'],
        'seller_id' => (int)$item['seller_id'],
        'book_id' => $id,
        't' => time()
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
    $recStmt = $pdo->prepare('SELECT book_id AS id, title, author, price, image_cover, condition_level, stock_quantity FROM books WHERE book_id <> ? AND status = "available" AND category <=> ? ORDER BY created_at DESC LIMIT 4');
    $recStmt->execute([$id, $item['category'] ?? null]);
}
$recommended = $recStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$isExchange) $recommended = wanawanSortRecommendedBooks(wanawanAnnotateBooks($pdo, $recommended));

if (($_GET['source'] ?? '') === 'mood_match') {
    $breadcrumbs = [
        ['label' => '< Mood Match', 'url' => '/pages/mood_match.php'],
        ['label' => $title, 'url' => ''],
    ];
} else {
    $breadcrumbs = [
        ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'],
        ['label' => $isExchange ? 'ลานแลกเปลี่ยนหนังสือ' : 'หนังสือแนะนำ', 'url' => '/pages/home.php?tab=' . ($isExchange ? 'exchange' : (($item['book_type'] ?? 'used') === 'new' ? 'new' : 'used')) . '#book-section'],
        ['label' => $title, 'url' => ''],
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
    * { box-sizing: border-box; }
    .detail-wrap { max-width: 1180px; margin: 0 auto; padding: 36px 40px 70px; }
    .product-layout { display: grid; grid-template-columns: minmax(300px, 440px) 1fr; gap: 40px; align-items: start; }
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
    .seller-avatar { width: 46px; height: 46px; border-radius: 50%; background: var(--accent-pink-light); display: grid; place-items: center; color: var(--primary-color); font-weight: 700; overflow: hidden; flex: 0 0 46px; }
    .seller-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .seller-info-wrapper { flex: 1; min-width: 0; }
    .seller-name { font-weight: 600; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .seller-sub { font-size: var(--text-xs); color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .seller-profile-label { margin-left: auto; color: var(--primary-color); font-size: var(--text-sm); font-weight: 600; flex: 0 0 auto; white-space: nowrap; }
    .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 20px 0; }
    .action-btn { min-height: 50px; border-radius: 9px; border: 1px solid var(--primary-color); font-family: var(--font-family); font-size: 15px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
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
    @media (max-width: 950px) { .detail-wrap { padding: 24px 18px 50px; } .product-layout { grid-template-columns: 1fr; gap: 28px; } .gallery-main { min-height: unset; height: auto; padding: 20px 0; } .gallery-main img { max-height: 420px; height: auto; } .recommend-grid { grid-template-columns: repeat(2, 1fr); } .tech-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 480px) { .actions { grid-template-columns: 1fr; } .action-wide { grid-column: auto; } .recommend-grid { grid-template-columns: repeat(2, 1fr); } .tech-grid { grid-template-columns: 1fr; } }
    /* ── BookPodcaster Widget CSS ──────────────────────────────────────────────── */
    .bpw { margin-top: 18px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; user-select: none; }
    .bpw-generate-btn { width: 100%; display: flex; align-items: center; justify-content: center; gap: 9px; padding: 12px 18px; border-radius: 14px; border: none; cursor: pointer; background: linear-gradient(135deg, #7c3aed, #4f46e5); color: #fff; font-size: 14px; font-weight: 600; letter-spacing: .01em; box-shadow: 0 6px 24px rgba(109,40,217,.35); transition: transform .15s, box-shadow .15s; }
    .bpw-generate-btn:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(109,40,217,.45); } .bpw-generate-btn:active { transform: scale(.97); }
    .bpw-generate-btn svg { flex-shrink: 0; transition: transform .2s; } .bpw-generate-btn:hover svg { transform: scale(1.15); }
    .bpw-loading, .bpw-error, .bpw-player { border-radius: 16px; border: 1px solid rgba(255,255,255,.10); background: rgba(255,255,255,.05); backdrop-filter: blur(18px); box-shadow: 0 8px 32px rgba(0,0,0,.18); }
    .bpw-loading { padding: 22px; display: flex; flex-direction: column; align-items: center; gap: 12px; }
    .bpw-wave { display: flex; align-items: center; gap: 2.5px; height: 36px; } .bpw-bar { width: 3px; border-radius: 99px; background: #a78bfa; }
    @keyframes bpwBar { 0%,100%{height:3px;opacity:.4} 50%{height:26px;opacity:1} }
    .bpw-loading-text { font-size: 12px; color: rgba(255,255,255,.5); animation: bpwPulse 1.4s ease-in-out infinite; }
    @keyframes bpwPulse { 0%,100%{opacity:.4} 50%{opacity:1} }
    .bpw-error { padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; border-color: rgba(239,68,68,.25); background: rgba(127,29,29,.35); }
    .bpw-error-msg { display: flex; gap: 8px; align-items: flex-start; color: #fca5a5; font-size: 12px; line-height: 1.5; }
    .bpw-retry-btn { align-self: flex-start; padding: 6px 14px; border-radius: 8px; border: none; cursor: pointer; background: rgba(239,68,68,.2); color: #fca5a5; font-size: 12px; font-weight: 600; transition: background .2s; } .bpw-retry-btn:hover { background: rgba(239,68,68,.4); }
    .bpw-player { overflow: hidden; }
    .bpw-header { display: flex; align-items: center; gap: 14px; padding: 16px 18px 14px; background: linear-gradient(135deg, rgba(109,40,217,.45), rgba(67,56,202,.30)); position: relative; }
    .bpw-cover { width: 52px; height: 66px; border-radius: 8px; object-fit: cover; box-shadow: 0 4px 12px rgba(0,0,0,.35); flex-shrink: 0; }
    .bpw-cover-placeholder { width: 52px; height: 66px; border-radius: 8px; flex-shrink: 0; background: linear-gradient(135deg, #6d28d9, #3730a3); display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,.35); } .bpw-cover-placeholder svg { opacity: .6; }
    .bpw-title-block { overflow: hidden; flex: 1; }
    .bpw-eyebrow { font-size: 10px; text-transform: uppercase; letter-spacing: .1em; color: #a78bfa; font-weight: 700; margin-bottom: 3px; }
    .bpw-book-title { color: #fff; font-size: 13px; font-weight: 700; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .bpw-book-dna { color: rgba(255,255,255,.4); font-size: 11px; margin-top: 4px; font-style: italic; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .bpw-regen-btn { position: absolute; top: 10px; right: 10px; width: 28px; height: 28px; border-radius: 8px; border: none; background: transparent; cursor: pointer; color: rgba(255,255,255,.3); display: flex; align-items: center; justify-content: center; transition: color .2s, background .2s; } .bpw-regen-btn:hover { color: rgba(255,255,255,.75); background: rgba(255,255,255,.1); }
    .bpw-viz { padding: 14px 18px 4px; } .bpw-bar-row { display: flex; align-items: center; justify-content: center; gap: 2.5px; height: 36px; }
    .bpw-progress-wrap { padding: 8px 18px 4px; }
    .bpw-seek { width: 100%; height: 4px; border-radius: 99px; appearance: none; cursor: pointer; accent-color: #7c3aed; background: rgba(255,255,255,.12); } .bpw-seek::-webkit-slider-thumb { appearance: none; width: 13px; height: 13px; border-radius: 50%; background: #7c3aed; cursor: pointer; box-shadow: 0 0 0 3px rgba(124,58,237,.3); }
    .bpw-times { display: flex; justify-content: space-between; margin-top: 4px; font-size: 10px; color: rgba(255,255,255,.3); }
    .bpw-controls { display: flex; align-items: center; justify-content: center; gap: 22px; padding: 10px 18px 16px; }
    .bpw-ctrl-btn { background: none; border: none; cursor: pointer; color: rgba(255,255,255,.35); padding: 6px; border-radius: 8px; display: flex; align-items: center; transition: color .2s; } .bpw-ctrl-btn:hover { color: rgba(255,255,255,.8); }
    .bpw-play-btn { width: 52px; height: 52px; border-radius: 50%; border: none; cursor: pointer; background: #7c3aed; color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 16px rgba(109,40,217,.5); transition: background .2s, transform .1s; } .bpw-play-btn:hover { background: #6d28d9; } .bpw-play-btn:active { transform: scale(.94); }
    .bpw-script { margin: 0 14px 14px; padding: 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,.09); background: rgba(255,255,255,.04); }
    .bpw-script-label { font-size: 10px; text-transform: uppercase; letter-spacing: .1em; color: #a78bfa; font-weight: 700; margin-bottom: 8px; }
    .bpw-script-text { font-size: 12px; color: rgba(255,255,255,.6); line-height: 1.7; }
    </style>
</head>
<body>
<?php require __DIR__ . '/../components/header.php'; ?>

<main class="detail-wrap">
    <section class="product-layout">
        <div>
            <div class="gallery-main" id="mainGallery">
                <?php if ($cover): ?>
                    <div style="position:relative; width:100%; height:100%;">
                        <img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars($title) ?>" id="mainImage" style="<?= $isOutOfStock ? 'filter: grayscale(100%); opacity: 0.6;' : '' ?>">
                        <?php if ($isOutOfStock): ?>
                            <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; color: #555; text-shadow: 0 2px 4px rgba(255,255,255,0.8); z-index: 10; pointer-events: none;">สินค้าหมด</div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="cover-placeholder"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8"/></svg><div>ยังไม่มีรูปหนังสือ</div></div>
                <?php endif; ?>
            </div>
            <?php if (count($images) > 1): ?><div class="thumbs">
                <?php foreach ($images as $index => $image): ?><button type="button" class="thumb <?= $index === 0 ? 'active' : '' ?>" onclick="selectImage(this, '<?= htmlspecialchars($image, ENT_QUOTES) ?>')"><img src="<?= htmlspecialchars($image) ?>" alt="ภาพหนังสือ <?= $index + 1 ?>"></button><?php endforeach; ?>
            </div><?php endif; ?>

            <?php /* 🚧 TEMPORARY DISABLE — AI Podcast widget hidden. Remove this comment block to restore.
            <script>window._bpwData = <?= $podcastJsonBlock ?>;</script>
            <div class="bpw" id="book-podcaster">
                <button class="bpw-generate-btn" id="bpw-gen-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3a9 9 0 0 0-9 9v5a3 3 0 0 0 3 3h1a1 1 0 0 0 1-1v-5a1 1 0 0 0-1-1H5v-1a7 7 0 1 1 14 0v1h-2a1 1 0 0 0-1 1v5a1 1 0 0 0 1 1h1a3 3 0 0 0 3-3v-5a9 9 0 0 0-9-9z"/></svg>
                    สร้าง AI Podcast
                </button>
                <div class="bpw-loading" id="bpw-loading" style="display:none">
                    <div class="bpw-wave" id="bpw-load-wave"></div>
                    <div class="bpw-loading-text">✨ กำลังเขียนสคริปต์และสังเคราะห์เสียง…</div>
                </div>
                <div class="bpw-error" id="bpw-error" style="display:none">
                    <div class="bpw-error-msg"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;margin-top:1px;color:#f87171"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg><span id="bpw-error-msg"></span></div>
                    <button class="bpw-retry-btn" id="bpw-retry-btn">↺ ลองอีกครั้ง</button>
                </div>
                <div class="bpw-player" id="bpw-player" style="display:none">
                    <div class="bpw-header">
                        <div id="bpw-cover-wrap"></div>
                        <div class="bpw-title-block">
                            <div class="bpw-eyebrow">AI Podcast</div>
                            <div class="bpw-book-title" id="bpw-display-title"></div>
                            <div class="bpw-book-dna" id="bpw-display-dna"></div>
                        </div>
                        <button class="bpw-regen-btn" id="bpw-regen-btn" title="สร้างใหม่"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35A7.958 7.958 0 0 0 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0 1 12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg></button>
                    </div>
                    <div class="bpw-viz"><div class="bpw-bar-row" id="bpw-viz-bars"></div></div>
                    <div class="bpw-progress-wrap">
                        <input type="range" class="bpw-seek" id="bpw-seek" min="0" max="100" step="0.1" value="0">
                        <div class="bpw-times"><span id="bpw-cur">0:00</span><span id="bpw-dur">--:--</span></div>
                    </div>
                    <div class="bpw-controls">
                        <button class="bpw-ctrl-btn" id="bpw-replay-btn" title="เล่นใหม่"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/></svg></button>
                        <button class="bpw-play-btn" id="bpw-play-btn">
                            <svg id="bpw-play-icon" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="margin-left:2px"><path d="M8 5v14l11-7z"/></svg>
                            <svg id="bpw-pause-icon" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" style="display:none"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                        </button>
                        <button class="bpw-ctrl-btn" id="bpw-script-btn" title="แสดงสคริปต์"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg></button>
                    </div>
                    <div class="bpw-script" id="bpw-script-panel" style="display:none">
                        <div class="bpw-script-label">สคริปต์ที่สร้าง</div>
                        <div class="bpw-script-text" id="bpw-script-text"></div>
                    </div>
                </div>
            </div>
            */ ?>
        </div>

        <div>
            <span class="type-pill"><?= htmlspecialchars($isExchange ? 'แลกเปลี่ยน' : $condition) ?></span>
            <span class="product-source-tag product-source-tag--<?=htmlspecialchars($productSourceTag[1])?>"><?=htmlspecialchars($productSourceTag[0])?></span>
            <?php
            $locText = '';
            $itemProv = $isExchange ? ($item['owner_province'] ?? '') : ($item['seller_province'] ?? '');
            if (!empty($itemProv)) {
                $locText = $itemProv;
            }
            if ($distance !== null) {
                $distStr = distanceLabelPHP($distance);
                $locText = $locText !== '' ? ($locText . ' · ~' . $distStr) : ('~' . $distStr);
            }
            ?>
            <span class="type-pill product-detail-loc" style="background:#fffaf7;color:var(--text-muted);border:1px solid #e2d1c3;margin-left:7px;<?= $locText === '' ? 'display:none;' : 'display:inline-flex;align-items:center;gap:4px;' ?>"
                  data-book-id="<?= !$isExchange ? $id : '' ?>"
                  data-exchange-id="<?= $isExchange ? $id : '' ?>">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--primary-color)"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <span class="loc-text"><?= htmlspecialchars($locText) ?></span>
            </span>
            <h1 class="product-title"><?= htmlspecialchars($title) ?></h1>
            <div class="meta">ผู้เขียน: <strong><?= htmlspecialchars($item['author'] ?: '-') ?></strong><?php if (!empty($item['category'])): ?> &nbsp;•&nbsp; หมวดหมู่: <?= htmlspecialchars($item['category']) ?><?php endif; ?></div>

            <?php if ($isExchange): ?>
                <a class="seller-box seller-box-link" href="/pages/exchange_profile.php?user_id=<?= (int)$item['owner_id'] ?>"><div class="seller-avatar"><?= htmlspecialchars(mb_substr($sellerName, 0, 1)) ?></div><div class="seller-info-wrapper"><div class="seller-name"><?= htmlspecialchars($sellerName) ?></div><div class="seller-sub">เจ้าของหนังสือ <span class="product-detail-loc" style="<?= $locText === '' ? 'display:none;' : 'display:inline-flex; align-items:center; gap:4px; vertical-align:middle;' ?>" data-book-id="<?= !$isExchange ? $id : '' ?>" data-exchange-id="<?= $isExchange ? $id : '' ?>">• <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:var(--primary-color)"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span class="loc-text" style="transform:translateY(1px)"><?= htmlspecialchars($locText) ?></span></span></div></div><span class="seller-profile-label">ดูโปรไฟล์ ›</span></a>
                <div class="exchange-actions">
                    <?php $follow_type='user';$follow_id=(int)$item['owner_id'];$follow_class='action-btn action-outline';require __DIR__.'/../components/follow_button.php';?>
                    <?php if (!$isOwner): ?>
                    <?php if (in_array($item['status'], ['in_progress', 'completed'])): ?>
                        <div class="owner-notice" style="padding:16px 20px;border-radius:10px;background:#f5f5f5;border:1px solid #ddd;color:#666;text-align:center;font-weight:600">
                            <?= $item['status'] === 'in_progress' ? 'กำลังเจรจา' : 'แลกเปลี่ยนแล้ว' ?>
                        </div>
                    <?php else: ?>
                        <a class="action-btn action-primary" href="/pages/exchange_request.php?exchange_id=<?= $id ?>">ยื่นคำขอแลกเปลี่ยน</a>
                        <a class="action-btn action-outline" href="/pages/chat.php?seller_type=user&amp;seller_id=<?= (int)$item['owner_id'] ?>&amp;exchange_id=<?= $id ?>&amp;t=<?= time() ?>">แชทพูดคุยเบื้องต้นกับคนขาย</a>
                    <?php endif; ?>
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
                <a class="seller-box seller-box-link" href="/pages/partner_detail.php?type=<?= urlencode($item['seller_type']) ?>&amp;id=<?= (int)$item['seller_id'] ?>"><div class="seller-avatar"><?php if (!empty($item['profile_image'])): ?><img src="<?= htmlspecialchars(bookImageUrl($item['profile_image'])) ?>" alt=""><?php else: ?><?= htmlspecialchars(mb_substr($sellerName, 0, 1)) ?><?php endif; ?></div><div class="seller-info-wrapper"><div class="seller-name"><?= htmlspecialchars($sellerName) ?></div><div class="seller-sub">มีสินค้า <?= number_format($sellerBookCount) ?> เล่ม <span class="product-detail-loc" style="<?= $locText === '' ? 'display:none;' : 'display:inline-flex; align-items:center; gap:4px; vertical-align:middle;' ?>" data-book-id="<?= !$isExchange ? $id : '' ?>" data-exchange-id="<?= $isExchange ? $id : '' ?>">• <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color:var(--primary-color)"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><span class="loc-text" style="transform:translateY(1px)"><?= htmlspecialchars($locText) ?></span></span></div></div><span class="seller-profile-label">ดูโปรไฟล์ ›</span></a>
                <?php if ($isOutOfStock): ?>
                <div class="stock-indicator stock-out">✕ สินค้าหมดแล้ว</div>
                <?php elseif ($stockQuantity < 10): ?>
                <div class="stock-indicator stock-low">⚠ เหลือเพียง <?= $stockQuantity ?> เล่ม รีบสั่งก่อนหมด!</div>
                <?php else: ?>
                <div class="stock-indicator stock-normal">คลัง <?= number_format($stockQuantity) ?> เล่ม</div>
                <?php endif; ?>
                <?php if (!$isOwner): ?>
                <?php if ($isOutOfStock): ?>
                <div class="actions">
                    <button class="action-btn action-primary" disabled style="opacity: 0.5; background: #999; border-color: #999;">ซื้อสินค้า (หมด)</button>
                    <button class="action-btn action-outline" disabled style="opacity: 0.5; color: #999; border-color: #999;">เพิ่มลงตะกร้า (หมด)</button>
                    <button class="action-btn action-primary action-wide" style="background: #7b8893; border-color: #7b8893;" onclick="alert('ระบบจะแจ้งเตือนเมื่อมีสินค้าให้คุณทราบ')">แจ้งเตือนเมื่อมีสินค้า</button>
                    <a class="action-btn action-outline action-wide" href="<?=htmlspecialchars($chatUrl)?>">แชทกับคนขาย</a>
                    <a class="action-btn action-primary action-wide" href="/pages/digital_twin.php?source=book&amp;id=<?= $id ?>">Digital Twin ของหนังสือเล่มนี้</a>
                </div>
                <?php else: ?>
                <div class="actions"><button class="action-btn action-primary" onclick="addToCart(<?= $id ?>)">เพิ่มลงรถเข็น</button><button class="action-btn action-outline" onclick="buyNow(<?= $id ?>)">ซื้อเลย</button><a class="action-btn action-outline action-wide" href="<?=htmlspecialchars($chatUrl)?>">แชทกับคนขาย</a><a class="action-btn action-primary action-wide" href="/pages/digital_twin.php?source=book&amp;id=<?= $id ?>">Digital Twin ของหนังสือเล่มนี้</a></div>
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
                'stock_quantity' => $isExchange ? 1 : ($rec['stock_quantity'] ?? 1),
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

<script>
(function () {
  'use strict';
  const root = document.getElementById('book-podcaster');
  if (!root) return;
  const genBtn = document.getElementById('bpw-gen-btn');
  const loadPanel = document.getElementById('bpw-loading');
  const loadWave = document.getElementById('bpw-load-wave');
  const errPanel = document.getElementById('bpw-error');
  const errMsgEl = document.getElementById('bpw-error-msg');
  const retryBtn = document.getElementById('bpw-retry-btn');
  const playerPanel = document.getElementById('bpw-player');
  const coverWrap = document.getElementById('bpw-cover-wrap');
  const displayTitle = document.getElementById('bpw-display-title');
  const displayDna = document.getElementById('bpw-display-dna');
  const regenBtn = document.getElementById('bpw-regen-btn');
  const vizBars = document.getElementById('bpw-viz-bars');
  const seekBar = document.getElementById('bpw-seek');
  const curEl = document.getElementById('bpw-cur');
  const durEl = document.getElementById('bpw-dur');
  const replayBtn = document.getElementById('bpw-replay-btn');
  const playBtn = document.getElementById('bpw-play-btn');
  const playIcon = document.getElementById('bpw-play-icon');
  const pauseIcon = document.getElementById('bpw-pause-icon');
  const scriptBtn = document.getElementById('bpw-script-btn');
  const scriptPanel = document.getElementById('bpw-script-panel');
  const scriptText = document.getElementById('bpw-script-text');

  const _d = window._bpwData || {};
  const bookTitle = _d.title || '';
  const synopsis = _d.synopsis || '';
  const bookDNA = _d.dna || '';
  const coverUrl = _d.cover || '';

  const audio = new Audio();
  let currentScript = '';
  let isPlaying = false;
  let isDragging = false;
  let audioBlobUrl = null;

  const BAR_COUNT = 22;
  for (let i = 0; i < BAR_COUNT; i++) {
    const bar = document.createElement('span');
    bar.className = 'bpw-bar';
    bar.style.cssText = ['animation: bpwBar ease-in-out infinite', 'animation-delay:' + ((i * 58) % 360) + 'ms', 'animation-duration:' + (520 + (i * 72) % 460) + 'ms'].join(';');
    loadWave.appendChild(bar);
  }

  const vizBarEls = [];
  for (let i = 0; i < BAR_COUNT; i++) {
    const bar = document.createElement('span');
    bar.className = 'bpw-bar';
    bar.style.height = '3px';
    bar.style.opacity = '0.3';
    vizBars.appendChild(bar);
    vizBarEls.push(bar);
  }

  function fmtTime(s) {
    if (isNaN(s) || !isFinite(s)) return '0:00';
    s = Math.max(0, Math.floor(s));
    return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  }

  function showState(state) {
    genBtn.style.display = state === 'idle' ? '' : 'none';
    loadPanel.style.display = state === 'loading' ? '' : 'none';
    errPanel.style.display = state === 'error' ? '' : 'none';
    playerPanel.style.display = state === 'player' ? '' : 'none';
  }

  let vizAnimId = null;
  function startViz() {
    cancelAnimationFrame(vizAnimId);
    function frame() {
      vizBarEls.forEach(function(bar, i) {
        const h = 3 + Math.abs(Math.sin(Date.now() / (550 + i * 72) + i)) * 26;
        bar.style.height = h + 'px';
        bar.style.opacity = 0.35 + (h / 29) * 0.65;
      });
      vizAnimId = requestAnimationFrame(frame);
    }
    frame();
  }
  function stopViz() {
    cancelAnimationFrame(vizAnimId); vizAnimId = null;
    vizBarEls.forEach(function(b) { b.style.height = '3px'; b.style.opacity = '0.3'; });
  }

  function setPlayUI(playing) {
    isPlaying = playing;
    playIcon.style.display = playing ? 'none' : '';
    pauseIcon.style.display = playing ? '' : 'none';
    playing ? startViz() : stopViz();
  }

  function buildCover() {
    coverWrap.innerHTML = '';
    if (coverUrl) {
      const img = document.createElement('img');
      img.src = coverUrl; img.alt = bookTitle; img.className = 'bpw-cover';
      coverWrap.appendChild(img);
    } else {
      const ph = document.createElement('div');
      ph.className = 'bpw-cover-placeholder';
      ph.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3v10.55A4 4 0 1 0 14 17V7h4V3h-6z"/></svg>';
      coverWrap.appendChild(ph);
    }
  }

  function b64toBlob(b64, type) {
    const bstr = atob(b64);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) { u8arr[n] = bstr.charCodeAt(n); }
    return new Blob([u8arr], { type: type });
  }

  async function generate() {
    audio.pause();
    showState('loading');
    scriptText.textContent = '';
    scriptPanel.style.display = 'none';

    try {
      const res = await fetch('../api/generate-podcast.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ bookTitle: bookTitle, synopsis: synopsis, bookDNA: bookDNA }) });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || 'Server error ' + res.status);
      if (!data.audio_base64) throw new Error('ไม่ได้รับข้อมูลเสียง กรุณาลองใหม่');

      currentScript = data.script || 'ไม่พบสคริปต์';
      scriptText.textContent = currentScript;
      buildCover();
      displayTitle.textContent = bookTitle;
      displayDna.textContent = bookDNA;

      if (audioBlobUrl) URL.revokeObjectURL(audioBlobUrl);
      const blob = b64toBlob(data.audio_base64, 'audio/mpeg');
      audioBlobUrl = URL.createObjectURL(blob);
      audio.src = audioBlobUrl;
      
      showState('player');
      audio.play().catch(e => console.log('Auto-play blocked:', e));
    } catch (err) {
      errMsgEl.textContent = err.message || 'เกิดข้อผิดพลาดที่ไม่คาดคิด';
      showState('error');
    }
  }

  audio.addEventListener('play', () => setPlayUI(true));
  audio.addEventListener('pause', () => setPlayUI(false));
  audio.addEventListener('ended', () => { setPlayUI(false); seekBar.value = 100; });
  audio.addEventListener('timeupdate', () => {
    if (!isDragging) {
      const p = (audio.currentTime / (audio.duration || 1)) * 100;
      seekBar.value = isNaN(p) ? 0 : p;
      curEl.textContent = fmtTime(audio.currentTime);
    }
  });
  audio.addEventListener('loadedmetadata', () => {
    durEl.textContent = fmtTime(audio.duration);
  });

  playBtn.addEventListener('click', function() {
    if (!audio.src) return;
    if (audio.paused) audio.play(); else audio.pause();
  });
  replayBtn.addEventListener('click', function() { 
    if (audio.src) { audio.currentTime = 0; audio.play(); } 
  });
  
  seekBar.addEventListener('input', () => { isDragging = true; });
  seekBar.addEventListener('change', () => {
    if (audio.duration) { audio.currentTime = (seekBar.value / 100) * audio.duration; }
    isDragging = false;
  });

  scriptBtn.addEventListener('click', function() { 
    const hidden = scriptPanel.style.display === 'none'; 
    scriptPanel.style.display = hidden ? '' : 'none'; 
  });
  
  genBtn.addEventListener('click', generate);
  retryBtn.addEventListener('click', generate);
  regenBtn.addEventListener('click', generate);
  
  showState('idle');
}());
</script>
</body>
</html>
