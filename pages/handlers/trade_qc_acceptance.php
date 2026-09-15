<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../config/config.php';

$userId = (int)$_SESSION['user_id'];
$requestId = (int)($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'accept' or 'reject'
$reason = trim($_POST['reason'] ?? '');

if (!$requestId || !in_array($action, ['accept', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Check if user is part of this exchange request
$stmt = $pdo->prepare("SELECT * FROM exchange_requests WHERE request_id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Request not found']);
    exit;
}

// Get the owner of the targeted exchange
$stmt = $pdo->prepare("SELECT owner_id FROM exchanges WHERE exchange_id = ?");
$stmt->execute([$request['exchange_id']]);
$targetOwner = $stmt->fetchColumn();

$isRequester = ($request['requester_id'] == $userId);
$isOfferOwner = ($targetOwner == $userId);

if (!$isRequester && !$isOfferOwner) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get conversation ID to send chat message
    $convStmt = $pdo->prepare("SELECT conversation_id FROM chat_conversations WHERE referenced_exchange_id = ? AND ((customer_user_id = ? AND seller_id = ?) OR (customer_user_id = ? AND seller_id = ?)) LIMIT 1");
    $convStmt->execute([
        $request['exchange_id'],
        $request['requester_id'], $targetOwner,
        $targetOwner, $request['requester_id']
    ]);
    $conversationId = $convStmt->fetchColumn();

    if ($action === 'accept') {
        if ($isRequester) {
            $pdo->prepare("UPDATE exchange_requests SET requester_qc_acceptance_status = 'accepted' WHERE request_id = ?")->execute([$requestId]);
        } else {
            $pdo->prepare("UPDATE exchange_requests SET offer_qc_acceptance_status = 'accepted' WHERE request_id = ?")->execute([$requestId]);
        }
        
        if ($conversationId) {
            $sysMsg = "✅ [ผู้ใช้ยอมรับผล QC แล้ว]";
            $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type) VALUES (?, ?, ?, 'system')")
                ->execute([$conversationId, $userId, $sysMsg]);
        }
    } else if ($action === 'reject') {
        if ($isRequester) {
            $pdo->prepare("UPDATE exchange_requests SET requester_qc_acceptance_status = 'rejected', status = 'cancelled' WHERE request_id = ?")->execute([$requestId]);
        } else {
            $pdo->prepare("UPDATE exchange_requests SET offer_qc_acceptance_status = 'rejected', status = 'cancelled' WHERE request_id = ?")->execute([$requestId]);
        }
        
        if ($conversationId) {
            $sysMsg = "❌ [ผู้ใช้ปฏิเสธผล QC และยกเลิกการแลกเปลี่ยน]\nเหตุผล: " . $reason;
            $pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_user_id, message_text, msg_type) VALUES (?, ?, ?, 'system')")
                ->execute([$conversationId, $userId, $sysMsg]);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("QC Acceptance Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
}
