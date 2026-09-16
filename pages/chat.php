<?php
session_start();
if(empty($_SESSION['user_id'])){header('Location:/pages/login.php');exit;}
require_once __DIR__.'/../config/config.php';$userId=(int)$_SESSION['user_id'];
if(empty($_SESSION['chat_csrf']))$_SESSION['chat_csrf']=bin2hex(random_bytes(24));
function chatImage(?string $v):?string{
    if(!$v)return null;
    if(preg_match('#^https?://#i',$v))return $v;
    $v=ltrim(str_replace('\\','/',$v),'/');
    if(str_starts_with($v,'pages/'))return '/'.$v;
    if(str_starts_with($v,'uploads/'))return '/pages/'.$v;
    if(str_starts_with($v,'assets/'))return '/'.$v;
    return '/assets/image/books/'.$v;
}
function sellerInfo(PDO $pdo,string $type,int $id):?array{if($type==='publisher'){$q=$pdo->prepare("SELECT publisher_id id,company_name name,profile_image FROM publishers WHERE publisher_id=?");}elseif($type==='shop'){$q=$pdo->prepare("SELECT shop_id id,shop_name name,profile_image FROM shops WHERE shop_id=?");}elseif($type==='user'){$q=$pdo->prepare("SELECT user_id id,COALESCE(NULLIF(display_name,''),email) name,profile_image FROM users WHERE user_id=?");}else return null;$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC)?:null;}
$type=in_array($_GET['seller_type']??'', ['publisher','shop','user'],true)?$_GET['seller_type']:'';$sellerId=(int)($_GET['seller_id']??0);$bookId=(int)($_GET['book_id']??0);$exchangeId=(int)($_GET['exchange_id']??0);
if($type&&$sellerId){
 if($type==='user'&&$sellerId===$userId){header('Location:/pages/chat.php');exit;}
 $seller=sellerInfo($pdo,$type,$sellerId);if(!$seller){http_response_code(404);exit('ไม่พบผู้รับข้อความ');}
 $book=null;if($bookId){$q=$pdo->prepare("SELECT book_id,title FROM books WHERE book_id=? AND seller_type=? AND seller_id=?");$q->execute([$bookId,$type,$sellerId]);$book=$q->fetch(PDO::FETCH_ASSOC);if(!$book)$bookId=0;}
 $exchange=null;if($exchangeId&&$type==='user'){$q=$pdo->prepare("SELECT e.exchange_id,e.title FROM exchanges e LEFT JOIN exchange_requests er ON er.exchange_id=e.exchange_id AND er.requester_id IN (?,?) WHERE e.exchange_id=? AND (e.owner_id=? OR er.requester_id=?)");$q->execute([$userId,$sellerId,$exchangeId,$sellerId,$sellerId]);$exchange=$q->fetch(PDO::FETCH_ASSOC);if(!$exchange)$exchangeId=0;}else{$exchangeId=0;}
 if($type==='user'){$find=$pdo->prepare("SELECT * FROM chat_conversations WHERE seller_type='user' AND ((customer_user_id=? AND seller_id=?) OR (customer_user_id=? AND seller_id=?)) LIMIT 1");$find->execute([$userId,$sellerId,$sellerId,$userId]);}
 else{$find=$pdo->prepare('SELECT * FROM chat_conversations WHERE customer_user_id=? AND seller_type=? AND seller_id=?');$find->execute([$userId,$type,$sellerId]);}
 $existing=$find->fetch(PDO::FETCH_ASSOC);
 if(!$existing){$q=$pdo->prepare('INSERT INTO chat_conversations(customer_user_id,seller_type,seller_id,referenced_book_id,referenced_exchange_id,last_message_at) VALUES(?,?,?,?,?,NOW())');$q->execute([$userId,$type,$sellerId,$bookId?:null,$exchangeId?:null]);$conversationId=(int)$pdo->lastInsertId();}
 else{$conversationId=(int)$existing['conversation_id'];if($bookId&&(int)$existing['referenced_book_id']!==$bookId)$pdo->prepare('UPDATE chat_conversations SET referenced_book_id=?,referenced_exchange_id=NULL WHERE conversation_id=?')->execute([$bookId,$conversationId]);if($exchangeId&&(int)($existing['referenced_exchange_id']??0)!==$exchangeId)$pdo->prepare('UPDATE chat_conversations SET referenced_exchange_id=?,referenced_book_id=NULL WHERE conversation_id=?')->execute([$exchangeId,$conversationId]);}
 if($bookId&&(!$existing||(int)$existing['referenced_book_id']!==$bookId)){$text='สนใจสอบถามเกี่ยวกับหนังสือ “'.$book['title'].'”';$pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text,referenced_book_id) VALUES(?,?,?,?)')->execute([$conversationId,$userId,$text,$bookId]);if($type==='user'){$recipients=[$sellerId];}else{$staffTable=$type==='publisher'?'publisher_staff':'shop_staff';$idCol=$type==='publisher'?'publisher_id':'shop_id';$staff=$pdo->prepare("SELECT user_id FROM $staffTable WHERE $idCol=?");$staff->execute([$sellerId]);$recipients=$staff->fetchAll(PDO::FETCH_COLUMN);}foreach($recipients as $sid) wanawanNotifyUser($pdo, (int)$sid, 'มีข้อความสอบถามสินค้าใหม่', $text, 'chat', ['chat_conversation_id' => $conversationId]);}
 if($exchangeId&&(!$existing||(int)($existing['referenced_exchange_id']??0)!==$exchangeId)){$text='สนใจแลกเปลี่ยนหนังสือ “'.$exchange['title'].'”';$pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text,referenced_exchange_id) VALUES(?,?,?,?)')->execute([$conversationId,$userId,$text,$exchangeId]);wanawanNotifyUser($pdo, $sellerId, 'มีข้อความเกี่ยวกับการแลกเปลี่ยนใหม่', $text, 'chat', ['chat_conversation_id' => $conversationId]);}
 if($bookId||$exchangeId){$pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);}
 header('Location:/pages/chat.php?conversation='.$conversationId);exit;
}
$conversationId=(int)($_GET['conversation']??$_POST['conversation_id']??0);
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['send_message'])){
 if(!hash_equals($_SESSION['chat_csrf'],$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');$text=trim($_POST['message_text']??'');
 $q=$pdo->prepare("SELECT * FROM chat_conversations WHERE conversation_id=? AND (customer_user_id=? OR (seller_type='user' AND seller_id=?))");$q->execute([$conversationId,$userId,$userId]);$conv=$q->fetch(PDO::FETCH_ASSOC);
 if($conv&&$text!==''){$pdo->beginTransaction();try{$pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId,$userId,$text]);$pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);if($conv['seller_type']==='user'){$recipients=[(int)$conv['customer_user_id']===$userId?(int)$conv['seller_id']:(int)$conv['customer_user_id']];}else{$table=$conv['seller_type']==='publisher'?'publisher_staff':'shop_staff';$col=$conv['seller_type']==='publisher'?'publisher_id':'shop_id';$s=$pdo->prepare("SELECT user_id FROM $table WHERE $col=?");$s->execute([$conv['seller_id']]);$recipients=$s->fetchAll(PDO::FETCH_COLUMN);}foreach($recipients as $sid) wanawanNotifyUser($pdo, (int)$sid, 'ข้อความใหม่', mb_strimwidth($text,0,120,'…'), 'chat', ['chat_conversation_id' => $conversationId]);$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}}
 header('Location:/pages/chat.php?conversation='.$conversationId);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['propose_full_trade'])){
 if(!hash_equals($_SESSION['chat_csrf'],$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
 $opt = $_POST['trade_option'];
 $deadline = $_POST['deadline_days'] ?? '';
 $meetupDate = trim($_POST['meetup_date'] ?? '');
 $meetupTime = trim($_POST['meetup_time'] ?? '');
 $meetupLocation = trim($_POST['meetup_location'] ?? '');
 if($opt) {
  if ($opt === 'meetup' && $meetupDate && $meetupTime && $meetupLocation) {
   $pdo->prepare('UPDATE exchange_requests SET meetup_date=?, meetup_time=?, meetup_location=? WHERE request_id=? AND status="accepted"')->execute([$meetupDate, $meetupTime, $meetupLocation, (int)$_POST['request_id']]);
   $msg = '[PROPOSE_FULL_TRADE:meetup:'.$meetupDate.':'.$meetupTime.':'.$meetupLocation.']';
  } else {
   $msg = '[PROPOSE_FULL_TRADE:'.$opt.($deadline ? ':'.$deadline : '').']';
  }
  $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId,$userId,$msg]);
  $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
 }
 header('Location:/pages/chat.php?conversation='.$conversationId);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['accept_full_trade'])){
 if(!hash_equals($_SESSION['chat_csrf'],$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
 $opt = $_POST['trade_option'];
 $deadline = $_POST['deadline_days'] ?? '';
 $meetupDate = $_POST['meetup_date'] ?? '';
 $meetupTime = $_POST['meetup_time'] ?? '';
 $meetupLoc = $_POST['meetup_location'] ?? '';
 
 if($opt === 'meetup') {
     $pdo->prepare('UPDATE exchange_requests SET trade_option=? WHERE request_id=? AND status="accepted"')->execute([$opt, (int)$_POST['request_id']]);
     $msg = '[ACCEPTED_FULL_TRADE:meetup:'.$meetupDate.':'.$meetupTime.':'.$meetupLoc.']';
 } else if($deadline) {
     $pdo->prepare('UPDATE exchange_requests SET trade_option=?, shipping_deadline_days=? WHERE request_id=? AND status="accepted"')->execute([$opt, (int)$deadline, (int)$_POST['request_id']]);
     $msg = '[ACCEPTED_FULL_TRADE:'.$opt.($deadline ? ':'.$deadline : '').']';
 } else {
     $pdo->prepare('UPDATE exchange_requests SET trade_option=? WHERE request_id=? AND status="accepted"')->execute([$opt, (int)$_POST['request_id']]);
     $msg = '[ACCEPTED_FULL_TRADE:'.$opt.']';
 }
 
 $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId,$userId,$msg]);
 $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
 header('Location:/pages/chat.php?conversation='.$conversationId);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['reject_full_trade'])){
 if(!hash_equals($_SESSION['chat_csrf'],$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
 $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId,$userId,'[REJECTED_FULL_TRADE]']);
 $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
 header('Location:/pages/chat.php?conversation='.$conversationId);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['accept_condition_chat'])){
    if(!hash_equals($_SESSION['chat_csrf'],$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
    $formId = (int)$_POST['accept_condition_form_id'];
    $reqId = (int)$_POST['request_id'];
    
    $pdo->prepare("UPDATE trade_condition_forms SET is_accepted_by_partner=1, accepted_at=NOW() WHERE form_id=?")->execute([$formId]);
    
    $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId,$userId,'ยอมรับสภาพหนังสือก่อนส่งแล้ว']);
    $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
    header('Location:/pages/chat.php?conversation='.$conversationId);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['delete_conversation'])){
 if(!hash_equals($_SESSION['chat_csrf'],$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
 $delId=(int)$_POST['delete_conversation_id'];
 $pdo->prepare("DELETE FROM chat_conversations WHERE conversation_id=? AND (customer_user_id=? OR (seller_type='user' AND seller_id=?))")->execute([$delId,$userId,$userId]);
 header('Location:/pages/chat.php');exit;
}
$list=$pdo->prepare("SELECT c.*,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN COALESCE(NULLIF(cu.display_name,''),cu.email) ELSE COALESCE(p.company_name,s.shop_name,NULLIF(su.display_name,''),su.email) END seller_name,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN cu.profile_image ELSE COALESCE(p.profile_image,s.profile_image,su.profile_image) END profile_image,(SELECT message_text FROM chat_messages WHERE conversation_id=c.conversation_id ORDER BY message_id DESC LIMIT 1) last_text FROM chat_conversations c LEFT JOIN publishers p ON c.seller_type='publisher' AND p.publisher_id=c.seller_id LEFT JOIN shops s ON c.seller_type='shop' AND s.shop_id=c.seller_id LEFT JOIN users su ON c.seller_type='user' AND su.user_id=c.seller_id LEFT JOIN users cu ON cu.user_id=c.customer_user_id WHERE c.customer_user_id=? OR (c.seller_type='user' AND c.seller_id=?) ORDER BY c.last_message_at DESC,c.updated_at DESC");$list->execute([$userId,$userId,$userId,$userId]);$conversations=$list->fetchAll(PDO::FETCH_ASSOC);
$active=null;$messages=[];$book=null;$exchange=null;$exchangeRequest=null;if($conversationId){$q=$pdo->prepare("SELECT c.*,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN COALESCE(NULLIF(cu.display_name,''),cu.email) ELSE COALESCE(p.company_name,s.shop_name,NULLIF(su.display_name,''),su.email) END seller_name,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN cu.profile_image ELSE COALESCE(p.profile_image,s.profile_image,su.profile_image) END profile_image FROM chat_conversations c LEFT JOIN publishers p ON c.seller_type='publisher' AND p.publisher_id=c.seller_id LEFT JOIN shops s ON c.seller_type='shop' AND s.shop_id=c.seller_id LEFT JOIN users su ON c.seller_type='user' AND su.user_id=c.seller_id LEFT JOIN users cu ON cu.user_id=c.customer_user_id WHERE c.conversation_id=? AND (c.customer_user_id=? OR (c.seller_type='user' AND c.seller_id=?))");$q->execute([$userId,$userId,$conversationId,$userId,$userId]);$active=$q->fetch(PDO::FETCH_ASSOC);if($active){$q=$pdo->prepare('SELECT m.*,u.display_name FROM chat_messages m JOIN users u ON u.user_id=m.sender_user_id WHERE m.conversation_id=? ORDER BY m.message_id');$q->execute([$conversationId]);$messages=$q->fetchAll(PDO::FETCH_ASSOC);$pdo->prepare('UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_user_id<>?')->execute([$conversationId,$userId]);if($active['referenced_book_id']){$q=$pdo->prepare('SELECT * FROM books WHERE book_id=?');$q->execute([$active['referenced_book_id']]);$book=$q->fetch(PDO::FETCH_ASSOC);}elseif($active['referenced_exchange_id']){$q=$pdo->prepare('SELECT * FROM exchanges WHERE exchange_id=?');$q->execute([$active['referenced_exchange_id']]);$exchange=$q->fetch(PDO::FETCH_ASSOC);if($exchange&&$active['seller_type']==='user'){$q=$pdo->prepare("SELECT er.*,offer.title offer_title,offer.image_cover offer_image,offer.condition_percent offer_condition FROM exchange_requests er LEFT JOIN exchanges offer ON offer.exchange_id=er.offered_exchange_id JOIN exchanges tgt ON tgt.exchange_id=er.exchange_id WHERE (er.exchange_id=? OR er.offered_exchange_id=?) AND (er.requester_id IN (?,?) OR tgt.owner_id IN (?,?)) AND er.status NOT IN ('cancelled','rejected') ORDER BY FIELD(er.status,'accepted','pending','completed') ASC,er.request_id DESC LIMIT 1");$_r=(int)$active['referenced_exchange_id'];$_u=(int)$active['customer_user_id'];$_s=(int)$active['seller_id'];$q->execute([$_r,$_r,$_u,$_s,$_u,$_s]);$exchangeRequest=$q->fetch(PDO::FETCH_ASSOC)?:null;}}}}
$ownMessageIds=[];$lastReadOwnMessageId=0;$lastMessageId=0;foreach($messages as $message){$lastMessageId=max($lastMessageId,(int)$message['message_id']);if((int)$message['sender_user_id']===$userId){$ownMessageIds[]=(int)$message['message_id'];if((int)$message['is_read']===1)$lastReadOwnMessageId=(int)$message['message_id'];}}
$profile_active='messages';
$exchangeRequestPanel=null;
if($exchange&&$exchangeRequest){
 $exchangeRequestPanel=[
  'target'=>['title'=>$exchange['title'],'image'=>chatImage($exchange['image_cover']??null),'condition'=>(int)($exchange['condition_percent']??0)],
  'offer'=>['title'=>$exchangeRequest['offer_title']?:'ขอพูดคุยก่อน','image'=>chatImage($exchangeRequest['offer_image']??null),'condition'=>(int)($exchangeRequest['offer_condition']??0)],
  'message'=>$exchangeRequest['message']?:'สนใจแลกเปลี่ยนสินค้ารายการนี้',
  'status'=>['pending'=>'รอตอบรับ','accepted'=>'กำลังเจรจา','completed'=>'แลกเปลี่ยนสำเร็จ','rejected'=>'ปฏิเสธแล้ว','cancelled'=>'ยกเลิกแล้ว'][$exchangeRequest['status']]??$exchangeRequest['status'],
  'rawStatus'=>$exchangeRequest['status'],
  'requestId'=>(int)$exchangeRequest['request_id'],
  'tradeOption'=>$exchangeRequest['trade_option']??null,
  'shippingDeadline'=>(int)($exchangeRequest['shipping_deadline_days']??0),
  'isRequester'=>((int)$exchangeRequest['requester_id']===$userId),
  'requesterSlipStatus'=>$exchangeRequest['requester_slip_status']??null,
  'offerSlipStatus'=>$exchangeRequest['offer_slip_status']??null,
  'requesterTrackingToAdmin'=>$exchangeRequest['requester_tracking_to_admin']??null,
  'offerTrackingToAdmin'=>$exchangeRequest['offer_tracking_to_admin']??null,
  'conditionsAccepted'=>(function() use ($pdo,$exchangeRequest){ $s=$pdo->prepare('SELECT COUNT(*) FROM trade_condition_forms WHERE request_id=? AND is_accepted_by_partner=1');$s->execute([(int)$exchangeRequest['request_id']]);return (int)$s->fetchColumn()>=2;})(),
  'myCondition'=>(function() use ($pdo,$exchangeRequest,$userId){ $s=$pdo->prepare('SELECT form_id,is_accepted_by_partner,required_deposit_amount,deposit_status,deposit_approved_at,tracking_number,is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=?');$s->execute([(int)$exchangeRequest['request_id'],$userId]);return $s->fetch(PDO::FETCH_ASSOC)?:null;})(),
  'partnerCondition'=>(function() use ($pdo,$exchangeRequest,$userId){ $ex=$pdo->prepare('SELECT owner_id FROM exchanges WHERE exchange_id=?');$ex->execute([(int)$exchangeRequest['exchange_id']]);$row=$ex->fetch(PDO::FETCH_ASSOC);$pid=(int)($row&&(int)$row['owner_id']!==$userId?$row['owner_id']:$exchangeRequest['requester_id']);$s=$pdo->prepare('SELECT form_id,is_accepted_by_partner,required_deposit_amount,deposit_status,deposit_approved_at,tracking_number,is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=?');$s->execute([(int)$exchangeRequest['request_id'],$pid]);return $s->fetch(PDO::FETCH_ASSOC)?:null;})(),
  'requesterQcStatus'=>$exchangeRequest['admin_qc_requester_status']??'pending',
  'offerQcStatus'=>$exchangeRequest['admin_qc_offer_status']??'pending',
  'requesterQcAcceptanceStatus'=>$exchangeRequest['requester_qc_acceptance_status']??'pending',
  'offerQcAcceptanceStatus'=>$exchangeRequest['offer_qc_acceptance_status']??'pending',
  'adminTrackingToRequester'=>$exchangeRequest['admin_tracking_to_requester']??null,
  'adminTrackingToOffer'=>$exchangeRequest['admin_tracking_to_offer']??null,
  'manageUrl'=>'/pages/exchange_requests.php?tab='.((int)$exchangeRequest['requester_id']===$userId?'sent':'received')
 ];
}
$chatContextBar=null;
if($book){
 $chatContextBar=['title'=>$book['title'],'subtitle'=>'สินค้าที่กำลังสอบถาม · ฿'.number_format((float)$book['price'],2),'image'=>chatImage($book['image_cover']??null),'url'=>'/pages/book_detail.php?id='.(int)$book['book_id']];
}elseif($exchangeRequestPanel){
 $chatContextBar=['title'=>$exchangeRequestPanel['target']['title'].' ↔ '.$exchangeRequestPanel['offer']['title'],'subtitle'=>'คำขอแลกเปลี่ยน · '.$exchangeRequestPanel['status'],'image'=>$exchangeRequestPanel['target']['image'],'url'=>$exchangeRequestPanel['manageUrl']];
}elseif($exchange){
 $chatContextBar=['title'=>$exchange['title'],'subtitle'=>'รายการแลกเปลี่ยน · สภาพ '.(int)($exchange['condition_percent']??0).'%','image'=>chatImage($exchange['image_cover']??null),'url'=>'/pages/book_detail.php?source=exchange&id='.(int)$exchange['exchange_id']];
}
if($exchangeRequestPanel):?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const data=<?=json_encode($exchangeRequestPanel,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
 const product=document.querySelector('.product');
 const oldCard=product?.querySelector('.product-card');
 if(!product||!oldCard)return;
 const makeBook=(book,label)=>{
  const wrap=document.createElement('div');wrap.className='exchange-request-book';
  if(book.image){const img=document.createElement('img');img.src=book.image;img.alt=book.title;wrap.append(img)}
  else{const placeholder=document.createElement('div');placeholder.className='exchange-request-placeholder';wrap.append(placeholder)}
  const title=document.createElement('strong');title.textContent=book.title;wrap.append(title);
  const meta=document.createElement('small');meta.textContent=label+(book.condition?' · '+book.condition+'%':'');wrap.append(meta);
  return wrap;
 };
 const summary=document.createElement('section');summary.className='exchange-request-summary';
 const status=document.createElement('div');status.className='exchange-request-status';
 status.append(Object.assign(document.createElement('span'),{textContent:'คำขอแลกเปลี่ยน'}),Object.assign(document.createElement('span'),{textContent:data.status}));
 const pair=document.createElement('div');pair.className='exchange-request-pair';
 pair.append(makeBook(data.target,'หนังสือที่ขอ'),Object.assign(document.createElement('span'),{className:'exchange-request-swap',textContent:'↔'}),makeBook(data.offer,'หนังสือที่เสนอ'));
 const message=document.createElement('p');message.className='exchange-request-message';message.textContent='“'+data.message+'”';
 const manage=document.createElement('a');manage.className='exchange-request-manage';manage.href=data.manageUrl;manage.textContent='ดูคำขอแลกเปลี่ยน';
  summary.append(status,pair,message,manage);
 oldCard.replaceWith(summary);

 const insertBanner=function(el){setTimeout(function(){const ex=document.querySelector('.trade-action-banner');if(ex)ex.remove();el.classList.add('trade-action-banner');el.style.margin='15px';el.style.marginBottom='0';const ctxBar=document.querySelector('.chat-context-bar');const chatHead=document.querySelector('.chat-head');if(ctxBar)ctxBar.after(el);else if(chatHead)chatHead.after(el);},150);};
 const makeCondBtn=function(){const w=document.createElement('div');w.style.cssText='padding:12px 16px;background:#fffbeb;color:#92400e;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #fde68a;margin-bottom:12px;gap:10px;';w.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#d97706;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">กรอกฟอร์มยืนยันสภาพสินค้า</strong><span style="font-size:12px;opacity:0.8;">กรุณาแนบรูปภาพสินค้าของคุณ</span></div></div>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='กรอกฟอร์ม';btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#d97706; border-color:#d97706; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';btn.onclick=function(){const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');const cid=(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');if(modal&&iframe){iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1&conversation_id='+cid;modal.style.display='flex';}else{window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId+'&conversation_id='+cid;}};w.append(btn);return w;};

 if(data.rawStatus==='accepted' || data.rawStatus==='completed'){
  const needsDL=(data.tradeOption==='direct_escrow'||data.tradeOption==='admin_qc');
  const hasDL=data.shippingDeadline>0;
    if(!data.tradeOption){
   const propForm = document.createElement('form');
   propForm.method = 'post';
   propForm.style.cssText = 'padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,.05)';
   propForm.innerHTML = `<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['chat_csrf']??''); ?>">
   <input type="hidden" name="request_id" value="${data.requestId}">
   <label style="font-size:12px; font-weight:600; color:#1e40af; margin-bottom:6px; display:block;">💬 กำลังเจรจา: กรุณาเสนอรูปแบบการแลกเปลี่ยน</label>
   <select name="trade_option" id="tradeOptionSelect" style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #bfdbfe;border-radius:6px;margin-bottom:10px;font-size:13px;outline:none;" required>
     <option value="" disabled selected>-- เลือกรูปแบบการแลกเปลี่ยน --</option>
     <option value="direct_escrow">ส่งตรงให้กัน (P2P Escrow)</option>
     <option value="admin_qc">ส่งผ่านส่วนกลาง (Admin QC)</option>
     <option value="meetup">นัดรับ (Meet-up)</option>
   </select>
   <div id="deadlineGroup" style="display:none;background:#f8fafc;padding:10px;border-radius:6px;margin-bottom:10px;border:1px solid #e2e8f0;">
     <label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">ระยะเวลาจัดส่ง (วัน)</label>
     <select name="deadline_days" style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;outline:none;">
       <option value="3">ภายใน 3 วัน</option>
       <option value="5">ภายใน 5 วัน</option>
       <option value="7">ภายใน 7 วัน</option>
     </select>
   </div>
   <div id="meetupGroup" style="display:none;background:#f8fafc;padding:10px;border-radius:6px;margin-bottom:10px;border:1px solid #e2e8f0;">
     <div style="display:flex;gap:8px;margin-bottom:8px;">
       <div style="flex:1;"><label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">วันที่</label><input type="date" name="meetup_date" min="<?php echo date('Y-m-d'); ?>" style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;outline:none;"></div>
       <div style="flex:1;"><label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">เวลา</label><input type="time" name="meetup_time" style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;outline:none;"></div>
     </div>
     <label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;">สถานที่นัดรับ</label>
     <input type="text" name="meetup_location" placeholder="ระบุสถานที่นัดพบ..." style="box-sizing:border-box;width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;outline:none;">
   </div>
   <button name="propose_full_trade" class="btn primary" style="width:100%;padding:10px;border-radius:6px;background:#3b82f6;color:#fff;font-weight:600;border:0;cursor:pointer;">เสนอเงื่อนไขนี้</button>`;
   const w=document.createElement('div');w.append(propForm);insertBanner(w);
   
   propForm.querySelector('#tradeOptionSelect').addEventListener('change', function() {
     const val = this.value;
     propForm.querySelector('#deadlineGroup').style.display = (val === 'direct_escrow' || val === 'admin_qc') ? 'block' : 'none';
     propForm.querySelector('#meetupGroup').style.display = (val === 'meetup') ? 'block' : 'none';
   });
  } else if(needsDL&&!hasDL){
   const b=document.createElement('div');b.style.cssText='padding:14px;background:#fff3cd;color:#856404;border-radius:8px;font-size:14px;text-align:center;border:1px solid #ffeeba';b.innerHTML='\u23f3 <strong>\u0e23\u0e2d\u0e02\u0e49\u0e2d\u0e2a\u0e23\u0e38\u0e1b\u0e27\u0e31\u0e19\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07</strong><br>\u0e01\u0e23\u0e38\u0e13\u0e32\u0e15\u0e01\u0e25\u0e07\u0e27\u0e31\u0e19\u0e08\u0e31\u0e14\u0e2a\u0e48\u0e07\u0e43\u0e19\u0e41\u0e0a\u0e17\u0e01\u0e48\u0e2d\u0e19\u0e14\u0e33\u0e40\u0e19\u0e34\u0e19\u0e01\u0e32\u0e23\u0e15\u0e48\u0e2d';const w=document.createElement('div');w.append(b);insertBanner(w);
  } else if(data.tradeOption==='admin_qc'){
    const cb=document.createElement('div');
    const mySlip=data.isRequester?data.requesterSlipStatus:data.offerSlipStatus;
    const myTr=data.isRequester?data.requesterTrackingToAdmin:data.offerTrackingToAdmin;
    const ptTr=data.isRequester?data.offerTrackingToAdmin:data.requesterTrackingToAdmin;
    const ptQcStatus=data.isRequester?data.offerQcStatus:data.requesterQcStatus;
    const myQcAcc=data.isRequester?data.requesterQcAcceptanceStatus:data.offerQcAcceptanceStatus;
    const ptQcAcc=data.isRequester?data.offerQcAcceptanceStatus:data.requesterQcAcceptanceStatus;
    const myAdminTr=data.isRequester?data.adminTrackingToRequester:data.adminTrackingToOffer;
    const myRv=(data.partnerCondition)?parseInt(data.partnerCondition.is_received):0;

    if(!data.conditionsAccepted){
        if (data.myCondition && !data.partnerCondition) {
            const b = document.createElement('div');
            b.style.cssText = 'padding:12px 16px;background:#fffbeb;color:#92400e;border-radius:8px;display:flex;align-items:center;border:1px solid #fde68a;margin-bottom:12px;gap:12px;';
            b.innerHTML = '<div style="color:#d97706;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รออีกฝ่ายเพิ่มฟอร์มภาพสินค้า</strong><span style="font-size:12px;opacity:0.8;">คุณกรอกฟอร์มแล้ว กรุณารอให้อีกฝ่ายกรอกข้อมูล</span></div>';
            cb.append(b);
        } else if (!data.myCondition && data.partnerCondition) {
            const b = document.createElement('div');
            b.style.cssText = 'padding:12px 16px;background:#fffbeb;color:#92400e;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #fde68a;margin-bottom:12px;gap:10px;';
            b.innerHTML = '<div style="display:flex;align-items:center;gap:12px;"><div style="color:#d97706;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">อีกฝ่ายเพิ่มรูปภาพสินค้าแล้ว</strong><span style="font-size:12px;opacity:0.8;">ดูรายละเอียดเพื่อตรวจสอบและกรอกข้อมูลของคุณ</span></div></div>';
            const btn=document.createElement('button');btn.className='btn primary';btn.textContent='ดูแบบฟอร์ม';btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#d97706; border-color:#d97706; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
            btn.onclick=function(){const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');const cid=(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');if(modal&&iframe){iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1&conversation_id='+cid;modal.style.display='flex';}else{window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId+'&conversation_id='+cid;}};b.append(btn);cb.append(b);
        } else if (data.myCondition && data.partnerCondition) {
            const b = document.createElement('div');
            b.style.cssText = 'padding:12px 16px;background:#fefce8;color:#854d0e;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #fef08a;margin-bottom:12px;gap:10px;';
            b.innerHTML = '<div style="display:flex;align-items:center;gap:12px;"><div style="color:#eab308;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">ตรวจสอบสภาพสินค้า</strong><span style="font-size:12px;opacity:0.8;">ทั้งสองฝ่ายกรอกข้อมูลแล้ว กรุณาเข้าไปกดยอมรับ</span></div></div>';
            const btn=document.createElement('button');btn.className='btn primary';btn.textContent='ตรวจสอบ';btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#eab308; border-color:#eab308; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
            btn.onclick=function(){const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');const cid=(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');if(modal&&iframe){iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1&conversation_id='+cid;modal.style.display='flex';}else{window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId+'&conversation_id='+cid;}};b.append(btn);cb.append(b);
        } else {
            const banner = makeCondBtn(); 
            if(banner) cb.append(banner);
        }
    }
    else if(!mySlip){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#fff1f2;color:#be123c;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #ffe4e6;margin-bottom:12px;gap:10px;';
        b.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#e11d48;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">ชำระค่าบริการส่วนกลาง (QC)</strong><span style="font-size:12px;opacity:0.8;">กรุณาโอนเงินค่าบริการและแนบสลิป</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.textContent='แนบสลิป';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#e11d48; border-color:#e11d48; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg> ชำระค่าบริการส่วนกลาง (QC)';
                iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
            }
        };b.append(btn);cb.append(b);
    }
    else if(mySlip==='pending'||mySlip==='pending_verification'){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#fffbeb;color:#92400e;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #fde68a;margin-bottom:12px;gap:10px;';
        b.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#d97706;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รอแอดมินตรวจสอบ</strong><span style="font-size:12px;opacity:0.8;">ทีมงานกำลังตรวจสอบสลิปการชำระเงินของคุณ</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary outline';
        btn.textContent='รอแอดมินตรวจสอบ';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; border:1px solid #d97706; color:#d97706; background:transparent; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg> สถานะการชำระเงิน (QC)';
                iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
            }
        };b.append(btn);cb.append(b);
    }
    else if(!myTr){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#eff6ff;color:#1e40af;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bfdbfe;margin-bottom:12px;gap:10px;';
        b.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#2563eb;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">แจ้งเลขพัสดุส่วนกลาง (QC)</strong><span style="font-size:12px;opacity:0.8;">กรุณาจัดส่งสินค้าไปยังบริษัท</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.textContent='เพิ่มที่อยู่และส่งสินค้าไปยังบริษัท';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#2563eb; border-color:#2563eb; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg> แจ้งเลขพัสดุส่วนกลาง (QC)';
                iframe.src='/pages/trade_qc_logistics_form.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_qc_logistics_form.php?request_id='+data.requestId;
            }
        };b.append(btn);cb.append(b);
    }
    else if(ptQcStatus === 'pending'){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#f3f4f6;color:#374151;border-radius:8px;display:flex;align-items:center;border:1px solid #e5e7eb;margin-bottom:12px;gap:12px;';
        b.innerHTML='<div style="color:#4b5563;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รอสินค้าถึงแอดมิน แล้วตรวจสอบสินค้าอีกครั้ง</strong><span style="font-size:12px;opacity:0.8;">ส่งพัสดุเรียบร้อยแล้ว กรุณารอทีมงานตรวจสอบ</span></div>';
        cb.append(b);
    }
    else if(myQcAcc === 'pending'){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#eff6ff;color:#1e40af;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bfdbfe;margin-bottom:12px;gap:10px;flex-wrap:wrap;';
        b.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#2563eb;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">แอดมินส่งฟอร์มตรวจสภาพสินค้าแล้ว</strong><span style="font-size:12px;opacity:0.8;">กรุณาตรวจสอบและยืนยันผลการประเมินจากส่วนกลาง</span></div></div>';
        
        const btnContainer = document.createElement('div');
        btnContainer.style.cssText = 'display:flex; gap:8px; align-items:center;';

        const btnView=document.createElement('button');
        btnView.className='btn primary';
        btnView.textContent='ดูรายละเอียด';
        btnView.style.cssText='white-space:nowrap; padding:6px 14px; background:#2563eb; border:1px solid #2563eb; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btnView.onclick=function(){
            const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path></svg> ฟอร์มรายงาน QC จากส่วนกลาง';
                iframe.src='/pages/trade_qc_report_view.php?request_id='+data.requestId+'&iframe=1';
                modal.style.display='flex';
            }
        };
        
        const btnReject=document.createElement('button');
        btnReject.className='btn outline danger';
        btnReject.textContent='ปฏิเสธ';
        btnReject.style.cssText='white-space:nowrap; padding:6px 14px; background:transparent; border:1px solid #ef4444; color:#ef4444; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btnReject.onclick=function(){
            Swal.fire({
                title: 'ปฏิเสธผล QC',
                text: 'กรุณาระบุเหตุผลที่ปฏิเสธผล QC (การปฏิเสธจะยกเลิกการแลกเปลี่ยนนี้ทันที)',
                input: 'text',
                inputPlaceholder: 'เหตุผลการปฏิเสธ',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ยืนยันปฏิเสธ',
                cancelButtonText: 'ยกเลิก',
                inputValidator: (value) => {
                    if (!value || value.trim() === '') {
                        return 'กรุณาระบุเหตุผล!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const f = new FormData(); f.append('action', 'reject'); f.append('request_id', data.requestId); f.append('reason', result.value);
                    fetch('/pages/handlers/trade_qc_acceptance.php', { method: 'POST', body: f }).then(res=>res.json()).then(res=>{
                        if(res.success) window.location.reload();
                        else Swal.fire('ข้อผิดพลาด', res.message||'Error', 'error');
                    });
                }
            });
        };

        const btnAccept=document.createElement('button');
        btnAccept.className='btn primary';
        btnAccept.textContent='คอนเฟิร์มรับสินค้า';
        btnAccept.style.cssText='white-space:nowrap; padding:6px 14px; background:#10b981; border:1px solid #10b981; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btnAccept.onclick=function(){
            Swal.fire({
                title: 'ยืนยันการรับสินค้า?',
                text: 'คุณยืนยันที่จะยอมรับผลการตรวจสอบและรับสินค้า ใช่หรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ใช่, ยืนยัน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    const f = new FormData(); f.append('action', 'accept'); f.append('request_id', data.requestId);
                    fetch('/pages/handlers/trade_qc_acceptance.php', {
                        method: 'POST', body: f
                    }).then(res=>res.json()).then(res=>{
                        if(res.success) window.location.reload();
                        else Swal.fire('ข้อผิดพลาด', res.message||'Error', 'error');
                    });
                }
            });
        };
        
        btnContainer.append(btnView, btnReject, btnAccept);
        b.append(btnContainer);
        cb.append(b);
    }
    else if(!myAdminTr){
        const b=document.createElement('div');
        b.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;border:1px solid #bbf7d0;margin-bottom:12px;gap:12px;';
        b.innerHTML='<div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รอส่วนกลางจัดส่งสินค้า</strong><span style="font-size:12px;opacity:0.8;">ยอมรับผลการประเมินแล้ว แอดมินกำลังจัดส่งสินค้าให้คุณ</span></div>';
        cb.append(b);
    }
    else if(!myRv){
        const sb=document.createElement('div');
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bbf7d0;margin-bottom:12px;gap:10px;flex-wrap:wrap;';
        sb.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">แอดมินจัดส่งสินค้าแล้ว!</strong><span style="font-size:12px;opacity:0.8;">ขนส่ง/เลขพัสดุ: <span style="font-weight:600;">'+myAdminTr+'</span></span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.textContent='ยืนยันได้รับสินค้าแล้ว';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#10b981; border:1px solid #10b981; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            Swal.fire({
                title: 'ยืนยันการรับสินค้า?',
                text: 'ยืนยันว่าได้รับสินค้าจากแอดมินเรียบร้อยแล้วใช่หรือไม่?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'ใช่, ได้รับแล้ว',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';
                    f.innerHTML='<input type="hidden" name="action" value="receive_item"><input type="hidden" name="request_id" value="'+data.requestId+'">';
                    document.body.append(f);f.submit();
                }
            });
        };
        sb.append(btn);
        cb.append(sb);
    }
    else {
        const sb=document.createElement('div');
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;border:1px solid #bbf7d0;margin-bottom:12px;gap:12px;';
        sb.innerHTML='<div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">คุณได้รับสินค้าแล้ว!</strong><span style="font-size:12px;opacity:0.8;">การแลกเปลี่ยนฝั่งคุณเสร็จสมบูรณ์</span></div>';
        cb.append(sb);
    }

    if(cb.children.length>0)insertBanner(cb);
} else if(data.tradeOption==='direct_escrow'){
   if(!data.conditionsAccepted){const cb=document.createElement('div');const banner = makeCondBtn(); if(banner) cb.append(banner);insertBanner(cb);}
   else{
    const myS=(data.myCondition&&data.myCondition.deposit_status)?data.myCondition.deposit_status:'pending';
    const ptS=(data.partnerCondition&&data.partnerCondition.deposit_status)?data.partnerCondition.deposit_status:'pending';
    const myR=(data.myCondition&&data.myCondition.required_deposit_amount!=null)?data.myCondition.required_deposit_amount:null;
    const ptR=(data.partnerCondition&&data.partnerCondition.required_deposit_amount!=null)?data.partnerCondition.required_deposit_amount:null;
    const cb=document.createElement('div');
    if(myR===null){const b=document.createElement('div');b.style.cssText='padding:15px;background:#fff5eb;color:#c05621;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fbd38d;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';b.innerHTML='\ud83d\udcb0 <strong>\u0e01\u0e33\u0e2b\u0e19\u0e14\u0e40\u0e07\u0e34\u0e19\u0e21\u0e31\u0e14\u0e08\u0e33</strong><br>\u0e01\u0e23\u0e38\u0e13\u0e32\u0e23\u0e30\u0e1a\u0e38\u0e21\u0e39\u0e25\u0e04\u0e48\u0e32\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e40\u0e1e\u0e37\u0e48\u0e2d\u0e40\u0e23\u0e35\u0e22\u0e01\u0e40\u0e01\u0e47\u0e1a\u0e40\u0e07\u0e34\u0e19\u0e21\u0e31\u0e14\u0e08\u0e33\u0e08\u0e32\u0e01\u0e2d\u0e35\u0e01\u0e1d\u0e48\u0e32\u0e22<br><br>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='\u0e40\u0e2a\u0e19\u0e2d\u0e21\u0e31\u0e14\u0e08\u0e33';btn.style.cssText='padding:8px 16px;background:#dd6b20;border-color:#dd6b20';btn.onclick=function(){let a=prompt('\u0e01\u0e23\u0e2d\u0e01\u0e21\u0e39\u0e25\u0e04\u0e48\u0e32\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32 (\u0e1a\u0e32\u0e17):');if(a!==null&&a.trim()!==''){a=parseFloat(a);if(!isNaN(a)&&a>=0){const f=document.createElement('form');f.method='post';f.action='/pages/handlers/trade_submit_logistics.php';f.innerHTML='<input type="hidden" name="action" value="set_deposit"><input type="hidden" name="request_id" value="'+data.requestId+'"><input type="hidden" name="amount" value="'+a+'">';document.body.append(f);f.submit();}else alert('\u0e01\u0e23\u0e38\u0e13\u0e32\u0e01\u0e23\u0e2d\u0e01\u0e15\u0e31\u0e27\u0e40\u0e25\u0e02\u0e17\u0e35\u0e48\u0e16\u0e39\u0e01\u0e15\u0e49\u0e2d\u0e07');}};b.append(btn);cb.append(b);}
    else if(ptR===null){const b=document.createElement('div');b.style.cssText='padding:14px;background:#fff5eb;color:#c05621;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fbd38d;margin-bottom:10px';b.innerHTML='\u23f3 <strong>\u0e23\u0e2d\u0e2d\u0e35\u0e01\u0e1d\u0e48\u0e32\u0e22\u0e1b\u0e23\u0e30\u0e40\u0e21\u0e34\u0e19\u0e21\u0e39\u0e25\u0e04\u0e48\u0e32\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32</strong><br>\u0e40\u0e1e\u0e37\u0e48\u0e2d\u0e01\u0e33\u0e2b\u0e19\u0e14\u0e40\u0e07\u0e34\u0e19\u0e21\u0e31\u0e14\u0e08\u0e33\u0e17\u0e35\u0e48\u0e04\u0e38\u0e13\u0e15\u0e49\u0e2d\u0e07\u0e0a\u0e33\u0e23\u0e30';cb.append(b);}
    else{
     const myD=(parseFloat(ptR)===0)||(myS==='approved');
     const ptD=(parseFloat(myR)===0)||(ptS==='approved');
     if(!myD){
    const b=document.createElement('div');
    b.id = 'my-deposit-banner';
    b.style.cssText='padding:12px 16px;background:#fff1f2;color:#be123c;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #ffe4e6;margin-bottom:12px;gap:10px;';
    if(myS==='pending_verification' || myS==='reviewing'){
        b.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#e11d48;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รอแอดมินตรวจสอบความถูกต้องของสลิป</strong><span style="font-size:12px;opacity:0.8;">จำนวน ฿'+parseFloat(ptR).toFixed(2)+'</span></div></div>';
    }else{
        b.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#e11d48;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">คุณต้องชำระเงินมัดจำ</strong><span style="font-size:12px;opacity:0.8;">จำนวน ฿'+parseFloat(ptR).toFixed(2)+'</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='ชำระเงิน';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#e11d48; border-color:#e11d48; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
    const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
    if(modal&&iframe){
        modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"></path></svg> แนบสลิปชำระเงินมัดจำ';
        iframe.src='/pages/trade_upload_slip.php?request_id='+data.requestId+'&iframe=1&conversation_id='+(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');
        modal.style.display='flex';
    } else {
        window.location.href='/pages/trade_upload_slip.php?request_id='+data.requestId;
    }
};b.append(btn);}cb.append(b);}
     if(!ptD){
    const b=document.createElement('div');
    b.style.cssText='padding:12px 16px;background:#eff6ff;color:#1e40af;border-radius:8px;display:flex;align-items:center;border:1px solid #dbeafe;margin-bottom:12px;gap:12px;';
    b.innerHTML='<div style="color:#3b82f6;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">รออีกฝ่ายชำระเงินมัดจำ</strong><span style="font-size:12px;opacity:0.8;">จำนวน ฿'+parseFloat(myR).toFixed(2)+'</span></div>';
    cb.append(b);
}
     if(myD&&ptD){
    const dObj=new Date((data.myCondition&&data.myCondition.deposit_approved_at)||(data.partnerCondition&&data.partnerCondition.deposit_approved_at)||new Date());
    dObj.setDate(dObj.getDate()+parseInt(data.shippingDeadline));
    const dls=dObj.toLocaleDateString('th-TH',{year:'numeric',month:'long',day:'numeric'});
    const myTr=(data.myCondition)?data.myCondition.tracking_number:null;
    const ptTr=(data.partnerCondition)?data.partnerCondition.tracking_number:null;
    const ptRv=(data.partnerCondition)?data.partnerCondition.is_received:0;
    const myRv=(data.myCondition)?data.myCondition.is_received:0;
    const sb=document.createElement('div');
    
    if(!myTr){
        sb.style.cssText='padding:12px 16px;background:#fdf5f7;color:#8b5266;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #f8e1e7;margin-bottom:12px;gap:10px;';
        sb.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#b66f68;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">การวางมัดจำเสร็จสิ้น!</strong><span style="font-size:12px;opacity:0.8;">จัดส่งสินค้าภายใน '+dls+'</span></div></div>';
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='แจ้งเลขพัสดุ';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:linear-gradient(135deg, #d18aa7, #b66f68); border:none; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:"Kanit", sans-serif;';
        btn.onclick=function(){
            const modal=document.getElementById('conditionModal');
            const iframe=document.getElementById('conditionIframe');
            if(modal&&iframe){
                modal.querySelector('h3').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg> แจ้งเลขพัสดุ';
                iframe.src='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId+'&iframe=1&conversation_id='+(window.wanawanChatReadStatus&&window.wanawanChatReadStatus.conversationId||'');
                modal.style.display='flex';
            } else {
                window.location.href='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId;
            }
        };
        sb.append(btn);
    } else if(!ptTr){
        sb.style.cssText='padding:12px 16px;background:#fefce8;color:#854d0e;border-radius:8px;display:flex;align-items:center;border:1px solid #fef08a;margin-bottom:12px;gap:12px;';
        sb.innerHTML='<div style="color:#eab308;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">จัดส่งสินค้าแล้ว</strong><span style="font-size:12px;opacity:0.8;">รออีกฝ่ายจัดส่ง</span></div>';
    } else if(!ptRv){
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;justify-content:space-between;border:1px solid #bbf7d0;margin-bottom:12px;gap:10px;';
        sb.innerHTML='<div style="display:flex;align-items:center;gap:12px;"><div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">อีกฝ่ายจัดส่งแล้ว!</strong><span style="font-size:12px;opacity:0.8;">เลขพัสดุ: '+ptTr+'</span></div></div>';
        const btnContainer = document.createElement('div');
        btnContainer.style.cssText = 'display:flex; gap:8px;';
        const btnClaim=document.createElement('button');
        btnClaim.className='btn outline';
        btnClaim.innerHTML='รายงานปัญหา';
        btnClaim.style.cssText='white-space:nowrap; padding:6px 14px; background:#fff; border:1px solid #ef4444; color:#ef4444; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btnClaim.onclick=function(){
            alert('ระบบเคลมสินค้ากำลังอยู่ในช่วงพัฒนาค่ะ หากมีปัญหากรุณาติดต่อแอดมิน');
        };
        const btn=document.createElement('button');
        btn.className='btn primary';
        btn.innerHTML='ยืนยันได้รับสินค้า';
        btn.style.cssText='white-space:nowrap; padding:6px 14px; background:#22c55e; border-color:#22c55e; color:#fff; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit;';
        btn.onclick=function(){
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(2px);';
            const modal = document.createElement('div');
            modal.style.cssText = 'background:#fff;padding:24px;border-radius:12px;width:90%;max-width:340px;text-align:center;box-shadow:0 10px 25px rgba(0,0,0,0.2);font-family:inherit;';
            modal.innerHTML = '<div style="color:#22c55e;margin-bottom:12px;"><svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin:0 auto;display:block;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><strong style="display:block;font-size:18px;margin-bottom:8px;color:#1f2937;">ยืนยันการรับสินค้า</strong><p style="font-size:14px;color:#4b5563;margin:0 0 20px;">คุณได้รับสินค้าในสภาพที่ตกลงไว้เรียบร้อยแล้วใช่หรือไม่?</p>';
            const btnWrap = document.createElement('div');
            btnWrap.style.cssText = 'display:flex;gap:12px;justify-content:center;';
            const cancelBtn = document.createElement('button');
            cancelBtn.innerHTML = 'ยกเลิก';
            cancelBtn.style.cssText = 'flex:1;padding:10px;border:none;background:#f3f4f6;color:#4b5563;border-radius:8px;font-weight:500;cursor:pointer;font-family:inherit;transition:background 0.2s;';
            cancelBtn.onmouseover = () => cancelBtn.style.background = '#e5e7eb';
            cancelBtn.onmouseout = () => cancelBtn.style.background = '#f3f4f6';
            cancelBtn.onclick = () => overlay.remove();
            const confirmBtn = document.createElement('button');
            confirmBtn.innerHTML = 'ยืนยันได้รับสินค้า';
            confirmBtn.style.cssText = 'flex:1;padding:10px;border:none;background:#22c55e;color:#fff;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit;transition:background 0.2s;';
            confirmBtn.onmouseover = () => confirmBtn.style.background = '#16a34a';
            confirmBtn.onmouseout = () => confirmBtn.style.background = '#22c55e';
            confirmBtn.onclick = () => {
                confirmBtn.innerText = 'กำลังดำเนินการ...';
                confirmBtn.disabled = true;
                const f=document.createElement('form');
                f.method='post';
                f.action='/pages/handlers/trade_submit_logistics.php';
                f.innerHTML='<input type="hidden" name="action" value="receive_item"><input type="hidden" name="request_id" value="'+data.requestId+'">';
                document.body.append(f);
                f.submit();
            };
            btnWrap.append(cancelBtn, confirmBtn);
            modal.append(btnWrap);
            overlay.append(modal);
            document.body.append(overlay);
        };
        btnContainer.append(btnClaim, btn);
        sb.append(btnContainer);
    } else if(!myRv){
        sb.style.cssText='padding:12px 16px;background:#fefce8;color:#854d0e;border-radius:8px;display:flex;align-items:center;border:1px solid #fef08a;margin-bottom:12px;gap:12px;';
        sb.innerHTML='<div style="color:#eab308;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">คุณได้รับสินค้าแล้ว</strong><span style="font-size:12px;opacity:0.8;">รออีกฝ่ายกดยืนยันรับสินค้า</span></div>';
    } else {
        sb.style.cssText='padding:12px 16px;background:#f0fdf4;color:#166534;border-radius:8px;display:flex;align-items:center;border:1px solid #bbf7d0;margin-bottom:12px;gap:12px;';
        sb.innerHTML='<div style="color:#22c55e;display:flex;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div style="text-align:left;"><strong style="display:block;font-size:14px;">การแลกเปลี่ยนสำเร็จ!</strong></div>';
    }
    cb.append(sb);
}
    }
    if(cb.children.length>0)insertBanner(cb);
   }
  }
 }
});
</script>
<?php endif;?>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const composer=document.querySelector('.composer');
 const textInput=composer?.querySelector('[name="message_text"]');
 const layout=document.querySelector('.layout');
 const fitChatToViewport=()=>{
  if(!layout||window.innerWidth>1200)return;
  const top=Math.max(0,layout.getBoundingClientRect().top);
  layout.style.height=Math.max(280,window.innerHeight-top)+'px';
 };
 fitChatToViewport();
 window.addEventListener('resize',fitChatToViewport);
 window.addEventListener('orientationchange',()=>setTimeout(fitChatToViewport,150));

 const chatHead=document.querySelector('.chat-head');
 if(chatHead&&!chatHead.querySelector('.chat-back')){
  const back=document.createElement('a');
  back.className='chat-back';
  back.href='/pages/chat.php';
  back.setAttribute('aria-label','กลับไปรายการแชท');
 back.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>';
 chatHead.prepend(back);
 }
 const contextData=<?=json_encode($chatContextBar,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
 if(chatHead&&contextData&&!document.querySelector('.chat-context-bar')){
  const context=document.createElement('a');
  context.className='chat-context-bar';
  context.href=contextData.url;
  if(contextData.image){
   const thumb=document.createElement('img');
   thumb.src=contextData.image;
   thumb.alt='';
   context.append(thumb);
  }else{
   const placeholder=document.createElement('span');
   placeholder.className='chat-context-placeholder';
   context.append(placeholder);
  }
  const copy=document.createElement('span');
  copy.className='chat-context-copy';
  const title=document.createElement('strong');
  title.textContent=contextData.title;
  const subtitle=document.createElement('small');
  subtitle.textContent=contextData.subtitle;
  copy.append(title,subtitle);
  const arrow=document.createElement('span');
  arrow.className='chat-context-arrow';
  arrow.textContent='›';
  context.append(copy,arrow);
  chatHead.after(context);
 }
 if(!composer||!textInput||composer.querySelector('[name="chat_image"]'))return;
 textInput.required=false;
 const label=document.createElement('label');
 label.className='chat-attach';
 label.title='ถ่ายภาพหรือเลือกรูป';
 label.setAttribute('aria-label','ถ่ายภาพหรือเลือกรูป');
 label.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="15" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 5l1.4-2h5.2L16 5"/></svg>';
 const input=document.createElement('input');
 input.type='file';
 input.name='chat_image';
 input.accept='image/jpeg,image/png,image/webp,image/gif';
 label.append(input);
 composer.insertBefore(label,textInput);
 const preview=document.createElement('div');
 preview.className='chat-image-preview';
 preview.hidden=true;
 const image=document.createElement('img');
 image.alt='ตัวอย่างรูปที่จะส่ง';
 const remove=document.createElement('button');
 remove.type='button';
 remove.setAttribute('aria-label','นำรูปออก');
 remove.textContent='×';
 preview.append(image,remove);
 composer.before(preview);
 let previewUrl='';
 const clear=()=>{input.value='';preview.hidden=true;if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=''};
 input.addEventListener('change',()=>{
  const file=input.files?.[0];
  if(!file)return clear();
  if(file.size>8*1024*1024){clear();alert('รูปภาพต้องมีขนาดไม่เกิน 8 MB');return}
  if(previewUrl)URL.revokeObjectURL(previewUrl);
  previewUrl=URL.createObjectURL(file);
  image.src=previewUrl;
  preview.hidden=false;
 });
 remove.addEventListener('click',clear);
});
</script>
<?php
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>กล่องข้อความ - วนวรรณ</title><link rel="stylesheet" href="../assets/css/styles.css"><script>window.wanawanChatReadStatus=<?=json_encode(['conversationId'=>$conversationId,'ownMessageIds'=>$ownMessageIds,'lastReadMessageId'=>$lastReadOwnMessageId,'lastMessageId'=>$lastMessageId,'peerImage'=>chatImage($active['profile_image']??null),'peerName'=>$active['seller_name']??'คู่สนทนา','threadProfiles'=>array_map(fn($c)=>['id'=>(int)$c['conversation_id'],'image'=>chatImage($c['profile_image']??null),'name'=>$c['seller_name']??'คู่สนทนา'],$conversations)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;</script><script src="/assets/js/chat-read-status.js" defer></script><style>
*{box-sizing:border-box}.layout{display:flex;max-width:1440px;margin:auto;min-height:760px}.chat{flex:1;display:grid;grid-template-columns:280px 1fr 270px;border:1px solid #ddd8d2;margin:28px;border-radius:16px;overflow:hidden;background:#fff}.threads{border-right:1px solid #ddd8d2}.threads h2,.chat-head,.product h3{padding:20px;margin:0;color:var(--secondary-color);border-bottom:1px solid #ddd8d2}.thread{display:flex;align-items:center;gap:11px;padding:14px 18px;border-bottom:1px solid #eee;color:inherit;text-decoration:none}.thread-copy{min-width:0;flex:1}.thread-avatar,.chat-head-avatar{width:42px;height:42px;flex:0 0 42px;border-radius:50%;object-fit:cover;background:#f2dbe4;display:grid;place-items:center;color:#a95575;font-weight:700}.chat-head-avatar{width:40px;height:40px;flex-basis:40px}.thread.active{background:#e9f1f2}.thread strong,.thread small{display:block}.thread small{color:var(--text-muted);margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.conversation{display:flex;flex-direction:column;min-width:0}.chat-head{display:flex;align-items:center;gap:11px}.messages{flex:1;padding:25px;overflow:auto;background:#fcfaf8}.bubble{max-width:72%;margin:0 0 14px;padding:13px 16px;border:1px solid #ddd8d2;border-radius:16px;background:#fff;line-height:1.55}.bubble.mine{margin-left:auto;background:#d18aa7;color:#fff;border-color:#d18aa7}.bubble time{display:block;font-size:10px;opacity:.7;text-align:right}.composer{display:flex;gap:10px;padding:14px;border-top:1px solid #ddd8d2}.composer input{flex:1;border:1px solid #ccc;border-radius:22px;padding:12px 16px;font:inherit}.composer button{border:0;border-radius:50%;width:45px;background:#d18aa7;color:#fff;font:inherit}.product{border-left:1px solid #ddd8d2;padding-bottom:20px}.product-card{margin:18px;border:1px solid #ddd8d2;border-radius:12px;padding:12px;background:#fff}.product-card img{width:100%;height:210px;object-fit:cover;border-radius:8px;cursor:pointer}.product-card strong{display:block;margin:10px 0;color:var(--secondary-color)}.chat-product-actions{display:grid;gap:8px;margin-top:14px}.chat-product-btn{min-height:40px;border:1px solid #d3948b;border-radius:8px;display:grid;place-items:center;padding:8px;text-decoration:none;font:inherit;font-weight:600;cursor:pointer}.chat-product-btn.cart,.chat-product-btn.exchange{background:#d3948b;color:#fff}.chat-product-btn.detail{background:#fff;color:#b66f68}.empty{display:grid;place-items:center;color:var(--text-muted)}@media(max-width:1000px){.chat{grid-template-columns:240px 1fr}.product{display:none}}@media(max-width:750px){.layout{display:block}.chat{margin:12px;grid-template-columns:1fr}.threads{display:none}}
.layout{width:100%;max-width:none;margin:0;background:#fff}.chat{min-width:0;min-height:calc(100vh - 132px);margin:28px 32px}.chat>.empty{padding:40px;text-align:center;background:linear-gradient(135deg,#fffdfb,#fff7f2);font-size:17px}@media(min-width:1001px){.chat:not(:has(.conversation)){grid-template-columns:minmax(280px,340px) minmax(0,1fr)}.chat:not(:has(.conversation))>.product{display:none}}@media(max-width:800px){.chat{min-height:650px;margin:16px}}
.bubble{width:fit-content;min-width:54px;max-width:min(72%,680px);overflow-wrap:anywhere;word-break:break-word}.bubble.mine{width:fit-content}.read-receipt{display:block;margin-top:5px;text-align:right;font-size:11px;font-weight:500;opacity:.82}
.exchange-request-summary{margin:18px;border:1px solid #e0d6d1;border-radius:14px;background:#fff;overflow:hidden}.exchange-request-status{display:flex;justify-content:space-between;gap:8px;padding:12px 14px;background:#fff7f2;color:var(--secondary-color);font-size:13px;font-weight:700}.exchange-request-pair{display:grid;grid-template-columns:1fr 28px 1fr;align-items:center;gap:7px;padding:14px}.exchange-request-book{text-align:center;min-width:0}.exchange-request-book img,.exchange-request-placeholder{width:76px;height:105px;margin:auto;border-radius:7px;object-fit:cover;background:#f6e7e0}.exchange-request-book strong{display:block;margin-top:7px;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.exchange-request-book small{font-size:11px;color:var(--text-muted)}.exchange-request-swap{text-align:center;color:#4e8490;font-size:22px}.exchange-request-message{margin:0;padding:0 14px 14px;color:var(--secondary-color);font-size:13px;line-height:1.5}.exchange-request-manage{display:grid;margin:0 14px 14px;min-height:38px;place-items:center;border-radius:8px;background:var(--secondary-color);color:#fff;text-decoration:none;font-weight:600;font-size:13px}
.layout{height:calc(100dvh - 76px);min-height:0;overflow:hidden}.chat{height:calc(100% - 56px);min-height:0}.threads,.conversation,.product{min-height:0;overflow:hidden}.threads,.product{overflow-y:auto}.threads{scrollbar-width:none;-ms-overflow-style:none}.threads::-webkit-scrollbar{display:none;width:0;height:0}.messages{min-height:0;overflow-y:auto;overscroll-behavior:contain}.bubble{display:flex;flex-direction:column;align-items:flex-start}.bubble.mine{align-items:flex-end}.bubble-text{white-space:pre-wrap}.chat-image{display:block;width:min(300px,100%);max-height:360px;border-radius:11px;object-fit:cover}.composer{flex-wrap:nowrap}.chat-attach{width:45px;height:45px;flex:0 0 45px;border:1px solid #d3948b;border-radius:50%;display:grid;place-items:center;color:#b66f68;cursor:pointer}.chat-attach svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.chat-attach input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}.chat-image-preview{position:relative;width:max-content;max-width:calc(100% - 28px);margin:10px 14px 0;padding:6px;border:1px solid #eadbd4;border-radius:11px;background:#fff}.chat-image-preview img{display:block;width:90px;height:90px;border-radius:8px;object-fit:cover}.chat-image-preview button{position:absolute;right:-8px;top:-8px;width:25px;height:25px;border:0;border-radius:50%;background:#b94f5e;color:#fff;font-size:18px;cursor:pointer}
.chat-back{display:none;width:38px;height:38px;flex:0 0 38px;border:0;border-radius:50%;background:#f8e9e4;color:var(--secondary-color);place-items:center;text-decoration:none}.chat-back svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.chat-context-bar{display:none;grid-template-columns:48px minmax(0,1fr) 24px;align-items:center;gap:11px;padding:9px 14px;border-bottom:1px solid #e5ddd8;background:#fffaf7;color:inherit;text-decoration:none}.chat-context-bar img,.chat-context-placeholder{width:48px;height:48px;border-radius:7px;object-fit:cover;background:#f3e4dd}.chat-context-copy{min-width:0}.chat-context-copy strong,.chat-context-copy small{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.chat-context-copy strong{color:var(--secondary-color);font-size:14px}.chat-context-copy small{margin-top:3px;color:var(--text-muted);font-size:12px}.chat-context-arrow{color:#b66f68;font-size:28px;text-align:right}
@media(max-width:1200px){
 .layout{display:flex;flex-direction:column;height:calc(100dvh - 76px);min-height:0;overflow:hidden}
 .chat{margin:18px;flex:1;min-height:0;grid-template-columns:minmax(0,1fr)}
 .chat:has(.conversation)>.threads{display:none}
 .chat:has(.conversation)>.conversation{display:flex}
 .chat:not(:has(.conversation))>.empty{display:none}
 .threads{display:block;border-right:0}
 .conversation{height:100%;min-height:0}
 .chat-head{display:flex;align-items:center;gap:10px;padding-right:64px}
 .chat-back{display:grid}
 .chat-context-bar{display:grid}
 .product{display:none}
}
@media(max-width:900px){
 .chat{margin:12px;height:calc(100% - 24px);grid-template-columns:minmax(0,1fr)}
 .chat:has(.conversation)>.threads{display:none}
 .chat:has(.conversation)>.conversation{display:flex}
 .conversation{height:100%;min-height:0}
 .threads{display:block;border-right:0}
 .thread{padding:18px 20px}
 .chat-head{display:flex;align-items:center;gap:10px;padding-right:64px}
 .chat-back{display:grid}
 .messages{padding:18px 14px}
 .bubble{max-width:86%}
 .composer{padding:10px;flex-wrap:nowrap}
 .composer input{min-width:0}
 .chat-image{max-height:280px}
 .chat-attach{width:42px;height:42px;flex-basis:42px}
}
@media(max-width:600px){
 .layout{height:calc(100dvh - 64px)}
 .header-topbar>.chat-menu-toggle{width:38px;height:38px;flex-basis:38px}
 .chat{margin:0;height:100%;border-left:0;border-right:0;border-bottom:0;border-radius:0}
 .threads h2{padding:17px 60px 17px 16px;font-size:21px}
 .chat-head{padding:13px 58px 13px 10px;font-size:19px}
 .messages{padding:14px 10px}
 .composer{padding:9px 8px}
 .bubble{max-width:88%}
}
@media(min-width:601px) and (max-width:1200px){
 .chat{grid-template-columns:clamp(240px,32vw,300px) minmax(0,1fr)}
 .chat:has(.conversation)>.threads{display:block}
 .chat:not(:has(.conversation))>.empty{display:grid}
 .threads{display:block;border-right:1px solid #ddd8d2}
 .conversation{display:flex;min-width:0}
 .chat-head{padding:20px 64px 20px 20px}
 .chat-back{display:none}
}
</style>
<style>
 .confirm-modal-overlay { position: fixed; inset: 0; z-index: 9999; background: rgba(36, 58, 62, 0.4); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; animation: fadeIn 0.2s ease-out; }
 .confirm-modal-box { background: #fff; padding: 32px 28px; border-radius: 20px; width: 90%; max-width: 340px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.12); animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
 .confirm-icon { width: 64px; height: 64px; margin: 0 auto 18px; background: #fff0f4; color: #d9534f; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 4px solid #fff9fa; box-shadow: 0 4px 10px rgba(217,83,79,0.1); }
 .confirm-icon svg { width: 30px; height: 30px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
 .confirm-modal-box h3 { margin: 0 0 10px; color: var(--secondary-color); font-size: 21px; font-weight: 600; }
 .confirm-modal-box p { margin: 0 0 26px; color: var(--text-muted); font-size: 15px; line-height: 1.5; }
 .confirm-actions { display: flex; gap: 12px; }
 .confirm-actions button { flex: 1; min-height: 46px; border: 0; border-radius: 12px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 15px; transition: transform 0.15s, background-color 0.2s; }
 .confirm-actions button:active { transform: scale(0.97); }
 .btn-cancel { background: #f2ebe8; color: var(--text-dark); }
 .btn-cancel:hover { background: #e5ddd8; }
 .btn-confirm { background: #d9534f; color: #fff; box-shadow: 0 4px 12px rgba(217,83,79,0.2); }
 .btn-confirm:hover { background: #c9302c; }
 @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
 @keyframes popIn { from { opacity: 0; transform: translateY(15px) scale(0.92); } to { opacity: 1; transform: translateY(0) scale(1); } }
 </style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script></head><body><?php require __DIR__.'/../components/header.php'; require_once __DIR__.'/../config/chat_system_messages.php';?><div class="layout"><?php require __DIR__.'/../components/profile_sidebar.php';?><main class="chat"><aside class="threads"><h2>ข้อความล่าสุด</h2><style>.thread-swipe-wrapper::-webkit-scrollbar{display:none}</style><?php foreach($conversations as $c):?><div class="thread-swipe-wrapper" style="display:flex; overflow-x:auto; scroll-snap-type:x mandatory; scrollbar-width:none; border-bottom:1px solid #eee;"><a class="thread <?=$conversationId==(int)$c['conversation_id']?'active':''?>" href="?conversation=<?=$c['conversation_id']?>" style="flex:0 0 100%; scroll-snap-align:start; border-bottom:0; min-width:100%;"><strong><?=htmlspecialchars($c['seller_name'])?></strong><small><?=htmlspecialchars($c['last_text']?:'เริ่มต้นบทสนทนา')?></small></a><form method="post" style="flex:0 0 75px; scroll-snap-align:end; background:#d9534f; display:flex;" onsubmit="return handleThreadDelete(event, this);"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['chat_csrf'])?>"><input type="hidden" name="delete_conversation" value="1"><input type="hidden" name="delete_conversation_id" value="<?=$c['conversation_id']?>"><button class="thread-delete-btn" type="submit" style="width:100%; border:0; background:transparent; color:#fff; font-weight:600; font:inherit; cursor:pointer;">ลบ</button></form></div><?php endforeach;?></aside><?php if(!$active):?><section class="empty">เลือกบทสนทนาเพื่อเริ่มแชต</section><aside class="product"></aside><?php else:?><section class="conversation"><h2 class="chat-head"><?=htmlspecialchars($active['seller_name'])?></h2><div class="messages"><?php foreach($messages as $m): $sysMsg = parse_chat_system_message($m['message_text']); ?><div class="bubble <?=$m['sender_user_id']==$userId?'mine':''?> <?=$sysMsg ? 'system' : ''?>" <?=$sysMsg ? 'style="background:transparent;padding:0;border:0;box-shadow:none;"' : ''?>><?= $sysMsg ? $sysMsg : nl2br(htmlspecialchars($m['message_text']))?><time><?=date('H:i',strtotime($m['created_at']))?></time></div><?php endforeach;?></div><form class="composer" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['chat_csrf'])?>"><input type="hidden" name="conversation_id" value="<?=$conversationId?>"><input name="message_text" placeholder="พิมพ์ข้อความ..." required autocomplete="off"><button name="send_message">➤</button></form></section><aside class="product"><h3><?=$exchange?'ข้อมูลรายการแลกเปลี่ยน':'ข้อมูลสินค้าที่ถามถึง'?></h3><?php if($book):?><div class="product-card"><?php if($img=chatImage($book['image_cover'])):?><img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($book['title'])?>"><?php endif;?><strong><?=htmlspecialchars($book['title'])?></strong><span>฿<?=number_format($book['price'],2)?></span><div class="chat-product-actions"><button class="chat-product-btn cart" type="button" onclick="addToCart(<?=$book['book_id']?>)">เพิ่มลงตะกร้า</button><a class="chat-product-btn detail" href="/pages/book_detail.php?id=<?=$book['book_id']?>">ดูรายละเอียดสินค้า</a></div></div><?php elseif($exchange):?><div class="product-card"><?php if($img=chatImage($exchange['image_cover'])):?><img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($exchange['title'])?>"><?php endif;?><strong><?=htmlspecialchars($exchange['title'])?></strong><span>รายการแลกเปลี่ยน · สภาพ <?=number_format((int)($exchange['condition_percent']??0))?>%</span><div class="chat-product-actions"><?php if((int)$exchange['owner_id']!==$userId):?><a class="chat-product-btn exchange" href="/pages/exchange_request.php?exchange_id=<?=$exchange['exchange_id']?>">ยื่นคำขอแลกเปลี่ยน</a><?php endif;?><a class="chat-product-btn detail" href="/pages/book_detail.php?source=exchange&amp;id=<?=$exchange['exchange_id']?>">ดูรายละเอียดรายการแลกเปลี่ยน</a></div></div><?php else:?><div class="empty" style="padding:50px 15px">บทสนทนาทั่วไป</div><?php endif;?></aside><?php endif;?></main></div><?php require __DIR__.'/../components/footer.php';?><?php if($book):?><script>document.querySelector('.product-card img')?.addEventListener('click',()=>location.href='/pages/book_detail.php?id=<?=$book['book_id']?>');</script><?php elseif($exchange):?><script>document.querySelector('.product-card img')?.addEventListener('click',()=>location.href='/pages/book_detail.php?source=exchange&id=<?=$exchange['exchange_id']?>');</script><?php endif;?>
  <div id="conditionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(4px);animation:fadeIn 0.2s ease-out;">
     <div style="background:#fff;width:95%;max-width:640px;border-radius:12px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,0.2);display:flex;flex-direction:column;height:90vh;animation:popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
         <div style="padding:15px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;background:#fffaf0;">
             <h3 style="margin:0;color:#175564;font-size:16px;display:flex;align-items:center;gap:8px;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> ฟอร์มยืนยันสภาพสินค้า</h3>
             <button onclick="document.getElementById('conditionModal').style.display='none';document.getElementById('conditionIframe').src='';" style="background:none;border:none;font-size:24px;cursor:pointer;color:#999;padding:0;line-height:1;">&times;</button>
         </div>
         <iframe id="conditionIframe" style="width:100%;flex:1;border:none;background:#fff;" src=""></iframe>
     </div>
 </div>
 <div id="deleteConfirmModal" class="confirm-modal-overlay">
     <div class="confirm-modal-box">
         <div class="confirm-icon">
             <svg viewBox="0 0 24 24"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M10 11v6M14 11v6"/></svg>
         </div>
         <h3>ยืนยันการลบแชท</h3>
         <p>คุณแน่ใจหรือไม่ว่าต้องการลบแชทนี้?<br>การกระทำนี้ไม่สามารถย้อนกลับได้</p>
         <div class="confirm-actions">
             <button type="button" class="btn-cancel" onclick="closeDeleteModal()">ยกเลิก</button>
             <button type="button" class="btn-confirm" id="confirmDeleteBtn">ลบแชท</button>
         </div>
     </div>
 </div>
 <script>
 let currentDeleteForm = null;
 function handleThreadDelete(e, form) {
     e.preventDefault();
     currentDeleteForm = form;
     document.getElementById('deleteConfirmModal').style.display = 'flex';
     return false;
 }
 function closeDeleteModal() {
     document.getElementById('deleteConfirmModal').style.display = 'none';
     currentDeleteForm = null;
 }
 document.addEventListener('DOMContentLoaded', () => {
      document.addEventListener('DOMContentLoaded', () => {
     window.addEventListener('message', function(e) {
         if(e.data && e.data.action === 'close_condition_modal') {
             const m = document.getElementById('conditionModal');
             if(m) {
                 m.style.display = 'none';
                 document.getElementById('conditionIframe').src = '';
                 window.location.reload();
             }
         }
     });
     
     document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
         if(currentDeleteForm) currentDeleteForm.submit();
     });
     
     document.querySelectorAll('.thread-swipe-wrapper').forEach(wrapper => {
        let isDown = false;
        let startX;
        let scrollLeft;
        let isDragged = false;
        
        wrapper.querySelectorAll('a, img').forEach(el => el.draggable = false);
        
        wrapper.addEventListener('mousedown', (e) => {
            isDragged = false;
            if (e.target.closest('.thread-delete-btn')) return; // let buttons work
            isDown = true;
            startX = e.pageX - wrapper.offsetLeft;
            scrollLeft = wrapper.scrollLeft;
            wrapper.style.scrollSnapType = 'none';
        });
        wrapper.addEventListener('mouseleave', () => {
            if(!isDown) return;
            isDown = false;
            wrapper.style.scrollSnapType = 'x mandatory';
            if (wrapper.scrollLeft > 30) wrapper.scrollTo({left: 75, behavior: 'smooth'});
            else wrapper.scrollTo({left: 0, behavior: 'smooth'});
        });
        wrapper.addEventListener('mouseup', (e) => {
            if(!isDown) return;
            isDown = false;
            wrapper.style.scrollSnapType = 'x mandatory';
            if (wrapper.scrollLeft > 30) wrapper.scrollTo({left: 75, behavior: 'smooth'});
            else wrapper.scrollTo({left: 0, behavior: 'smooth'});
        });
        wrapper.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - wrapper.offsetLeft;
            const walk = (startX - x);
            if (Math.abs(walk) > 5) isDragged = true;
            wrapper.scrollLeft = scrollLeft + walk;
        });
        wrapper.addEventListener('click', (e) => {
            if (isDragged) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });
});
</script>
</body></html>
