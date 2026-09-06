<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';

function followResponse(bool $success, string $message, array $extra = [], int $status = 200): never {
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') followResponse(false, 'ไม่รองรับคำขอนี้', [], 405);
$viewerId = (int)($_SESSION['user_id'] ?? 0);
if (!$viewerId) followResponse(false, 'กรุณาเข้าสู่ระบบก่อน', ['login_url' => '/pages/login.php'], 401);
if (!hash_equals($_SESSION['follow_csrf'] ?? '', $_POST['csrf_token'] ?? '')) followResponse(false, 'คำขอไม่ถูกต้อง', [], 403);

$type = $_POST['target_type'] ?? '';
$targetId = (int)($_POST['target_id'] ?? 0);
$targets = [
    'publisher' => ['table' => 'publishers', 'id' => 'publisher_id'],
    'shop' => ['table' => 'shops', 'id' => 'shop_id'],
    'user' => ['table' => 'users', 'id' => 'user_id'],
];
if (!isset($targets[$type]) || $targetId < 1) followResponse(false, 'ไม่พบโปรไฟล์ที่ต้องการติดตาม', [], 422);
if ($type === 'user' && $targetId === $viewerId) followResponse(false, 'ไม่สามารถติดตามโปรไฟล์ของตัวเองได้', [], 422);

$target = $targets[$type];
$exists = $pdo->prepare("SELECT 1 FROM {$target['table']} WHERE {$target['id']}=?");
$exists->execute([$targetId]);
if (!$exists->fetchColumn()) followResponse(false, 'ไม่พบโปรไฟล์ที่ต้องการติดตาม', [], 404);

$pdo->beginTransaction();
try {
    $check = $pdo->prepare('SELECT 1 FROM profile_follows WHERE follower_user_id=? AND target_type=? AND target_id=? FOR UPDATE');
    $check->execute([$viewerId, $type, $targetId]);
    $following = (bool)$check->fetchColumn();
    if ($following) {
        $pdo->prepare('DELETE FROM profile_follows WHERE follower_user_id=? AND target_type=? AND target_id=?')->execute([$viewerId, $type, $targetId]);
        $following = false;
    } else {
        $pdo->prepare('INSERT INTO profile_follows(follower_user_id,target_type,target_id) VALUES(?,?,?)')->execute([$viewerId, $type, $targetId]);
        $following = true;
        $followerStmt=$pdo->prepare("SELECT COALESCE(NULLIF(display_name,''),email) FROM users WHERE user_id=?");
        $followerStmt->execute([$viewerId]);
        $followerName=(string)$followerStmt->fetchColumn();
        if($type==='user'){$recipients=[$targetId];}
        else{
            $staffTable=$type==='publisher'?'publisher_staff':'shop_staff';
            $targetColumn=$type==='publisher'?'publisher_id':'shop_id';
            $recipientStmt=$pdo->prepare("SELECT user_id FROM $staffTable WHERE $targetColumn=?");
            $recipientStmt->execute([$targetId]);
            $recipients=array_map('intval',$recipientStmt->fetchAll(PDO::FETCH_COLUMN));
        }
        foreach(array_unique($recipients) as $recipient){
            if($recipient!==$viewerId) wanawanNotifyUser($pdo, $recipient, 'มีผู้ติดตามใหม่', $followerName.' เริ่มติดตามโปรไฟล์ของคุณ', 'follow', ['related_user_id' => $viewerId]);
        }
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM profile_follows WHERE target_type=? AND target_id=?');
    $count->execute([$type, $targetId]);
    $followers = (int)$count->fetchColumn();
    $pdo->prepare("UPDATE {$target['table']} SET followers_count=? WHERE {$target['id']}=?")->execute([$followers, $targetId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    followResponse(false, 'ไม่สามารถบันทึกการติดตามได้ กรุณาลองใหม่', [], 500);
}

followResponse(true, $following ? 'ติดตามแล้ว' : 'เลิกติดตามแล้ว', [
    'following' => $following,
    'followers_count' => $followers,
]);
