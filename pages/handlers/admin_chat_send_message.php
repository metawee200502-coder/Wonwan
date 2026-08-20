<?php
session_start();
require_once __DIR__.'/../../config/config.php';
$userId=(int)($_SESSION['user_id']??0);$role=$_SESSION['role']??'';
$wantsJson=str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
if(!$userId||!in_array($role,['publisher_admin','shop_admin'],true)){header('Location:/pages/login.php');exit;}
$type=$role==='publisher_admin'?'publisher':'shop';$conversationId=(int)($_POST['conversation_id']??0);
if(!hash_equals($_SESSION['admin_chat_csrf']??'',(string)($_POST['csrf_token']??''))){http_response_code(403);exit('คำขอไม่ถูกต้อง');}
if($type==='publisher')$sellerStmt=$pdo->prepare('SELECT publisher_id FROM publisher_staff WHERE user_id=? LIMIT 1');
else $sellerStmt=$pdo->prepare('SELECT shop_id FROM shop_staff WHERE user_id=? LIMIT 1');
$sellerStmt->execute([$userId]);$sellerId=(int)$sellerStmt->fetchColumn();
$nameStmt=$type==='publisher'?$pdo->prepare('SELECT company_name FROM publishers WHERE publisher_id=?'):$pdo->prepare('SELECT shop_name FROM shops WHERE shop_id=?');$nameStmt->execute([$sellerId]);$senderName=wanawanSellerName((string)$nameStmt->fetchColumn(),$type);
$convStmt=$pdo->prepare('SELECT * FROM chat_conversations WHERE conversation_id=? AND seller_type=? AND seller_id=?');$convStmt->execute([$conversationId,$type,$sellerId]);$conversation=$convStmt->fetch(PDO::FETCH_ASSOC);
if(!$conversation){http_response_code(403);exit('ไม่สามารถส่งข้อความในห้องนี้ได้');}
$text=trim((string)($_POST['message_text']??''));$mediaPath=null;$mediaType=null;$uploadedFile=null;
try{
 if(!empty($_FILES['chat_media']['name'])&&($_FILES['chat_media']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
  $file=$_FILES['chat_media'];if($file['error']!==UPLOAD_ERR_OK)throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ');
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
  $types=['image/jpeg'=>['jpg','image',8],'image/png'=>['png','image',8],'image/webp'=>['webp','image',8],'image/gif'=>['gif','image',8],'video/mp4'=>['mp4','video',30],'video/webm'=>['webm','video',30],'video/quicktime'=>['mov','video',30]];
  if(!isset($types[$mime]))throw new RuntimeException('รองรับรูป JPG, PNG, WEBP, GIF และวิดีโอ MP4, WEBM, MOV');
  [$ext,$mediaType,$maxMb]=$types[$mime];if($file['size']>$maxMb*1024*1024)throw new RuntimeException('ไฟล์มีขนาดเกิน '.$maxMb.' MB');
  $dir=__DIR__.'/../uploads/chat';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('ไม่สามารถเตรียมพื้นที่จัดเก็บไฟล์ได้');
  $name='chat_'.$userId.'_'.bin2hex(random_bytes(12)).'.'.$ext;$uploadedFile=$dir.'/'.$name;
  if(!move_uploaded_file($file['tmp_name'],$uploadedFile))throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ');
  $mediaPath='uploads/chat/'.$name;
 }
 if($text===''&&!$mediaPath)throw new RuntimeException('กรุณาพิมพ์ข้อความหรือเลือกไฟล์แนบ');
 $pdo->beginTransaction();
 $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text,image_path,media_type) VALUES(?,?,?,?,?)')->execute([$conversationId,$userId,$text,$mediaPath,$mediaType]);$messageId=(int)$pdo->lastInsertId();
 $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
 $notice=$text!==''?mb_strimwidth($text,0,120,'…'):($mediaType==='video'?'ส่งวิดีโอ':'ส่งรูปภาพ');
 wanawanChatNotify($pdo,(int)$conversation['customer_user_id'],$conversationId,$senderName,$notice);
 $pdo->commit();
 if($wantsJson){header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>true,'message'=>['id'=>$messageId,'text'=>$text,'image'=>$mediaPath?'/pages/'.$mediaPath:null,'media_type'=>$mediaType?:'image','mine'=>true,'is_read'=>false,'time'=>date('H:i'),'date_key'=>date('Y-m-d'),'date_label'=>date('d/m/Y')]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($uploadedFile&&is_file($uploadedFile))@unlink($uploadedFile);if($wantsJson){header('Content-Type: application/json; charset=utf-8');http_response_code(422);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit;}header('Location:/pages/admin_chat.php?conversation='.$conversationId.'&error='.urlencode($e->getMessage()));exit;}
header('Location:/pages/admin_chat.php?conversation='.$conversationId);exit;
