<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/digital_twin.php';

if (empty($_SESSION['user_id'])) {
    $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/pages/home.php';
    header('Location: /pages/login.php');
    exit;
}
$isDiscover = !empty($_GET['discover']);
$source = ($_GET['source'] ?? 'book') === 'exchange' ? 'exchange' : 'book';
$itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$item = $isDiscover
    ? ['item_id'=>0,'item_type'=>'book','title'=>'ผู้ช่วยค้นหาหนังสือ Wonwan','author'=>null,'category'=>null,'synopsis'=>'ค้นหาและเปรียบเทียบหนังสือในคลังตามความต้องการของคุณ','price'=>null]
    : ($itemId ? wanawanDigitalTwinItem($pdo, $source, (int)$itemId) : null);
if ($isDiscover) { $source = 'book'; $itemId = 0; }
if (!$item) { http_response_code(404); exit('ไม่พบหนังสือเล่มนี้'); }
if (empty($_SESSION['digital_twin_csrf'])) $_SESSION['digital_twin_csrf'] = bin2hex(random_bytes(24));

$conversationStmt = $pdo->prepare('SELECT conversation_id FROM digital_twin_conversations WHERE user_id=? AND item_type=? AND item_id=?');
$conversationStmt->execute([(int)$_SESSION['user_id'], $source, (int)$itemId]);
$conversationId = (int)$conversationStmt->fetchColumn();
$messages = [];
if ($conversationId) {
    $messageStmt = $pdo->prepare('SELECT role,content,created_at FROM digital_twin_messages WHERE conversation_id=? ORDER BY message_id ASC LIMIT 80');
    $messageStmt->execute([$conversationId]);
    $messages = $messageStmt->fetchAll(PDO::FETCH_ASSOC);
}

function digitalTwinCover(?string $value): string {
    if (!$value) return '/assets/image/minilogo.png';
    if (preg_match('#^https?://#i', $value)) return $value;
    $value = ltrim(str_replace('\\', '/', $value), '/');
    if (str_starts_with($value, 'pages/')) return '/' . $value;
    if (str_starts_with($value, 'uploads/')) return '/pages/' . $value;
    return '/assets/image/books/' . $value;
}
if ($isDiscover) {
    $cover = '/assets/image/minilogo.png';
} elseif ($source === 'exchange') {
    $coverStmt = $pdo->prepare('SELECT image_cover FROM exchanges WHERE exchange_id=?');
} else {
    $coverStmt = $pdo->prepare('SELECT image_cover FROM books WHERE book_id=?');
}
if (!$isDiscover) { $coverStmt->execute([(int)$itemId]); $cover = digitalTwinCover($coverStmt->fetchColumn() ?: null); }
$isConfigured = !empty(wanawanDigitalTwinConfig()['api_key']) && wanawanDigitalTwinConfig()['api_key'] !== 'YOUR_GEMINI_API_KEY';
$isWishlisted = false;
if (!$isDiscover) {
    $wishlistStmt = $source === 'exchange'
        ? $pdo->prepare('SELECT 1 FROM exchange_wishlists WHERE user_id=? AND exchange_id=?')
        : $pdo->prepare('SELECT 1 FROM wishlists WHERE user_id=? AND book_id=?');
    $wishlistStmt->execute([(int)$_SESSION['user_id'], (int)$itemId]);
    $isWishlisted = (bool)$wishlistStmt->fetchColumn();
}
$initialMessage = trim((string)($_GET['message'] ?? ''));
$titleMapStmt = $pdo->query("SELECT book_id,title FROM books WHERE status<>'hidden'");
$productTitleMap = [];
foreach ($titleMapStmt->fetchAll(PDO::FETCH_ASSOC) as $titleRow) $productTitleMap[(string)$titleRow['book_id']] = (string)$titleRow['title'];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Digital Twin · <?=htmlspecialchars($item['title'])?></title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        *{box-sizing:border-box}.twin-page{min-height:calc(100vh - 74px);background:linear-gradient(145deg,#fff8f3 0%,#fff 48%,#f5fbfb 100%);padding:30px 20px}.twin-shell{max-width:1120px;height:calc(100vh - 134px);min-height:620px;margin:auto;display:grid;grid-template-columns:310px minmax(0,1fr);overflow:hidden;border:1px solid #eadbd4;border-radius:22px;background:#fff;box-shadow:0 18px 55px rgba(76,46,40,.09)}
        .twin-book{padding:30px 25px;background:#fff5f1;border-right:1px solid #eadbd4;overflow:auto;scrollbar-width:none;-ms-overflow-style:none}.twin-book::-webkit-scrollbar{display:none;width:0;height:0}.twin-back{display:inline-flex;gap:7px;color:#17616c;text-decoration:none;font-weight:700;font-size:14px;margin-bottom:24px}.twin-cover{width:150px;height:215px;display:block;margin:0 auto 20px;object-fit:contain;border-radius:11px;background:#fff;box-shadow:0 9px 24px #65413624}.twin-kicker{color:#c25f82;font-size:12px;font-weight:800;letter-spacing:.08em}.twin-book h1{font-size:21px;line-height:1.45;color:#004f5d;margin:8px 0}.twin-meta{color:#846f67;font-size:13px;line-height:1.7}.twin-product-actions{display:grid;gap:9px;margin-top:18px}.twin-product-action{min-height:42px;border:1px solid #d37d97;border-radius:10px;background:#fff;color:#ad536f;font-family:var(--font-family);font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px}.twin-product-action svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;flex:0 0 auto}.twin-product-action:hover{background:#fff0f4}.twin-product-action.primary{background:#d37d97;color:#fff}.twin-product-action.buy{background:#00616d;border-color:#00616d;color:#fff}.twin-product-action.detail{border-color:#87b3b7;color:#00616d}.twin-product-action.active{background:#f8dce5;color:#a64364}.twin-product-action.active .heart-icon{fill:currentColor}.twin-note{margin-top:18px;padding:14px;border-radius:12px;background:#fff;border:1px solid #efdfe0;color:#796a65;font-size:12px;line-height:1.6}
        .twin-chat{min-width:0;min-height:0;height:100%;display:flex;flex-direction:column;overflow:hidden}.twin-head{height:78px;flex:0 0 auto;padding:18px 25px;border-bottom:1px solid #eee2dd;display:flex;align-items:center;gap:13px}.twin-orb{width:42px;height:42px;display:grid;place-items:center;border-radius:50%;color:#fff;background:linear-gradient(135deg,#d86f99,#056573);box-shadow:0 5px 16px #9b668c40}.twin-head strong{display:block;color:#005764;font-size:18px}.twin-head small{color:#9a8580}.twin-clear{margin-left:auto;border:0;background:transparent;color:#b36a79;font-family:inherit;cursor:pointer}.twin-messages{flex:1 1 auto;min-height:0;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;touch-action:pan-y;padding:25px;scroll-behavior:smooth;scrollbar-width:none;-ms-overflow-style:none}.twin-messages::-webkit-scrollbar{display:none;width:0;height:0}.message{display:flex;margin-bottom:16px}.message.user{justify-content:flex-end}.bubble{max-width:min(76%,650px);padding:13px 16px;border-radius:17px;line-height:1.7;font-size:14px;white-space:pre-wrap;overflow-wrap:anywhere}.assistant .bubble{background:#f5f8f7;color:#3e4c4b;border-bottom-left-radius:5px}.user .bubble{background:#d57e98;color:#fff;border-bottom-right-radius:5px}.bubble a{color:#006572;font-weight:700}.twin-welcome{text-align:center;max-width:580px;margin:30px auto;color:#806f69}.twin-welcome .twin-orb{width:64px;height:64px;margin:0 auto 15px;font-size:24px}.twin-welcome h2{color:#005764;margin:0 0 8px}.suggestions{display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:18px}.suggestion{border:1px solid #dfb8c4;background:#fff;color:#9b4d69;border-radius:20px;padding:9px 14px;font-family:inherit;cursor:pointer}.typing{display:none;flex:0 0 auto;color:#8a7772;font-size:13px;padding:0 28px 8px}.typing.show{display:block}.twin-form{flex:0 0 auto;padding:14px 20px 20px;border-top:1px solid #eee2dd}.twin-input-wrap{display:flex;gap:9px;padding:7px 7px 7px 16px;border:1px solid #ddceca;border-radius:16px;background:#fff}.twin-input{flex:1;resize:none;border:0;outline:0;font:14px/1.5 inherit;max-height:110px;padding:8px 0}.twin-send{width:44px;height:44px;border:0;border-radius:12px;background:#00616d;color:#fff;cursor:pointer;font-size:18px}.twin-send:disabled{opacity:.5}.twin-error{display:none;flex:0 0 auto;margin:0 20px 8px;padding:10px 13px;background:#fff0f0;color:#b44949;border-radius:9px;font-size:13px}.twin-error.show{display:block}.setup-banner{flex:0 0 auto;padding:10px 18px;background:#fff5d8;color:#7a5c1b;font-size:13px;text-align:center}
        .bubble a{display:inline-flex;align-items:center;gap:6px;margin:3px 0;padding:5px 10px;border:1px solid #b9d7d8;border-radius:8px;background:#fff;color:#006572;font-weight:700;text-decoration:none}.bubble a::after{content:'↗';font-size:12px}.bubble a:hover{background:#eaf7f6;border-color:#72afb3}
        .assistant .bubble strong{font-weight:800;color:#244f54}
        @media(max-width:760px){.twin-page{padding:0}.twin-shell{height:calc(100vh - 68px);min-height:0;border:0;border-radius:0;grid-template-columns:1fr}.twin-book{display:none}.twin-head{height:68px}.bubble{max-width:88%}.twin-messages{padding:18px 14px}.twin-form{padding:10px 12px 14px}}
    </style>
</head>
<body>
<?php require __DIR__ . '/../components/header.php'; ?>
<main class="twin-page">
    <section class="twin-shell">
        <aside class="twin-book">
            <img class="twin-cover" src="<?=htmlspecialchars($cover)?>" alt="ปก <?=htmlspecialchars($item['title'])?>">
            <span class="twin-kicker">DIGITAL TWIN</span>
            <h1><?=htmlspecialchars($item['title'])?></h1>
            <div class="twin-meta"><?=$isDiscover?'บอกแนวเรื่อง ราคา ผู้เขียน หรืออารมณ์ที่อยากอ่าน แล้วให้ AI ช่วยค้นเล่มที่เหมาะกับคุณ':'ผู้เขียน: '.htmlspecialchars($item['author'] ?: 'ไม่ระบุ').'<br>หมวดหมู่: '.htmlspecialchars($item['category'] ?: 'ไม่ระบุ')?></div>
            <?php if(!$isDiscover):?>
            <div class="twin-product-actions">
                <button class="twin-product-action <?=$isWishlisted?'active':''?>" type="button" aria-label="<?=$isWishlisted?'นำออกจากรายการโปรด':'เพิ่มในรายการโปรด'?>" onclick="toggleWishlist(<?=(int)$itemId?>,this,<?=json_encode($source)?>)"><svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></svg><span class="twin-action-label"><?=$isWishlisted?'อยู่ในรายการโปรด':'รายการโปรด'?></span></button>
                <a class="twin-product-action detail" href="/pages/book_detail.php?source=<?=urlencode($source)?>&amp;id=<?=(int)$itemId?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>ดูรายละเอียด<?=$source==='exchange'?'รายการ':'สินค้า'?></a>
                <?php if($source==='book'):?>
                    <button class="twin-product-action primary" type="button" onclick="addToCart(<?=(int)$itemId?>)"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M3 4h2l2.4 10.4a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 8H7"/></svg>เพิ่มลงตะกร้า</button>
                    <button class="twin-product-action buy" type="button" onclick="twinBuyNow(<?=(int)$itemId?>)"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg>สั่งซื้อเลย</button>
                <?php else:?>
                    <a class="twin-product-action buy" href="/pages/exchange_request.php?exchange_id=<?=(int)$itemId?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h11l-3-3m3 3-3 3M17 17H6l3 3m-3-3 3-3"/></svg>ยื่นคำขอแลกเปลี่ยน</a>
                <?php endif;?>
            </div>
            <?php endif;?>
            <div class="twin-note">Digital Twin วิเคราะห์จากข้อมูลหนังสือใน Wonwan Web และจะบอกคุณเมื่อข้อมูลบางส่วนยังไม่เพียงพอ</div>
        </aside>
        <div class="twin-chat">
            <?php if(!$isConfigured):?><div class="setup-banner">ระบบสนทนาพร้อมแล้ว เหลือเชื่อมกุญแจ Gemini API เพื่อเริ่มใช้งานจริง</div><?php endif;?>
            <header class="twin-head"><span class="twin-orb">✦</span><div><strong><?=$isDiscover?'ค้นพบเล่มที่ใช่ ในแบบที่เป็นคุณ':'คุยกับ “'.htmlspecialchars(mb_strimwidth($item['title'],0,48,'…','UTF-8')).'”'?></strong><small>ถาม วิเคราะห์ เปรียบเทียบ และค้นหาเล่มที่คล้ายกัน</small></div><button class="twin-clear" type="button" id="clearChat">เริ่มคุยใหม่</button></header>
            <div class="twin-messages" id="messages">
                <?php if(!$messages):?><div class="twin-welcome" id="welcome"><span class="twin-orb">✦</span><h2>อยากรู้เรื่องไหนเกี่ยวกับเล่มนี้?</h2><p>ฉันช่วยดูแนวเรื่อง อารมณ์ ความเหมาะสม หรือเปรียบเทียบกับหนังสือเล่มอื่นใน Wonwan ได้</p><div class="suggestions"><button class="suggestion">เล่มนี้เป็นแนวไหน เหมาะกับใคร?</button><button class="suggestion">มีแนวรักมากน้อยแค่ไหน?</button><button class="suggestion">แนะนำเล่มที่คล้ายกันให้หน่อย</button></div></div><?php endif;?>
                <?php foreach($messages as $message):?><div class="message <?=htmlspecialchars($message['role'])?>"><div class="bubble"><?=htmlspecialchars($message['content'])?></div></div><?php endforeach;?>
            </div>
            <div class="typing" id="typing">✦ Digital Twin กำลังวิเคราะห์...</div><div class="twin-error" id="error"></div>
            <form class="twin-form" id="chatForm"><div class="twin-input-wrap"><textarea class="twin-input" id="messageInput" rows="1" maxlength="1000" placeholder="เช่น เล่มนี้ต่างจากเรื่อง... ยังไง?"></textarea><button class="twin-send" id="sendButton" type="submit" aria-label="ส่งคำถาม">➤</button></div></form>
        </div>
    </section>
</main>
<script>
const twin={source:<?=json_encode($source)?>,itemId:<?=(int)$itemId?>,discover:<?=$isDiscover?'true':'false'?>,csrf:<?=json_encode($_SESSION['digital_twin_csrf'])?>};
const productTitles=<?=json_encode($productTitleMap,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
const messages=document.getElementById('messages'),input=document.getElementById('messageInput'),form=document.getElementById('chatForm'),send=document.getElementById('sendButton'),typing=document.getElementById('typing'),errorBox=document.getElementById('error');
function scrollBottom(){messages.scrollTop=messages.scrollHeight}
function appendFormattedText(container,text){const bold=/\*\*(.+?)\*\*/gs;let last=0,match;while((match=bold.exec(text))){container.append(document.createTextNode(text.slice(last,match.index)));const strong=document.createElement('strong');strong.textContent=match[1];container.append(strong);last=bold.lastIndex}container.append(document.createTextNode(text.slice(last)))}
function linkify(container,text){container.textContent='';const productUrl='\/pages\/book_detail\\.php\\?[^\\s)\\]\n]+';const regex=new RegExp('\\[([^\\]\\n]+)\\]\\(((?:https?:\\/\\/[^\\s)]+)|(?:'+productUrl+'))\\)|\\(?('+productUrl+')\\)?','g');let last=0,match;while((match=regex.exec(text))){appendFormattedText(container,text.slice(last,match.index));const a=document.createElement('a');const rawUrl=match[2]||match[3];a.href=rawUrl.includes('id=null')?'/pages/book_detail.php?source='+encodeURIComponent(twin.source)+'&id='+twin.itemId:rawUrl;let productId='';try{productId=new URL(a.href,location.origin).searchParams.get('id')||''}catch(_){}a.textContent=(match[1]||((rawUrl.startsWith('http')?'ดูแหล่งข้อมูล':(productTitles[productId]||'ดูรายละเอียดหนังสือ')))).replace(/\*\*/g,'');if(rawUrl.startsWith('http')){a.target='_blank';a.rel='noopener noreferrer'}container.append(a);last=regex.lastIndex}appendFormattedText(container,text.slice(last))}
function addMessage(role,text){document.getElementById('welcome')?.remove();const row=document.createElement('div');row.className='message '+role;const bubble=document.createElement('div');bubble.className='bubble';linkify(bubble,text);row.append(bubble);messages.append(row);scrollBottom()}
document.querySelectorAll('.message.assistant .bubble').forEach(bubble=>linkify(bubble,bubble.textContent||''));
async function request(payload){const response=await fetch('/pages/handlers/digital_twin_chat.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...payload,source:twin.source,item_id:twin.itemId,discover:twin.discover,csrf_token:twin.csrf})});const data=await response.json().catch(()=>({ok:false,message:'ระบบตอบกลับไม่สมบูรณ์'}));if(!response.ok||!data.ok)throw new Error(data.message||'เกิดข้อผิดพลาด');return data}
form.addEventListener('submit',async event=>{event.preventDefault();const text=input.value.trim();if(!text||send.disabled)return;addMessage('user',text);input.value='';send.disabled=true;typing.classList.add('show');errorBox.classList.remove('show');scrollBottom();try{const data=await request({message:text});addMessage('assistant',data.reply)}catch(error){errorBox.textContent=error.message;errorBox.classList.add('show')}finally{send.disabled=false;typing.classList.remove('show');input.focus()}});
input.addEventListener('keydown',event=>{if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();form.requestSubmit()}});
document.querySelectorAll('.suggestion').forEach(button=>button.addEventListener('click',()=>{input.value=button.textContent;form.requestSubmit()}));
document.getElementById('clearChat').addEventListener('click',async()=>{if(!confirm('ล้างประวัติการคุยกับหนังสือเล่มนี้ใช่หรือไม่?'))return;try{await request({action:'clear'});location.reload()}catch(error){errorBox.textContent=error.message;errorBox.classList.add('show')}});scrollBottom();
(function(){const el=document.querySelector('.twin-book');if(!el)return;let drag=false,startY=0,scrollY=0;el.addEventListener('mousedown',e=>{if(e.button!==0)return;drag=true;startY=e.clientY;scrollY=el.scrollTop;el.style.cursor='grabbing';e.preventDefault()},{passive:false});window.addEventListener('mousemove',e=>{if(!drag)return;el.scrollTop=scrollY-(e.clientY-startY)});window.addEventListener('mouseup',()=>{drag=false;el.style.cursor=''})})();
async function twinBuyNow(bookId){try{await wanawanAction('/pages/handlers/cart_handler.php',{action:'add',book_id:bookId});const checkout=document.createElement('form');checkout.method='post';checkout.action='/pages/checkout_summary.php';const selected=document.createElement('input');selected.type='hidden';selected.name='selected_ids';selected.value=JSON.stringify([String(bookId)]);checkout.appendChild(selected);document.body.appendChild(checkout);checkout.submit()}catch(error){showWanawanPopup('ไม่สามารถสั่งซื้อได้',error.message,{type:'error',showCart:false})}}
<?php if($initialMessage!==''):?>history.replaceState({},'',location.pathname+'?discover=1');input.value=<?=json_encode($initialMessage,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;setTimeout(()=>form.requestSubmit(),80);<?php endif;?>
</script>
</body></html>
