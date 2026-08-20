<?php
session_start();
if(empty($_SESSION['user_id'])){header('Location:/pages/login.php');exit;}
require_once __DIR__.'/../config/config.php';
$userId=(int)$_SESSION['user_id'];$profile_active='exchange';
if(empty($_SESSION['exchange_manage_csrf']))$_SESSION['exchange_manage_csrf']=bin2hex(random_bytes(24));
$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['exchange_manage_csrf'],$_POST['csrf_token']??''))$error='คำขอไม่ถูกต้อง กรุณาลองใหม่';
 else{
  $requestId=(int)($_POST['request_id']??0);$action=$_POST['request_action']??'';
  $q=$pdo->prepare("SELECT er.*,e.owner_id,e.title target_title FROM exchange_requests er JOIN exchanges e ON e.exchange_id=er.exchange_id WHERE er.request_id=? AND (er.requester_id=? OR e.owner_id=?)");
  $q->execute([$requestId,$userId,$userId]);$request=$q->fetch(PDO::FETCH_ASSOC);
  if(!$request)$error='ไม่พบคำขอแลกเปลี่ยน';
  else{
   $isOwner=(int)$request['owner_id']===$userId;$allowed=$isOwner?['accept','reject','complete']:['cancel'];
   if(!in_array($action,$allowed,true))$error='คุณไม่มีสิทธิ์ดำเนินการนี้';
   elseif($action==='accept'&&$request['status']!=='pending')$error='คำขอนี้ไม่อยู่ในสถานะรอตอบรับ';
   elseif($action==='complete'&&!in_array($request['status'],['pending','accepted'],true))$error='ไม่สามารถยืนยันการแลกเปลี่ยนสำหรับคำขอนี้ได้';
   elseif(in_array($action,['reject','cancel'],true)&&!in_array($request['status'],['pending','accepted'],true))$error='ไม่สามารถเปลี่ยนสถานะคำขอนี้ได้';
   else{
    $pdo->beginTransaction();
    try{
     $newStatus=['accept'=>'accepted','reject'=>'rejected','complete'=>'completed','cancel'=>'cancelled'][$action];
     $pdo->prepare('UPDATE exchange_requests SET status=? WHERE request_id=?')->execute([$newStatus,$requestId]);
     if($action==='accept'){$pdo->prepare("UPDATE exchanges SET status='in_progress' WHERE exchange_id IN (?,?)")->execute([(int)$request['exchange_id'],(int)($request['offered_exchange_id']??0)]);}
     elseif($action==='complete'){$pdo->prepare("UPDATE exchanges SET status='completed' WHERE exchange_id IN (?,?)")->execute([(int)$request['exchange_id'],(int)($request['offered_exchange_id']??0)]);}
     elseif(in_array($action,['reject','cancel'],true)&&$request['status']==='accepted'){$pdo->prepare("UPDATE exchanges SET status='open' WHERE exchange_id IN (?,?) AND status='in_progress'")->execute([(int)$request['exchange_id'],(int)($request['offered_exchange_id']??0)]);}
     $recipient=$isOwner?(int)$request['requester_id']:(int)$request['owner_id'];
     $statusText=['accept'=>'ตอบรับคำขอแล้ว','reject'=>'ปฏิเสธคำขอแล้ว','complete'=>'ยืนยันการแลกเปลี่ยนสำเร็จแล้ว','cancel'=>'ยกเลิกคำขอแล้ว'][$action];
     $pdo->prepare("INSERT INTO notifications(user_id,title,message,notification_type,is_read,sent_via) VALUES(?,'อัปเดตคำขอแลกเปลี่ยน',?,'exchange',0,'web')")->execute([$recipient,$statusText.' สำหรับ “'.$request['target_title'].'”']);
     $pdo->commit();$message=$statusText;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error='ไม่สามารถอัปเดตคำขอได้ กรุณาลองใหม่';}
   }
  }
 }
}
$tab=($_GET['tab']??'received')==='sent'?'sent':'received';$status=$_GET['status']??'';$category=trim($_GET['category']??'');
$where=$tab==='received'?['target.owner_id=:uid']:['er.requester_id=:uid'];$params=[':uid'=>$userId];
if(in_array($status,['pending','accepted','completed','rejected','cancelled'],true)){$where[]='er.status=:status';$params[':status']=$status;}
if($category!==''){$where[]='target.category=:category';$params[':category']=$category;}
$sql="SELECT er.*,target.title target_title,target.author target_author,target.image_cover target_image,target.condition_percent target_condition,target.category,target.owner_id,
 offer.title offer_title,offer.author offer_author,offer.image_cover offer_image,offer.condition_percent offer_condition,
 COALESCE(NULLIF(requester.display_name,''),requester.email) requester_name,requester.profile_image requester_image,
 COALESCE(NULLIF(owner.display_name,''),owner.email) owner_name,owner.profile_image owner_image
 FROM exchange_requests er JOIN exchanges target ON target.exchange_id=er.exchange_id
 JOIN users requester ON requester.user_id=er.requester_id JOIN users owner ON owner.user_id=target.owner_id
 LEFT JOIN exchanges offer ON offer.exchange_id=er.offered_exchange_id WHERE ".implode(' AND ',$where)." ORDER BY er.updated_at DESC,er.request_id DESC";
$stmt=$pdo->prepare($sql);$stmt->execute($params);$requests=$stmt->fetchAll(PDO::FETCH_ASSOC);
$categories=$pdo->query("SELECT DISTINCT category FROM exchanges WHERE category IS NOT NULL AND category<>'' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$total=$tab==='received'?$pdo->prepare("SELECT COUNT(*) FROM exchange_requests er JOIN exchanges e ON e.exchange_id=er.exchange_id WHERE e.owner_id=?"):$pdo->prepare("SELECT COUNT(*) FROM exchange_requests WHERE requester_id=?");$total->execute([$userId]);$allCount=(int)$total->fetchColumn();
function xrImage(?string $v):?string{if(!$v)return null;if(preg_match('#^https?://#i',$v))return $v;$v=ltrim(str_replace('\\','/',$v),'/');$name=basename($v);if(is_file(__DIR__.'/uploads/profiles/'.$name))return '/pages/uploads/profiles/'.$name;return str_starts_with($v,'uploads/')?'/pages/'.$v:'/assets/image/books/'.$v;}
function xrStatus(string $s):array{return match($s){'accepted'=>['กำลังเจรจา','progress'],'completed'=>['แลกเปลี่ยนสำเร็จ','done'],'rejected'=>['ปฏิเสธแล้ว','rejected'],'cancelled'=>['ยกเลิกแล้ว','cancelled'],default=>['รอตอบรับ','pending']};}
$requestControls=array_map(fn($request)=>['id'=>(int)$request['request_id'],'status'=>$request['status']],$requests);
?>
<style>
.exchange-status-control{position:relative}.exchange-status-select{width:100%;min-height:42px;padding:8px 38px 8px 12px;border:1px solid #b8c8c9;border-radius:9px;background:#fff;color:var(--secondary-color);font:inherit;font-weight:600;cursor:pointer;appearance:none}.exchange-status-control:after{content:'';position:absolute;right:15px;top:50%;width:9px;height:9px;border-right:2px solid var(--secondary-color);border-bottom:2px solid var(--secondary-color);transform:translateY(-70%) rotate(45deg);pointer-events:none}.exchange-status-select option[value=accept]{color:#2781df}.exchange-status-select option[value=complete]{color:#15912b}.exchange-status-select option[value=reject]{color:#d33131}.exchange-status-select:disabled{cursor:default;opacity:.72;background:#f7f7f7}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const controls=<?=json_encode($requestControls,JSON_UNESCAPED_UNICODE)?>;
 if(<?=json_encode($tab)?>!=='received')return;
 document.querySelectorAll('.request-card').forEach((card,index)=>{
  const info=controls[index];
  const actions=card.querySelector('.actions');
  if(!info||!actions)return;
  const oldForm=actions.querySelector('form');
  const form=document.createElement('form');
  form.method='post';
  form.className='exchange-status-control';
  form.innerHTML='<input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'],ENT_QUOTES)?>"><input type="hidden" name="request_id" value="'+info.id+'"><input type="hidden" name="request_action" value="">';
  const select=document.createElement('select');
  select.className='exchange-status-select';
  select.setAttribute('aria-label','เลือกสถานะคำขอแลกเปลี่ยน');
  const options=info.status==='pending'
   ?[['','ตอบรับคำขอ'],['accept','กำลังเจรจา'],['complete','แลกเปลี่ยนสำเร็จ'],['reject','ปฏิเสธ']]
   :info.status==='accepted'
    ?[['','กำลังเจรจา'],['complete','แลกเปลี่ยนสำเร็จ'],['reject','ปฏิเสธ']]
    :[['',info.status==='completed'?'แลกเปลี่ยนสำเร็จ':info.status==='rejected'?'ปฏิเสธแล้ว':'ยกเลิกแล้ว']];
  options.forEach(([value,label])=>{const option=document.createElement('option');option.value=value;option.textContent=label;select.append(option)});
  if(!['pending','accepted'].includes(info.status))select.disabled=true;
  select.addEventListener('change',()=>{if(!select.value)return;form.querySelector('[name="request_action"]').value=select.value;select.disabled=true;form.submit()});
  form.append(select);
  if(oldForm)oldForm.replaceWith(form);else actions.append(form);
 });
});
</script>
<?php
?>
<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>จัดการคำขอแลกเปลี่ยน - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css"><style>
*{box-sizing:border-box}.account{display:flex;min-height:850px;background:#fff}.manage{flex:1;max-width:1160px;margin:0 auto;padding:36px 42px 80px;min-width:0}.title{margin:0 0 14px;color:var(--secondary-color);font-size:34px}.tabs{display:flex;gap:12px;margin-bottom:14px}.tabs a{min-width:195px;padding:10px 24px;border-radius:24px;background:#fde8df;color:var(--secondary-color);text-align:center;text-decoration:none;font-weight:700;box-shadow:0 2px 4px #0002}.tabs a.active{background:var(--secondary-color);color:#fff}.tools{display:flex;align-items:center;justify-content:space-between;gap:18px;margin-bottom:24px}.filters{display:flex;gap:12px}.filters select{width:225px;height:42px;border:1px solid #cbd2cd;border-radius:9px;padding:0 14px;background:#fff;font:inherit;color:#6d6864}.result{color:var(--secondary-color);font-weight:600}.notice{padding:13px 17px;border-radius:9px;margin-bottom:16px}.ok{background:#e8f4e4;color:#3d7139}.bad{background:#fde5e1;color:#a44943}.request-list{display:grid;gap:24px}.request-card{border:1px solid #cbd1cc;border-radius:16px;background:#fff;overflow:hidden;box-shadow:0 2px 5px #00000012}.request-head{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 22px;border-bottom:1px solid #ddd}.person{display:flex;align-items:center;gap:12px}.avatar{width:58px;height:58px;border-radius:50%;overflow:hidden;background:#f5dfe5;display:grid;place-items:center;color:var(--secondary-color);font-weight:700}.avatar img{width:100%;height:100%;object-fit:cover}.person strong,.person span{display:block}.person strong{color:var(--secondary-color);font-size:18px}.person span{color:var(--text-muted)}.status{padding:7px 13px;border-radius:20px;font-size:13px;font-weight:700}.status.pending{background:#fff1df;color:#ef6d23}.status.progress{background:#e7f1f3;color:#176273}.status.done{background:#e0f5df;color:#198a23}.status.rejected,.status.cancelled{background:#fde7e3;color:#c4443d}.request-body{display:grid;grid-template-columns:minmax(0,1fr) 290px}.exchange-pair{display:grid;grid-template-columns:1fr 70px 1fr;align-items:center;gap:12px;padding:26px 28px}.book{text-align:center;min-width:0}.cover{width:120px;height:175px;margin:auto;border-radius:7px;background:#f7ebe5;overflow:hidden}.cover img{width:100%;height:100%;object-fit:cover}.book strong{display:block;margin-top:10px;color:var(--secondary-color);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.book small{display:block;color:#aaa}.swap{font-size:38px;color:#4e8490;text-align:center}.request-side{border-left:1px solid #ddd;padding:25px 20px;display:flex;flex-direction:column}.request-message{color:var(--secondary-color);line-height:1.55;margin:0 0 18px;overflow-wrap:anywhere}.actions{display:grid;gap:8px;margin-top:auto}.btn{min-height:42px;border:1px solid var(--secondary-color);border-radius:9px;display:grid;place-items:center;padding:8px;background:#fff;color:var(--secondary-color);text-decoration:none;font:inherit;font-weight:600;cursor:pointer}.btn.primary{background:var(--secondary-color);color:#fff}.btn.danger{border-color:#ef4e47;color:#df3d36}.action-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}.empty{padding:90px 20px;text-align:center;border:1px dashed #ccd3cd;border-radius:14px;color:var(--text-muted)}@media(max-width:1050px){.manage{padding:30px 24px}.request-body{grid-template-columns:1fr}.request-side{border-left:0;border-top:1px solid #ddd}.actions{grid-template-columns:repeat(2,1fr)}}@media(max-width:800px){.account{display:block}.manage{padding:28px 18px 60px}.tools{align-items:flex-start;flex-direction:column}.filters{width:100%}.filters select{flex:1;width:auto}.tabs{overflow-x:auto}.tabs a{min-width:180px}.exchange-pair{padding:22px 16px}}@media(max-width:520px){.title{font-size:28px}.filters{flex-direction:column}.filters select{width:100%;flex:auto}.request-head{align-items:flex-start}.avatar{width:48px;height:48px}.exchange-pair{grid-template-columns:1fr 38px 1fr;gap:5px}.cover{width:100%;max-width:105px;height:145px}.swap{font-size:27px}.request-side{padding:20px 15px}.actions{grid-template-columns:1fr}.action-row{grid-template-columns:1fr}.result{font-size:13px}}
</style></head><body><?php require __DIR__.'/../components/header.php';?><div class="account"><?php require __DIR__.'/../components/profile_sidebar.php';?><main class="manage"><h1 class="title">จัดการคำขอแลกเปลี่ยน</h1><nav class="tabs"><a class="<?=$tab==='received'?'active':''?>" href="?tab=received">คำขอที่ได้รับ</a><a class="<?=$tab==='sent'?'active':''?>" href="?tab=sent">คำขอที่ส่งไป</a></nav><?php if($message):?><div class="notice ok"><?=htmlspecialchars($message)?></div><?php endif;?><?php if($error):?><div class="notice bad"><?=htmlspecialchars($error)?></div><?php endif;?><div class="tools"><form class="filters"><input type="hidden" name="tab" value="<?=$tab?>"><select name="category" onchange="this.form.submit()"><option value="">ทุกหมวดหมู่</option><?php foreach($categories as $value):?><option value="<?=htmlspecialchars($value)?>" <?=$category===$value?'selected':''?>><?=htmlspecialchars($value)?></option><?php endforeach;?></select><select name="status" onchange="this.form.submit()"><option value="">ทุกสถานะ</option><?php foreach(['pending'=>'รอตอบรับ','accepted'=>'กำลังเจรจา','completed'=>'แลกเปลี่ยนสำเร็จ','rejected'=>'ปฏิเสธแล้ว','cancelled'=>'ยกเลิกแล้ว'] as $value=>$label):?><option value="<?=$value?>" <?=$status===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></form><span class="result">แสดง <?=number_format(count($requests))?> รายการ จาก <?=number_format($allCount)?> รายการ</span></div><section class="request-list"><?php if(!$requests):?><div class="empty">ยังไม่มีคำขอแลกเปลี่ยนในหมวดนี้</div><?php else:foreach($requests as $request):[$statusLabel,$statusClass]=xrStatus($request['status']);$otherName=$tab==='received'?$request['requester_name']:$request['owner_name'];$otherImage=$tab==='received'?$request['requester_image']:$request['owner_image'];$otherId=$tab==='received'?(int)$request['requester_id']:(int)$request['owner_id'];?><article class="request-card"><header class="request-head"><div class="person"><span class="avatar"><?php if($img=xrImage($otherImage)):?><img src="<?=htmlspecialchars($img)?>" alt=""><?php else:?><?=htmlspecialchars(mb_substr($otherName,0,1))?><?php endif;?></span><span><strong><?=htmlspecialchars($otherName)?></strong><span><?=$tab==='received'?'ผู้ยื่นคำขอ':'ผู้รับคำขอ'?></span></span></div><span class="status <?=$statusClass?>"><?=$statusLabel?></span></header><div class="request-body"><div class="exchange-pair"><div class="book"><div class="cover"><?php if($img=xrImage($tab==='received'?$request['target_image']:$request['offer_image'])):?><img src="<?=htmlspecialchars($img)?>" alt=""><?php endif;?></div><strong><?=htmlspecialchars(($tab==='received'?$request['target_title']:$request['offer_title'])?:'ยังไม่ระบุหนังสือ')?></strong><small><?=$tab==='received'?'หนังสือของคุณ':'หนังสือที่เสนอ'?></small></div><div class="swap">↔</div><div class="book"><div class="cover"><?php if($img=xrImage($tab==='received'?$request['offer_image']:$request['target_image'])):?><img src="<?=htmlspecialchars($img)?>" alt=""><?php endif;?></div><strong><?=htmlspecialchars(($tab==='received'?$request['offer_title']:$request['target_title'])?:'ขอพูดคุยก่อน')?></strong><small><?=$tab==='received'?'หนังสือที่เสนอ':'หนังสือของผู้รับ'?></small></div></div><aside class="request-side"><p class="request-message">“<?=htmlspecialchars($request['message']?:'สนใจแลกเปลี่ยนหนังสือรายการนี้') ?>”</p><div class="actions"><a class="btn" href="/pages/book_detail.php?source=exchange&amp;id=<?=$request['exchange_id']?>">ดูรายละเอียด</a><a class="btn primary" href="/pages/chat.php?seller_type=user&amp;seller_id=<?=$otherId?>&amp;exchange_id=<?=$request['exchange_id']?>">ส่งข้อความ</a><?php if($tab==='received'&&$request['status']==='pending'):?><form class="action-row" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>"><input type="hidden" name="request_id" value="<?=$request['request_id']?>"><button class="btn primary" name="request_action" value="accept">ตอบรับ</button><button class="btn danger" name="request_action" value="reject">ปฏิเสธ</button></form><?php elseif($tab==='received'&&$request['status']==='accepted'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>"><input type="hidden" name="request_id" value="<?=$request['request_id']?>"><button class="btn primary" name="request_action" value="complete">ยืนยันแลกเปลี่ยนสำเร็จ</button></form><?php elseif($tab==='sent'&&in_array($request['status'],['pending','accepted'],true)):?><form method="post" onsubmit="return confirm('ต้องการยกเลิกคำขอนี้ใช่หรือไม่?')"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['exchange_manage_csrf'])?>"><input type="hidden" name="request_id" value="<?=$request['request_id']?>"><button class="btn danger" name="request_action" value="cancel">ยกเลิกคำขอ</button></form><?php endif;?></div></aside></div></article><?php endforeach;endif;?></section></main></div><?php require __DIR__.'/../components/footer.php';?></body></html>
