<?php
$file = __DIR__ . '/../pages/trade_qc_logistics_form.php';
$content = file_get_contents($file);

$searchStr = <<<HTML
if (!\$requestId) {
    exit('Invalid request');
}
HTML;

$replaceStr = <<<HTML
if (!\$requestId) {
    exit('Invalid request');
}

if (\$_SERVER['REQUEST_METHOD'] === 'POST' && isset(\$_POST['action']) && \$_POST['action'] === 'user_qc_decision') {
    \$reqId = (int)\$_POST['request_id'];
    \$side = \$_POST['side'];
    \$decision = \$_POST['decision'];
    \$conversation_id = (int)\$_POST['conversation_id'];
    
    \$q = \$pdo->prepare('SELECT * FROM exchange_requests WHERE request_id = ?');
    \$q->execute([\$reqId]);
    \$req = \$q->fetch();
    
    if (\$req) {
        if (\$decision === 'rejected') {
            \$pdo->query("UPDATE exchange_requests SET status = 'cancelled' WHERE request_id = \$reqId");
            \$msg = "❌ ลูกค้ายืนยันปฏิเสธการรับสินค้า รายการถูกยกเลิกแล้ว (ส่วนกลางจะดำเนินการส่งหนังสือคืนให้เจ้าของเดิม)";
            \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, created_at) VALUES (?, ?, ?, 'system_alert', NOW())")
                ->execute([\$conversation_id, \$_SESSION['user_id'], \$msg]);
        } else {
            if (\$side === 'requester') {
                \$pdo->query("UPDATE exchange_requests SET requester_qc_acceptance_status = 'accepted' WHERE request_id = \$reqId");
            } else {
                \$pdo->query("UPDATE exchange_requests SET offer_qc_acceptance_status = 'accepted' WHERE request_id = \$reqId");
            }
            
            \$q = \$pdo->query("SELECT requester_qc_acceptance_status, offer_qc_acceptance_status FROM exchange_requests WHERE request_id = \$reqId")->fetch();
            if (\$q['requester_qc_acceptance_status'] === 'accepted' && \$q['offer_qc_acceptance_status'] === 'accepted') {
                \$msg = "✅ ผู้ใช้งานทั้งสองฝ่ายยืนยันยอมรับสภาพสินค้าเรียบร้อยแล้ว! แอดมินจะดำเนินการจัดส่งพัสดุในลำดับถัดไป";
                \$pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type, created_at) VALUES (?, ?, ?, 'system_alert', NOW())")
                    ->execute([\$conversation_id, \$_SESSION['user_id'], \$msg]);
            }
        }
    }
    header("Location: /pages/chat.php?conversation=" . \$conversation_id);
    exit;
}
HTML;

$content = str_replace($searchStr, $replaceStr, $content);
file_put_contents($file, $content);
echo "Handler added.\n";
