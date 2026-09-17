<?php
session_start();if(empty($_SESSION['user_id'])){header('Location:/pages/login.php');exit;}require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../config/order_reservations.php';$userId=(int)$_SESSION['user_id'];wanawanExpirePendingOrders($pdo,$userId);$orderIds=array_values(array_unique(array_map('intval',$_SESSION['checkout_batch_order_ids']??[])));if(!$orderIds){header('Location:/pages/orders.php');exit;}$marks=implode(',',array_fill(0,count($orderIds),'?'));
function batchSellerUsers(PDO $pdo,string $type,int $sellerId):array{if($type==='user')return[$sellerId];if(!in_array($type,['publisher','shop'],true))return[];$table=$type==='publisher'?'publisher_staff':'shop_staff';$column=$type==='publisher'?'publisher_id':'shop_id';$q=$pdo->prepare("SELECT user_id FROM $table WHERE $column=?");$q->execute([$sellerId]);return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));}
$enableReservationCountdown=true;$reservationState=$pdo->prepare("SELECT COUNT(*) total,SUM(payment_status='pending') pending_count,MIN(UNIX_TIMESTAMP(reservation_expires_at)) deadline FROM orders WHERE user_id=? AND order_id IN ($marks)");$reservationState->execute(array_merge([$userId],$orderIds));$reservationState=$reservationState->fetch(PDO::FETCH_ASSOC);$reservationCountdownDeadline=(int)($reservationState['deadline']??0);$reservationCountdownExpired=(int)($reservationState['pending_count']??0)!==(int)($reservationState['total']??0);if($reservationCountdownExpired&&$_SERVER['REQUEST_METHOD']==='POST'){header('Location:/pages/orders.php?tab=cancelled&expired=1');exit;}
$pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@SESSION.sql_mode,'ONLY_FULL_GROUP_BY',''))");
$orderStmt=$pdo->prepare("SELECT o.*,COALESCE(p.company_name,s.shop_name,NULLIF(u.display_name,''),u.email,'ผู้ขายวนวรรณ') seller_name,b.seller_type,b.seller_id FROM orders o JOIN order_items oi ON oi.order_id=o.order_id JOIN books b ON b.book_id=oi.book_id LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id LEFT JOIN users u ON b.seller_type='user' AND u.user_id=b.seller_id WHERE o.user_id=? AND o.order_id IN ($marks) GROUP BY o.order_id ORDER BY o.order_id");$orderStmt->execute(array_merge([$userId],$orderIds));$orders=$orderStmt->fetchAll(PDO::FETCH_ASSOC);if(count($orders)!==count($orderIds)){http_response_code(403);exit('ไม่พบชุดคำสั่งซื้อ');}
$paymentError='';if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['confirm_payment'])){$slip=null;if(!empty($_FILES['payment_slip']['name'])&&$_FILES['payment_slip']['error']===UPLOAD_ERR_OK){$mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['payment_slip']['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png'][$mime]??null;if($ext&&$_FILES['payment_slip']['size']<=5*1024*1024){$dir=__DIR__.'/uploads/payment_slips';if(!is_dir($dir))mkdir($dir,0775,true);$file='slip_batch_'.bin2hex(random_bytes(10)).'.'.$ext;move_uploaded_file($_FILES['payment_slip']['tmp_name'],$dir.'/'.$file);$slip='uploads/payment_slips/'.$file;}}
if(!$slip)$paymentError='กรุณาแนบสลิป JPEG หรือ PNG ขนาดไม่เกิน 5 MB';else{$pdo->beginTransaction();try{$update=$pdo->prepare("UPDATE orders SET payment_status='reviewing',payment_slip_url=? WHERE order_id=? AND user_id=? AND payment_status='pending'");foreach($orders as $order){$update->execute([$slip,$order['order_id'],$userId]);$itemQ=$pdo->prepare('SELECT oi.book_id FROM order_items oi WHERE oi.order_id=?');$itemQ->execute([$order['order_id']]);foreach($itemQ->fetchAll(PDO::FETCH_COLUMN) as $bookId)$pdo->prepare('DELETE FROM cart_items WHERE user_id=? AND book_id=?')->execute([$userId,$bookId]);foreach(batchSellerUsers($pdo,$order['seller_type'],(int)$order['seller_id']) as $sellerUser) wanawanNotifyUser($pdo, $sellerUser, 'มีสลิปคำสั่งซื้อใหม่', 'คำสั่งซื้อ #'.$order['order_number'].' รอให้ร้านตรวจสอบสลิปและยอดชำระ', 'order');}wanawanNotifyUser($pdo, $userId, 'ส่งคำสั่งซื้อเรียบร้อยแล้ว', 'ชำระคำสั่งซื้อ '.count($orders).' ร้านแล้ว กำลังรอแต่ละร้านตรวจสอบ', 'order');$pdo->commit();unset($_SESSION['checkout_selected_ids'],$_SESSION['checkout_batch_order_ids']);header('Location:/pages/orders.php?tab=pending');exit;}catch(Throwable $e){$pdo->rollBack();throw$e;}}}
$total=array_sum(array_column($orders,'total_amount'));

// สร้าง QR Code PromptPay สำหรับยอดรวมทั้งหมด
$promptpayPayload = generatePromptPayPayload(WANAWAN_PROMPTPAY_ID, (float)$total);
// ส่งไป QuickChart เพื่อทำเป็นรูปภาพ
$qr = 'https://quickchart.io/qr?size=300&margin=2&ecLevel=M&text=' . rawurlencode($promptpayPayload);
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ชำระเงินหลายร้าน - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>"><style>*{box-sizing:border-box}.wrap{max-width:1080px;margin:auto;padding:44px 30px 85px}.title{color:var(--secondary-color)}.layout{display:grid;grid-template-columns:1fr 380px;gap:25px}.card{border:1px solid #e1dcd7;border-radius:15px;padding:25px;background:#fff}.qr{text-align:center;padding:22px;background:#faf8f6;border-radius:12px}.qr img{width:260px;max-width:100%}.amount{font-size:35px;color:var(--secondary-color);font-weight:700}.order{padding:14px 0;border-bottom:1px solid #eee}.order strong,.row{display:flex;justify-content:space-between;gap:12px}.order span{font-size:13px;color:var(--text-muted)}.row{margin:17px 0}.total{font-size:21px;color:var(--secondary-color);font-weight:700;border-top:1px solid #ddd;padding-top:18px}.btn-pay-later{display:block;width:100%;height:54px;line-height:54px;border:1px solid #ccc;border-radius:10px;background:#fff;color:var(--text-dark);font-weight:700;text-align:center;text-decoration:none;margin-top:20px;transition:background .2s}.btn-pay-later:hover{background:#f9f9f9}.error{padding:12px;background:#fde7e3;color:#a33;border-radius:9px;margin-bottom:15px}
.loader{border:4px solid #f3f3f3;border-top:4px solid var(--secondary-color);border-radius:50%;width:30px;height:30px;animation:spin 1s linear infinite;margin:15px auto;}
@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
@media(max-width:780px){.layout{grid-template-columns:1fr}}</style></head><body><?php require __DIR__.'/../components/header.php';?><main class="wrap"><h1 class="title">ชำระเงินรวม <?=count($orders)?> ร้าน</h1><?php if($paymentError):?><div class="error"><?=htmlspecialchars($paymentError)?></div><?php endif;?><div class="layout"><section class="card"><div class="qr"><img src="<?=htmlspecialchars($qr)?>" alt="QR Code"><div class="amount">฿<?=number_format($total,2)?></div>
<div class="loader"></div>
<h3 style="color:var(--secondary-color);margin:10px 0 5px;">กำลังรอการชำระเงิน...</h3>
<p style="color:var(--text-muted);font-size:14px;margin-bottom:0;">ระบบจะตรวจสอบยอดเงินและยืนยันการชำระอัตโนมัติ<br>สแกนและชำระยอดรวมครั้งเดียว</p>
<button onclick="simulateWebhook()" style="margin-top:20px;padding:8px 15px;background:#fde7e3;color:#a33;border:1px solid #eba49a;border-radius:6px;cursor:pointer;font-size:12px;">[Test] จำลองการโอนเงินสำเร็จ</button>
</div></section><aside class="card"><h2>คำสั่งซื้อแยกตามร้าน</h2><?php foreach($orders as $order):?><div class="order"><strong><span><?=htmlspecialchars(wanawanSellerName($order['seller_name'],$order['seller_type']))?></span><b>฿<?=number_format($order['total_amount'],2)?></b></strong><span>#<?=htmlspecialchars($order['order_number'])?> · รวมค่าส่ง ฿<?=number_format($order['shipping_fee'],2)?></span></div><?php endforeach;?><div class="row total"><span>ยอดรวมทั้งหมด</span><span>฿<?=number_format($total,2)?></span></div><a href="/pages/orders.php?tab=pending" class="btn-pay-later">ชำระเงินภายหลัง</a></aside></div></main><?php require __DIR__.'/../components/footer.php';?>
<script>
const orderIdsStr = <?=json_encode(implode(',', array_column($orders, 'order_id')))?>;
let polling = setInterval(() => {
    fetch('../api/check_payment.php?order_ids=' + orderIdsStr)
    .then(res => res.json())
    .then(data => {
        if (data.status === 'reviewing' || data.status === 'paid' || data.status === 'preparing') {
            clearInterval(polling);
            alert('ชำระเงินสำเร็จ! ระบบกำลังพาคุณไปยังหน้าคำสั่งซื้อ');
            window.location.href = '/pages/orders.php?tab=pending';
        }
    }).catch(e => console.error(e));
}, 3000);

function simulateWebhook() {
    fetch('../api/simulate_webhook.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({order_ids: orderIdsStr.split(',')})
    }).then(res => res.json()).then(data => {
        if(!data.success) alert('Error: ' + data.error);
    });
}
</script>
</body></html>
