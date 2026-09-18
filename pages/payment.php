<?php
session_start();
if(empty($_SESSION['user_id'])){header('Location:/pages/login.php');exit;}
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../config/order_reservations.php';$userId=(int)$_SESSION['user_id'];wanawanExpirePendingOrders($pdo,$userId);
function paymentSellerUsers(PDO $pdo,array $items):array{
    $users=[];
    foreach($items as $item){
        $type=(string)($item['seller_type']??'');$sellerId=(int)($item['seller_id']??0);
        if(!$sellerId)continue;
        if($type==='user'){$users[]=$sellerId;continue;}
        if(!in_array($type,['publisher','shop'],true))continue;
        $table=$type==='publisher'?'publisher_staff':'shop_staff';
        $column=$type==='publisher'?'publisher_id':'shop_id';
        $staff=$pdo->prepare("SELECT user_id FROM {$table} WHERE {$column}=?");$staff->execute([$sellerId]);
        $users=array_merge($users,$staff->fetchAll(PDO::FETCH_COLUMN));
    }
    return array_values(array_unique(array_filter(array_map('intval',$users))));
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['shipping_name'])){
    $ids=$_SESSION['checkout_selected_ids']??[];if(!$ids){header('Location:/pages/cart.php');exit;}
    $name=trim($_POST['shipping_name']);$address=trim($_POST['shipping_address']);$phone=trim($_POST['shipping_phone']);
    if($name===''||$address===''||$phone===''){header('Location:/pages/checkout_summary.php');exit;}
    $marks=implode(',',array_fill(0,count($ids),'?'));$stmt=$pdo->prepare("SELECT ci.quantity,b.book_id,b.title,b.author,b.price,b.stock_quantity,b.image_cover,b.seller_type,b.seller_id FROM cart_items ci JOIN books b ON b.book_id=ci.book_id WHERE ci.user_id=? AND ci.book_id IN ($marks) AND b.status='available'");$stmt->execute(array_merge([$userId],$ids));$items=$stmt->fetchAll(PDO::FETCH_ASSOC);if(!$items){header('Location:/pages/cart.php');exit;}
    $subtotal=0;$quantityTotal=0;foreach($items as $i){if($i['quantity']>$i['stock_quantity'])exit('จำนวนสินค้าในสต็อกไม่เพียงพอ');$subtotal+=$i['price']*$i['quantity'];$quantityTotal+=(int)$i['quantity'];}$shipping=wanawanShippingFee($quantityTotal);$total=$subtotal+$shipping-($subtotal*.05);$number='WN'.date('YmdHis').random_int(100,999);
    $pdo->beginTransaction();try{$o=$pdo->prepare("INSERT INTO orders(user_id,order_number,subtotal,shipping_fee,total_amount,shipping_name,shipping_address,shipping_phone,shipping_method,payment_status,shipping_status) VALUES(?,?,?,?,?,?,?,?,?,'pending','pending')");$o->execute([$userId,$number,$subtotal,$shipping,$total,$name,$address,$phone,'PromptPay']);$orderId=(int)$pdo->lastInsertId();$oi=$pdo->prepare('INSERT INTO order_items(order_id,book_id,quantity,price_at_purchase) VALUES(?,?,?,?)');foreach($items as $i)$oi->execute([$orderId,$i['book_id'],$i['quantity'],$i['price']]);foreach(paymentSellerUsers($pdo,$items) as $sellerUser) wanawanNotifyUser($pdo, $sellerUser, 'มีคำสั่งซื้อใหม่', 'คำสั่งซื้อ #'.$number.' มีผู้ซื้อสั่งสินค้าของคุณ กรุณารอตรวจสอบการชำระเงิน', 'order');$pdo->commit();unset($_SESSION['checkout_selected_ids']);header('Location:/pages/payment.php?order='.$orderId);exit;}catch(Throwable $e){$pdo->rollBack();throw $e;}
}
$orderId=(int)($_GET['order']??$_POST['order_id']??0);$stmt=$pdo->prepare('SELECT * FROM orders WHERE order_id=? AND user_id=?');$stmt->execute([$orderId,$userId]);$order=$stmt->fetch(PDO::FETCH_ASSOC);if(!$order){http_response_code(404);exit('ไม่พบคำสั่งซื้อ');}if($order['payment_status']==='cancelled'&&($order['cancellation_reason']??'')==='หมดเวลาชำระเงิน 24 ชั่วโมง'){header('Location:/pages/orders.php?tab=cancelled&expired=1');exit;}$enableReservationCountdown=$order['payment_status']==='pending'&&!empty($order['stock_reserved']);$reservationCountdownDeadline=$enableReservationCountdown?strtotime((string)$order['reservation_expires_at']):0;$reservationCountdownExpired=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['confirm_payment']) && $order['payment_status']==='pending'){
    $slip=null;if(!empty($_FILES['payment_slip']['name'])&&$_FILES['payment_slip']['error']===UPLOAD_ERR_OK){$mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['payment_slip']['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png'][$mime]??null;if($ext&&$_FILES['payment_slip']['size']<=5*1024*1024){$dir=__DIR__.'/uploads/payment_slips';if(!is_dir($dir))mkdir($dir,0775,true);$name='slip_'.bin2hex(random_bytes(10)).'.'.$ext;move_uploaded_file($_FILES['payment_slip']['tmp_name'],$dir.'/'.$name);$slip='uploads/payment_slips/'.$name;}}
    if(!$slip){$paymentError='กรุณาแนบสลิป JPEG หรือ PNG ขนาดไม่เกิน 5 MB';}else{$pdo->beginTransaction();try{$pdo->prepare("UPDATE orders SET payment_status='reviewing',payment_slip_url=? WHERE order_id=? AND payment_status='pending'")->execute([$slip,$orderId]);$items=$pdo->prepare('SELECT oi.book_id,b.seller_type,b.seller_id FROM order_items oi JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=?');$items->execute([$orderId]);foreach($items as $i){$pdo->prepare('DELETE FROM cart_items WHERE user_id=? AND book_id=?')->execute([$userId,$i['book_id']]);}
    $saStmt=$pdo->query("SELECT user_id FROM users WHERE role='super_admin'");
    foreach($saStmt->fetchAll(PDO::FETCH_COLUMN) as $sa) wanawanNotifyUser($pdo, (int)$sa, 'มีสลิปคำสั่งซื้อใหม่', 'คำสั่งซื้อ #'.$order['order_number'].' รอการตรวจสอบ', 'order');
    wanawanNotifyUser($pdo, $userId, 'แจ้งชำระเงินเรียบร้อย', 'แนบสลิปคำสั่งซื้อ #'.$order['order_number'].' แล้ว กำลังรอผู้ดูแลระบบตรวจสอบ', 'order');$pdo->commit();header('Location:/pages/orders.php?tab=pending');exit;}catch(Throwable $e){$pdo->rollBack();throw $e;}}
}
$items=$pdo->prepare('SELECT oi.*,b.title,b.author,b.image_cover FROM order_items oi LEFT JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=?');$items->execute([$orderId]);$items=$items->fetchAll(PDO::FETCH_ASSOC);

// สร้าง QR Code PromptPay
$promptpayPayload = generatePromptPayPayload(WANAWAN_PROMPTPAY_ID, (float)$order['total_amount']);
// ส่งไป QuickChart เพื่อทำเป็นรูปภาพ
$qr = 'https://quickchart.io/qr?size=300&margin=2&ecLevel=M&text=' . rawurlencode($promptpayPayload);
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ชำระเงิน - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>"><style>*{box-sizing:border-box}.wrap{max-width:1120px;margin:auto;padding:48px 40px 85px}.title{border-left:5px solid var(--secondary-color);padding-left:20px;color:var(--secondary-color)}.layout{display:grid;grid-template-columns:1fr 390px;gap:25px}.card{border:1px solid #e1d8d1;border-radius:12px;padding:28px;background:#fff}.qr-area{text-align:center;background:#faf8f6;border:1px dashed #bcc6b4;border-radius:12px;padding:28px}.qr{width:280px;max-width:100%;background:#fff;padding:12px;border-radius:12px}.amount{font-size:38px;color:var(--secondary-color);font-weight:700;margin:12px}.summary{background:#f4f1ef;height:max-content}.item{display:flex;justify-content:space-between;gap:15px;padding:13px 0;border-bottom:1px solid #ddd}.row{display:flex;justify-content:space-between;margin:17px 0}.total{border-top:1px solid #ccc;padding-top:20px;font-size:22px;font-weight:700;color:var(--secondary-color)}.btn-pay-later{display:block;width:100%;height:58px;line-height:58px;border:1px solid #ccc;border-radius:8px;background:#fff;color:var(--text-dark);font-weight:700;font-size:17px;text-align:center;text-decoration:none;margin-top:20px;transition:background .2s}.btn-pay-later:hover{background:#f9f9f9}.order-no{color:var(--text-muted);font-size:13px}
.loader{border:4px solid #f3f3f3;border-top:4px solid var(--secondary-color);border-radius:50%;width:30px;height:30px;animation:spin 1s linear infinite;margin:15px auto;}
@keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
@media(max-width:800px){.layout{grid-template-columns:1fr}}@media(max-width:500px){.wrap{padding:30px 16px}}</style></head><body><?php require __DIR__.'/../components/header.php';?><main class="wrap"><h1 class="title">ชำระเงิน</h1><div class="layout"><section class="card"><h2>สแกนเพื่อชำระเงิน</h2><div class="qr-area"><img class="qr" src="<?=htmlspecialchars($qr)?>" alt="QR Code คำสั่งซื้อ"><div class="amount">฿<?=number_format($order['total_amount'],2)?></div><div class="order-no">เลขคำสั่งซื้อ <?=htmlspecialchars($order['order_number'])?></div>
<form method="POST" enctype="multipart/form-data">
    <h3 style="color:var(--secondary-color);margin:10px 0 5px;">แนบสลิปการโอนเงิน</h3>
    <p style="color:var(--text-muted);font-size:14px;margin-bottom:0;">กรุณาโอนเงินตามยอดด้านบนและแนบสลิปเพื่อยืนยัน</p>
    <input type="file" name="payment_slip" id="payment-slip-input" class="file-input" accept="image/jpeg, image/png" style="margin-top:20px;width:100%;padding:10px;border:1px dashed #ccc;border-radius:8px;background:#fff;" required>
    <div style="text-align: center;">
        <img id="slip-preview" style="display:none; max-width: 100%; max-height: 250px; margin-top: 15px; border-radius: 8px; border: 1px solid #ddd;" alt="Slip Preview">
    </div>
    <button type="submit" name="confirm_payment" style="margin-top:15px;width:100%;padding:12px;background:var(--secondary-color);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;">ยืนยันการชำระเงิน</button>
</form>
</div></section><aside class="card summary"><h2>สรุปรายการสั่งซื้อ</h2><?php foreach($items as $item):?><div class="item"><span><?=htmlspecialchars($item['title']?:'หนังสือ')?> × <?=$item['quantity']?></span><strong>฿<?=number_format($item['price_at_purchase']*$item['quantity'],2)?></strong></div><?php endforeach;?><div class="row"><span>ยอดรวมสินค้า</span><span>฿<?=number_format($order['subtotal'],2)?></span></div><div class="row"><span>ค่าจัดส่ง</span><span>฿<?=number_format($order['shipping_fee'],2)?></span></div><div class="row total"><span>ยอดสุทธิ</span><span>฿<?=number_format($order['total_amount'],2)?></span></div><div><strong>ที่อยู่สำหรับจัดส่ง</strong><p><?=nl2br(htmlspecialchars($order['shipping_name']."\n".$order['shipping_address']."\nโทร: ".$order['shipping_phone']))?></p></div><a href="/pages/orders.php?tab=pending" class="btn-pay-later">ชำระเงินภายหลัง</a></aside></div></main><?php require __DIR__.'/../components/footer.php';?>
<script>
document.getElementById('payment-slip-input').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('slip-preview');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) { preview.src = e.target.result; preview.style.display = 'inline-block'; }
        reader.readAsDataURL(file);
    } else {
        preview.src = ''; preview.style.display = 'none';
    }
});
</script>
</body></html>
