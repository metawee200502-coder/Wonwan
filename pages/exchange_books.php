<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$active_zone = 'exchange';
$breadcrumbs = [
    ['label' => 'หน้าหลัก', 'url' => '/pages/home.php'],
    ['label' => 'ลานแลกเปลี่ยนหนังสือ', 'url' => ''],
];

$keyword = trim($_GET['q'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$category = trim($_GET['category'] ?? '');
$sort = $_GET['sort'] ?? 'latest';
$buyerLat = (float)($_COOKIE['buyer_lat'] ?? 0);$buyerLng = (float)($_COOKIE['buyer_lng'] ?? 0);
if(empty($buyerLat) && !empty($_SESSION['user_id'])){$userLoc=$pdo->prepare('SELECT latitude,longitude FROM users WHERE user_id=?');$userLoc->execute([$_SESSION['user_id']]);$u=$userLoc->fetch(PDO::FETCH_ASSOC);if($u&&$u['latitude']){$buyerLat=(float)$u['latitude'];$buyerLng=(float)$u['longitude'];}}
$hasLocation = ($buyerLat != 0 && $buyerLng != 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$where = ["e.status<>'hidden'"];
$params = [];
if ($keyword !== '') { $where[] = '(e.title LIKE :keyword OR e.author LIKE :keyword OR e.isbn LIKE :keyword)'; $params[':keyword'] = "%$keyword%"; }
if (in_array($condition, ['99','90','80','70'], true)) { $where[] = 'e.condition_percent=:condition'; $params[':condition'] = $condition; }
if ($category !== '') { $where[] = 'e.category=:category'; $params[':category'] = $category; }
$whereSql = implode(' AND ', $where);
$orderSql = match($sort) {
    'distance' => $hasLocation ? 'distance ASC, e.created_at DESC' : 'e.created_at DESC',
    'oldest' => 'e.created_at ASC',
    'condition' => 'CAST(e.condition_percent AS UNSIGNED) DESC,e.created_at DESC',
    'title' => 'e.title ASC',
    default => 'e.created_at DESC',
};

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM exchanges e WHERE $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$distanceSql = $hasLocation ? ", (6371 * acos(cos(radians(:buyerLat)) * cos(radians(u.latitude)) * cos(radians(u.longitude) - radians(:buyerLng)) + sin(radians(:buyerLat)) * sin(radians(u.latitude)))) AS distance" : ", NULL AS distance";
$stmt = $pdo->prepare("SELECT e.*,u.display_name AS owner_name $distanceSql FROM exchanges e LEFT JOIN users u ON u.user_id=e.owner_id WHERE $whereSql ORDER BY $orderSql LIMIT :limit OFFSET :offset");
foreach ($params as $key => $value) $stmt->bindValue($key, $value);
if($hasLocation){$stmt->bindValue(':buyerLat',$buyerLat);$stmt->bindValue(':buyerLng',$buyerLng);}
$stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);
$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
$stmt->execute();
$exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
$latestExchanges = $pdo->query("SELECT e.*,COALESCE(NULLIF(u.display_name,''),u.email,'สมาชิกวนวรรณ') owner_name
    FROM exchanges e LEFT JOIN users u ON u.user_id=e.owner_id
    WHERE e.status<>'hidden' ORDER BY e.created_at DESC,e.exchange_id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT DISTINCT category FROM exchanges WHERE status<>'hidden' AND category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

function exchangeImage(?string $value): ?string {
    if (!$value) return null;
    if (preg_match('#^https?://#i',$value)) return $value;
    $value=ltrim(str_replace('\\','/',$value),'/');
    return str_starts_with($value,'uploads/') ? '/pages/'.$value : '/assets/image/books/'.$value;
}
function exchangeQuery(array $changes=[]): string {
    $query=array_merge($_GET,$changes);
    foreach($query as $key=>$value) if($value===''||$value===null) unset($query[$key]);
    return '?' . http_build_query($query);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลานแลกเปลี่ยนหนังสือ - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
    *{box-sizing:border-box}.exchange-wrap{max-width:1200px;margin:auto;padding:0 40px}.exchange-hero{display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:center;padding:38px 0 45px}.exchange-title{display:flex;align-items:center;gap:14px;color:var(--secondary-color);font-size:34px;margin:0 0 12px}.exchange-title svg{width:40px;height:40px}.exchange-quote{color:var(--text-muted);font-size:var(--text-md);line-height:1.7;max-width:480px}.post-btn{display:inline-flex;align-items:center;gap:9px;margin-top:22px;padding:12px 24px;border-radius:10px;background:#d3948b;color:#fff;text-decoration:none;font-weight:600;box-shadow:0 3px 7px rgba(0,0,0,.14)}.post-btn svg{width:20px;height:20px}.exchange-latest-box{width:100%;max-width:500px;height:275px;justify-self:end;position:relative;overflow:hidden;border:1px solid #ead4d8;border-radius:var(--radius-lg);background:linear-gradient(135deg,#f5e3e5,#fff7f2);box-shadow:0 12px 34px rgba(91,58,67,.12)}.exchange-latest-slide{position:absolute;inset:0;display:grid;grid-template-columns:48% 52%;color:inherit;text-decoration:none;opacity:0;visibility:hidden;transform:translateX(12px);transition:opacity .55s ease,transform .55s ease,visibility .55s}.exchange-latest-slide.active{opacity:1;visibility:visible;transform:none}.exchange-latest-cover{display:grid;place-items:center;padding:22px;background:linear-gradient(145deg,#edd7db,#faede7)}.exchange-latest-cover img{width:145px;height:205px;object-fit:cover;border-radius:8px;box-shadow:0 10px 25px rgba(48,33,37,.2)}.exchange-latest-detail{position:relative;padding:28px 24px 34px;display:flex;flex-direction:column;justify-content:center;min-width:0;background:#fffaf7}.exchange-latest-badge{position:absolute;top:17px;right:18px;padding:4px 10px;border-radius:15px;background:#f6dce6;color:#ae5275;font-size:11px;font-weight:700}.exchange-latest-detail h3{margin:0 0 7px;color:var(--secondary-color);font-size:20px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.exchange-latest-author,.exchange-latest-owner,.exchange-latest-condition{margin:0;color:var(--text-muted);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.exchange-latest-owner{margin-top:5px}.exchange-latest-condition{margin-top:15px;color:#c56689;font-weight:700}.exchange-latest-dots{position:absolute;z-index:4;right:20px;bottom:15px;display:flex;gap:6px}.exchange-latest-dot{width:6px;height:6px;border:0;border-radius:50%;padding:0;background:#dfc8cf;cursor:pointer;transition:.25s}.exchange-latest-dot.active{width:18px;border-radius:6px;background:#c66f90}.exchange-latest-empty{height:100%;display:grid;place-items:center;color:var(--text-muted)}.filter-box{border:1px solid #d8ddd4;border-radius:14px;padding:22px 28px;background:#fff;box-shadow:0 3px 9px rgba(0,0,0,.08)}.filter-grid{display:grid;grid-template-columns:1.8fr 1fr 1fr;gap:32px}.field label{display:block;color:var(--secondary-color);font-weight:600;font-size:var(--text-sm);margin-bottom:7px}.field input,.field select{width:100%;height:40px;padding:0 12px;border:1px solid var(--border-color);border-radius:8px;background:#fff;font-family:var(--font-family)}.filter-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}.filter-btn{min-width:110px;height:38px;border-radius:8px;border:1px solid #d3948b;font-family:var(--font-family);cursor:pointer}.filter-submit{background:#d3948b;color:#fff}.filter-reset{display:grid;place-items:center;background:#fff;color:#d3948b;text-decoration:none}.result-bar{display:flex;justify-content:space-between;align-items:center;border:1px solid #d8ddd4;border-radius:12px;padding:15px 26px;margin:34px 0;color:var(--secondary-color);box-shadow:0 2px 6px rgba(0,0,0,.06);font-weight:600}.sort{display:flex;align-items:center;gap:12px}.sort select{height:38px;border:1px solid var(--border-color);border-radius:8px;padding:0 12px;font-family:var(--font-family)}.exchange-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}.empty-exchange{grid-column:1/-1;text-align:center;padding:80px 20px;color:var(--text-muted);border:1px dashed var(--border-color);border-radius:12px}.empty-exchange svg{width:54px;height:54px;color:var(--primary-color);opacity:.55}.pagination{display:flex;justify-content:center;gap:10px;padding:42px 0 70px}.pagination a,.pagination span{min-width:42px;height:42px;border:1px solid var(--secondary-color);border-radius:8px;display:grid;place-items:center;text-decoration:none;color:var(--secondary-color);font-weight:600}.pagination a.active{background:var(--secondary-color);color:#fff}.exchange-content{padding-bottom:1px}
@media(max-width:1000px){.exchange-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:760px){.exchange-wrap{padding:0 16px}.exchange-hero{grid-template-columns:1fr}.exchange-latest-box{max-width:none;height:245px}.exchange-latest-slide{grid-template-columns:43% 57%}.exchange-latest-cover{padding:14px}.exchange-latest-cover img{width:105px;height:155px}.exchange-latest-detail{padding:24px 15px 30px}.exchange-latest-detail h3{font-size:16px}.filter-grid{grid-template-columns:1fr;gap:14px}.exchange-grid{grid-template-columns:repeat(2,1fr)}.result-bar{align-items:flex-start;gap:14px;flex-direction:column}}
@media(max-width:480px){
    .exchange-grid{grid-template-columns:repeat(2, 1fr) !important; gap:10px !important}
    
    .exchange-hero{padding:20px 0 24px !important;gap:20px !important}
    .exchange-title{font-size:20px !important;gap:8px !important;margin:0 0 8px !important}
    .exchange-title svg{width:24px !important;height:24px !important}
    .exchange-quote{font-size:13px !important;line-height:1.5 !important}
    .post-btn{margin-top:16px !important;padding:8px 16px !important;font-size:12px !important;gap:6px !important}
    .post-btn svg{width:16px !important;height:16px !important}
    
    .exchange-latest-box{height:190px !important}
    .exchange-latest-slide{grid-template-columns:43% 57% !important;grid-template-rows:1fr !important}
    .exchange-latest-cover{padding:16px 0 16px 16px !important;background:transparent !important}
    .exchange-latest-cover img{width:95px !important;height:140px !important;margin:0 auto;box-shadow:0 4px 12px rgba(0,0,0,.15) !important}
    .exchange-latest-detail{position:relative !important;padding:20px 14px 28px 12px !important;align-items:flex-start !important;text-align:left !important;background:transparent !important;justify-content:center !important}
    .exchange-latest-detail h3{font-size:14px !important;margin:0 0 6px !important;-webkit-line-clamp:2 !important;white-space:normal !important}
    .exchange-latest-author,.exchange-latest-owner,.exchange-latest-condition{display:block !important;font-size:11px !important;margin:0 0 2px !important}
    .exchange-latest-condition{margin-top:10px !important;font-size:12px !important}
    .exchange-latest-badge{top:12px !important;right:12px !important;left:auto !important;font-size:9px !important;padding:3px 8px !important;z-index:10 !important}
    .exchange-latest-dots{right:14px !important;left:auto !important;bottom:12px !important;justify-content:flex-end !important;align-items:center !important;gap:5px !important;width:auto !important;height:auto !important}
    .exchange-latest-dot{width:5px !important;height:5px !important;min-width:5px !important;max-width:5px !important;min-height:5px !important;max-height:5px !important;border-radius:50% !important}
    .exchange-latest-dot.active{width:14px !important;max-width:14px !important;border-radius:4px !important}
    
    .filter-box{padding:12px 10px !important;margin-bottom:24px !important;border-radius:8px !important}
    .filter-grid{grid-template-columns:1fr 1fr !important;gap:8px 10px !important}
    .field:first-child{grid-column:span 2 !important}
    .field label{font-size:10.5px !important;margin-bottom:3px !important}
    .field input,.field select{height:30px !important;padding:0 6px !important;font-size:11px !important;border-radius:4px !important}
    .filter-actions{display:grid !important;grid-template-columns:1fr 1fr !important;margin-top:10px !important;gap:8px !important}
    .filter-btn{width:100% !important;min-width:0 !important;height:30px !important;font-size:11.5px !important;border-radius:4px !important;padding:0 !important}
    
    .result-bar{padding:12px 14px !important;margin:20px 0 !important;font-size:11px !important;border-radius:8px !important;gap:8px !important;flex-direction:column !important;align-items:flex-start !important}
    .sort{width:100% !important;justify-content:space-between !important;gap:8px !important}
    .sort select{height:30px !important;padding:0 6px !important;font-size:11px !important}
}
    #exchange-list{scroll-margin-top:140px}
    </style>
</head>
<body>
<div class="home-sticky-header" style="position: sticky; top: 0; z-index: 1000;"><?php require __DIR__.'/../components/header.php'; ?></div>
<main class="exchange-content"><div class="exchange-wrap">
    <section class="exchange-hero"><div><h1 class="exchange-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 7h11l-3-3M18 7l-3 3M17 17H6l3 3M6 17l3-3"/></svg>ลานแลกเปลี่ยนหนังสือ</h1><p class="exchange-quote">“พบปะเพื่อนร่วมอุดมการณ์การอ่าน ส่งต่อความรู้ และค้นหาขุมทรัพย์วรรณกรรมเล่มใหม่ ผ่านการแลกเปลี่ยนที่ไร้พรมแดน”</p><a class="post-btn" href="#exchange-list"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>โพสต์หนังสือสำหรับแลก</a></div><div class="exchange-latest-box"><?php if($latestExchanges):foreach($latestExchanges as $index=>$hero):$img=exchangeImage($hero['image_cover']??null)?:'/assets/image/minilogo.png';?><a class="exchange-latest-slide <?=$index===0?'active':''?>" href="/pages/book_detail.php?source=exchange&amp;id=<?=(int)$hero['exchange_id']?>"><div class="exchange-latest-cover"><img src="<?=htmlspecialchars($img)?>" alt="ปกหนังสือ <?=htmlspecialchars($hero['title'])?>"></div><div class="exchange-latest-detail"><span class="exchange-latest-badge">โพสต์ล่าสุด</span><h3><?=htmlspecialchars($hero['title'])?></h3><p class="exchange-latest-author"><?=htmlspecialchars($hero['author']?:'ไม่ระบุผู้เขียน')?></p><p class="exchange-latest-owner"><?=htmlspecialchars($hero['owner_name'])?></p><p class="exchange-latest-condition">สภาพ <?=htmlspecialchars((string)$hero['condition_percent'])?>%</p></div></a><?php endforeach;?><div class="exchange-latest-dots"><?php foreach($latestExchanges as $index=>$hero):?><button class="exchange-latest-dot <?=$index===0?'active':''?>" type="button" data-slide="<?=$index?>" aria-label="แสดงประกาศล่าสุดลำดับ <?=$index+1?>"></button><?php endforeach;?></div><?php else:?><div class="exchange-latest-empty">ยังไม่มีประกาศแลกเปลี่ยน</div><?php endif;?></div></section>
    <form class="filter-box" method="get"><div class="filter-grid"><div class="field"><label>ค้นหาตามชื่อ/ผู้แต่ง</label><input name="q" value="<?=htmlspecialchars($keyword)?>" placeholder="พิมพ์ชื่อหนังสือที่คุณกำลังตามหา..."></div><div class="field"><label>สภาพหนังสือ</label><select name="condition"><option value="">ทั้งหมด</option><?php foreach(['99'=>'เหมือนใหม่ 99%','90'=>'สภาพดี 90%','80'=>'สภาพปานกลาง 80%','70'=>'มีร่องรอย 70%'] as $value=>$label): ?><option value="<?=$value?>" <?=$condition===$value?'selected':''?>><?=$label?></option><?php endforeach; ?></select></div><div class="field"><label>หมวดหมู่</label><select name="category"><option value="">ทั้งหมด</option><?php foreach($categories as $value): ?><option <?=$category===$value?'selected':''?>><?=htmlspecialchars($value)?></option><?php endforeach; ?></select></div></div><div class="filter-actions"><button class="filter-btn filter-submit">กรองข้อมูล</button><a class="filter-btn filter-reset" href="/pages/exchange_books.php">ล้าง</a></div></form>
    <div class="result-bar" id="exchange-list"><span>แสดงผลลัพธ์ <?= $total ? number_format($offset+1).'–'.number_format(min($offset+$perPage,$total)) : '0' ?> จากทั้งหมด <?=number_format($total)?> รายการ</span><form class="sort" method="get"><?php foreach($_GET as $key=>$value): if($key==='sort'||$key==='page')continue; ?><input type="hidden" name="<?=htmlspecialchars($key)?>" value="<?=htmlspecialchars($value)?>"><?php endforeach; ?><label>จัดเรียงตาม:</label><select name="sort" onchange="this.form.submit()"><option value="latest" <?=$sort==='latest'?'selected':''?>>ใหม่ล่าสุด</option><?php if($hasLocation):?><option value="distance" <?=$sort==='distance'?'selected':''?>>ใกล้ฉันที่สุด</option><?php endif;?><option value="oldest" <?=$sort==='oldest'?'selected':''?>>เก่าสุด</option><option value="condition" <?=$sort==='condition'?'selected':''?>>สภาพดีที่สุด</option><option value="title" <?=$sort==='title'?'selected':''?>>ชื่อหนังสือ</option></select></form></div>
    <div class="exchange-grid"><?php if(!$exchanges): ?><div class="empty-exchange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 7h11l-3-3M18 7l-3 3M17 17H6l3 3M6 17l3-3"/></svg><h3>ยังไม่มีรายการแลกเปลี่ยน</h3><p>เมื่อมีสมาชิกโพสต์หนังสือ รายการจะแสดงที่นี่</p></div><?php else: foreach($exchanges as $row){$distanceText=$row['distance']!==null?number_format($row['distance'],1).' กม.':'';$book=['id'=>(int)$row['exchange_id'],'title'=>$row['title'],'author'=>$row['author']??'','price'=>0,'badge'=>'แลกเปลี่ยน','image'=>exchangeImage($row['image_cover']??null),'distance'=>$distanceText,'status'=>$row['status']??'open','stock_quantity'=>1];$card_type='exchange';require __DIR__.'/../components/book_card.php';} endif; ?></div>
    <?php if($totalPages>1): ?><nav class="pagination" aria-label="หน้ารายการ"><?php for($p=1;$p<=$totalPages;$p++): ?><a class="<?=$p===$page?'active':''?>" href="<?=htmlspecialchars(exchangeQuery(['page'=>$p]))?>"><?=$p?></a><?php endfor; ?></nav><?php else: ?><div style="height:60px"></div><?php endif; ?>
</div></main>
<?php require __DIR__.'/../components/footer.php'; ?>
<script>
document.querySelector('.post-btn')?.setAttribute('href','/pages/post_exchange.php');
document.addEventListener('DOMContentLoaded',()=>{
    const filterForm=document.querySelector('form.filter-box');
    if(filterForm){
        filterForm.addEventListener('submit',event=>{
            event.preventDefault();
            const params=new URLSearchParams(new FormData(filterForm));
            window.location.href='/pages/exchange_catalog.php?'+params.toString();
        });
    }
    const box=document.querySelector('.exchange-latest-box'),slides=Array.from(document.querySelectorAll('.exchange-latest-slide')),dots=Array.from(document.querySelectorAll('.exchange-latest-dot'));
    if(!box||slides.length<2)return;
    let current=0,timer;
    const show=index=>{current=(index+slides.length)%slides.length;slides.forEach((slide,i)=>slide.classList.toggle('active',i===current));dots.forEach((dot,i)=>dot.classList.toggle('active',i===current))};
    const start=()=>{clearInterval(timer);timer=setInterval(()=>show(current+1),1750)};
    dots.forEach(dot=>dot.addEventListener('click',event=>{event.preventDefault();show(Number(dot.dataset.slide));start()}));
    box.addEventListener('mouseenter',()=>clearInterval(timer));box.addEventListener('mouseleave',start);
    document.addEventListener('visibilitychange',()=>document.hidden?clearInterval(timer):start());
    start();
});
function setGPSCookie(){
    if(navigator.geolocation){
        navigator.geolocation.getCurrentPosition(p=>{
            document.cookie = "buyer_lat="+p.coords.latitude+"; path=/; max-age=86400";
            document.cookie = "buyer_lng="+p.coords.longitude+"; path=/; max-age=86400";
            location.reload();
        }, ()=>alert('ไม่สามารถดึงตำแหน่งได้'));
    } else { alert('ไม่รองรับการดึงตำแหน่ง'); }
}
</script>
</body></html>
