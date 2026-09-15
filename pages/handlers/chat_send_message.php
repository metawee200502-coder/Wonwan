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
 $senderStmt=$pdo->prepare("SELECT COALESCE(NULLIF(display_name,''),email,'สมาชิกวนวรรณ'), profile_image FROM users WHERE user_id=?");$senderStmt->execute([$userId]);
 $senderInfo=$senderStmt->fetch(PDO::FETCH_NUM);
 $senderName=(string)($senderInfo[0]??'');
 $senderImg=(string)($senderInfo[1]??'/assets/image/default-avatar.png');
 foreach(array_unique(array_map('intval',$recipients)) as $recipient)wanawanChatNotify($pdo,$recipient,$conversationId,$senderName,$noticeText);
 $pdo->commit();
 
 $isSystem = false;
 $sysContent = null;
 if (preg_match('/\[PROPOSE_FULL_TRADE:([^\]]+)\]/i', $text, $matches)) {
    $isSystem = true;
    $parts = explode(':', trim($matches[1]));
    $opt = $parts[0] ?? '';
    $days = $parts[1] ?? '';
    $optNamesList = ['direct_escrow'=>'ส่งตรงให้กัน (P2P Escrow)', 'meetup'=>'นัดรับ (Meet-up)', 'admin_qc'=>'ส่งผ่านส่วนกลาง (Admin QC)'];
    $displayName = $optNamesList[$opt] ?? $opt;
    $sysContent = '<div style="background:linear-gradient(135deg, #fdf5f7 0%, #f7e6eb 100%);border:1px solid #f2dbe4;padding:14px 16px;border-radius:14px;color:#8b5266;font-size:13px;max-width:300px;box-shadow:0 4px 12px rgba(169,85,117,0.06);font-family:inherit;">' .
        '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">' .
        '<img src="'.htmlspecialchars((string)$senderImg).'" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 4px rgba(169,85,117,0.1)">' .
        '<div style="line-height:1.2"><strong style="font-size:14px;color:#a95575;font-weight:600;">'.htmlspecialchars((string)$senderName).'</strong><br><small style="color:#b87d90;font-size:11px;">เสนอเงื่อนไขการแลกเปลี่ยน</small></div>' .
        '</div>' .
        '<div style="background:rgba(255,255,255,0.85);padding:10px;border-radius:8px;border:1px solid #f2dbe4;margin-bottom:12px;font-size:13px;color:#8b5266;">' .
        '<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>รูปแบบ:</span> <strong style="color:#a95575;">'.$displayName.'</strong></div>' .
        ($days ? '<div style="display:flex;justify-content:space-between;"><span>ระยะเวลา:</span> <strong style="color:#a95575;">ภายใน '.$days.' วัน</strong></div>' : '') .
        '</div>' .
        '<div style="text-align:center;font-size:12px;color:#a95575;background:rgba(169,85,117,0.1);padding:6px;border-radius:6px;font-family:inherit;">⏳ รอการตอบรับ...</div>' .
        '</div>';
 } elseif (preg_match('/\[ACCEPTED_FULL_TRADE:([^\]]+)\]/i', $text, $matches)) {
    $isSystem = true;
    $parts = explode(':', trim($matches[1]));
    $opt = $parts[0] ?? '';
    $days = $parts[1] ?? '';
    $optNamesList = ['direct_escrow'=>'ส่งตรงให้กัน (P2P Escrow)', 'meetup'=>'นัดรับ (Meet-up)', 'admin_qc'=>'ส่งผ่านส่วนกลาง (Admin QC)'];
    $displayName = $optNamesList[$opt] ?? $opt;
    $sysContent = '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
        '<span style="display:inline-flex;align-items:center;vertical-align:middle;margin-right:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></span> <strong>ตกลงเงื่อนไขแล้ว:</strong> <span style="background:#fff;padding:3px 6px;border-radius:6px;margin-left:4px;color:#15803d;font-size:12px;">'.$displayName.($days?' (ภายใน '.$days.' วัน)':'').'</span>'.
        '</div>';
 } elseif (strpos($text, '[REJECTED_FULL_TRADE]') !== false) {
    $isSystem = true;
    $sysContent = '<div style="background:linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);border:1px solid #fecaca;padding:10px 14px;border-radius:10px;color:#991b1b;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
        '<span style="font-size:14px;margin-right:4px;">❌</span> <strong>ปฏิเสธข้อเสนอแล้ว</strong> กรุณายื่นข้อเสนอใหม่'.
        '</div>';
 }

 echo json_encode(['success'=>true,'message'=>['id'=>$messageId,'text'=>$text,'image'=>$imagePath?'/pages/'.$imagePath:null,'media_type'=>'image','mine'=>true,'is_read'=>false,'time'=>date('H:i'),'date_key'=>date('Y-m-d'),'date_label'=>date('d/m/Y'),'is_system'=>$isSystem,'html_content'=>$sysContent]],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($imagePath){$uploaded=__DIR__.'/../'.ltrim($imagePath,'/');if(is_file($uploaded))unlink($uploaded);}http_response_code(500);echo json_encode(['success'=>false,'message'=>'ส่งข้อความไม่สำเร็จ กรุณาลองใหม่']);}
