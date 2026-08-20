<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/digital_twin.php';

function digitalTwinJson(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (empty($_SESSION['user_id'])) digitalTwinJson(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อนคุยกับ Digital Twin'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') digitalTwinJson(['ok' => false, 'message' => 'ไม่รองรับคำขอนี้'], 405);

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;
if (!hash_equals((string)($_SESSION['digital_twin_csrf'] ?? ''), (string)($input['csrf_token'] ?? ''))) {
    digitalTwinJson(['ok' => false, 'message' => 'หน้าสนทนาหมดอายุ กรุณาโหลดหน้าใหม่'], 419);
}

$type = ($input['source'] ?? 'book') === 'exchange' ? 'exchange' : 'book';
$itemId = filter_var($input['item_id'] ?? null, FILTER_VALIDATE_INT);
$isDiscover = !empty($input['discover']);
$item = $isDiscover
    ? ['item_id'=>0,'item_type'=>'book','title'=>'ผู้ช่วยค้นหาหนังสือ Wonwan','author'=>null,'category'=>null,'synopsis'=>'ช่วยค้นและเปรียบเทียบหนังสือจากคลัง Wonwan ตามความต้องการของลูกค้า','price'=>null]
    : ($itemId ? wanawanDigitalTwinItem($pdo, $type, (int)$itemId) : null);
if ($isDiscover) { $type = 'book'; $itemId = 0; }
if (!$item) digitalTwinJson(['ok' => false, 'message' => 'ไม่พบหนังสือเล่มนี้'], 404);

$userId = (int)$_SESSION['user_id'];
$conversationStmt = $pdo->prepare("INSERT INTO digital_twin_conversations(user_id,item_type,item_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE conversation_id=LAST_INSERT_ID(conversation_id),updated_at=NOW()");
$conversationStmt->execute([$userId, $type, (int)$itemId]);
$conversationId = (int)$pdo->lastInsertId();

if (($input['action'] ?? '') === 'clear') {
    $pdo->prepare('DELETE FROM digital_twin_messages WHERE conversation_id=?')->execute([$conversationId]);
    digitalTwinJson(['ok' => true]);
}

$message = trim((string)($input['message'] ?? ''));
if ($message === '') digitalTwinJson(['ok' => false, 'message' => 'กรุณาพิมพ์คำถาม'], 422);
if (mb_strlen($message) > 1000) digitalTwinJson(['ok' => false, 'message' => 'คำถามยาวเกินไป กรุณาใช้ไม่เกิน 1,000 ตัวอักษร'], 422);
if (isset($_SESSION['digital_twin_last_sent']) && microtime(true) - (float)$_SESSION['digital_twin_last_sent'] < 1.2) {
    digitalTwinJson(['ok' => false, 'message' => 'กรุณารอสักครู่ก่อนส่งคำถามถัดไป'], 429);
}
$_SESSION['digital_twin_last_sent'] = microtime(true);

$insert = $pdo->prepare('INSERT INTO digital_twin_messages(conversation_id,role,content) VALUES(?,\'user\',?)');
$insert->execute([$conversationId, $message]);
$userMessageId = (int)$pdo->lastInsertId();

try {
    $historyStmt = $pdo->prepare('SELECT role,content FROM digital_twin_messages WHERE conversation_id=? ORDER BY message_id DESC LIMIT 14');
    $historyStmt->execute([$conversationId]);
    $history = array_reverse($historyStmt->fetchAll(PDO::FETCH_ASSOC));
    $related = wanawanDigitalTwinRelatedBooks($pdo, $item, $message);
    $reply = wanawanGeminiReply(wanawanDigitalTwinConfig(), wanawanDigitalTwinPrompt($item, $related), $history);
    $pdo->prepare("INSERT INTO digital_twin_messages(conversation_id,role,content) VALUES(?,'assistant',?)")->execute([$conversationId, $reply]);
    digitalTwinJson(['ok' => true, 'reply' => $reply]);
} catch (Throwable $error) {
    $pdo->prepare('DELETE FROM digital_twin_messages WHERE message_id=? AND conversation_id=?')->execute([$userMessageId, $conversationId]);
    error_log('Digital Twin chat error: ' . $error->getMessage());
    digitalTwinJson(['ok' => false, 'message' => $error->getMessage()], 503);
}
