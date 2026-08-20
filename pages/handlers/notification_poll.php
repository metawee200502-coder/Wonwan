<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['success'=>false]);exit;}
require_once __DIR__.'/../../config/config.php';
$userId=(int)$_SESSION['user_id'];$afterId=max(0,(int)($_GET['after_id']??0));
$count=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$count->execute([$userId]);$unread=(int)$count->fetchColumn();
$stmt=$pdo->prepare('SELECT notification_id,title,message,notification_type,chat_conversation_id,created_at FROM notifications WHERE user_id=? AND is_read=0 AND notification_id>? ORDER BY notification_id ASC LIMIT 10');
$stmt->execute([$userId,$afterId]);$admin=in_array($_SESSION['role']??'', ['publisher_admin','shop_admin'],true);$items=[];
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $notice){
 $url=$admin?'/pages/admin_notifications.php?read='.(int)$notice['notification_id']:'/pages/notifications.php?read='.(int)$notice['notification_id'];
 $items[]=['id'=>(int)$notice['notification_id'],'title'=>$notice['title'],'message'=>$notice['message'],'type'=>$notice['notification_type'],'url'=>$url,'time'=>date('H:i',strtotime($notice['created_at']))];
}
$latest=$pdo->prepare('SELECT COALESCE(MAX(notification_id),0) FROM notifications WHERE user_id=?');$latest->execute([$userId]);
$chatUnreadTotal=0;$chatUnreadByConversation=[];
if($admin){
 $role=$_SESSION['role']??'';$sellerType=$role==='publisher_admin'?'publisher':'shop';$staffTable=$sellerType==='publisher'?'publisher_staff':'shop_staff';$sellerColumn=$sellerType==='publisher'?'publisher_id':'shop_id';
 $seller=$pdo->prepare("SELECT $sellerColumn FROM $staffTable WHERE user_id=? LIMIT 1");$seller->execute([$userId]);$sellerId=(int)$seller->fetchColumn();
 if($sellerId){$chat=$pdo->prepare("SELECT c.conversation_id,COUNT(m.message_id) unread_count FROM chat_conversations c LEFT JOIN chat_messages m ON m.conversation_id=c.conversation_id AND m.is_read=0 AND m.sender_user_id=c.customer_user_id WHERE c.seller_type=? AND c.seller_id=? GROUP BY c.conversation_id");$chat->execute([$sellerType,$sellerId]);foreach($chat->fetchAll(PDO::FETCH_ASSOC) as $row){$value=(int)$row['unread_count'];$chatUnreadByConversation[(int)$row['conversation_id']]=$value;$chatUnreadTotal+=$value;}}
}
echo json_encode(['success'=>true,'user_id'=>$userId,'unread_count'=>$unread,'latest_id'=>(int)$latest->fetchColumn(),'notifications'=>$items,'chat_unread_count'=>$chatUnreadTotal,'chat_unread_by_conversation'=>$chatUnreadByConversation],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
