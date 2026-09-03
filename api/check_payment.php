<?php
session_start();
header('Content-Type: application/json');
if (empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/config.php';
$userId = (int)$_SESSION['user_id'];

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$orderIds = isset($_GET['order_ids']) ? explode(',', $_GET['order_ids']) : [];

if ($orderId > 0) {
    // Check single order
    $stmt = $pdo->prepare('SELECT payment_status FROM orders WHERE order_id = ? AND user_id = ?');
    $stmt->execute([$orderId, $userId]);
    $status = $stmt->fetchColumn();
    if ($status === false) {
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    echo json_encode(['status' => $status]);
    exit;
} else if (!empty($orderIds)) {
    // Check batch orders (comma separated)
    $orderIds = array_map('intval', $orderIds);
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $pdo->prepare("SELECT payment_status FROM orders WHERE order_id IN ($placeholders) AND user_id = ?");
    $params = array_merge($orderIds, [$userId]);
    $stmt->execute($params);
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If all orders in batch are 'paid' (or anything other than pending)
    $isDone = true;
    foreach ($statuses as $s) {
        if ($s === 'pending') {
            $isDone = false;
            break;
        }
    }
    
    echo json_encode(['status' => $isDone ? 'paid' : 'pending']);
    exit;
}

echo json_encode(['error' => 'Invalid parameters']);
