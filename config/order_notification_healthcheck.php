<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/order_notifications.php';
$order = $pdo->query('SELECT order_id,user_id,order_number FROM orders ORDER BY order_id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    fwrite(STDERR, "latest_order=missing\n");
    exit(1);
}
$user = $pdo->prepare("SELECT email,COALESCE(NULLIF(display_name,''),email) display_name,notify_preference,line_user_id FROM users WHERE user_id=?");
$user->execute([(int) $order['user_id']]);
$settings = $user->fetch(PDO::FETCH_ASSOC);
echo 'preference=' . ($settings['notify_preference'] ?? '') . PHP_EOL;
echo 'line_connected=' . (!empty($settings['line_user_id']) ? 'yes' : 'no') . PHP_EOL;
$details = wanawanOrderNotificationDetails($pdo, $order);
$emailSent = wanawanSendOrderEmail(
    (string) $settings['email'],
    (string) $settings['display_name'],
    (string) $order['order_number'],
    'ทดสอบการแจ้งเตือนคำสั่งซื้อ',
    'ระบบแจ้งเตือนของ Wonwan Web เชื่อมต่อเรียบร้อยแล้ว',
    $details
);
$lineSent = wanawanSendLineOrderMessage(
    (string) ($settings['line_user_id'] ?? ''),
    (string) $order['order_number'],
    'ทดสอบการแจ้งเตือนคำสั่งซื้อ',
    'ระบบแจ้งเตือนของ Wonwan Web เชื่อมต่อเรียบร้อยแล้ว',
    $details
);
echo 'email_sent=' . ($emailSent ? 'yes' : 'no') . PHP_EOL;
echo 'line_sent=' . ($lineSent ? 'yes' : 'no') . PHP_EOL;
exit($emailSent && $lineSent ? 0 : 1);
