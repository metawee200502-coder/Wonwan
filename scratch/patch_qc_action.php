<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

$searchAction = <<<HTML
    } elseif (\$action === 'physical_qc') {
        \$request_id = (int)\$_POST['request_id'];
        \$side = \$_POST['side']; // 'requester' or 'offer'
        \$qc_result = \$_POST['qc_result']; // 'passed' or 'failed'
        
        if (\$side === 'requester') {
            \$stmt = \$pdo->prepare("UPDATE exchange_requests SET admin_qc_requester_status = ? WHERE request_id = ?");
        } else {
            \$stmt = \$pdo->prepare("UPDATE exchange_requests SET admin_qc_offer_status = ? WHERE request_id = ?");
        }
        \$stmt->execute([\$qc_result, \$request_id]);
        
        \$sideLabel = (\$side === 'requester') ? 'ผู้ขอแลก' : 'ผู้ลงประกาศ';
        \$resultLabel = (\$qc_result === 'passed') ? 'ผ่าน' : 'ไม่ผ่าน';
        logAdminAction(\$pdo, \$_SESSION['email'] ?? 'unknown', 'PHYSICAL_QC', (string)\$request_id, "บันทึกผลตรวจสภาพหนังสือของ {\$sideLabel} ว่า {\$resultLabel} สำหรับรายการแลกเปลี่ยนคำขอที่ {\$request_id}");
        
        \$_SESSION['flash_msg'] = "บันทึกผลการตรวจสภาพหนังสือเรียบร้อยแล้ว";
    } elseif (\$action === 'admin_ship') {
HTML;

$replaceAction = <<<HTML
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
        
        \$conv_stmt = \$pdo->prepare("SELECT conversation_id FROM conversations WHERE request_id = ?");
        \$conv_stmt->execute([\$request_id]);
        \$conv = \$conv_stmt->fetch();
        if (\$conv) {
            \$conversation_id = \$conv['conversation_id'];
            \$msgContent = "รายงานการตรวจสอบสภาพสินค้าจากส่วนกลาง (" . (\$side === 'requester' ? "ผู้ขอแลก" : "ผู้โพสต์") . ")\\n";
            \$msgContent .= "สถานะ: " . (\$is_match ? "ตรงปก/ผ่าน" : "ไม่ตรงปก/มีปัญหา") . "\\n";
            \$msgContent .= "หมายเหตุ: " . \$admin_notes;
            
            \$msg_stmt = \$pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message, msg_type, metadata, created_at) VALUES (?, 0, ?, 'admin_qc_report', ?, NOW())");
            \$meta = json_encode([
                'side' => \$side,
                'item_owner_id' => \$item_owner_id,
                'is_match' => \$is_match,
                'photos' => \$photo_urls
            ], JSON_UNESCAPED_UNICODE);
            \$msg_stmt->execute([\$conversation_id, \$msgContent, \$meta]);
        }
        
        \$pdo->commit();
        
        \$sideLabel = (\$side === 'requester') ? 'ผู้ขอแลก' : 'ผู้ลงประกาศ';
        \$resultLabel = (\$qc_result === 'passed') ? 'ผ่าน' : 'ไม่ผ่าน';
        logAdminAction(\$pdo, \$_SESSION['email'] ?? 'unknown', 'PHYSICAL_QC', (string)\$request_id, "บันทึกผลตรวจสภาพหนังสือของ {\$sideLabel} ว่า {\$resultLabel} พร้อมแนบรายงาน QC");
        
        \$_SESSION['flash_msg'] = "บันทึกรายงาน QC และส่งเข้าแชทเรียบร้อยแล้ว";
    } elseif (\$action === 'admin_ship') {
HTML;

$content = str_replace($searchAction, $replaceAction, $content);

file_put_contents($file, $content);
echo "Action patched.\n";
