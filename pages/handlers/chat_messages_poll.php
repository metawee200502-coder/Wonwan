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
$messages=[];
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $message){
    $timestamp=strtotime($message['created_at']);
    $text = $message['message_text'];
    $isSystem = false;
    $sysContent = null;
    $isMine = ((int)$message['sender_user_id']===$userId);
    
    $exchangeRequestId = '';
    $er = null;
    if($conversation['seller_type']==='user' && $conversation['referenced_exchange_id']) {
        $q=$pdo->prepare("SELECT * FROM exchange_requests WHERE exchange_id=? AND requester_id IN (?,?) AND status NOT IN ('cancelled','rejected') ORDER BY FIELD(status,'accepted','pending','completed') ASC,request_id DESC LIMIT 1");
        $q->execute([(int)$conversation['referenced_exchange_id'],(int)$conversation['customer_user_id'],(int)$conversation['seller_id']]);
        $er = $q->fetch(PDO::FETCH_ASSOC);
        if ($er) {
            $exchangeRequestId = $er['request_id'];
        }
    }

    if (preg_match('/\[PROPOSE_FULL_TRADE:([^\]]+)\]/i', $text, $matches)) {
        $isSystem = true;
        $parts = explode(':', trim($matches[1]));
        $opt = $parts[0] ?? '';
        $days = $parts[1] ?? '';
        $mDate = $parts[1] ?? '';
        $mTime = $parts[2] ?? '';
        $mLoc = $parts[3] ?? '';
        
        $alreadySet = false;
        if ($opt === 'meetup') {
            $alreadySet = ($er && $er['trade_option'] === $opt && $er['meetup_date'] === $mDate && $er['meetup_time'] === $mTime && $er['meetup_location'] === $mLoc);
        } else {
            $alreadySet = ($er && $er['trade_option'] === $opt && ($days === '' || $er['shipping_deadline_days'] == $days));
        }
        
        $optNamesList = ['direct_escrow'=>'ส่งตรงให้กัน (P2P Escrow)', 'meetup'=>'นัดรับ (Meet-up)', 'admin_qc'=>'ส่งผ่านส่วนกลาง (Admin QC)'];
        $displayName = $optNamesList[$opt] ?? $opt;
        
        $senderStmt=$pdo->prepare("SELECT COALESCE(NULLIF(display_name,''),email,'ผู้ใช้'), profile_image FROM users WHERE user_id=?");
        $senderStmt->execute([$message['sender_user_id']]);
        $senderInfo = $senderStmt->fetch(PDO::FETCH_NUM);
        $senderName = $senderInfo[0] ?? 'ผู้ใช้';
        $senderImg = $senderInfo[1] ?? '/assets/image/default-avatar.png';
        
        $sysContent = '<div style="background:linear-gradient(135deg, #fdf5f7 0%, #f7e6eb 100%);border:1px solid #f2dbe4;padding:14px 16px;border-radius:14px;color:#8b5266;font-size:13px;max-width:300px;box-shadow:0 4px 12px rgba(169,85,117,0.06);font-family:inherit;">' .
            '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">' .
            '<img src="'.htmlspecialchars((string)$senderImg).'" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 4px rgba(169,85,117,0.1)">' .
            '<div style="line-height:1.2"><strong style="font-size:14px;color:#a95575;font-weight:600;">'.htmlspecialchars((string)$senderName).'</strong><br><small style="color:#b87d90;font-size:11px;">เสนอเงื่อนไขการแลกเปลี่ยน</small></div>' .
            '</div>' .
            '<div style="background:rgba(255,255,255,0.85);padding:10px;border-radius:8px;border:1px solid #f2dbe4;margin-bottom:12px;font-size:13px;color:#8b5266;">' .
            '<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>รูปแบบ:</span> <strong style="color:#a95575;">'.$displayName.'</strong></div>' .
            ($opt === 'meetup' ? 
                '<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>วันที่:</span> <strong style="color:#a95575;">'.htmlspecialchars($mDate).'</strong></div>' .
                '<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>เวลา:</span> <strong style="color:#a95575;">'.htmlspecialchars($mTime).' น.</strong></div>' .
                '<div style="display:flex;justify-content:space-between;"><span>สถานที่:</span> <strong style="color:#a95575;">'.htmlspecialchars($mLoc).'</strong></div>'
            : 
                ($days ? '<div style="display:flex;justify-content:space-between;"><span>ระยะเวลา:</span> <strong style="color:#a95575;">ภายใน '.$days.' วัน</strong></div>' : '') 
            ) .
            '</div>' .
            ($alreadySet ? 
                ($isMine ? 
                    '<div style="text-align:center;font-size:13px;color:#059669;background:#ecfdf5;padding:8px;border-radius:6px;font-weight:600;border:1px solid #a7f3d0;font-family:inherit;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:text-bottom;margin-right:2px;"><polyline points="20 6 9 17 4 12"></polyline></svg> อีกฝ่ายตอบรับแล้ว</div>' 
                : 
                    '<div style="text-align:center;font-size:13px;color:#059669;background:#ecfdf5;padding:8px;border-radius:6px;font-weight:600;border:1px solid #a7f3d0;font-family:inherit;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:text-bottom;margin-right:2px;"><polyline points="20 6 9 17 4 12"></polyline></svg> คุณยอมรับแล้ว</div>'
                )
            : 
                ($isMine ? '<div style="text-align:center;font-size:12px;color:#a95575;background:rgba(169,85,117,0.1);padding:6px;border-radius:6px;font-family:inherit;">⏳ รอการตอบรับ...</div>' : 
                '<form method="post" style="display:flex;gap:6px;">'.
                '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['chat_csrf'] ?? '').'">'.
                '<input type="hidden" name="trade_option" value="'.htmlspecialchars($opt).'">'.
                '<input type="hidden" name="deadline_days" value="'.htmlspecialchars($days).'">'.
                '<input type="hidden" name="meetup_date" value="'.htmlspecialchars($mDate).'">'.
                '<input type="hidden" name="meetup_time" value="'.htmlspecialchars($mTime).'">'.
                '<input type="hidden" name="meetup_location" value="'.htmlspecialchars($mLoc).'">'.
                '<input type="hidden" name="request_id" value="'.htmlspecialchars($exchangeRequestId).'">'.
                '<button style="flex:1;font-size:13px;background:#fff;border:1px solid #d3948b;color:#d3948b;padding:8px;border-radius:6px;font-weight:600;cursor:pointer;transition:transform 0.2s;font-family:inherit;" name="reject_full_trade" onmouseover="this.style.transform=\'scale(1.02)\'" onmouseout="this.style.transform=\'scale(1)\'">ปฏิเสธ</button>'.
                '<button style="flex:2;font-size:13px;background:linear-gradient(135deg, #d18aa7, #b66f68);border:0;color:#fff;padding:8px;border-radius:6px;font-weight:600;cursor:pointer;box-shadow:0 3px 8px rgba(209,138,167,0.3);transition:transform 0.2s;font-family:inherit;" name="accept_full_trade" onmouseover="this.style.transform=\'scale(1.02)\'" onmouseout="this.style.transform=\'scale(1)\'">ตกลง</button>'.
                '</form>')
            ).
            '</div>';
    } elseif (preg_match('/\[ACCEPTED_FULL_TRADE:([^\]]+)\]/i', $text, $matches)) {
        $isSystem = true;
        $parts = explode(':', trim($matches[1]));
        $opt = $parts[0] ?? '';
        $days = $parts[1] ?? '';
        $mDate = $parts[1] ?? '';
        $mTime = $parts[2] ?? '';
        $mLoc = $parts[3] ?? '';
        $optNamesList = ['direct_escrow'=>'ส่งตรงให้กัน (P2P Escrow)', 'meetup'=>'นัดรับ (Meet-up)', 'admin_qc'=>'ส่งผ่านส่วนกลาง (Admin QC)'];
        $displayName = $optNamesList[$opt] ?? $opt;
        
        $detailStr = '';
        if ($opt === 'meetup') {
            $detailStr = "($mDate $mTime น. @ $mLoc)";
        } else if ($days) {
            $detailStr = "(ภายใน $days วัน)";
        }
        
        $sysContent = '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="display:inline-flex;align-items:center;vertical-align:middle;margin-right:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></span> <strong>ตกลงเงื่อนไขแล้ว:</strong> <span style="background:#fff;padding:3px 6px;border-radius:6px;margin-left:4px;color:#15803d;font-size:12px;">'.$displayName.' '.$detailStr.'</span>'.
            '</div>';
    } elseif (strpos($text, '[REJECTED_FULL_TRADE]') !== false) {
        $isSystem = true;
        $sysContent = '<div style="background:linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);border:1px solid #fecaca;padding:10px 14px;border-radius:10px;color:#991b1b;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">❌</span> <strong>ปฏิเสธข้อเสนอแล้ว</strong> กรุณายื่นข้อเสนอใหม่'.
            '</div>';
    } elseif (preg_match('/\[CONDITION_FORM_SUBMITTED:(\d+)\]/', $text, $matches)) {
        $isSystem = true;
        $formOwnerId = (int)$matches[1];
        
        // Fetch form details
        $formStmt = $pdo->prepare("SELECT * FROM trade_condition_forms WHERE request_id=? AND owner_id=?");
        $formStmt->execute([$exchangeRequestId, $formOwnerId]);
        $form = $formStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($form) {
            $senderStmt=$pdo->prepare("SELECT COALESCE(NULLIF(display_name,''),email,'ผู้ใช้'), profile_image FROM users WHERE user_id=?");
            $senderStmt->execute([$formOwnerId]);
            $senderInfo = $senderStmt->fetch(PDO::FETCH_NUM);
            $senderName = $senderInfo[0] ?? 'ผู้ใช้';
            $senderImg = $senderInfo[1] ?? '/assets/image/default-avatar.png';
            
            $isMyForm = ($formOwnerId === $userId);
            
            $sysContent = '<div style="background:linear-gradient(135deg, #fffaf0 0%, #fff3db 100%);border:1px solid #fde68a;padding:14px;border-radius:14px;color:#92400e;font-size:13px;max-width:320px;box-shadow:0 4px 12px rgba(217,119,6,0.06);font-family:inherit;">' .
                '<div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">' .
                '<img src="'.htmlspecialchars((string)$senderImg).'" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 2px 4px rgba(217,119,6,0.1)">' .
                '<div style="line-height:1.2"><strong style="font-size:14px;color:#b45309;font-weight:600;">'.htmlspecialchars((string)$senderName).'</strong><br><small style="color:#d97706;font-size:11px;">ส่งหลักฐานยืนยันสภาพสินค้าแล้ว</small></div>' .
                '</div>' .
                
                '<div style="display:flex;gap:4px;margin-bottom:10px;">' .
                '<img src="'.htmlspecialchars($form['front_cover_url']).'" style="width:32%;height:80px;object-fit:cover;border-radius:6px;border:1px solid #fde68a;cursor:pointer;" onclick="if(window.openConditionModal) { window.openConditionModal(\'' . htmlspecialchars($exchangeRequestId) . '\'); } else { window.open(this.src); }">' .
                '<img src="'.htmlspecialchars($form['back_cover_url']).'" style="width:32%;height:80px;object-fit:cover;border-radius:6px;border:1px solid #fde68a;cursor:pointer;" onclick="if(window.openConditionModal) { window.openConditionModal(\'' . htmlspecialchars($exchangeRequestId) . '\'); } else { window.open(this.src); }">' .
                '<img src="'.htmlspecialchars($form['spine_url']).'" style="width:32%;height:80px;object-fit:cover;border-radius:6px;border:1px solid #fde68a;cursor:pointer;" onclick="if(window.openConditionModal) { window.openConditionModal(\'' . htmlspecialchars($exchangeRequestId) . '\'); } else { window.open(this.src); }">' .
                '</div>' .
                
                ($form['defects_description'] ? '<div style="background:rgba(255,255,255,0.7);padding:8px;border-radius:6px;margin-bottom:8px;font-size:12px;"><strong>ตำหนิ:</strong> '.htmlspecialchars($form['defects_description']).'</div>' : '') .
                
                ($isMyForm ? 
                    '<div style="text-align:center;font-size:12px;color:#d97706;background:rgba(217,119,6,0.1);padding:6px;border-radius:6px;margin-top:10px;">⏳ รออีกฝ่ายตรวจสอบและยอมรับ...</div>' 
                : 
                    ($form['is_accepted_by_partner'] ? 
                        '<div style="text-align:center;font-size:13px;color:#059669;background:#ecfdf5;padding:8px;border-radius:6px;font-weight:600;border:1px solid #a7f3d0;margin-top:10px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:text-bottom;margin-right:2px;"><polyline points="20 6 9 17 4 12"></polyline></svg> คุณยอมรับสภาพสินค้านี้แล้ว</div>'
                    :
                        '<form method="post" style="margin-top:12px;display:flex;gap:8px;">' .
                        '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['chat_csrf'] ?? '').'">' .
                        '<input type="hidden" name="accept_condition_form_id" value="'.htmlspecialchars($form['form_id']).'">' .
                        '<input type="hidden" name="accept_condition_owner_id" value="'.htmlspecialchars($formOwnerId).'">' .
                        '<input type="hidden" name="request_id" value="'.htmlspecialchars($exchangeRequestId).'">' .
                        '<button type="submit" name="reject_condition_chat" style="flex:1;font-size:13px;background:linear-gradient(135deg, #ef4444, #b91c1c);border:0;color:#fff;padding:10px;border-radius:8px;font-weight:600;cursor:pointer;box-shadow:0 4px 10px rgba(239,68,68,0.3);transition:transform 0.2s;font-family:inherit;" onmouseover="this.style.transform=\'scale(1.02)\'" onmouseout="this.style.transform=\'scale(1)\'">❌ ปฏิเสธ</button>' .
                        '<button type="submit" name="accept_condition_chat" style="flex:2;font-size:13px;background:linear-gradient(135deg, #f59e0b, #d97706);border:0;color:#fff;padding:10px;border-radius:8px;font-weight:600;cursor:pointer;box-shadow:0 4px 10px rgba(217,119,6,0.3);transition:transform 0.2s;font-family:inherit;" onmouseover="this.style.transform=\'scale(1.02)\'" onmouseout="this.style.transform=\'scale(1)\'">🔍 ยอมรับสภาพ</button>' .
                        '</form>'
                    )
                ) .
                '</div>';
        }
    } elseif (preg_match('/\[CONDITION_FORM_REJECTED:(\d+)\]/', $text, $matches)) {
        $isSystem = true;
        $sysContent = '<div style="background:linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);border:1px solid #fecaca;padding:10px 14px;border-radius:10px;color:#991b1b;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">❌</span> <strong>ปฏิเสธสภาพสินค้า</strong> ฟอร์มถูกยกเลิกแล้ว กรุณากรอกใหม่'.
            '</div>';
    } elseif (preg_match('/\[CONDITION_FORM_ACCEPTED:(\d+)\]/', $text, $matches)) {
        $isSystem = true;
        $sysContent = '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="display:inline-flex;align-items:center;vertical-align:middle;margin-right:4px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg></span> <strong>ยอมรับสภาพสินค้าเรียบร้อยแล้ว</strong>'.
            '</div>';
    } elseif (preg_match('/\[QC_PAYMENT_SUBMITTED:(\d+)\]/', $text, $matches)) {
        $isSystem = true;
        $sysContent = '<div style="background:linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);border:1px solid #fde68a;padding:10px 14px;border-radius:10px;color:#92400e;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">💸</span> <strong>แจ้งชำระเงิน QC</strong> ส่งสลิปโอนเงินเรียบร้อยแล้ว'.
            '</div>';
    } elseif (preg_match('/\[QC_ADDRESS_SUBMITTED:(\d+)\]/', $text, $matches)) {
        $isSystem = true;
        $sysContent = '<div style="background:linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);border:1px solid #bfdbfe;padding:10px 14px;border-radius:10px;color:#1e3a8a;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">🏠</span> <strong>ที่อยู่จัดส่ง</strong> บันทึกที่อยู่จัดส่งเพื่อรับคืนหนังสือจาก QC เรียบร้อยแล้ว'.
            '</div>';
    } elseif (preg_match('/\[QC_TRACKING_SUBMITTED:(\d+)\]/', $text, $matches)) {
        $isSystem = true;
        $sysContent = '<div style="background:linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%);border:1px solid #d8b4fe;padding:10px 14px;border-radius:10px;color:#6b21a8;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">📦</span> <strong>แจ้งเลขพัสดุ QC</strong> จัดส่งหนังสือให้ส่วนกลางและแจ้งเลขพัสดุเรียบร้อยแล้ว'.
            '</div>';
    } elseif (preg_match('/\[P2P_ITEM_RECEIVED:(\d+)\]/', $text, $matches)) {
        $isSystem = true;
        $sysContent = '<div style="background:linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);border:1px solid #bbf7d0;padding:10px 14px;border-radius:10px;color:#166534;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">🎉</span> <strong>ยืนยันการรับสินค้า</strong> ได้รับสินค้าเรียบร้อยแล้ว'.
            '</div>';
    } elseif (preg_match('/\[P2P_TRACKING_SUBMITTED:(.*?):(.*?)\]/', $text, $matches)) {
        $isSystem = true;
        $sysContent = '<div style="background:linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);border:1px solid #bfdbfe;padding:10px 14px;border-radius:10px;color:#1e40af;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">📦</span> <strong>จัดส่งสินค้าแล้ว</strong><br>ขนส่ง: <strong>'.htmlspecialchars($matches[1]).'</strong> เลขพัสดุ: <strong>'.htmlspecialchars($matches[2]).'</strong>'.
            '</div>';
    } elseif (preg_match('/\[P2P_TRACKING_SUBMITTED:(\d+)\]/', $text, $matches)) {
        $isSystem = true;
        $sysContent = '<div style="background:linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);border:1px solid #bfdbfe;padding:10px 14px;border-radius:10px;color:#1e40af;font-size:13px;box-shadow:0 2px 6px rgba(0,0,0,0.03);display:inline-block;font-family:inherit;">'.
            '<span style="font-size:14px;margin-right:4px;">📦</span> <strong>แจ้งเลขพัสดุ</strong> จัดส่งสินค้าและแจ้งเลขพัสดุเรียบร้อยแล้ว'.
            '</div>';
    }

    $messages[]=['id'=>(int)$message['message_id'],'text'=>$message['message_text'],'image'=>$message['image_path']?'/pages/'.ltrim($message['image_path'],'/'):null,'media_type'=>$message['media_type']?:'image','mine'=>$isMine,'is_read'=>(bool)$message['is_read'],'time'=>date('H:i',$timestamp),'date_key'=>date('Y-m-d',$timestamp),'date_label'=>date('d/m/Y',$timestamp),'is_system'=>$isSystem,'html_content'=>$sysContent];
}
$read=$pdo->prepare('SELECT COALESCE(MAX(message_id),0) FROM chat_messages WHERE conversation_id=? AND sender_user_id=? AND is_read=1');$read->execute([$conversationId,$userId]);
echo json_encode(['success'=>true,'messages'=>$messages,'last_read_message_id'=>(int)$read->fetchColumn()],JSON_UNESCAPED_UNICODE);
