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
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['delete_conversation'])){
 if(!hash_equals($_SESSION['chat_csrf'],$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
 $delId=(int)$_POST['delete_conversation_id'];
 $pdo->prepare("DELETE FROM chat_conversations WHERE conversation_id=? AND (customer_user_id=? OR (seller_type='user' AND seller_id=?))")->execute([$delId,$userId,$userId]);
 header('Location:/pages/chat.php');exit;
}
$list=$pdo->prepare("SELECT c.*,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN COALESCE(NULLIF(cu.display_name,''),cu.email) ELSE COALESCE(p.company_name,s.shop_name,NULLIF(su.display_name,''),su.email) END seller_name,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN cu.profile_image ELSE COALESCE(p.profile_image,s.profile_image,su.profile_image) END profile_image,(SELECT message_text FROM chat_messages WHERE conversation_id=c.conversation_id ORDER BY message_id DESC LIMIT 1) last_text FROM chat_conversations c LEFT JOIN publishers p ON c.seller_type='publisher' AND p.publisher_id=c.seller_id LEFT JOIN shops s ON c.seller_type='shop' AND s.shop_id=c.seller_id LEFT JOIN users su ON c.seller_type='user' AND su.user_id=c.seller_id LEFT JOIN users cu ON cu.user_id=c.customer_user_id WHERE c.customer_user_id=? OR (c.seller_type='user' AND c.seller_id=?) ORDER BY c.last_message_at DESC,c.updated_at DESC");$list->execute([$userId,$userId,$userId,$userId]);$conversations=$list->fetchAll(PDO::FETCH_ASSOC);
$active=null;$messages=[];$book=null;$exchange=null;$exchangeRequest=null;if($conversationId){$q=$pdo->prepare("SELECT c.*,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN COALESCE(NULLIF(cu.display_name,''),cu.email) ELSE COALESCE(p.company_name,s.shop_name,NULLIF(su.display_name,''),su.email) END seller_name,CASE WHEN c.seller_type='user' AND c.seller_id=? THEN cu.profile_image ELSE COALESCE(p.profile_image,s.profile_image,su.profile_image) END profile_image FROM chat_conversations c LEFT JOIN publishers p ON c.seller_type='publisher' AND p.publisher_id=c.seller_id LEFT JOIN shops s ON c.seller_type='shop' AND s.shop_id=c.seller_id LEFT JOIN users su ON c.seller_type='user' AND su.user_id=c.seller_id LEFT JOIN users cu ON cu.user_id=c.customer_user_id WHERE c.conversation_id=? AND (c.customer_user_id=? OR (c.seller_type='user' AND c.seller_id=?))");$q->execute([$userId,$userId,$conversationId,$userId,$userId]);$active=$q->fetch(PDO::FETCH_ASSOC);if($active){$q=$pdo->prepare('SELECT m.*,u.display_name FROM chat_messages m JOIN users u ON u.user_id=m.sender_user_id WHERE m.conversation_id=? ORDER BY m.message_id');$q->execute([$conversationId]);$messages=$q->fetchAll(PDO::FETCH_ASSOC);$pdo->prepare('UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_user_id<>?')->execute([$conversationId,$userId]);if($active['referenced_book_id']){$q=$pdo->prepare('SELECT * FROM books WHERE book_id=?');$q->execute([$active['referenced_book_id']]);$book=$q->fetch(PDO::FETCH_ASSOC);}elseif($active['referenced_exchange_id']){$q=$pdo->prepare('SELECT * FROM exchanges WHERE exchange_id=?');$q->execute([$active['referenced_exchange_id']]);$exchange=$q->fetch(PDO::FETCH_ASSOC);if($exchange&&$active['seller_type']==='user'){$q=$pdo->prepare("SELECT er.*,offer.title offer_title,offer.image_cover offer_image,offer.condition_percent offer_condition FROM exchange_requests er LEFT JOIN exchanges offer ON offer.exchange_id=er.offered_exchange_id WHERE er.exchange_id=? AND er.requester_id IN (?,?) ORDER BY er.updated_at DESC LIMIT 1");$q->execute([(int)$active['referenced_exchange_id'],(int)$active['customer_user_id'],(int)$active['seller_id']]);$exchangeRequest=$q->fetch(PDO::FETCH_ASSOC)?:null;}}}}
$ownMessageIds=[];$lastReadOwnMessageId=0;$lastMessageId=0;foreach($messages as $message){$lastMessageId=max($lastMessageId,(int)$message['message_id']);if((int)$message['sender_user_id']===$userId){$ownMessageIds[]=(int)$message['message_id'];if((int)$message['is_read']===1)$lastReadOwnMessageId=(int)$message['message_id'];}}
$profile_active='messages';
$exchangeRequestPanel=null;
if($exchange&&$exchangeRequest){
 $exchangeRequestPanel=[
  'target'=>['title'=>$exchange['title'],'image'=>chatImage($exchange['image_cover']??null),'condition'=>(int)($exchange['condition_percent']??0)],
  'offer'=>['title'=>$exchangeRequest['offer_title']?:'ขอพูดคุยก่อน','image'=>chatImage($exchangeRequest['offer_image']??null),'condition'=>(int)($exchangeRequest['offer_condition']??0)],
  'message'=>$exchangeRequest['message']?:'สนใจแลกเปลี่ยนสินค้ารายการนี้',
  'status'=>['pending'=>'รอตอบรับ','accepted'=>'กำลังเจรจา','completed'=>'แลกเปลี่ยนสำเร็จ','rejected'=>'ปฏิเสธแล้ว','cancelled'=>'ยกเลิกแล้ว'][$exchangeRequest['status']]??$exchangeRequest['status'],
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
document.addEventListener('DOMContentLoaded', function() {
 try {
 const data=<?=json_encode($exchangeRequestPanel,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
 const product=document.querySelector('.product');
 const oldCard=product ? product.querySelector('.product-card') : null;
 
 const makeBook=function(book,label) {
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
 summary.append(status,pair,message,manage);oldCard.replaceWith(summary);
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
  if(composer && textInput && !composer.querySelector('[name="chat_image"]')){
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
    const file=input.files && input.files[0] ? input.files[0] : null;
    if(!file)return clear();
    if(file.size>8*1024*1024){clear();alert('รูปภาพต้องมีขนาดไม่เกิน 8 MB');return}
    if(previewUrl)URL.revokeObjectURL(previewUrl);
    previewUrl=URL.createObjectURL(file);
    image.src=previewUrl;
    preview.hidden=false;
   });
   remove.addEventListener('click',clear);
  }
 } catch(err) {
  console.error(err);
 }
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
</head><body><?php require __DIR__.'/../components/header.php';?><div class="layout"><?php require __DIR__.'/../components/profile_sidebar.php';?><main class="chat"><aside class="threads"><h2>ข้อความล่าสุด</h2><style>.thread-swipe-wrapper::-webkit-scrollbar{display:none}</style><?php foreach($conversations as $c):?><div class="thread-swipe-wrapper" style="display:flex; overflow-x:auto; scroll-snap-type:x mandatory; scrollbar-width:none; border-bottom:1px solid #eee;"><a class="thread <?=$conversationId==(int)$c['conversation_id']?'active':''?>" href="?conversation=<?=$c['conversation_id']?>" style="flex:0 0 100%; scroll-snap-align:start; border-bottom:0; min-width:100%;"><strong><?=htmlspecialchars($c['seller_name'])?></strong><small><?=htmlspecialchars($c['last_text']?:'เริ่มต้นบทสนทนา')?></small></a><form method="post" style="flex:0 0 75px; scroll-snap-align:end; background:#d9534f; display:flex;" onsubmit="return handleThreadDelete(event, this);"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['chat_csrf'])?>"><input type="hidden" name="delete_conversation" value="1"><input type="hidden" name="delete_conversation_id" value="<?=$c['conversation_id']?>"><button class="thread-delete-btn" type="submit" style="width:100%; border:0; background:transparent; color:#fff; font-weight:600; font:inherit; cursor:pointer;">ลบ</button></form></div><?php endforeach;?></aside><?php if(!$active):?><section class="empty">เลือกบทสนทนาเพื่อเริ่มแชต</section><aside class="product"></aside><?php else:?><section class="conversation"><h2 class="chat-head"><?=htmlspecialchars($active['seller_name'])?></h2><div class="messages"><?php foreach($messages as $m):?><div class="bubble <?=$m['sender_user_id']==$userId?'mine':''?>"><?=nl2br(htmlspecialchars($m['message_text']))?><time><?=date('H:i',strtotime($m['created_at']))?></time></div><?php endforeach;?></div><form class="composer" method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['chat_csrf'])?>"><input type="hidden" name="conversation_id" value="<?=$conversationId?>"><input name="message_text" placeholder="พิมพ์ข้อความ..." required autocomplete="off"><button name="send_message">➤</button></form></section><aside class="product"><h3><?=$exchange?'ข้อมูลรายการแลกเปลี่ยน':'ข้อมูลสินค้าที่ถามถึง'?></h3><?php if($book):?><div class="product-card"><?php if($img=chatImage($book['image_cover'])):?><img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($book['title'])?>"><?php endif;?><strong><?=htmlspecialchars($book['title'])?></strong><span>฿<?=number_format($book['price'],2)?></span><div class="chat-product-actions"><button class="chat-product-btn cart" type="button" onclick="addToCart(<?=$book['book_id']?>)">เพิ่มลงตะกร้า</button><a class="chat-product-btn detail" href="/pages/book_detail.php?id=<?=$book['book_id']?>">ดูรายละเอียดสินค้า</a></div></div><?php elseif($exchange):?><div class="product-card"><?php if($img=chatImage($exchange['image_cover'])):?><img src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($exchange['title'])?>"><?php endif;?><strong><?=htmlspecialchars($exchange['title'])?></strong><span>รายการแลกเปลี่ยน · สภาพ <?=number_format((int)($exchange['condition_percent']??0))?>%</span><div class="chat-product-actions"><?php if((int)$exchange['owner_id']!==$userId):?><a class="chat-product-btn exchange" href="/pages/exchange_request.php?exchange_id=<?=$exchange['exchange_id']?>">ยื่นคำขอแลกเปลี่ยน</a><?php endif;?><a class="chat-product-btn detail" href="/pages/book_detail.php?source=exchange&amp;id=<?=$exchange['exchange_id']?>">ดูรายละเอียดรายการแลกเปลี่ยน</a></div></div><?php else:?><div class="empty" style="padding:50px 15px">บทสนทนาทั่วไป</div><?php endif;?></aside><?php endif;?></main></div><?php require __DIR__.'/../components/footer.php';?><?php if($book):?><script>document.querySelector('.product-card img')?.addEventListener('click',()=>location.href='/pages/book_detail.php?id=<?=$book['book_id']?>');</script><?php elseif($exchange):?><script>document.querySelector('.product-card img')?.addEventListener('click',()=>location.href='/pages/book_detail.php?source=exchange&id=<?=$exchange['exchange_id']?>');</script><?php endif;?>
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
