<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/../../config/config.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!hash_equals((string)($_SESSION['checkout_recommendation_csrf'] ?? ''), (string)($input['csrf_token'] ?? ''))) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'หน้านี้หมดอายุ กรุณารีเฟรชแล้วลองใหม่']);
    exit;
}

$bookId = (int)($input['book_id'] ?? 0);
$userId = (int)$_SESSION['user_id'];
try {
    $book = $pdo->prepare("SELECT stock_quantity FROM books WHERE book_id=? AND status='available'");
    $book->execute([$bookId]);
    $stock = (int)$book->fetchColumn();
    if ($bookId < 1 || $stock < 1) throw new RuntimeException('สินค้านี้หมดหรือไม่พร้อมจำหน่ายแล้ว');

    $stmt = $pdo->prepare("INSERT INTO cart_items(user_id,book_id,quantity) VALUES(?,?,1)
        ON DUPLICATE KEY UPDATE quantity=LEAST(quantity+1,?)");
    $stmt->execute([$userId, $bookId, $stock]);
    $selected = array_values(array_unique(array_map('intval', $_SESSION['checkout_selected_ids'] ?? [])));
    if (!in_array($bookId, $selected, true)) $selected[] = $bookId;
    $_SESSION['checkout_selected_ids'] = $selected;
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
