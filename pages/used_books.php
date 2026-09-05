<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/book_merchandising.php';

$active_zone = 'used';
$breadcrumbs = [
    ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'],
    ['label' => 'คลังหนังสือมือสอง', 'url' => ''],
];

$keyword = trim($_GET['q'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$sellerType = trim($_GET['seller_type'] ?? '');
$category = trim($_GET['category'] ?? '');
$maxPrice = is_numeric($_GET['price'] ?? null) ? (float)$_GET['price'] : 0;
$shopId = filter_input(INPUT_GET, 'shop_id', FILTER_VALIDATE_INT) ?: 0;

$where = ["b.status='available'", "b.book_type='used'", "b.condition_level <> 'new'"];
$params = [];
if ($keyword !== '') { $where[] = '(b.title LIKE :keyword OR b.author LIKE :keyword OR b.isbn LIKE :keyword OR b.isbn_13 LIKE :keyword)'; $params[':keyword'] = "%$keyword%"; }
if (in_array($condition, ['good','fair','old'], true)) { $where[] = 'b.condition_level=:condition'; $params[':condition'] = $condition; }
if (in_array($sellerType, ['shop','publisher','user'], true)) { $where[] = 'b.seller_type=:seller_type'; $params[':seller_type'] = $sellerType; }
if ($category !== '') { $where[] = 'b.category=:category'; $params[':category'] = $category; }
if ($maxPrice > 0) { $where[] = 'b.price<=:max_price'; $params[':max_price'] = $maxPrice; }
if ($shopId > 0) { $where[] = "b.seller_type='shop' AND b.seller_id=:shop_id"; $params[':shop_id'] = $shopId; }
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT b.*, COALESCE(s.shop_name,p.company_name,NULLIF(u.display_name,''),u.email,NULLIF(b.publisher_name,''),'ผู้ขายวนวรรณ') AS seller_name,
    COALESCE(s.profile_image,p.profile_image,u.profile_image) AS seller_image,
    CASE WHEN b.seller_type='publisher' THEN p.status='approved' WHEN b.seller_type='shop' THEN s.status='approved' ELSE 0 END AS seller_verified,
    COALESCE((SELECT AVG(r.rating) FROM book_reviews r WHERE r.book_id=b.book_id),0) AS avg_rating,
    b.seller_lat, b.seller_lng, b.seller_province
    FROM books b
    LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id
    LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id
    LEFT JOIN users u ON b.seller_type='user' AND u.user_id=b.seller_id
    WHERE $whereSql ORDER BY b.created_at DESC,b.book_id DESC LIMIT 8");
$stmt->execute($params);
$usedBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$usedBooks = wanawanAnnotateBooks($pdo, $usedBooks);

$newArrivalBooks = $pdo->query("SELECT b.*,COALESCE(s.shop_name,p.company_name,NULLIF(u.display_name,''),u.email,'ผู้ขายวนวรรณ') seller_name
    FROM books b
    LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id
    LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id
    LEFT JOIN users u ON b.seller_type='user' AND u.user_id=b.seller_id
    WHERE b.status='available' AND b.book_type='used' AND b.condition_level<>'new'
      AND b.created_at>=DATE_SUB(NOW(),INTERVAL 1 MONTH)
    ORDER BY b.created_at DESC,b.book_id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$newArrivalBooks = wanawanAnnotateBooks($pdo, $newArrivalBooks);

$categories = $pdo->query("SELECT DISTINCT category FROM books WHERE status='available' AND book_type='used' AND condition_level<>'new' AND category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$recommendedSellers = $pdo->query("SELECT s.shop_id AS seller_id,'shop' AS seller_type,
    s.shop_name AS seller_name,s.profile_image AS seller_image,s.created_at,
    COUNT(DISTINCT b.book_id) books_count,
    COALESCE((SELECT SUM(oi.quantity) FROM order_items oi INNER JOIN orders o ON o.order_id=oi.order_id
        INNER JOIN books sb ON sb.book_id=oi.book_id WHERE sb.seller_type='shop' AND sb.seller_id=s.shop_id
        AND sb.book_type='used' AND sb.condition_level<>'new' AND o.payment_status NOT IN('cancelled','failed')),0) sold_count,
    COALESCE(AVG(r.rating),0) rating,COUNT(DISTINCT r.review_id) review_count
    FROM shops s
    LEFT JOIN books b ON b.seller_type='shop' AND b.seller_id=s.shop_id
        AND b.book_type='used' AND b.condition_level<>'new' AND b.status='available'
    LEFT JOIN book_reviews r ON r.book_id=b.book_id
    GROUP BY s.shop_id,s.shop_name,s.profile_image,s.created_at,s.followers_count
    ORDER BY sold_count DESC,rating DESC,s.followers_count DESC,s.shop_id DESC")->fetchAll(PDO::FETCH_ASSOC);
$salesRank = 0;
foreach ($recommendedSellers as &$seller) {
    $rank = (int)$seller['sold_count'] > 0 ? ++$salesRank : 0;
    if (strtotime((string)$seller['created_at']) >= strtotime('-1 month')) $seller['store_badge'] = 'ร้านหนังสือมาใหม่';
    elseif ($rank > 0 && $rank <= 10) $seller['store_badge'] = 'TOP '.$rank;
    elseif ((int)$seller['review_count'] > 0 && (float)$seller['rating'] >= 4) $seller['store_badge'] = 'ร้านแนะนำ';
    else $seller['store_badge'] = '';
}
unset($seller);

function usedImage(?string $value): ?string {
    if (!$value) return null;
    if (preg_match('#^https?://#i',$value)) return $value;
    $value=ltrim(str_replace('\\','/',$value),'/');
    return str_starts_with($value,'uploads/') ? '/pages/'.$value : '/assets/image/books/'.$value;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คลังหนังสือมือสอง - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
    *{box-sizing:border-box}.used-wrap{max-width:1200px;margin:auto;padding:0 40px}.used-hero{display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:center;padding:38px 0 46px}.used-title{display:flex;gap:14px;align-items:center;color:var(--secondary-color);font-size:34px;margin:0 0 12px}.used-title svg{width:38px;height:38px}.used-quote{color:var(--text-muted);font-size:var(--text-md);line-height:1.7;max-width:520px}.hero-actions{display:flex;gap:12px;margin-top:24px}.hero-btn{padding:11px 24px;border-radius:10px;border:1px solid #d3948b;text-decoration:none;font-weight:600}.hero-primary{background:#d3948b;color:#fff}.hero-outline{background:#fff;color:#d3948b}.used-arrival-box{width:100%;max-width:500px;height:275px;justify-self:end;position:relative;overflow:hidden;border:1px solid #ead4d8;border-radius:var(--radius-lg);background:linear-gradient(135deg,#f5e3e5,#fff7f2);box-shadow:0 12px 34px rgba(91,58,67,.12)}.arrival-slide{position:absolute;inset:0;display:grid;grid-template-columns:48% 52%;color:inherit;text-decoration:none;opacity:0;visibility:hidden;transform:translateX(12px);transition:opacity .55s ease,transform .55s ease,visibility .55s}.arrival-slide.active{opacity:1;visibility:visible;transform:none}.arrival-cover{display:grid;place-items:center;padding:22px;background:linear-gradient(145deg,#edd7db,#faede7)}.arrival-cover img{width:145px;height:205px;object-fit:cover;border-radius:8px;box-shadow:0 10px 25px rgba(48,33,37,.2)}.arrival-detail{position:relative;padding:28px 24px 34px;display:flex;flex-direction:column;justify-content:center;min-width:0;background:#fffaf7}.arrival-badge{position:absolute;top:17px;right:18px;padding:4px 10px;border-radius:15px;background:#f6dce6;color:#ae5275;font-size:11px;font-weight:700}.arrival-detail h3{margin:0 0 7px;color:var(--secondary-color);font-size:20px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.arrival-author,.arrival-store{margin:0;color:var(--text-muted);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.arrival-store{margin-top:5px}.arrival-price{margin-top:17px;color:#c56689;font-size:23px}.arrival-dots{position:absolute;z-index:4;right:20px;bottom:15px;display:flex;gap:6px}.arrival-dot{width:6px;height:6px;border:0;border-radius:50%;padding:0;background:#dfc8cf;cursor:pointer;transition:.25s}.arrival-dot.active{width:18px;border-radius:6px;background:#c66f90}.arrival-empty{height:100%;display:grid;place-items:center;color:var(--text-muted)}.filter-box{border:1px solid #d8ddd4;border-radius:14px;padding:22px 24px;box-shadow:0 3px 9px rgba(0,0,0,.08);background:#fff}.filter-grid{display:grid;grid-template-columns:1.7fr repeat(4,1fr);gap:14px;align-items:end}.field label{display:block;color:var(--secondary-color);font-weight:600;font-size:var(--text-sm);margin-bottom:7px}.field input,.field select{width:100%;height:40px;padding:0 11px;border:1px solid var(--border-color);border-radius:8px;background:#fff;font-family:var(--font-family)}.filter-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.filter-btn{min-width:115px;height:38px;border-radius:8px;border:1px solid #d3948b;font-family:var(--font-family);cursor:pointer}.filter-submit{background:#d3948b;color:#fff}.filter-reset{display:grid;place-items:center;background:#fff;color:#d3948b;text-decoration:none}.used-section{padding:36px 0 6px}.section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}.section-head h2{margin:0;color:var(--secondary-color);font-size:27px}.section-head a{color:var(--secondary-color);text-decoration:none;font-weight:600}.seller-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.seller-card{min-height:118px;border:1px solid #d8ddd4;border-radius:12px;display:flex;align-items:center;gap:14px;padding:16px 18px;text-decoration:none;color:var(--secondary-color);box-shadow:0 2px 5px rgba(0,0,0,.07)}.seller-rank{display:inline-flex;width:max-content;margin:6px 0 2px;background:#f6dce6;color:#ae5275;border-radius:12px;padding:3px 9px;font-size:10px;font-weight:700;line-height:1.2}.seller-avatar{width:54px;height:54px;border-radius:12px;background:var(--accent-pink-light);display:grid;place-items:center;overflow:hidden;font-weight:700;flex:0 0 54px}.seller-avatar img{width:100%;height:100%;object-fit:cover}.seller-info{min-width:0;display:flex;flex-direction:column;align-items:flex-start}.seller-info strong{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.35}.seller-info span{display:block;font-size:11px;line-height:1.45;color:#c49300}.used-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}.zone-empty{grid-column:1/-1;text-align:center;padding:50px;color:var(--text-muted)}.trust-box{margin:36px 0 70px;padding:42px 50px;background:#fbd8e2;border-radius:14px;display:grid;grid-template-columns:2fr 1fr;gap:30px}.trust-box h2{color:var(--secondary-color);font-size:25px;margin:0 0 12px}.trust-box>div>p{color:var(--secondary-color);font-size:var(--text-sm)}.trust-grid{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:25px}.trust-item{display:flex;gap:12px;color:var(--secondary-color)}.trust-icon{width:38px;height:38px;border-radius:8px;background:#d99490;color:#fff;display:grid;place-items:center;flex-shrink:0}.trust-item strong{display:block}.trust-item span{display:block;font-size:var(--text-xs);margin-top:4px}.shield{display:grid;place-items:center;color:#d99490}.shield svg{width:130px;height:130px}.used-content{padding-bottom:1px}@media(max-width:1000px){.filter-grid{grid-template-columns:repeat(2,1fr)}.used-grid{grid-template-columns:repeat(2,1fr)}.seller-grid{grid-template-columns:repeat(3,1fr)}.seller-card:nth-child(n+4){display:none}}@media(max-width:700px){.used-wrap{padding:0 16px}.used-hero{grid-template-columns:1fr}.used-arrival-box{max-width:none;height:245px}.arrival-slide{grid-template-columns:43% 57%}.arrival-cover{padding:14px}.arrival-cover img{width:105px;height:155px}.arrival-detail{padding:24px 15px 30px}.arrival-detail h3{font-size:16px}.arrival-price{font-size:19px}.filter-grid,.used-grid,.trust-box,.trust-grid{grid-template-columns:repeat(2, 1fr)}.seller-grid{grid-template-columns:repeat(3,1fr)}.used-title{font-size:28px}.trust-box{padding:28px 22px}.shield{display:none}}
    </style>
</head>
<body>
<div class="home-sticky-header"><?php require __DIR__.'/../components/header.php'; ?></div>
<main class="used-content"><div class="used-wrap">
    <section class="used-hero"><div><h1 class="used-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h6"/></svg>คลังหนังสือมือสอง</h1><p class="used-quote">“ส่งต่อเรื่องราวดี ๆ ที่ไม่ได้ใช้งานแล้ว เพื่อสร้างแรงบันดาลใจใหม่ ๆ ให้กับผู้อื่น เราเป็นสะพานเชื่อมระหว่างคนรักหนังสือและผู้ส่งต่อความรู้”</p><div class="hero-actions"><a class="hero-btn hero-primary" href="/pages/add_book.php">เริ่มขายหนังสือ</a><a class="hero-btn hero-outline" href="#trust">วิธีการใช้งาน</a></div></div><div class="used-arrival-box"><?php if($newArrivalBooks):foreach($newArrivalBooks as $index=>$hero):$img=usedImage($hero['image_cover']??null)?:'/assets/image/minilogo.png';?><a class="arrival-slide <?=$index===0?'active':''?>" href="/pages/book_detail.php?id=<?=(int)$hero['book_id']?>"><div class="arrival-cover"><img src="<?=htmlspecialchars($img)?>" alt="ปกหนังสือ <?=htmlspecialchars($hero['title'])?>"></div><div class="arrival-detail"><?php if(($hero['badge']??'')==='มาใหม่'):?><span class="arrival-badge">มาใหม่</span><?php endif;?><h3><?=htmlspecialchars($hero['title'])?></h3><p class="arrival-author"><?=htmlspecialchars($hero['author']?:'ไม่ระบุผู้เขียน')?></p><p class="arrival-store"><?=htmlspecialchars(wanawanSellerName($hero['seller_name'],$hero['seller_type']))?></p><strong class="arrival-price">฿<?=number_format((float)$hero['price'],2)?></strong></div></a><?php endforeach;?><div class="arrival-dots"><?php foreach($newArrivalBooks as $index=>$hero):?><button class="arrival-dot <?=$index===0?'active':''?>" type="button" data-slide="<?=$index?>" aria-label="แสดงหนังสือมาใหม่ลำดับ <?=$index+1?>"></button><?php endforeach;?></div><?php else:?><div class="arrival-empty">ยังไม่มีหนังสือมือสองมาใหม่ในเดือนนี้</div><?php endif;?></div></section>
    <form class="filter-box" method="get"><div class="filter-grid"><div class="field"><label>ค้นหาตามชื่อ/ผู้แต่ง</label><input name="q" value="<?=htmlspecialchars($keyword)?>" placeholder="พิมพ์ชื่อหนังสือที่คุณกำลังตามหา..."></div><div class="field"><label>สภาพหนังสือ</label><select name="condition"><option value="">ทั้งหมด</option><option value="good" <?=$condition==='good'?'selected':''?>>สภาพดี</option><option value="fair" <?=$condition==='fair'?'selected':''?>>สภาพพอใช้</option><option value="old" <?=$condition==='old'?'selected':''?>>หนังสือเก่า</option></select></div><div class="field"><label>ประเภทผู้ขาย</label><select name="seller_type"><option value="">ทั้งหมด</option><option value="shop" <?=$sellerType==='shop'?'selected':''?>>ร้านค้า</option><option value="publisher" <?=$sellerType==='publisher'?'selected':''?>>สำนักพิมพ์</option></select></div><div class="field"><label>หมวดหมู่</label><select name="category"><option value="">ทั้งหมด</option><?php foreach($categories as $value): ?><option <?=$category===$value?'selected':''?>><?=htmlspecialchars($value)?></option><?php endforeach; ?></select></div><div class="field"><label>ราคาสูงสุด</label><select name="price"><option value="">ทั้งหมด</option><?php foreach([100,200,300,500,1000] as $value): ?><option value="<?=$value?>" <?=$maxPrice==$value?'selected':''?>>ไม่เกิน ฿<?=$value?></option><?php endforeach; ?></select></div></div><div class="filter-actions"><button class="filter-btn filter-submit">กรองข้อมูล</button><a class="filter-btn filter-reset" href="/pages/used_books.php">ล้าง</a></div></form>
    <?php if($recommendedSellers): ?><section class="used-section"><div class="section-head"><h2>ร้านหนังสือมือสองน่าสนใจ</h2><a href="/pages/partners.php?type=shop">ดูทั้งหมด ›</a></div><div class="seller-grid"><?php foreach($recommendedSellers as $seller): ?><a class="seller-card" href="/pages/partner_detail.php?type=shop&amp;id=<?=(int)$seller['seller_id']?>"><span class="seller-avatar"><?php if($img=usedImage($seller['seller_image'])): ?><img src="<?=htmlspecialchars($img)?>" alt=""><?php else: ?><?=htmlspecialchars(mb_substr($seller['seller_name'],0,1))?><?php endif; ?></span><span class="seller-info"><strong><?=htmlspecialchars(wanawanSellerName($seller['seller_name'],'shop'))?></strong><?php if($seller['store_badge']!==''):?><span class="seller-rank"><?=htmlspecialchars($seller['store_badge'])?></span><?php endif;?><span>ขายแล้ว <?=number_format((int)$seller['sold_count'])?> เล่ม · ★ <?=number_format((float)$seller['rating'],1)?> · มี <?=number_format((int)$seller['books_count'])?> รายการ</span></span></a><?php endforeach; ?></div></section><?php endif; ?>
    <section class="used-section"><div class="section-head"><h2>มาใหม่ล่าสุด</h2><a href="/pages/catalog.php?type=used&section=latest">ดูทั้งหมด ›</a></div><div class="used-grid"><?php if(!$usedBooks): ?><p class="zone-empty">ไม่พบหนังสือตามตัวกรองที่เลือก</p><?php else: foreach(array_slice($usedBooks,0,4) as $row){$book=['id'=>(int)$row['book_id'],'title'=>$row['title'],'author'=>$row['author']??'','price'=>(float)$row['price'],'badge'=>(string)($row['badge']??''),'image'=>usedImage($row['image_cover']??null),'seller_type'=>(string)($row['seller_type']??'user'),'seller_verified'=>!empty($row['seller_verified']),'seller_province'=>$row['seller_province']??'','seller_lat'=>$row['seller_lat']??null,'seller_lng'=>$row['seller_lng']??null,'stock_quantity'=>$row['stock_quantity']??1];$card_type='used';require __DIR__.'/../components/book_card.php';} endif; ?></div></section>
    <?php require __DIR__.'/../components/trust_box.php'; ?>
</div></main>
<?php require __DIR__.'/../components/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const filterForm=document.querySelector('form.filter-box');
    if(filterForm){filterForm.action='/pages/catalog.php';filterForm.insertAdjacentHTML('afterbegin','<input type="hidden" name="type" value="used"><input type="hidden" name="section" value="latest">')}
    const box=document.querySelector('.used-arrival-box'),slides=Array.from(document.querySelectorAll('.arrival-slide')),dots=Array.from(document.querySelectorAll('.arrival-dot'));
    if(!box||slides.length<2)return;
    let current=0,timer;
    const show=index=>{current=(index+slides.length)%slides.length;slides.forEach((slide,i)=>slide.classList.toggle('active',i===current));dots.forEach((dot,i)=>dot.classList.toggle('active',i===current))};
    const start=()=>{clearInterval(timer);timer=setInterval(()=>show(current+1),1750)};
    dots.forEach(dot=>dot.addEventListener('click',event=>{event.preventDefault();show(Number(dot.dataset.slide));start()}));
    box.addEventListener('mouseenter',()=>clearInterval(timer));box.addEventListener('mouseleave',start);
    document.addEventListener('visibilitychange',()=>document.hidden?clearInterval(timer):start());
    start();
});
</script>
</body></html>
