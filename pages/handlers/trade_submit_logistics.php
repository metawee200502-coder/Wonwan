<?php
session_start();
if (empty($_SESSION['user_id'])) {
    die("Unauthorized");
}
require_once __DIR__ . '/../../config/config.php';

$userId = (int)$_SESSION['user_id'];
$requestId = (int)($_POST['request_id'] ?? 0);
$action = $_POST['action_type'] ?? $_POST['action'] ?? '';

if (!$requestId || !$action) {
    die("ข้อมูลไม่ครบถ้วน");
}

// Verify user is part of the request
$q = $pdo->prepare('SELECT er.*, e.owner_id FROM exchange_requests er JOIN exchanges e ON er.exchange_id = e.exchange_id WHERE er.request_id = ?');
$q->execute([$requestId]);
$req = $q->fetch(PDO::FETCH_ASSOC);

if (!$req || !in_array($userId, [(int)$req['requester_id'], (int)$req['owner_id']], true)) {
    die("ไม่มีสิทธิ์เข้าถึง");
}

$isRequester = ((int)$req['requester_id'] === $userId);
$uploadDir = __DIR__ . '/../../uploads/payments';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    if ($action === 'upload_slip') {
        if (empty($_FILES['slip_image']['name'])) {
            throw new Exception('กรุณาอัปโหลดสลิปโอนเงิน');
        }
        
        $ext = strtolower(pathinfo($_FILES['slip_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            throw new Exception('รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, WEBP)');
        }
        
        $filename = "slip_{$requestId}_{$userId}_" . time() . ".$ext";
        $dest = "$uploadDir/$filename";
        if (!move_uploaded_file($_FILES['slip_image']['tmp_name'], $dest)) {
            throw new Exception('อัปโหลดล้มเหลว');
        }
        
        $slipUrl = "/uploads/payments/$filename";
        $colUrl = $isRequester ? 'requester_slip_url' : 'offer_slip_url';
        $colStatus = $isRequester ? 'requester_slip_status' : 'offer_slip_status';
        
        // Update DB
        $pdo->prepare("UPDATE exchange_requests SET {$colUrl} = ?, {$colStatus} = 'pending' WHERE request_id = ?")->execute([$slipUrl, $requestId]);
        
        // Notify in chat
        $q = $pdo->prepare('SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id=? AND customer_user_id=? AND seller_id=? LIMIT 1');
        $q->execute([$req['exchange_id'], $req['requester_id'], $req['owner_id'] ?? 0]);
        $convId = $q->fetchColumn();
        if ($convId) {
            $pdo->prepare('INSERT INTO chat_messages(conversation_id, sender_user_id, message_text) VALUES(?,?,?)')
                ->execute([$convId, $userId, '[QC_PAYMENT_SUBMITTED:' . $userId . ']']);
        }
        
        header("Location: /pages/trade_qc_logistics_form.php?request_id=" . $requestId);
        exit;
        
    } elseif ($action === 'submit_tracking') {
        $address = trim($_POST['user_address'] ?? '');
        $carrier = trim($_POST['carrier'] ?? '');
        $trackingNum = trim($_POST['tracking_number'] ?? '');
        
        if (empty($address) || empty($carrier) || empty($trackingNum)) {
            throw new Exception('กรุณากรอกข้อมูลให้ครบถ้วน');
        }
        
        $tracking = $carrier . ' : ' . $trackingNum;
        
        $colAddr = $isRequester ? 'requester_address' : 'offer_address';
        $colTrack = $isRequester ? 'requester_tracking_to_admin' : 'offer_tracking_to_admin';
        
        $pdo->prepare("UPDATE exchange_requests SET {$colAddr} = ?, {$colTrack} = ? WHERE request_id = ?")->execute([$address, $tracking, $requestId]);
        
        // Notify in chat
        $q = $pdo->prepare('SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id=? AND customer_user_id=? AND seller_id=? LIMIT 1');
        $q->execute([$req['exchange_id'], $req['requester_id'], $req['owner_id'] ?? 0]);
        $convId = $q->fetchColumn();
        if ($convId) {
            $pdo->prepare('INSERT INTO chat_messages(conversation_id, sender_user_id, message_text) VALUES(?,?,?)')
                ->execute([$convId, $userId, '[QC_TRACKING_SUBMITTED:' . $userId . ']']);
        }
        
        header("Location: /pages/trade_qc_logistics_form.php?request_id=" . $requestId);
        exit;
    } elseif ($action === 'receive_item') {
        // Find the partner's condition form because they shipped it and we are receiving it
        $partnerId = $isRequester ? $req['owner_id'] : $req['requester_id'];
        $stmt = $pdo->prepare("UPDATE trade_condition_forms SET is_received = 1, received_at = NOW() WHERE request_id = ? AND owner_id = ?");
        $stmt->execute([$requestId, $partnerId]);
        
        // Notify in chat
        $q = $pdo->prepare('SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id=? AND customer_user_id=? AND seller_id=? LIMIT 1');
        $q->execute([$req['exchange_id'], $req['requester_id'], $req['owner_id'] ?? 0]);
        $convId = $q->fetchColumn();
        if ($convId) {
            $pdo->prepare('INSERT INTO chat_messages(conversation_id, sender_user_id, message_text) VALUES(?,?,?)')
                ->execute([$convId, $userId, '[P2P_ITEM_RECEIVED:' . $userId . ']']);
        }
        
        // Check if both received, then mark exchange_requests status as completed
        $check = $pdo->prepare("SELECT COUNT(*) FROM trade_condition_forms WHERE request_id = ? AND is_received = 1");
        $check->execute([$requestId]);
        if ($check->fetchColumn() == 2) {
            $pdo->prepare("UPDATE exchange_requests SET status = 'completed', updated_at = NOW() WHERE request_id = ?")->execute([$requestId]);
            $pdo->prepare("UPDATE exchanges SET status = 'completed' WHERE exchange_id IN (?, ?)")->execute([$req['exchange_id'], $req['offered_exchange_id']]);
        }
        
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    } elseif ($action === 'set_deposit') {
        $amount = (float)($_POST['amount'] ?? 0);
        
        if ($amount < 0) {
            throw new Exception('จำนวนเงินไม่ถูกต้อง');
        }
        
        $pdo->prepare("UPDATE trade_condition_forms SET required_deposit_amount = ? WHERE request_id = ? AND owner_id = ?")
            ->execute([$amount, $requestId, $userId]);
            
        // Notify in chat
        $q = $pdo->prepare('SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id=? AND customer_user_id=? AND seller_id=? LIMIT 1');
        $q->execute([$req['exchange_id'], $req['requester_id'], $req['owner_id'] ?? 0]);
        $convId = $q->fetchColumn();
        if ($convId) {
            $pdo->prepare('INSERT INTO chat_messages(conversation_id, sender_user_id, message_text) VALUES(?,?,?)')
                ->execute([$convId, $userId, '[P2P_DEPOSIT_PROPOSED:' . $userId . ':' . $amount . ']']);
        }
        
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    } else {
        throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
