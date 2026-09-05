<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location: /pages/login.php'); exit; }
require_once __DIR__ . '/../config/config.php';

$userId = (int)$_SESSION['user_id'];
$orderItemId = filter_input(INPUT_GET, 'order_item_id', FILTER_VALIDATE_INT) ?: (int)($_POST['order_item_id'] ?? 0);
if (empty($_SESSION['review_csrf'])) $_SESSION['review_csrf'] = bin2hex(random_bytes(24));

$stmt = $pdo->prepare("SELECT oi.order_item_id,oi.book_id,oi.quantity,oi.price_at_purchase,o.order_id,o.order_number,o.shipping_status,b.title,b.author,b.image_cover,r.review_id,r.rating,r.review_text
    FROM order_items oi JOIN orders o ON o.order_id=oi.order_id JOIN books b ON b.book_id=oi.book_id
    LEFT JOIN book_reviews r ON r.order_item_id=oi.order_item_id
    WHERE oi.order_item_id=? AND o.user_id=? LIMIT 1");
$stmt->execute([$orderItemId,$userId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$item || $item['shipping_status'] !== 'delivered') { http_response_code(403); exit('รีวิวได้เฉพาะสินค้าที่รับสำเร็จแล้ว'); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $text = trim((string)($_POST['review_text'] ?? ''));
    if (!hash_equals($_SESSION['review_csrf'], (string)($_POST['csrf_token'] ?? ''))) $error = 'ไม่สามารถยืนยันคำขอได้ กรุณาลองใหม่';
    elseif ($rating < 1 || $rating > 5) $error = 'กรุณาเลือกคะแนน 1–5 ดาว';
    elseif (mb_strlen($text) > 2000) $error = 'ข้อความรีวิวยาวเกิน 2,000 ตัวอักษร';
    else {
        $isNewReview=empty($item['review_id']);
        $save = $pdo->prepare("INSERT INTO book_reviews(book_id,user_id,order_item_id,rating,review_text) VALUES(?,?,?,?,?)
            ON DUPLICATE KEY UPDATE rating=VALUES(rating),review_text=VALUES(review_text),updated_at=NOW()");
        $save->execute([(int)$item['book_id'],$userId,$orderItemId,$rating,$text !== '' ? $text : null]);
        if($isNewReview){$reviewId=(int)$pdo->lastInsertId();if(!$reviewId){$find=$pdo->prepare('SELECT review_id FROM book_reviews WHERE order_item_id=?');$find->execute([$orderItemId]);$reviewId=(int)$find->fetchColumn();}wanawanNotifyNewReview($pdo,$reviewId,(int)$item['book_id'],$userId);}
        header('Location: /pages/orders.php?tab=completed&reviewed=1'); exit;
    }
}
function reviewCover(?string $value): ?string { if(!$value)return null;if(preg_match('#^https?://#i',$value))return $value;$value=ltrim(str_replace('\\','/',$value),'/');return str_starts_with($value,'uploads/')?'/pages/'.$value:'/assets/image/books/'.$value; }
$currentRating=(int)($item['rating']??0);
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>รีวิวสินค้า - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>"><style>
*{box-sizing:border-box}.page{max-width:780px;margin:0 auto;padding:48px 22px 90px}.back{color:var(--secondary-color);text-decoration:none}.card{margin-top:20px;background:#fff;border:1px solid #eadbd4;border-radius:20px;padding:30px;box-shadow:0 10px 30px rgba(75,45,35,.06)}h1{margin:0 0 22px;color:var(--secondary-color)}.product{display:flex;gap:18px;align-items:center;padding:16px;border-radius:14px;background:#fff5f0}.cover{width:86px;height:112px;border-radius:9px;object-fit:cover;background:#f5e1da}.product h2{margin:0 0 6px;font-size:20px}.muted{color:var(--text-muted)}.stars{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:7px;margin:28px 0 20px}.stars input{position:absolute;opacity:0}.stars label{font-size:42px;line-height:1;color:#ddd;cursor:pointer;transition:.15s}.stars label:hover,.stars label:hover~label,.stars input:checked~label{color:#e4a32b;transform:translateY(-1px)}textarea{width:100%;min-height:150px;border:1px solid #d8cdc7;border-radius:12px;padding:15px;font:inherit;resize:vertical}.actions{display:flex;gap:12px;margin-top:18px}.btn{height:48px;padding:0 24px;border-radius:9px;border:1px solid var(--secondary-color);font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-grid;place-items:center}.save{background:var(--secondary-color);color:#fff}.cancel{background:#fff;color:var(--secondary-color)}.error{padding:12px;border-radius:8px;background:#fde8e5;color:#a33;margin-bottom:15px}@media(max-width:560px){.card{padding:20px}.actions{flex-direction:column}.btn{width:100%}}
</style></head><body><?php require __DIR__.'/../components/header.php';?><main class="page"><a class="back" href="/pages/orders.php?tab=completed">‹ กลับไปรายการสั่งซื้อ</a><section class="card"><h1><?=empty($item['review_id'])?'รีวิวสินค้าที่ได้รับ':'แก้ไขรีวิวของฉัน'?></h1><?php if($error):?><div class="error"><?=htmlspecialchars($error)?></div><?php endif;?><div class="product"><?php if($cover=reviewCover($item['image_cover'])):?><img class="cover" src="<?=htmlspecialchars($cover)?>" alt=""><?php endif;?><div><h2><?=htmlspecialchars($item['title'])?></h2><div class="muted"><?=htmlspecialchars($item['author']?:'-')?> · คำสั่งซื้อ #<?=htmlspecialchars($item['order_number'])?></div></div></div><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['review_csrf'])?>"><input type="hidden" name="order_item_id" value="<?=$orderItemId?>"><div class="stars" aria-label="ให้คะแนนสินค้า"><?php for($star=5;$star>=1;$star--):?><input id="star<?=$star?>" type="radio" name="rating" value="<?=$star?>" <?=$currentRating===$star?'checked':''?> required><label for="star<?=$star?>" title="<?=$star?> ดาว">★</label><?php endfor;?></div><textarea name="review_text" maxlength="2000" placeholder="เล่าความประทับใจ คุณภาพสินค้า การแพ็ก หรือสิ่งที่อยากบอกผู้ซื้อคนอื่น..."><?=htmlspecialchars($item['review_text']??'')?></textarea><div class="actions"><button class="btn save" type="submit">บันทึกรีวิว</button><a class="btn cancel" href="/pages/orders.php?tab=completed">ยกเลิก</a></div></form></section></main><?php require __DIR__.'/../components/footer.php';?></body></html>
