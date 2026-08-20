<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['success'=>false]);exit;}
require_once __DIR__.'/../../config/config.php';
$userId=(int)$_SESSION['user_id'];$conversationId=(int)($_GET['conversation_id']??0);$afterId=max(0,(int)($_GET['after_id']??0));
$access=$pdo->prepare('SELECT * FROM chat_conversations WHERE conversation_id=?');$access->execute([$conversationId]);$conversation=$access->fetch(PDO::FETCH_ASSOC);
$allowed=$conversation&&((int)$conversation['customer_user_id']===$userId||($conversation['seller_type']==='user'&&(int)$conversation['seller_id']===$userId));
if($conversation&&!$allowed&&in_array($_SESSION['role']??'', ['publisher_admin','shop_admin'],true)){
 $staffTable=$conversation['seller_type']==='publisher'?'publisher_staff':($conversation['seller_type']==='shop'?'shop_staff':null);
 $staffColumn=$conversation['seller_type']==='publisher'?'publisher_id':($conversation['seller_type']==='shop'?'shop_id':null);
 if($staffTable){$staff=$pdo->prepare("SELECT 1 FROM $staffTable WHERE user_id=? AND $staffColumn=? LIMIT 1");$staff->execute([$userId,$conversation['seller_id']]);$allowed=(bool)$staff->fetchColumn();}
}
if(!$allowed){http_response_code(403);echo json_encode(['success'=>false]);exit;}
$pdo->prepare('INSERT INTO chat_presence(user_id,conversation_id,last_seen_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE last_seen_at=NOW()')->execute([$userId,$conversationId]);
$pdo->prepare('UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender_user_id<>? AND is_read=0')->execute([$conversationId,$userId]);
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND notification_type='chat' AND chat_conversation_id=? AND is_read=0")->execute([$userId,$conversationId]);
$stmt=$pdo->prepare('SELECT message_id,sender_user_id,message_text,image_path,media_type,is_read,created_at FROM chat_messages WHERE conversation_id=? AND message_id>? ORDER BY message_id');
$stmt->execute([$conversationId,$afterId]);
$messages=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $message){$timestamp=strtotime($message['created_at']);$messages[]=['id'=>(int)$message['message_id'],'text'=>$message['message_text'],'image'=>$message['image_path']?'/pages/'.ltrim($message['image_path'],'/'):null,'media_type'=>$message['media_type']?:'image','mine'=>(int)$message['sender_user_id']===$userId,'is_read'=>(bool)$message['is_read'],'time'=>date('H:i',$timestamp),'date_key'=>date('Y-m-d',$timestamp),'date_label'=>date('d/m/Y',$timestamp)];}
$read=$pdo->prepare('SELECT COALESCE(MAX(message_id),0) FROM chat_messages WHERE conversation_id=? AND sender_user_id=? AND is_read=1');$read->execute([$conversationId,$userId]);
echo json_encode(['success'=>true,'messages'=>$messages,'last_read_message_id'=>(int)$read->fetchColumn()],JSON_UNESCAPED_UNICODE);
