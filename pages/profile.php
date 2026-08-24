<?php
session_start();if(empty($_SESSION['user_id'])){header('Location:/pages/login.php');exit;}require_once __DIR__.'/../config/config.php';$userId=(int)$_SESSION['user_id'];
if(empty($_SESSION['profile_csrf_token']))$_SESSION['profile_csrf_token']=bin2hex(random_bytes(24));
$profileMessage='';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['delete_book'])){
    $token=(string)($_POST['csrf_token']??'');$bookId=(int)($_POST['book_id']??0);
    if(!hash_equals((string)$_SESSION['profile_csrf_token'],$token)){$profileMessage='ไม่สามารถยืนยันคำขอได้ กรุณาลองใหม่';}
    else{
        $delete=$pdo->prepare("UPDATE books SET status='hidden' WHERE book_id=? AND ((seller_type='user' AND seller_id=?) OR (seller_type='shop' AND seller_id IN(SELECT shop_id FROM shop_staff WHERE user_id=?)) OR (seller_type='publisher' AND seller_id IN(SELECT publisher_id FROM publisher_staff WHERE user_id=?)))");
        $delete->execute([$bookId,$userId,$userId,$userId]);
        $profileMessage=$delete->rowCount()?'ลบโพสต์เรียบร้อยแล้ว':'ไม่พบโพสต์หรือคุณไม่มีสิทธิ์ลบโพสต์นี้';
    }
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['delete_exchange'])){
    $token=(string)($_POST['csrf_token']??'');$exchangeId=(int)($_POST['exchange_id']??0);
    if(!hash_equals((string)$_SESSION['profile_csrf_token'],$token)){$profileMessage='ไม่สามารถยืนยันคำขอได้ กรุณาลองใหม่';}
    else{$delete=$pdo->prepare("UPDATE exchanges SET status='completed' WHERE exchange_id=? AND owner_id=? AND status<>'completed'");$delete->execute([$exchangeId,$userId]);$profileMessage=$delete->rowCount()?'ลบโพสต์แลกเปลี่ยนเรียบร้อยแล้ว':'ไม่พบโพสต์หรือคุณไม่มีสิทธิ์ลบโพสต์นี้';}
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['save_notify'])){
    $token=(string)($_POST['csrf_token']??'');
    $notifyPreference=(string)($_POST['notify_preference']??'email');
    if(!hash_equals((string)$_SESSION['profile_csrf_token'],$token)){
        $profileMessage='ไม่สามารถยืนยันคำขอได้ กรุณาลองใหม่';
    }else{
        $stmt=$pdo->prepare('SELECT line_user_id FROM users WHERE user_id=?');$stmt->execute([$userId]);
        $hasLine = !empty($stmt->fetchColumn());
        if(!in_array($notifyPreference,['email','line','both','none'],true))$notifyPreference='email';
        if(in_array($notifyPreference,['line','both'],true) && !$hasLine){
            $profileMessage='กรุณาเชื่อมต่อบัญชี LINE ก่อนเลือกการแจ้งเตือนผ่าน LINE';
        } else {
            $pdo->prepare('UPDATE users SET notify_preference=? WHERE user_id=?')->execute([$notifyPreference,$userId]);
            $profileMessage='บันทึกการตั้งค่าการแจ้งเตือนแล้ว';
        }
    }
}
if(empty($_SESSION['line_disconnect_csrf']))$_SESSION['line_disconnect_csrf']=bin2hex(random_bytes(24));
$stmt=$pdo->prepare('SELECT * FROM users WHERE user_id=?');$stmt->execute([$userId]);$user=$stmt->fetch(PDO::FETCH_ASSOC);if(!$user)exit('ไม่พบผู้ใช้');
$tab=$_GET['tab']??'selling';$profile_active=in_array($tab,['orders','notify'])?$tab:'profile';$items=[];$orders=[];$reviews=[];
if($tab==='exchange'){$q=$pdo->prepare("SELECT exchange_id id,title,author,image_cover FROM exchanges WHERE owner_id=? AND status<>'completed' ORDER BY created_at DESC");$q->execute([$userId]);$items=$q->fetchAll(PDO::FETCH_ASSOC);}
elseif($tab==='orders'){$q=$pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');$q->execute([$userId]);$orders=$q->fetchAll(PDO::FETCH_ASSOC);}
elseif($tab==='reviews'){$q=$pdo->prepare('SELECT r.*,b.title,b.image_cover,o.order_number FROM book_reviews r JOIN books b ON b.book_id=r.book_id LEFT JOIN order_items oi ON oi.order_item_id=r.order_item_id LEFT JOIN orders o ON o.order_id=oi.order_id WHERE r.user_id=? ORDER BY r.created_at DESC');$q->execute([$userId]);$reviews=$q->fetchAll(PDO::FETCH_ASSOC);}
else{$q=$pdo->prepare("SELECT b.book_id id,b.title,b.author,b.price,b.image_cover,b.book_type FROM books b WHERE b.status<>'hidden' AND ((b.seller_type='user' AND b.seller_id=?) OR (b.seller_type='shop' AND b.seller_id IN(SELECT shop_id FROM shop_staff WHERE user_id=?)) OR (b.seller_type='publisher' AND b.seller_id IN(SELECT publisher_id FROM publisher_staff WHERE user_id=?))) ORDER BY b.created_at DESC");$q->execute([$userId,$userId,$userId]);$items=$q->fetchAll(PDO::FETCH_ASSOC);}
$exchangeCount=$pdo->prepare("SELECT COUNT(*) FROM exchanges WHERE owner_id=? AND status<>'completed'");$exchangeCount->execute([$userId]);$bookCount=(int)$exchangeCount->fetchColumn();
function profileImage(?string $v):?string{if(!$v)return null;if(preg_match('#^https?://#i',$v))return $v;$v=ltrim($v,'/');return str_starts_with($v,'uploads/')?'/pages/'.$v:'/assets/image/'.$v;}
?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const count=document.querySelector('.stats .stat:first-child strong');
 if(count){count.dataset.followersType='user';count.dataset.followersId='<?=$userId?>'}
});
</script>
<?php
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>โปรไฟล์ - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css"><style>*{box-sizing:border-box}.account{display:flex;max-width:1280px;margin:auto;min-height:820px}.content{flex:1;padding:45px 55px 85px;min-width:0}.hero{display:grid;grid-template-columns:190px 1fr;gap:35px;align-items:center;margin-bottom:42px}.avatar{width:180px;height:180px;border-radius:8px;background:#f7dfe8;display:grid;place-items:center;overflow:hidden;color:var(--primary-color);font-size:60px}.avatar img{width:100%;height:100%;object-fit:cover}.name{font-size:40px;color:var(--secondary-color);margin:0 0 15px}.bio{color:var(--text-muted);line-height:1.7;max-width:600px}.stats{display:flex;gap:60px;margin:22px 0}.stat strong,.stat span{display:block;text-align:center}.stat strong{font-size:28px;color:var(--secondary-color)}.edit{display:inline-grid;place-items:center;min-width:180px;height:46px;border-radius:8px;background:var(--secondary-color);color:#fff;text-decoration:none}.tabs{display:flex;gap:14px;margin-bottom:26px}.tabs a{flex:1;padding:11px;text-align:center;border-radius:24px;background:#f7dfe8;color:var(--secondary-color);text-decoration:none;font-weight:600}.tabs a.active{background:var(--secondary-color);color:#fff}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.empty{grid-column:1/-1;padding:80px;text-align:center;color:var(--text-muted)}.order,.review{border:1px solid #ddd;border-radius:12px;padding:20px;margin-bottom:12px}.order-head{display:flex;justify-content:space-between}.status{color:#a46745}.order a{color:var(--secondary-color)}@media(max-width:1000px){.grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:800px){.account{display:block}.content{padding:32px 18px}.hero{grid-template-columns:120px 1fr}.avatar{width:115px;height:115px}.name{font-size:29px}.grid{grid-template-columns:1fr}.tabs{overflow-x:auto}.tabs a{min-width:180px}}@media(max-width:480px){.hero{grid-template-columns:1fr;text-align:center}.avatar{margin:auto}.stats{justify-content:center}}</style></head><body><?php require __DIR__.'/../components/header.php';?><div class="account"><?php require __DIR__.'/../components/profile_sidebar.php';?><main class="content"><?php if($tab!=='notify'):?><section class="hero"><div class="avatar"><?php if($img=profileImage($user['profile_image'])):?><img src="<?=htmlspecialchars($img)?>" alt=""><?php else:?><?=htmlspecialchars(mb_substr($user['display_name']?:$user['email'],0,1))?><?php endif;?></div><div><h1 class="name"><?=htmlspecialchars($user['display_name']?:'สมาชิกวนวรรณ')?></h1><p class="bio"><?=nl2br(htmlspecialchars($user['bio']?:'คนรักหนังสือและเรื่องราวดี ๆ ในชุมชนวนวรรณ'))?></p><div class="stats"><div class="stat"><strong><?=number_format($user['followers_count'])?></strong><span>ผู้ติดตาม</span></div><div class="stat"><strong><?=number_format($bookCount)?></strong><span>หนังสือทั้งหมด</span></div></div><a class="edit" href="/pages/edit_profile.php">แก้ไขโปรไฟล์</a></div></section><nav class="tabs"><a class="<?=$tab==='selling'?'active':''?>" href="?tab=selling">หนังสือที่กำลังลงขาย</a><a class="<?=$tab==='exchange'?'active':''?>" href="?tab=exchange">หนังสือสำหรับแลกเปลี่ยน</a><a class="<?=$tab==='reviews'?'active':''?>" href="?tab=reviews">รีวิวของฉัน</a></nav><?php endif;?>
<?php if($tab==='orders'):?><h2>รายการการสั่งซื้อ</h2><?php if(!$orders):?><div class="empty">ยังไม่มีคำสั่งซื้อ</div><?php else:foreach($orders as $o):?><div class="order"><div class="order-head"><strong>#<?=htmlspecialchars($o['order_number'])?></strong><span class="status"><?=htmlspecialchars(['pending'=>'รอชำระเงิน','reviewing'=>'รอตรวจสอบ','paid'=>'ชำระแล้ว','cancelled'=>'ยกเลิก'][$o['payment_status']]??$o['payment_status'])?></span></div><p>ยอดสุทธิ ฿<?=number_format($o['total_amount'],2)?> • <?=htmlspecialchars($o['created_at'])?></p><a href="/pages/order_receipt.php?number=<?=urlencode($o['order_number'])?>">ดูรายละเอียด</a></div><?php endforeach;endif;?>
<?php elseif($tab==='reviews'):?><?php if(!$reviews):?><div class="empty">ยังไม่มีรีวิว</div><?php else:foreach($reviews as $r):?><div class="review"><strong><?=htmlspecialchars($r['title'])?> • <?=str_repeat('★',(int)$r['rating'])?></strong><p><?=htmlspecialchars($r['review_text']?:'ไม่ได้เขียนข้อความรีวิว')?></p></div><?php endforeach;endif;?>
<?php elseif($tab==='notify'):?>
    <h2>ตั้งค่าการรับการแจ้งเตือน</h2>
    <?php if(!empty($profileMessage)):?><div style="padding:15px;background:#eefcf1;color:#1e6a39;border-radius:8px;margin-bottom:20px;"><?=htmlspecialchars($profileMessage)?></div><?php endif;?>
    <form method="post" style="max-width:500px;background:#fff;padding:25px;border-radius:12px;border:1px solid #eadbd4;">
        <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['profile_csrf_token'])?>">
        <input type="hidden" name="save_notify" value="1">
        <p style="color:var(--text-muted);margin-bottom:20px;">เลือกรับข่าวสารเมื่อสถานะการชำระเงินหรือการจัดส่งเปลี่ยนแปลง</p>
        <select name="notify_preference" style="width:100%;height:46px;padding:0 12px;border:1px solid #ccd3c5;border-radius:8px;margin-bottom:15px;font:inherit;">
            <option value="email" <?=($user['notify_preference']??'email')==='email'?'selected':''?>>อีเมล</option>
            <option value="line" <?=($user['notify_preference']??'email')==='line'?'selected':''?>>LINE</option>
            <option value="both" <?=($user['notify_preference']??'email')==='both'?'selected':''?>>อีเมลและ LINE</option>
            <option value="none" <?=($user['notify_preference']??'email')==='none'?'selected':''?>>ไม่รับการแจ้งเตือนภายนอก</option>
        </select>
        <div style="margin-bottom:20px;">
            <?php if(empty($user['line_user_id'])):?>
                <div style="color:#b36d65;font-size:13px;margin-bottom:8px;">ยังไม่ได้เชื่อมต่อบัญชี LINE</div>
                <a href="/pages/line_connect.php" style="display:inline-grid;place-items:center;height:44px;padding:0 24px;border-radius:8px;background:#06c755;color:#fff;text-decoration:none;font-weight:700;">เชื่อมต่อ LINE</a>
            <?php else:?>
                <div style="color:#23853b;font-weight:600;margin-bottom:8px;">✓ เชื่อมต่อ LINE แล้ว</div>
                <a href="javascript:void(0)" onclick="disconnectLine()" style="color:#b34d55;text-decoration:underline;font-size:13px;">ยกเลิกการเชื่อมต่อ</a>
            <?php endif;?>
        </div>
        <button class="edit" style="width:100%;border:0;cursor:pointer;">บันทึกการตั้งค่า</button>
    </form>
    <script>
    if(!window.disconnectLine){
        window.disconnectLine = function(){
            const form=document.createElement('form');form.method='post';form.action='/pages/line_disconnect.php';
            const token=document.createElement('input');token.type='hidden';token.name='csrf_token';token.value=<?=json_encode($_SESSION['line_disconnect_csrf'])?>;
            form.append(token);document.body.append(form);form.submit();
        }
    }
    </script>
<?php else:?><div class="grid"><?php if(!$items):?><div class="empty">ยังไม่มีหนังสือในหมวดนี้</div><?php else:foreach($items as $row){$book=['id'=>$row['id'],'title'=>$row['title'],'author'=>$row['author']??'','price'=>$row['price']??0,'badge'=>$tab==='exchange'?'แลกเปลี่ยน':'มาใหม่','image'=>profileImage($row['image_cover']??null)];$card_type=$tab==='exchange'?'exchange':(($row['book_type']??'used')==='new'?'new':'used');$owner_controls=in_array($tab,['selling','exchange'],true);$owner_csrf_token=$_SESSION['profile_csrf_token'];require __DIR__.'/../components/book_card.php';$owner_controls=false;}endif;?></div><?php endif;?></main></div><?php require __DIR__.'/../components/footer.php';?></body></html>
