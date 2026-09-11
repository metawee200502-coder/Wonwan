<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/book_merchandising.php';

$keyword = trim((string)($_GET['q'] ?? ''));
$requestedType = (string)($_GET['type'] ?? 'all');
$type = in_array($requestedType, ['all','new','used','exchange'], true) ? $requestedType : 'all';
$breadcrumbs = [['label'=>'หน้าหลัก','url'=>'/pages/home.php'],['label'=>'ผลการค้นหา','url'=>'']];
$books = [];
$exchanges = [];

function searchImage(?string $value): ?string {
    if (!$value) return null;
    if (preg_match('#^https?://#i', $value)) return $value;
    $value = ltrim(str_replace('\\','/',$value),'/');
    if (str_starts_with($value,'pages/')) return '/'.$value;
    if (str_starts_with($value,'uploads/')) return '/pages/'.$value;
    if (str_starts_with($value,'assets/')) return '/'.$value;
    return '/assets/image/books/'.$value;
}

if ($keyword !== '') {
    $like = '%'.$keyword.'%';
    if ($type !== 'exchange') {
        $bookWhere = "b.status='available' AND (b.seller_type='user' OR (b.seller_type='publisher' AND p.status='approved') OR (b.seller_type='shop' AND s.status='approved')) AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.isbn_13 LIKE ? OR b.publisher_name LIKE ?)";
        $params = [$like,$like,$like,$like,$like];
        if ($type === 'new') $bookWhere .= " AND (b.book_type='new' OR b.condition_level='new')";
        elseif ($type === 'used') $bookWhere .= " AND b.book_type='used' AND b.condition_level<>'new'";
        $stmt = $pdo->prepare("SELECT b.*,
            CASE WHEN b.seller_type='publisher' THEN p.status='approved' WHEN b.seller_type='shop' THEN s.status='approved' ELSE 0 END seller_verified
            FROM books b
            LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id
            LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id
            WHERE $bookWhere
            ORDER BY CASE WHEN b.title=? THEN 0 WHEN b.title LIKE ? THEN 1 ELSE 2 END,b.created_at DESC,b.book_id DESC LIMIT 40");
        $stmt->execute([...$params,$keyword,$keyword.'%']);
        $books = wanawanAnnotateBooks($pdo,$stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    if (in_array($type,['all','exchange'],true)) {
        $stmt = $pdo->prepare("SELECT e.* FROM exchanges e WHERE e.status='open'
            AND (e.title LIKE ? OR e.author LIKE ? OR e.isbn LIKE ? OR e.category LIKE ?)
            ORDER BY CASE WHEN e.title=? THEN 0 WHEN e.title LIKE ? THEN 1 ELSE 2 END,e.created_at DESC,e.exchange_id DESC LIMIT 40");
        $stmt->execute([$like,$like,$like,$like,$keyword,$keyword.'%']);
        $exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
$totalResults = count($books)+count($exchanges);
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ค้นหาหนังสือ - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>"><style>
*{box-sizing:border-box}.search-page{width:100%;max-width:1500px;margin:auto;padding:42px 40px 80px}.search-heading{color:var(--secondary-color);font-size:34px;margin:0}.search-summary{color:var(--text-muted);margin:7px 0 28px}.search-form{display:flex;gap:10px;max-width:950px;margin-bottom:24px}.search-form input{flex:1;height:48px;border:1px solid #d8c9c5;border-radius:24px;padding:0 20px;font:inherit;outline:none}.search-form input:focus{border-color:#d18aa7}.search-form button{min-width:110px;border:0;border-radius:24px;background:#d18aa7;color:#fff;font:inherit;font-weight:700;cursor:pointer}.search-tabs{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:34px}.search-tabs a{padding:9px 18px;border:1px solid #e5c4ce;border-radius:20px;text-decoration:none;color:#a54e70;background:#fff}.search-tabs a.active{background:#d18aa7;color:#fff;border-color:#d18aa7}.result-section{margin-top:34px}.result-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}.result-head h2{margin:0;color:var(--secondary-color);font-size:25px}.result-head span{color:var(--text-muted);font-size:13px}.result-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,332px));gap:24px;align-items:start;justify-content:start}.search-empty{text-align:center;padding:90px 20px;border:1px dashed #ddcbc6;border-radius:14px;color:var(--text-muted)}.search-empty strong{display:block;color:var(--secondary-color);font-size:21px;margin-bottom:6px}
/* ── Grid Layout ── */
.result-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    align-items: start;
}
@media(max-width:1024px){.result-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:768px){.result-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:600px){.search-page{padding:30px 18px 60px}.search-form{flex-direction:column}.search-form button{height:44px}.result-grid{grid-template-columns:1fr}.search-heading{font-size:28px}}

</style></head><body><?php require __DIR__.'/../components/header.php';?><main class="search-page"><h1 class="search-heading">ค้นหาหนังสือ</h1><?php if($keyword!==''):?><p class="search-summary">พบ <?=number_format($totalResults)?> รายการสำหรับ “<?=htmlspecialchars($keyword)?>”</p><?php else:?><p class="search-summary">ค้นหาจากชื่อหนังสือ ผู้เขียน ISBN หรือสำนักพิมพ์</p><?php endif;?><form class="search-form" method="get"><input name="q" value="<?=htmlspecialchars($keyword)?>" placeholder="ค้นหาตามชื่อเรื่อง ผู้เขียน หรือ ISBN..." required><input type="hidden" name="type" value="<?=htmlspecialchars($type)?>"><button>ค้นหา</button></form><nav class="search-tabs"><?php foreach(['all'=>'ทั้งหมด','new'=>'หนังสือมือหนึ่ง','used'=>'หนังสือมือสอง','exchange'=>'แลกเปลี่ยน'] as $key=>$label):?><a class="<?=$type===$key?'active':''?>" href="?q=<?=urlencode($keyword)?>&amp;type=<?=$key?>"><?=$label?></a><?php endforeach;?></nav>
<?php if($keyword===''):?><div class="search-empty"><strong>กำลังมองหาหนังสือเล่มไหนอยู่คะ?</strong><span>พิมพ์ชื่อเรื่อง ผู้เขียน หรือ ISBN ในช่องค้นหาได้เลย</span></div><?php elseif(!$totalResults):?><div class="search-empty"><strong>ไม่พบหนังสือที่ค้นหา</strong><span>ลองตรวจสอบคำสะกดหรือใช้คำค้นที่สั้นลง</span></div><?php else:?>
<?php if($books):?><section class="result-section"><div class="result-head"><h2>หนังสือที่พบ</h2><span><?=number_format(count($books))?> รายการ</span></div><div class="result-grid"><?php foreach($books as $row){$book=['id'=>(int)$row['book_id'],'title'=>$row['title'],'author'=>$row['author']??'','price'=>(float)$row['price'],'badge'=>(string)($row['badge']??''),'image'=>searchImage($row['image_cover']??null),'seller_type'=>$row['seller_type']??'user','seller_verified'=>!empty($row['seller_verified']),'stock_quantity'=>$row['stock_quantity']??1];$card_type=(($row['book_type']??'used')==='new'||($row['condition_level']??'')==='new')?'new':'used';require __DIR__.'/../components/book_card.php';}?></div></section><?php endif;?>
<?php if($exchanges):?><section class="result-section"><div class="result-head"><h2>ประกาศแลกเปลี่ยนที่พบ</h2><span><?=number_format(count($exchanges))?> รายการ</span></div><div class="result-grid"><?php foreach($exchanges as $row){$book=['id'=>(int)$row['exchange_id'],'title'=>$row['title'],'author'=>$row['author']??'','price'=>0,'badge'=>'แลกเปลี่ยน','image'=>searchImage($row['image_cover']??null),'seller_type'=>'user','stock_quantity'=>1];$card_type='exchange';require __DIR__.'/../components/book_card.php';}?></div></section><?php endif;?>
<?php endif;?></main><?php require __DIR__.'/../components/footer.php';?></body></html>
