<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

// 1. Update queries for myCondition and partnerCondition
$oldQueries = <<<'PHP'
  'myCondition'=>(function() use ($pdo,$exchangeRequest,$userId){ $s=$pdo->prepare('SELECT required_deposit_amount,deposit_status,deposit_approved_at,tracking_number,is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=?');$s->execute([(int)$exchangeRequest['request_id'],$userId]);return $s->fetch(PDO::FETCH_ASSOC)?:null;})(),
  'partnerCondition'=>(function() use ($pdo,$exchangeRequest,$userId){ $ex=$pdo->prepare('SELECT owner_id FROM exchanges WHERE exchange_id=?');$ex->execute([(int)$exchangeRequest['exchange_id']]);$row=$ex->fetch(PDO::FETCH_ASSOC);$pid=(int)($row&&(int)$row['owner_id']!==$userId?$row['owner_id']:$exchangeRequest['requester_id']);$s=$pdo->prepare('SELECT required_deposit_amount,deposit_status,deposit_approved_at,tracking_number,is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=?');$s->execute([(int)$exchangeRequest['request_id'],$pid]);return $s->fetch(PDO::FETCH_ASSOC)?:null;})(),
PHP;

$newQueries = <<<'PHP'
  'myCondition'=>(function() use ($pdo,$exchangeRequest,$userId){ $s=$pdo->prepare('SELECT form_id,is_accepted_by_partner,required_deposit_amount,deposit_status,deposit_approved_at,tracking_number,is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=?');$s->execute([(int)$exchangeRequest['request_id'],$userId]);return $s->fetch(PDO::FETCH_ASSOC)?:null;})(),
  'partnerCondition'=>(function() use ($pdo,$exchangeRequest,$userId){ $ex=$pdo->prepare('SELECT owner_id FROM exchanges WHERE exchange_id=?');$ex->execute([(int)$exchangeRequest['exchange_id']]);$row=$ex->fetch(PDO::FETCH_ASSOC);$pid=(int)($row&&(int)$row['owner_id']!==$userId?$row['owner_id']:$exchangeRequest['requester_id']);$s=$pdo->prepare('SELECT form_id,is_accepted_by_partner,required_deposit_amount,deposit_status,deposit_approved_at,tracking_number,is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=?');$s->execute([(int)$exchangeRequest['request_id'],$pid]);return $s->fetch(PDO::FETCH_ASSOC)?:null;})(),
PHP;

$content = str_replace($oldQueries, $newQueries, $content);

// 2. Update accept_condition_chat handler message
$oldAcceptHandler = <<<'PHP'
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['accept_condition_chat'])){
    if(!hash_equals($_SESSION['chat_csrf'],$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
    $formId = (int)$_POST['accept_condition_form_id'];
    $reqId = (int)$_POST['request_id'];
    
    $pdo->prepare("UPDATE trade_condition_forms SET is_accepted_by_partner=1, accepted_at=NOW() WHERE form_id=?")->execute([$formId]);
    
    $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId,$userId,'[CONDITION_FORM_ACCEPTED:'.$userId.']']);
    $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
    header('Location:/pages/chat.php?conversation='.$conversationId);exit;
}
PHP;

$newAcceptHandler = <<<'PHP'
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['accept_condition_chat'])){
    if(!hash_equals($_SESSION['chat_csrf'],$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
    $formId = (int)$_POST['accept_condition_form_id'];
    $reqId = (int)$_POST['request_id'];
    
    $pdo->prepare("UPDATE trade_condition_forms SET is_accepted_by_partner=1, accepted_at=NOW() WHERE form_id=?")->execute([$formId]);
    
    $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId,$userId,'ยอมรับสภาพหนังสือก่อนส่งแล้ว']);
    $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
    header('Location:/pages/chat.php?conversation='.$conversationId);exit;
}
PHP;

$content = str_replace($oldAcceptHandler, $newAcceptHandler, $content);

// 3. Update makeCondBtn JS function to getCondBanner
$oldJS = <<<'JS'
  const makeCondBtn=function(){const w=document.createElement('div');w.style.cssText='padding:15px;background:#fffaf0;color:#92400e;border-radius:8px;font-size:14px;text-align:center;border:1px solid #fde68a;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px';w.innerHTML='\ud83d\udcdd <strong>\u0e01\u0e23\u0e2d\u0e01\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e22\u0e37\u0e19\u0e22\u0e31\u0e19\u0e2a\u0e20\u0e32\u0e1e\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32</strong><br>\u0e01\u0e23\u0e38\u0e13\u0e32\u0e01\u0e23\u0e2d\u0e01\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e1e\u0e23\u0e49\u0e2d\u0e23\u0e39\u0e1b\u0e16\u0e48\u0e32\u0e22\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32\u0e02\u0e2d\u0e07\u0e04\u0e38\u0e13<br><br>';const btn=document.createElement('button');btn.className='btn primary';btn.textContent='\u0e01\u0e23\u0e2d\u0e01\u0e1f\u0e2d\u0e23\u0e4c\u0e21\u0e2a\u0e20\u0e32\u0e1e\u0e2a\u0e34\u0e19\u0e04\u0e49\u0e32';btn.style.cssText='padding:8px 20px;background:#d97706;border-color:#d97706';btn.onclick=function(){const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');if(modal&&iframe){iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1';modal.style.display='flex';}else{window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId;}};w.append(btn);return w;};
JS;

$newJS = <<<'JS'
  const getCondBanner=function(){
      const w=document.createElement('div');
      w.style.cssText='padding:15px;border-radius:8px;font-size:14px;text-align:center;box-shadow:0 2px 4px rgba(0,0,0,.05);margin-bottom:10px;';
      
      const myCond = data.myCondition;
      const ptCond = data.partnerCondition;
      
      if(!myCond) {
          w.style.background='#fffaf0'; w.style.color='#92400e'; w.style.border='1px solid #fde68a';
          w.innerHTML='\ud83d\udcdd <strong>กรอกฟอร์มยืนยันสภาพสินค้า</strong><br>กรุณากรอกฟอร์มพร้อมรูปถ่ายสินค้าของคุณ<br><br>';
          const btn=document.createElement('button');btn.className='btn primary';
          btn.textContent='กรอกฟอร์มสภาพสินค้า';
          btn.style.cssText='padding:8px 20px;background:#d97706;border-color:#d97706';
          btn.onclick=function(){
              const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
              if(modal&&iframe){
                  iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&iframe=1';
                  modal.style.display='flex';
              } else { window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId; }
          };
          w.append(btn);
          return w;
      }
      
      if(myCond && !ptCond) {
          w.style.background='#eff6ff'; w.style.color='#1e40af'; w.style.border='1px solid #bfdbfe';
          w.innerHTML='\u23f3 <strong>รออีกฝ่ายเพิ่มข้อมูล</strong><br>คุณกรอกข้อมูลแล้ว กำลังรออีกฝ่ายกรอกฟอร์มสภาพสินค้า';
          return w;
      }
      
      if(ptCond && ptCond.is_accepted_by_partner != 1) {
          w.style.background='#f0fff4'; w.style.color='#276749'; w.style.border='1px solid #c6f6d5';
          w.innerHTML='\ud83d\udd0d <strong>ตรวจสอบและยอมรับสภาพหนังสือ</strong><br>อีกฝ่ายกรอกข้อมูลแล้ว กรุณาตรวจสอบและกดยอมรับ<br><br>';
          
          const vBtn=document.createElement('button');vBtn.className='btn primary';
          vBtn.textContent='ดูฟอร์มและยอมรับ';
          vBtn.style.cssText='padding:8px 20px;background:#38a169;border-color:#38a169;margin-right:10px;';
          vBtn.onclick=function(){
              const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
              if(modal&&iframe){
                  iframe.src='/pages/trade_condition_form.php?request_id='+data.requestId+'&readonly=1&iframe=1';
                  modal.style.display='flex';
              } else { window.location.href='/pages/trade_condition_form.php?request_id='+data.requestId+'&readonly=1'; }
          };
          
          const aForm=document.createElement('form');
          aForm.method='post'; aForm.style.display='inline-block';
          aForm.innerHTML=`<input type="hidden" name="csrf_token" value="${document.querySelector('input[name=csrf_token]').value}">
          <input type="hidden" name="accept_condition_form_id" value="${ptCond.form_id}">
          <input type="hidden" name="request_id" value="${data.requestId}">
          <input type="hidden" name="accept_condition_chat" value="1">
          <button type="submit" class="btn primary" style="padding:8px 20px;background:#3182ce;border-color:#3182ce;">ยอมรับ</button>`;
          
          w.append(vBtn);
          w.append(aForm);
          return w;
      }
      
      if(myCond && ptCond && ptCond.is_accepted_by_partner == 1 && myCond.is_accepted_by_partner != 1) {
          w.style.background='#eff6ff'; w.style.color='#1e40af'; w.style.border='1px solid #bfdbfe';
          w.innerHTML='\u23f3 <strong>รออีกฝ่ายยอมรับ</strong><br>คุณยอมรับเงื่อนไขแล้ว รออีกฝ่ายกดยอมรับฟอร์มของคุณ';
          return w;
      }
      
      return null;
  };
JS;

$content = str_replace($oldJS, $newJS, $content);

// 4. Update references to makeCondBtn
$content = str_replace('cb.append(makeCondBtn());', 'const banner = getCondBanner(); if(banner) cb.append(banner);', $content);

file_put_contents($file, $content);
echo "Applied banner flow fix successfully.\n";
