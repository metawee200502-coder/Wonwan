<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

$searchAction = <<<HTML
    } elseif (\$action === 'submit_qc_report') {
        \$request_id = (int)\$_POST['request_id'];
        \$side = \$_POST['side'];
        \$item_owner_id = (int)\$_POST['item_owner_id'];
        \$is_match = (int)\$_POST['is_match'];
        \$admin_notes = \$_POST['admin_notes'];
        
        \$qc_result = (\$is_match === 1) ? 'passed' : 'failed';
        
        \$photo_urls = [];
        if (!empty(\$_FILES['qc_photos']['name'][0])) {
            \$upload_dir = __DIR__ . '/../uploads/qc/';
            if (!is_dir(\$upload_dir)) mkdir(\$upload_dir, 0777, true);
            foreach (\$_FILES['qc_photos']['tmp_name'] as \$key => \$tmp_name) {
                if (\$_FILES['qc_photos']['error'][\$key] == 0) {
                    \$ext = pathinfo(\$_FILES['qc_photos']['name'][\$key], PATHINFO_EXTENSION);
                    \$filename = uniqid('qc_') . '.' . \$ext;
                    move_uploaded_file(\$tmp_name, \$upload_dir . \$filename);
                    \$photo_urls[] = '/uploads/qc/' . \$filename;
                }
            }
        }
        \$photo_json = json_encode(\$photo_urls, JSON_UNESCAPED_UNICODE);
        
        \$pdo->beginTransaction();
        
        \$stmt = \$pdo->prepare("INSERT INTO admin_qc_reports (request_id, item_owner_id, is_match, admin_notes, photo_urls) VALUES (?, ?, ?, ?, ?)");
        \$stmt->execute([\$request_id, \$item_owner_id, \$is_match, \$admin_notes, \$photo_json]);
        
        if (\$side === 'requester') {
            \$stmt = \$pdo->prepare("UPDATE exchange_requests SET admin_qc_requester_status = ? WHERE request_id = ?");
        } else {
            \$stmt = \$pdo->prepare("UPDATE exchange_requests SET admin_qc_offer_status = ? WHERE request_id = ?");
        }
        \$stmt->execute([\$qc_result, \$request_id]);
        
        \$reqInfo = \$pdo->query("SELECT r.exchange_id, r.requester_id, e.owner_id FROM exchange_requests r JOIN exchanges e ON r.exchange_id = e.exchange_id WHERE r.request_id = \$request_id")->fetch();
        \$conversation_id = 0;
        if (\$reqInfo) {
            \$conv_stmt = \$pdo->prepare("SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id = ? AND ((customer_user_id = ? AND seller_id = ?) OR (customer_user_id = ? AND seller_id = ?))");
            \$conv_stmt->execute([\$reqInfo['exchange_id'], \$reqInfo['requester_id'], \$reqInfo['owner_id'], \$reqInfo['owner_id'], \$reqInfo['requester_id']]);
            \$conv = \$conv_stmt->fetch();
            if (\$conv) {
                \$conversation_id = \$conv['conversation_id'];
            }
        }
        
        if (\$conversation_id) {
            \$admin_id = \$_SESSION['user_id'];
            \$msgContent = "รายงานการตรวจสอบสภาพสินค้าจากส่วนกลาง (" . (\$side === 'requester' ? "ผู้ขอแลก" : "ผู้โพสต์") . ")\\n";
            \$msgContent .= "สถานะ: " . (\$is_match ? "ตรงปก/ผ่าน" : "ไม่ตรงปก/มีปัญหา") . "\\n";
            \$msgContent .= "หมายเหตุ: " . \$admin_notes;
            
            \$msg_stmt = \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, metadata, created_at) VALUES (?, ?, ?, 'admin_qc_report', ?, NOW())");
            \$meta = json_encode([
                'side' => \$side,
                'item_owner_id' => \$item_owner_id,
                'is_match' => \$is_match,
                'photos' => \$photo_urls,
                'request_id' => \$request_id
            ], JSON_UNESCAPED_UNICODE);
            \$msg_stmt->execute([\$conversation_id, \$admin_id, \$msgContent, \$meta]);
        }
        
        \$pdo->commit();
        
        \$sideLabel = (\$side === 'requester') ? 'ผู้ขอแลก' : 'ผู้ลงประกาศ';
        \$resultLabel = (\$qc_result === 'passed') ? 'ผ่าน' : 'ไม่ผ่าน';
        logAdminAction(\$pdo, \$_SESSION['email'] ?? 'unknown', 'PHYSICAL_QC', (string)\$request_id, "บันทึกผลตรวจสภาพหนังสือของ {\$sideLabel} ว่า {\$resultLabel} พร้อมแนบรายงาน QC");
        
        \$_SESSION['flash_msg'] = "บันทึกรายงาน QC และส่งเข้าแชทเรียบร้อยแล้ว";
    } elseif (\$action === 'admin_ship') {
HTML;

$replaceAction = <<<HTML
    } elseif (\$action === 'submit_qc_report_both') {
        \$request_id = (int)\$_POST['request_id'];
        \$req_id = (int)\$_POST['requester_id'];
        \$off_id = (int)\$_POST['offer_id'];
        
        \$req_is_match = (int)\$_POST['req_is_match'];
        \$req_notes = \$_POST['req_notes'];
        \$req_qc_result = (\$req_is_match === 1) ? 'passed' : 'failed';
        
        \$off_is_match = (int)\$_POST['off_is_match'];
        \$off_notes = \$_POST['off_notes'];
        \$off_qc_result = (\$off_is_match === 1) ? 'passed' : 'failed';
        
        \$upload_dir = __DIR__ . '/../uploads/qc/';
        if (!is_dir(\$upload_dir)) mkdir(\$upload_dir, 0777, true);
        
        \$req_photo_urls = [];
        if (!empty(\$_FILES['req_photos']['name'][0])) {
            foreach (\$_FILES['req_photos']['tmp_name'] as \$key => \$tmp_name) {
                if (\$_FILES['req_photos']['error'][\$key] == 0) {
                    \$ext = pathinfo(\$_FILES['req_photos']['name'][\$key], PATHINFO_EXTENSION);
                    \$filename = uniqid('qc_req_') . '.' . \$ext;
                    move_uploaded_file(\$tmp_name, \$upload_dir . \$filename);
                    \$req_photo_urls[] = '/uploads/qc/' . \$filename;
                }
            }
        }
        \$req_photo_json = json_encode(\$req_photo_urls, JSON_UNESCAPED_UNICODE);
        
        \$off_photo_urls = [];
        if (!empty(\$_FILES['off_photos']['name'][0])) {
            foreach (\$_FILES['off_photos']['tmp_name'] as \$key => \$tmp_name) {
                if (\$_FILES['off_photos']['error'][\$key] == 0) {
                    \$ext = pathinfo(\$_FILES['off_photos']['name'][\$key], PATHINFO_EXTENSION);
                    \$filename = uniqid('qc_off_') . '.' . \$ext;
                    move_uploaded_file(\$tmp_name, \$upload_dir . \$filename);
                    \$off_photo_urls[] = '/uploads/qc/' . \$filename;
                }
            }
        }
        \$off_photo_json = json_encode(\$off_photo_urls, JSON_UNESCAPED_UNICODE);
        
        \$pdo->beginTransaction();
        
        \$stmt_insert = \$pdo->prepare("INSERT INTO admin_qc_reports (request_id, item_owner_id, is_match, admin_notes, photo_urls) VALUES (?, ?, ?, ?, ?)");
        \$stmt_insert->execute([\$request_id, \$req_id, \$req_is_match, \$req_notes, \$req_photo_json]);
        \$stmt_insert->execute([\$request_id, \$off_id, \$off_is_match, \$off_notes, \$off_photo_json]);
        
        \$stmt_update = \$pdo->prepare("UPDATE exchange_requests SET admin_qc_requester_status = ?, admin_qc_offer_status = ? WHERE request_id = ?");
        \$stmt_update->execute([\$req_qc_result, \$off_qc_result, \$request_id]);
        
        \$reqInfo = \$pdo->query("SELECT r.exchange_id, r.requester_id, e.owner_id FROM exchange_requests r JOIN exchanges e ON r.exchange_id = e.exchange_id WHERE r.request_id = \$request_id")->fetch();
        \$conversation_id = 0;
        if (\$reqInfo) {
            \$conv_stmt = \$pdo->prepare("SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id = ? AND ((customer_user_id = ? AND seller_id = ?) OR (customer_user_id = ? AND seller_id = ?))");
            \$conv_stmt->execute([\$reqInfo['exchange_id'], \$reqInfo['requester_id'], \$reqInfo['owner_id'], \$reqInfo['owner_id'], \$reqInfo['requester_id']]);
            \$conv = \$conv_stmt->fetch();
            if (\$conv) {
                \$conversation_id = \$conv['conversation_id'];
            }
        }
        
        if (\$conversation_id) {
            \$admin_id = \$_SESSION['user_id'];
            \$msg_stmt = \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, metadata, created_at) VALUES (?, ?, ?, 'admin_qc_report', ?, NOW())");
            
            // Requester's book (sent to Offerer)
            \$msgContentReq = "รายงานการตรวจสอบสภาพสินค้าจากส่วนกลาง (ผู้ขอแลก)\\nสถานะ: " . (\$req_is_match ? "ตรงปก/ผ่าน" : "ไม่ตรงปก/มีปัญหา") . "\\nหมายเหตุ: " . \$req_notes;
            \$metaReq = json_encode([
                'side' => 'requester',
                'item_owner_id' => \$req_id,
                'is_match' => \$req_is_match,
                'photos' => \$req_photo_urls,
                'request_id' => \$request_id
            ], JSON_UNESCAPED_UNICODE);
            \$msg_stmt->execute([\$conversation_id, \$admin_id, \$msgContentReq, \$metaReq]);
            
            // Offerer's book (sent to Requester)
            \$msgContentOff = "รายงานการตรวจสอบสภาพสินค้าจากส่วนกลาง (ผู้ลงประกาศ)\\nสถานะ: " . (\$off_is_match ? "ตรงปก/ผ่าน" : "ไม่ตรงปก/มีปัญหา") . "\\nหมายเหตุ: " . \$off_notes;
            \$metaOff = json_encode([
                'side' => 'offer',
                'item_owner_id' => \$off_id,
                'is_match' => \$off_is_match,
                'photos' => \$off_photo_urls,
                'request_id' => \$request_id
            ], JSON_UNESCAPED_UNICODE);
            \$msg_stmt->execute([\$conversation_id, \$admin_id, \$msgContentOff, \$metaOff]);
        }
        
        \$pdo->commit();
        
        logAdminAction(\$pdo, \$_SESSION['email'] ?? 'unknown', 'PHYSICAL_QC', (string)\$request_id, "บันทึกผลตรวจสภาพหนังสือของทั้ง 2 ฝ่ายเรียบร้อย");
        
        \$_SESSION['flash_msg'] = "บันทึกรายงาน QC และส่งเข้าแชทเรียบร้อยแล้ว";
    } elseif (\$action === 'admin_ship') {
HTML;

$content = str_replace($searchAction, $replaceAction, $content);
file_put_contents($file, $content);
echo "QC Unified Action patched.\n";
