<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if(empty($_SESSION['user_id'])){http_response_code(401);echo json_encode(['success'=>false,'message'=>'กรุณาเข้าสู่ระบบ']);exit;}
require_once __DIR__.'/../../config/config.php';
$userId=(int)$_SESSION['user_id'];$conversationId=(int)($_POST['conversation_id']??0);$text=trim($_POST['message_text']??'');$imagePath=null;
if(!hash_equals($_SESSION['chat_csrf']??'',$_POST['csrf_token']??'')){http_response_code(403);echo json_encode(['success'=>false,'message'=>'คำขอไม่ถูกต้อง']);exit;}
if(mb_strlen($text)>5000){http_response_code(422);echo json_encode(['success'=>false,'message'=>'กรุณาพิมพ์ข้อความไม่เกิน 5,000 ตัวอักษร']);exit;}
if(isset($_FILES['chat_image'])&&$_FILES['chat_image']['error']!==UPLOAD_ERR_NO_FILE){
 $file=$_FILES['chat_image'];
 if($file['error']!==UPLOAD_ERR_OK||$file['size']>8*1024*1024){http_response_code(422);echo json_encode(['success'=>false,'message'=>'รูปภาพต้องมีขนาดไม่เกิน 8 MB']);exit;}
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
 if(!isset($extensions[$mime])){http_response_code(422);echo json_encode(['success'=>false,'message'=>'รองรับเฉพาะไฟล์ JPG, PNG, WEBP และ GIF']);exit;}
 $uploadDir=__DIR__.'/../uploads/chat';if(!is_dir($uploadDir)&&!mkdir($uploadDir,0755,true)){http_response_code(500);echo json_encode(['success'=>false,'message'=>'ไม่สามารถเตรียมพื้นที่จัดเก็บรูปภาพได้']);exit;}
 $fileName='chat_'.$userId.'_'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];
 if(!move_uploaded_file($file['tmp_name'],$uploadDir.'/'.$fileName)){http_response_code(500);echo json_encode(['success'=>false,'message'=>'อัปโหลดรูปภาพไม่สำเร็จ']);exit;}
 $imagePath='uploads/chat/'.$fileName;
}
if($text===''&&!$imagePath){http_response_code(422);echo json_encode(['success'=>false,'message'=>'กรุณาพิมพ์ข้อความหรือเลือกรูปภาพ']);exit;}
$stmt=$pdo->prepare("SELECT * FROM chat_conversations WHERE conversation_id=? AND (customer_user_id=? OR (seller_type='user' AND seller_id=?))");
$stmt->execute([$conversationId,$userId,$userId]);$conversation=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$conversation){if($imagePath){$uploaded=__DIR__.'/../'.ltrim($imagePath,'/');if(is_file($uploaded))unlink($uploaded);}http_response_code(403);echo json_encode(['success'=>false,'message'=>'ไม่สามารถส่งข้อความในห้องนี้ได้']);exit;}
$pdo->beginTransaction();
try{
 $pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text,image_path) VALUES(?,?,?,?)')->execute([$conversationId,$userId,$text,$imagePath]);$messageId=(int)$pdo->lastInsertId();
 $pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([$conversationId]);
 if($conversation['seller_type']==='user'){$recipients=[(int)$conversation['customer_user_id']===$userId?(int)$conversation['seller_id']:(int)$conversation['customer_user_id']];}
 else{$table=$conversation['seller_type']==='publisher'?'publisher_staff':'shop_staff';$column=$conversation['seller_type']==='publisher'?'publisher_id':'shop_id';$staff=$pdo->prepare("SELECT user_id FROM $table WHERE $column=?");$staff->execute([$conversation['seller_id']]);$recipients=$staff->fetchAll(PDO::FETCH_COLUMN);}
 $noticeText=$text!==''?mb_strimwidth($text,0,120,'…'):'ส่งรูปภาพ';
 $senderStmt=$pdo->prepare("SELECT COALESCE(NULLIF(display_name,''),email,'สมาชิกวนวรรณ') FROM users WHERE user_id=?");$senderStmt->execute([$userId]);$senderName=(string)$senderStmt->fetchColumn();
 foreach(array_unique(array_map('intval',$recipients)) as $recipient)wanawanChatNotify($pdo,$recipient,$conversationId,$senderName,$noticeText);
 $pdo->commit();
 echo json_encode(['success'=>true,'message'=>['id'=>$messageId,'text'=>$text,'image'=>$imagePath?'/pages/'.$imagePath:null,'media_type'=>'image','mine'=>true,'is_read'=>false,'time'=>date('H:i'),'date_key'=>date('Y-m-d'),'date_label'=>date('d/m/Y')]],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($imagePath){$uploaded=__DIR__.'/../'.ltrim($imagePath,'/');if(is_file($uploaded))unlink($uploaded);}http_response_code(500);echo json_encode(['success'=>false,'message'=>'ส่งข้อความไม่สำเร็จ กรุณาลองใหม่']);}
