<?php
session_start();
if (empty($_SESSION['user_id'])) { header('Location:/pages/login.php'); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/checkout_recommendations.php';
require_once __DIR__.'/../config/promotions.php';
$userId=(int)$_SESSION['user_id'];

if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['selected_ids'])){
    $ids=array_values(array_unique(array_filter(array_map('intval',json_decode($_POST['selected_ids'],true)?:[]))));
    if($ids)$_SESSION['checkout_selected_ids']=$ids;
}
$ids=$_SESSION['checkout_selected_ids']??[];
if(!$ids){header('Location:/pages/cart.php');exit;}
$marks=implode(',',array_fill(0,count($ids),'?'));
$stmt=$pdo->prepare("SELECT ci.quantity,b.*,COALESCE(p.company_name,s.shop_name,NULLIF(u.display_name,''),u.email,'ผู้ขายวนวรรณ') seller_name FROM cart_items ci JOIN books b ON b.book_id=ci.book_id LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id LEFT JOIN users u ON b.seller_type='user' AND u.user_id=b.seller_id WHERE ci.user_id=? AND ci.book_id IN ($marks) AND b.status='available'");
$stmt->execute(array_merge([$userId],$ids));
$items=$stmt->fetchAll(PDO::FETCH_ASSOC);
if(!$items){unset($_SESSION['checkout_selected_ids']);header('Location:/pages/cart.php');exit;}
$recommendationContext=[];foreach($items as $contextItem)$recommendationContext[(int)$contextItem['book_id']]=(int)$contextItem['quantity'];ksort($recommendationContext);$recommendationContext=hash('sha256',json_encode($recommendationContext));
if(($_SESSION['checkout_recommendation_context']??'')!==$recommendationContext){$_SESSION['checkout_recommendation_context']=$recommendationContext;$_SESSION['checkout_recommendation_refreshes']=0;}
if(empty($_SESSION['checkout_recommendation_csrf']))$_SESSION['checkout_recommendation_csrf']=bin2hex(random_bytes(24));
if(isset($_GET['refresh_ai'])&&hash_equals($_SESSION['checkout_recommendation_csrf'],(string)($_GET['token']??''))){if((int)($_SESSION['checkout_recommendation_refreshes']??0)<3)$_SESSION['checkout_recommendation_refreshes']=(int)($_SESSION['checkout_recommendation_refreshes']??0)+1;header('Location:/pages/checkout_summary.php');exit;}
$recommendationRefreshes=min(3,(int)($_SESSION['checkout_recommendation_refreshes']??0));

$groups=[];$subtotal=0;$count=0;
foreach($items as $item){
    $key=$item['seller_type'].'-'.$item['seller_id'];
    $groups[$key]['name']=wanawanSellerName($item['seller_name'],$item['seller_type']);
    $groups[$key]['seller_type']=$item['seller_type'];$groups[$key]['seller_id']=(int)$item['seller_id'];
    $groups[$key]['items'][]=$item;
    $groups[$key]['subtotal']=($groups[$key]['subtotal']??0)+$item['price']*$item['quantity'];
    $groups[$key]['count']=($groups[$key]['count']??0)+$item['quantity'];
    $subtotal+=$item['price']*$item['quantity'];$count+=$item['quantity'];
}
foreach($groups as &$group){$group['shipping']=wanawanShippingFee((int)$group['count']);$group['promotion']=wanawanBestSellerPromotion($pdo,$group['seller_type'],$group['seller_id'],(float)$group['subtotal'],(int)$group['count'],$group['items']);}unset($group);
$shipping=array_sum(array_column($groups,'shipping'));$discount=array_sum(array_map(static fn(array $g):float=>(float)$g['promotion']['discount_amount'],$groups));$total=$subtotal+$shipping-$discount;
$recommendations=wanawanCheckoutRecommendations($pdo,$userId,$items,4,$recommendationRefreshes);
function checkoutImg(?string $v):?string{if(!$v)return null;if(preg_match('#^https?://#i',$v))return$v;$v=ltrim(str_replace('\\','/',$v),'/');return str_starts_with($v,'uploads/')?'/pages/'.$v:'/assets/image/books/'.$v;}
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>สรุปรายการสั่งซื้อ - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>"><style>
*{box-sizing:border-box}.wrap{max-width:1160px;margin:auto;padding:44px 32px 85px}.title{color:var(--secondary-color);font-size:33px}.layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:26px}.card{border:1px solid #dfe3df;border-radius:15px;background:#fff;padding:24px;margin-bottom:20px}.card h2{color:var(--secondary-color);font-size:19px;margin:0 0 20px}.fields{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field label{display:block;font-size:13px;color:var(--text-muted);margin-bottom:6px}.field input,.field textarea{width:100%;border:1px solid #d8d8d0;border-radius:9px;padding:12px;font:inherit}.field textarea{min-height:90px}.wide{grid-column:1/-1}.shop-group{border:1px solid #ebe1dc;border-radius:13px;overflow:hidden;margin-bottom:14px}.shop-head,.shop-foot,.row{display:flex;justify-content:space-between;gap:15px}.shop-head{padding:13px 16px;background:#fff8fa;color:#785a65}.book{display:grid;grid-template-columns:70px 1fr auto;gap:15px;align-items:center;padding:15px}.book img,.placeholder{width:64px;height:87px;object-fit:cover;border-radius:7px;background:#f9e8df}.book h3{font-size:15px;margin:0}.book p{margin:3px 0;color:var(--text-muted);font-size:13px}.shop-foot{justify-content:flex-end;padding:11px 15px;border-top:1px solid #eee;color:var(--text-muted);font-size:13px}.summary{position:sticky;top:20px;height:max-content}.shop-sum{padding:10px 0;border-bottom:1px solid #eee}.shop-sum strong{font-size:13px}.row{margin:15px 0}.total{border-top:1px solid #ddd;padding-top:18px;color:var(--secondary-color);font-size:21px;font-weight:700}.pay{width:100%;height:55px;border:0;border-radius:10px;background:var(--secondary-color);color:#fff;font:inherit;font-weight:700;cursor:pointer}.method{padding:16px;border:1px solid #e5ddd7;border-radius:10px;background:#faf8f6}.hint{text-align:center;color:var(--text-muted);font-size:12px}.ai-head{display:flex;justify-content:space-between;gap:14px}.ai-head p{margin:-12px 0 18px;color:var(--text-muted);font-size:13px}.ai-badge{white-space:nowrap;background:#edf5e9;color:#46613d;border-radius:99px;padding:6px 10px;font-size:11px;font-weight:700}.recommend-grid{display:grid;grid-template-columns:repeat(2, 1fr) repeat(2, 1fr);gap:12px}.recommend{display:grid;grid-template-columns:72px 1fr;gap:12px;border:1px solid #eadfd9;border-radius:12px;padding:12px}.recommend img,.recommend .placeholder{width:72px;height:98px}.recommend h3{font-size:14px;margin:4px 0}.reason{font-size:11px;color:#66735f;line-height:1.4;min-height:31px}.promo{display:inline-block;background:#fff0e8;color:#a74f28;border-radius:5px;padding:3px 6px;font-size:10px;font-weight:700}.recommend-price{font-weight:700;color:var(--secondary-color);margin-top:6px}.old-price{font-size:11px;color:#999;text-decoration:line-through;margin-left:5px}.add-recommend{border:1px solid var(--secondary-color);background:#fff;color:var(--secondary-color);border-radius:7px;padding:7px 9px;font:inherit;font-size:12px;font-weight:700;cursor:pointer;margin-top:8px}.add-recommend:disabled{opacity:.55}.ai-note{font-size:11px;color:#888;margin:14px 0 0}@media(max-width:820px){.layout{grid-template-columns:1fr}.summary{position:static}}@media(max-width:600px){.recommend-grid{grid-template-columns:repeat(2, 1fr)}}@media(max-width:520px){.wrap{padding:28px 14px}.fields{grid-template-columns:1fr}.wide{grid-column:auto}.book{grid-template-columns:58px 1fr}.book>strong{grid-column:2}.shop-foot{flex-direction:column}}
</style></head><body><?php require __DIR__.'/../components/header.php';?><main class="wrap"><h1 class="title">ตรวจสอบและสั่งซื้อ</h1><form class="layout" method="post" action="/pages/create_multi_orders.php"><div>
<section class="card"><h2>ข้อมูลการจัดส่ง</h2><div class="fields"><div class="field"><label>ชื่อผู้รับ</label><input name="shipping_name" value="<?=htmlspecialchars($_SESSION['display_name']??'')?>" required></div><div class="field"><label>เบอร์โทรศัพท์</label><input name="shipping_phone" pattern="[0-9+ -]{9,20}" required></div><div class="field wide"><label>ที่อยู่จัดส่ง</label><textarea name="shipping_address" required></textarea></div></div></section>
<section class="card"><h2>รายการสินค้า แยกตามร้าน</h2><?php foreach($groups as $group):?><section class="shop-group"><header class="shop-head"><strong><?=htmlspecialchars($group['name'])?></strong><span><?=$group['count']?> เล่ม</span></header><?php foreach($group['items'] as $item):?><article class="book"><?php if($img=checkoutImg($item['image_cover'])):?><img src="<?=htmlspecialchars($img)?>" alt=""><?php else:?><div class="placeholder"></div><?php endif;?><div><h3><?=htmlspecialchars($item['title'])?></h3><p><?=htmlspecialchars($item['author']?:'-')?> · จำนวน <?=$item['quantity']?> เล่ม</p></div><strong>฿<?=number_format($item['price']*$item['quantity'],2)?></strong></article><?php endforeach;?><footer class="shop-foot"><span>ยอดสินค้า ฿<?=number_format($group['subtotal'],2)?></span><span>ค่าส่ง ฿<?=number_format($group['shipping'],2)?></span><span><?=htmlspecialchars($group['promotion']['promotion_name'])?> −฿<?=number_format($group['promotion']['discount_amount'],2)?></span><strong>รวม ฿<?=number_format($group['subtotal']+$group['shipping']-$group['promotion']['discount_amount'],2)?></strong></footer></section><?php endforeach;?></section>
<?php if($recommendations):?><section class="card"><div class="ai-head"><div><h2>หนังสือที่ AI คิดว่าคุณน่าจะชอบ</h2><p>เลือกเพิ่มได้ก่อนชำระเงิน ระบบจะคำนวณยอดและโปรโมชันใหม่ให้อัตโนมัติ</p></div><span class="ai-badge">✦ AI แนะนำเฉพาะคุณ</span></div><div class="recommend-grid"><?php foreach($recommendations as $book):?><article class="recommend"><?php if($img=checkoutImg($book['image_cover'])):?><img src="<?=htmlspecialchars($img)?>" alt=""><?php else:?><div class="placeholder"></div><?php endif;?><div><span class="promo"><?=htmlspecialchars($book['promo_label'])?></span><h3><?=htmlspecialchars($book['title'])?></h3><div class="reason"><?=htmlspecialchars($book['ai_reason'])?></div><div class="recommend-price">฿<?=number_format($book['price'],2)?><?php if((float)($book['original_price']??0)>(float)$book['price']):?><span class="old-price">฿<?=number_format($book['original_price'],2)?></span><?php endif;?></div><button type="button" class="add-recommend" data-book-id="<?=$book['book_id']?>">+ เพิ่มในคำสั่งซื้อ</button></div></article><?php endforeach;?></div><p class="ai-note">คำแนะนำวิเคราะห์จากความเกี่ยวข้องของหนังสือ ประวัติการซื้อ และความนิยม โดยไม่ส่งข้อมูลส่วนตัวออกนอกระบบ</p></section><?php endif;?>
<section class="card"><h2>วิธีการชำระเงิน</h2><div class="method"><strong>PromptPay</strong><br><span>ชำระรวมครั้งเดียว ระบบจะแยกคำสั่งซื้อให้แต่ละร้านอัตโนมัติ</span></div></section></div>
<style>.promotion-row{margin:9px 0;color:#9d4868;font-size:13px;align-items:flex-start}.promotion-row span:first-child{min-width:0;line-height:1.45}.promotion-row span:last-child{white-space:nowrap;font-weight:600}</style>
<aside class="card summary">
    <h2>สรุปยอดชำระเงิน</h2>
    <?php foreach($groups as $group):?>
        <div class="shop-sum">
            <strong><?=htmlspecialchars($group['name'])?></strong>
            <div class="row"><span>สินค้า + ค่าส่ง</span><span>฿<?=number_format($group['subtotal']+$group['shipping'],2)?></span></div>
            <?php foreach($group['promotion']['applied_promotions'] as $applied):?>
                <div class="row promotion-row"><span><?=htmlspecialchars($applied['promotion_name'])?></span><span>− ฿<?=number_format($applied['discount_amount'],2)?></span></div>
            <?php endforeach;?>
        </div>
    <?php endforeach;?>
    <div class="row"><span>ยอดสินค้า (<?=$count?> เล่ม)</span><span>฿<?=number_format($subtotal,2)?></span></div>
    <div class="row"><span>ค่าจัดส่ง <?=count($groups)?> ร้าน</span><span>฿<?=number_format($shipping,2)?></span></div>
    <div class="row"><span>ส่วนลดทั้งหมด</span><span>− ฿<?=number_format($discount,2)?></span></div>
    <div class="row total"><span>ยอดรวมทั้งหมด</span><span>฿<?=number_format($total,2)?></span></div>
    <button class="pay">ชำระเงินและสั่งซื้อ <?=count($groups)?> ร้าน →</button>
    <p class="hint">ระบบใช้ทุกโปรโมชั่นที่เข้าเงื่อนไขให้อัตโนมัติ</p>
</aside></form></main><?php require __DIR__.'/../components/footer.php';?>
<script>
(()=>{
 const head=document.querySelector('.ai-head');if(!head)return;
 let remaining=Math.max(0,3-<?=$recommendationRefreshes?>);const token=<?=json_encode($_SESSION['checkout_recommendation_csrf'])?>;
 const controls=document.createElement('div');controls.className='ai-controls';const badge=head.querySelector('.ai-badge');if(badge)controls.appendChild(badge);
 const refresh=document.createElement('button');refresh.type='button';refresh.className='ai-refresh';controls.appendChild(refresh);head.appendChild(controls);
 const updateRefresh=()=>{refresh.disabled=remaining<=0;refresh.classList.toggle('is-disabled',remaining<=0);refresh.textContent=remaining>0?'↻ สุ่มใหม่ (เหลือ '+remaining+' ครั้ง)':'สุ่มครบ 3 ครั้งแล้ว'};updateRefresh();
 refresh.addEventListener('click',async()=>{if(remaining<=0)return;refresh.disabled=true;refresh.textContent='กำลังสุ่ม...';try{const response=await fetch('/pages/handlers/checkout_recommendations_refresh.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf_token:token})});const data=await response.json();if(!response.ok||!data.success)throw new Error(data.message||'สุ่มคำแนะนำไม่สำเร็จ');document.querySelector('.recommend-grid').innerHTML=data.html;remaining=data.remaining;updateRefresh()}catch(error){alert(error.message);updateRefresh()}});
 const style=document.createElement('style');style.textContent='.ai-head>.ai-badge{display:none}.ai-controls{display:flex;flex-direction:column;align-items:flex-end;gap:7px}.ai-refresh{display:inline-flex;align-items:center;justify-content:center;padding:7px 10px;border:1px solid var(--secondary-color);border-radius:8px;color:var(--secondary-color);background:#fff;font:inherit;font-size:11px;font-weight:700;white-space:nowrap;cursor:pointer}.ai-refresh:hover{background:#fff4f7}.ai-refresh.is-disabled{border-color:#ddd;color:#999;background:#f7f7f7;cursor:default}@media(max-width:600px){.ai-head{flex-direction:column}.ai-controls{width:100%;align-items:flex-start;flex-direction:row;flex-wrap:wrap}}';document.head.appendChild(style);
})();
document.addEventListener('click',async event=>{const button=event.target.closest('.add-recommend');if(!button)return;button.disabled=true;const old=button.textContent;button.textContent='กำลังเพิ่ม...';try{const response=await fetch('/pages/handlers/checkout_recommendation_handler.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({book_id:Number(button.dataset.bookId),csrf_token:<?=json_encode($_SESSION['checkout_recommendation_csrf'])?>})});const data=await response.json();if(!response.ok||!data.success)throw new Error(data.message||'เพิ่มสินค้าไม่สำเร็จ');button.textContent='✓ เพิ่มแล้ว';location.reload()}catch(error){alert(error.message);button.disabled=false;button.textContent=old}});
</script></body></html>
