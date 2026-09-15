<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$handlerCode = <<<'PHP'
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
    
    $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([$conversationId,$userId,'[CONDITION_FORM_ACCEPTED:'.$userId.']']);
    $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
    header('Location:/pages/chat.php?conversation='.$conversationId);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['delete_conversation'])){
PHP;

if (strpos($content, 'accept_full_trade') === false) {
    $content = str_replace(
        "if(\$_SERVER['REQUEST_METHOD']==='POST'&&isset(\$_POST['delete_conversation'])){",
        $handlerCode,
        $content
    );
    file_put_contents($file, $content);
    echo "Added missing handlers successfully.\n";
} else {
    echo "Handlers already exist.\n";
}
