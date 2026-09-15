<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // ... (Keep other actions like create if needed, but the system already creates exchange_requests elsewhere)
            
        case 'accept_condition': 
            // In reality, this endpoint might be called via AJAX. For now, logic is in trade_condition_form.php directly.
            // Keeping this for reference.
            echo json_encode(['success' => true, 'message' => 'Use trade_condition_form.php directly for now.']);
            break;
            
        case 'dispute': // POST /api/trades.php?action=dispute
            if ($method !== 'POST') throw new Exception('Invalid method');
            
            $requestId = (int)($_POST['request_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            
            // Assume we can determine against_user based on the request info (who is the other person)
            $reqStmt = $pdo->prepare("
                SELECT r.requester_id, e.owner_id as target_owner_id 
                FROM exchange_requests r
                JOIN exchanges e ON r.exchange_id = e.exchange_id
                WHERE r.request_id = ?
            ");
            $reqStmt->execute([$requestId]);
            $req = $reqStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$req) throw new Exception("Request not found");
            
            $againstUser = ($req['requester_id'] == $userId) ? $req['target_owner_id'] : $req['requester_id'];
            
            // Insert into trade_disputes
            $stmt = $pdo->prepare("INSERT INTO trade_disputes (request_id, raised_by, against_user, unboxing_video_url, actual_item_photos, reason) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$requestId, $userId, $againstUser, 'dummy/video.mp4', json_encode(['dummy/photo.jpg']), $reason]);
            
            // Update request status
            // Note: exchange_requests doesn't have a 'disputed' status natively (it has 'pending','accepted','completed','rejected','cancelled'). 
            // We might just leave it 'accepted' and let admin resolve, or add 'disputed' to the ENUM if needed later.
            
            echo json_encode(['success' => true, 'message' => 'Dispute opened']);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
