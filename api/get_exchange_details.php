<?php
session_start();
require __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$userId = (int)$_SESSION['user_id'];
$requestId = (int)($_GET['request_id'] ?? 0);

if (!$requestId) {
    echo json_encode(['success' => false, 'error' => 'Missing request ID']);
    exit;
}

// 1. Fetch Exchange Request
$sql = "SELECT er.*, 
        target.title as target_title, target.owner_id,
        offer.title as offer_title,
        requester.display_name as requester_name,
        owner.display_name as owner_name
        FROM exchange_requests er 
        JOIN exchanges target ON target.exchange_id = er.exchange_id
        JOIN users requester ON requester.user_id = er.requester_id
        JOIN users owner ON owner.user_id = target.owner_id
        LEFT JOIN exchanges offer ON offer.exchange_id = er.offered_exchange_id
        WHERE er.request_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$requestId]);
$req = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$req || ($req['requester_id'] != $userId && $req['owner_id'] != $userId)) {
    echo json_encode(['success' => false, 'error' => 'Not found or no permission']);
    exit;
}

// Check if user is requester or owner
$isRequester = ($req['requester_id'] == $userId);
$partnerName = $isRequester ? $req['owner_name'] : $req['requester_name'];
$myBook = $isRequester ? $req['offer_title'] : $req['target_title'];
$partnerBook = $isRequester ? $req['target_title'] : $req['offer_title'];

// 2. Fetch Financial Data (Orders)
$orderNumber = "EXC-FEE-" . $requestId . "-" . $userId;
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

$financials = null;
if ($order) {
    $financials = [
        'subtotal' => (float)$order['subtotal'],
        'fee' => (float)($order['gp_amount'] ?? 0),
        'payment_status' => $order['payment_status'],
        'refund_status' => null,
        'refund_amount' => null,
        'refund_date' => null,
        'order_date' => date('d M Y H:i', strtotime($order['created_at']))
    ];
    
    // Fetch Refund from wallet_transactions
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE order_id = ? AND type = 'REFUND' LIMIT 1");
    $stmt->execute([$order['order_id']]);
    $refund = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($refund) {
        $financials['refund_status'] = $refund['status'];
        $financials['refund_amount'] = (float)$refund['amount'];
        $financials['refund_date'] = date('d M Y H:i', strtotime($refund['created_at']));
    }
}

// 3. Logistics
$logistics = [];
if ($req['trade_option'] === 'admin_qc') {
    $logistics['my_tracking'] = $isRequester ? $req['requester_tracking_to_admin'] : $req['offer_tracking_to_admin'];
    $logistics['admin_tracking'] = $isRequester ? $req['admin_tracking_to_requester'] : $req['admin_tracking_to_offer'];
} else if ($req['trade_option'] === 'direct_escrow') {
    // For direct_escrow, they send to each other.
    $logistics['my_tracking'] = $isRequester ? $req['requester_tracking_to_admin'] : $req['offer_tracking_to_admin'];
    $logistics['partner_tracking'] = $isRequester ? $req['offer_tracking_to_admin'] : $req['requester_tracking_to_admin'];
}

$logistics['meetup_date'] = $req['meetup_date'] ?? null;
$logistics['meetup_time'] = $req['meetup_time'] ?? null;
$logistics['meetup_location'] = $req['meetup_location'] ?? null;

$data = [
    'success' => true,
    'raw_request_id' => $requestId,
    'request_id' => formatExchangeOrderId($requestId),
    'trade_option' => $req['trade_option'],
    'status' => $req['status'],
    'created_at' => date('d M Y H:i', strtotime($req['created_at'])),
    'updated_at' => date('d M Y H:i', strtotime($req['updated_at'])),
    'is_requester' => $isRequester,
    'partner_name' => $partnerName,
    'my_book' => $myBook,
    'partner_book' => $partnerBook,
    'logistics' => $logistics,
    'financials' => $financials,
    'has_qc' => ($req['trade_option'] === 'admin_qc' || $req['trade_option'] === 'direct_escrow')
];

echo json_encode($data);

