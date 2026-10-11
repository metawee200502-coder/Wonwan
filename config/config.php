<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/external_notifications.php';
if (!function_exists('wanawanSellerName')) {
    function wanawanSellerName(?string $name, string $type): string {
        $name = trim((string)$name);
        if ($name === '' || $type === 'user') return $name;
        $prefix = $type === 'publisher' ? 'สำนักพิมพ์' : ($type === 'shop' ? 'ร้านหนังสือ' : '');
        if ($prefix === '') return $name;
        if (mb_strpos($name, $prefix) === 0) $name = trim(mb_substr($name, mb_strlen($prefix)));
        return $prefix . ($name !== '' ? ' ' . $name : '');
    }
}
if (!function_exists('wanawanShippingFee')) {
    function wanawanShippingFee(int $quantity): float {
        return $quantity > 0 ? 45 : 0;
    }
}

if (!function_exists('wanawanSellerUserIds')) {
    function wanawanSellerUserIds(PDO $pdo,string $type,int $sellerId):array {
        if($type==='user')return[$sellerId];
        if(!in_array($type,['publisher','shop'],true))return[];
        $table=$type==='publisher'?'publisher_staff':'shop_staff';
        $column=$type==='publisher'?'publisher_id':'shop_id';
        $stmt=$pdo->prepare("SELECT user_id FROM $table WHERE $column=?");
        $stmt->execute([$sellerId]);
        return array_values(array_unique(array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN))));
    }
}

if (!function_exists('wanawanNotifyNewReview')) {
    function wanawanNotifyNewReview(PDO $pdo,int $reviewId,int $bookId,int $reviewerId):void {
        $stmt=$pdo->prepare("SELECT b.title,b.seller_type,b.seller_id,COALESCE(NULLIF(u.display_name,''),u.email,'ลูกค้า') reviewer_name FROM books b JOIN users u ON u.user_id=? WHERE b.book_id=?");
        $stmt->execute([$reviewerId,$bookId]);
        $review=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$review)return;
        foreach(wanawanSellerUserIds($pdo,$review['seller_type'],(int)$review['seller_id']) as $recipient){
            if($recipient!==$reviewerId) wanawanNotifyUser($pdo, $recipient, 'มีรีวิวใหม่จาก '.$review['reviewer_name'], 'ลูกค้ารีวิวสินค้า “'.$review['title'].'”', 'review', ['related_user_id' => $reviewerId, 'related_review_id' => $reviewId]);
        }
    }
}
if (!function_exists('wanawanChatNotify')) {
    function wanawanChatNotify(PDO $pdo,int $recipientId,int $conversationId,string $senderName,string $message): void {
        $active=$pdo->prepare('SELECT 1 FROM chat_presence WHERE user_id=? AND conversation_id=? AND last_seen_at>=DATE_SUB(NOW(),INTERVAL 8 SECOND)');
        $active->execute([$recipientId,$conversationId]);
        if($active->fetchColumn())return;
        $title='ข้อความใหม่จาก '.trim($senderName?:'คู่สนทนา');
        $existing=$pdo->prepare("SELECT notification_id FROM notifications WHERE user_id=? AND notification_type='chat' AND chat_conversation_id=? AND is_read=0 ORDER BY notification_id DESC LIMIT 1");
        $existing->execute([$recipientId,$conversationId]);$notificationId=(int)$existing->fetchColumn();
        if($notificationId){$pdo->prepare('UPDATE notifications SET title=?,message=?,created_at=NOW() WHERE notification_id=?')->execute([$title,$message,$notificationId]);return;}
        wanawanNotifyUser($pdo, $recipientId, $title, $message, 'chat', ['chat_conversation_id' => $conversationId]);
    }
}
$host = 'localhost';
$dbname = 'wanawan_db';
$db_user = 'root';
$db_pass = '';
 
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("เชื่อมต่อฐานข้อมูลไม่สำเร็จ: " . $e->getMessage());
}

// ระบบ PromptPay ส่วนกลาง
require_once __DIR__ . '/promptpay.php';
// คุณสามารถระบุเบอร์โทรศัพท์ (10 หลัก) หรือเลขบัตรประชาชน/นิติบุคคล (13 หลัก) ได้ที่นี่
define('WANAWAN_PROMPTPAY_ID', '0980254069'); // <--- เว้นที่ไว้สำหรับใส่เบอร์

// ตรวจคำสั่งซื้อหมดอายุทุกครั้งที่ระบบมีการใช้งาน เพื่อคืนสต็อกแม้ลูกค้าปิดหน้าชำระเงินไปแล้ว
try {
    require_once __DIR__ . '/order_reservations.php';
    wanawanExpirePendingOrders($pdo);
} catch (PDOException $e) {
    if (!in_array($e->getCode(), ['42S02','42S22'], true)) throw $e;
}

// ตรวจสอบโปรโมชั่นที่หมดอายุ หรือครบโควต้า เพื่อปิดการใช้งานและลบประกาศอัตโนมัติ
try {
    require_once __DIR__ . '/promotions.php';
    wanawanExpirePromotions($pdo);
} catch (PDOException $e) {
    if (!in_array($e->getCode(), ['42S02','42S22'], true)) throw $e;
}

// บัญชีสำนักพิมพ์และร้านค้าใช้งานเฉพาะระบบหลังบ้าน
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
    if (empty($_SESSION['last_active_update']) || time() - $_SESSION['last_active_update'] > 60) {
        try {
            $stmt = $pdo->prepare("SELECT is_banned, ban_until FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            if ($userCheck = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $is_banned = (bool)$userCheck['is_banned'];
                $ban_until = $userCheck['ban_until'] ? strtotime($userCheck['ban_until']) : null;
                
                if ($is_banned || ($ban_until && $ban_until > time())) {
                    session_destroy();
                    header('Location: /pages/login.php?error=banned');
                    exit;
                } else {
                    $pdo->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?")->execute([$_SESSION['user_id']]);
                    $_SESSION['last_active_update'] = time();
                }
            }
        } catch (Exception $e) {
            // Ignore error
        }
    }
}

// บัญชีสำนักพิมพ์และร้านค้าใช้งานเฉพาะระบบหลังบ้าน
if (session_status() === PHP_SESSION_ACTIVE
    && in_array($_SESSION['role'] ?? '', ['publisher_admin', 'shop_admin'], true)
) {
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $adminPages = [
        'dashboard.php', 'inventory.php', 'support.php', 'admin_orders.php',
        'admin_notifications.php', 'admin_chat.php', 'add_book.php', 'edit_book.php',
        'publisher_setup.php', 'shop_setup.php', 'admin_profile.php', 'partner_detail.php',
        'book_detail.php', 'seller_review_management.php', 'seller_reviews.php',
        'admin_promotions.php', 'seller_wallet.php', 'manage_seller_admins.php', 'seller_action_logs.php',
        'shop_return_address.php', 'admin_returns.php', 'dashboard_export.php',
    ];
    $currentPage = basename($scriptPath);
    $isBackendAction = str_contains($scriptPath, '/process/')
        || str_contains($scriptPath, '/pages/handlers/');

    if (!$isBackendAction && !in_array($currentPage, $adminPages, true)) {
        header('Location: /pages/dashboard.php');
        exit;
    }
}

// ==========================================
// ADMIN ACTION LOGGING
// ==========================================
if (!function_exists('logAdminAction')) {
    function logAdminAction(PDO $pdo, string $admin_email, string $action_type, string $target_id, string $details = ''): void {
        try {
            $stmt = $pdo->prepare("INSERT INTO admin_action_logs (admin_email, action_type, target_id, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin_email, $action_type, $target_id, $details]);
        } catch (PDOException $e) {
            error_log("Failed to log admin action: " . $e->getMessage());
        }
    }
}

if (!function_exists('logSellerAction')) {
    function logSellerAction(PDO $pdo, string $seller_type, int $seller_id, int $user_id, string $action_type, string $target_id, string $details = ''): void {
        try {
            $stmt = $pdo->prepare("INSERT INTO seller_action_logs (seller_type, seller_id, user_id, action_type, target_id, details) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$seller_type, $seller_id, $user_id, $action_type, $target_id, $details]);
        } catch (PDOException $e) {
            error_log("Failed to log seller action: " . $e->getMessage());
        }
    }
}


function formatExchangeOrderId($requestId) {
    if (!$requestId) return '';
    return '#EX-' . str_pad($requestId, 6, '0', STR_PAD_LEFT);
}

