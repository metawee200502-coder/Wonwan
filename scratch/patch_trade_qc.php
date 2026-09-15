<?php
$file = __DIR__ . '/../pages/trade_qc_logistics_form.php';
$content = file_get_contents($file);

$search1 = <<<HTML
\$requestId = (int)(\$_GET['request_id'] ?? 0);
HTML;

$replace1 = <<<HTML
\$requestId = (int)(\$_GET['request_id'] ?? \$_POST['request_id'] ?? 0);
HTML;

$content = str_replace($search1, $replace1, $content);

$search2 = <<<HTML
        if (\$decision === 'rejected') {
            \$pdo->query("UPDATE exchange_requests SET status = 'cancelled' WHERE request_id = \$reqId");
            \$msg = "❌ ลูกค้ายืนยันปฏิเสธการรับสินค้า รายการถูกยกเลิกแล้ว (ส่วนกลางจะดำเนินการส่งหนังสือคืนให้เจ้าของเดิม)";
            \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, created_at) VALUES (?, ?, ?, 'system_alert', NOW())")
                ->execute([\$conversation_id, \$_SESSION['user_id'], \$msg]);
        } else {
HTML;

$replace2 = <<<HTML
        if (\$decision === 'rejected') {
            \$pdo->query("UPDATE exchange_requests SET status = 'cancelled' WHERE request_id = \$reqId");
            \$msg = "❌ ลูกค้ายืนยันปฏิเสธการรับสินค้า รายการถูกยกเลิกแล้ว (ส่วนกลางจะดำเนินการส่งหนังสือคืนให้เจ้าของเดิม)";
            \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, created_at) VALUES (?, ?, ?, 'system_alert', NOW())")
                ->execute([\$conversation_id, \$_SESSION['user_id'], \$msg]);
            \$_SESSION['flash_qc_msg'] = "ปฏิเสธการรับสินค้าและยกเลิกรายการเรียบร้อยแล้ว";
            \$_SESSION['flash_qc_status'] = "rejected";
        } else {
HTML;

$content = str_replace($search2, $replace2, $content);

$search3 = <<<HTML
            \$q = \$pdo->query("SELECT requester_qc_acceptance_status, offer_qc_acceptance_status FROM exchange_requests WHERE request_id = \$reqId")->fetch();
            if (\$q['requester_qc_acceptance_status'] === 'accepted' && \$q['offer_qc_acceptance_status'] === 'accepted') {
                \$msg = "✅ ผู้ใช้งานทั้งสองฝ่ายยืนยันยอมรับสภาพสินค้าเรียบร้อยแล้ว! แอดมินจะดำเนินการจัดส่งพัสดุในลำดับถัดไป";
                \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, created_at) VALUES (?, ?, ?, 'system_alert', NOW())")
                    ->execute([\$conversation_id, \$_SESSION['user_id'], \$msg]);
            }
        }
    }
    header("Location: /pages/chat.php?conversation=" . \$conversation_id);
HTML;

$replace3 = <<<HTML
            \$q = \$pdo->query("SELECT requester_qc_acceptance_status, offer_qc_acceptance_status FROM exchange_requests WHERE request_id = \$reqId")->fetch();
            if (\$q['requester_qc_acceptance_status'] === 'accepted' && \$q['offer_qc_acceptance_status'] === 'accepted') {
                \$msg = "✅ ผู้ใช้งานทั้งสองฝ่ายยืนยันยอมรับสภาพสินค้าเรียบร้อยแล้ว! แอดมินจะดำเนินการจัดส่งพัสดุในลำดับถัดไป";
                \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, created_at) VALUES (?, ?, ?, 'system_alert', NOW())")
                    ->execute([\$conversation_id, \$_SESSION['user_id'], \$msg]);
            }
            \$_SESSION['flash_qc_msg'] = "ยอมรับสภาพสินค้าเรียบร้อย! กรุณารอรับหนังสือจากส่วนกลางในลำดับถัดไปครับ";
            \$_SESSION['flash_qc_status'] = "accepted";
        }
    }
    header("Location: /pages/chat.php?conversation=" . \$conversation_id);
HTML;

$content = str_replace($search3, $replace3, $content);

file_put_contents($file, $content);
echo "trade_qc_logistics_form.php patched\n";
