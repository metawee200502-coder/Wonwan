<?php
declare(strict_types=1);
require_once __DIR__ . '/password_reset.php';

function wanawanOrderUrl(): string
{
    return passwordResetBaseUrl() . '/pages/orders.php';
}

function wanawanOrderNotificationDetails(PDO $pdo, array $order): array
{
    $orderId = (int) ($order['order_id'] ?? 0);
    if ($orderId <= 0 && !empty($order['order_number'])) {
        $find = $pdo->prepare('SELECT order_id FROM orders WHERE order_number=?');
        $find->execute([(string) $order['order_number']]);
        $orderId = (int) $find->fetchColumn();
    }
    if ($orderId <= 0) return [];
    $summary = $pdo->prepare('SELECT subtotal,shipping_fee,discount_amount,total_amount,shipping_courier,tracking_number FROM orders WHERE order_id=?');
    $summary->execute([$orderId]);
    $details = $summary->fetch(PDO::FETCH_ASSOC) ?: [];
    $items = $pdo->prepare('SELECT b.title,oi.quantity,oi.price_at_purchase FROM order_items oi JOIN books b ON b.book_id=oi.book_id WHERE oi.order_id=? ORDER BY oi.order_item_id');
    $items->execute([$orderId]);
    $details['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
    try {
        $promotions = $pdo->prepare('SELECT promotion_name,discount_amount FROM order_applied_promotions WHERE order_id=? AND discount_amount>0 ORDER BY order_promotion_id');
        $promotions->execute([$orderId]);
        $details['promotions'] = $promotions->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $error) {
        $details['promotions'] = [];
    }
    return $details;
}

function wanawanSendOrderEmail(string $email, string $name, string $orderNumber, string $title, string $message, array $details = []): bool
{
    $file = __DIR__ . '/mail.local.php';
    if (!is_file($file)) return false;
    $config = require $file;
    $password = preg_replace('/\s+/', '', (string) ($config['app_password'] ?? ''));
    if (!is_array($config) || $password === '' || str_contains($password, 'APP_PASSWORD')) return false;
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeNumber = htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $url = htmlspecialchars(wanawanOrderUrl(), ENT_QUOTES, 'UTF-8');
    $itemRows = '';
    foreach ($details['items'] ?? [] as $item) {
        $itemTitle = htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8');
        $quantity = (int) $item['quantity'];
        $lineTotal = (float) $item['price_at_purchase'] * $quantity;
        $itemRows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #eee">' . $itemTitle . '<br><span style="font-size:12px;color:#8b756d">จำนวน ' . $quantity . ' × ฿' . number_format((float) $item['price_at_purchase'], 2) . '</span></td><td align="right" style="padding:10px 0;border-bottom:1px solid #eee;white-space:nowrap">฿' . number_format($lineTotal, 2) . '</td></tr>';
    }
    $promotionRows = '';
    foreach ($details['promotions'] ?? [] as $promotion) {
        $promotionRows .= '<tr><td style="padding:5px 0;color:#c45f7f">' . htmlspecialchars((string) $promotion['promotion_name'], ENT_QUOTES, 'UTF-8') . '</td><td align="right" style="padding:5px 0;color:#c45f7f;white-space:nowrap">− ฿' . number_format((float) $promotion['discount_amount'], 2) . '</td></tr>';
    }
    $summaryRows = '';
    if ($details) {
        $summaryRows = '<tr><td style="padding-top:13px">ยอดสินค้า</td><td align="right" style="padding-top:13px">฿' . number_format((float) ($details['subtotal'] ?? 0), 2) . '</td></tr>'
            . '<tr><td style="padding:5px 0">ค่าจัดส่ง</td><td align="right" style="padding:5px 0">฿' . number_format((float) ($details['shipping_fee'] ?? 0), 2) . '</td></tr>'
            . $promotionRows;
        if ((float) ($details['discount_amount'] ?? 0) > 0 && !$promotionRows) {
            $summaryRows .= '<tr><td style="padding:5px 0;color:#c45f7f">ส่วนลดทั้งหมด</td><td align="right" style="padding:5px 0;color:#c45f7f">− ฿' . number_format((float) $details['discount_amount'], 2) . '</td></tr>';
        }
        $summaryRows .= '<tr><td style="padding-top:12px;border-top:1px solid #ddd;color:#005b66;font-size:18px;font-weight:bold">ยอดรวมสุทธิ</td><td align="right" style="padding-top:12px;border-top:1px solid #ddd;color:#005b66;font-size:18px;font-weight:bold">฿' . number_format((float) ($details['total_amount'] ?? 0), 2) . '</td></tr>';
    }
    $html = '<!doctype html><html lang="th"><body style="margin:0;background:#fff7f2;font-family:Arial,Tahoma,sans-serif;color:#3c302c">'
        . '<table role="presentation" width="100%"><tr><td align="center" style="padding:36px 16px"><table role="presentation" width="100%" style="max-width:580px;background:#fff;border:1px solid #eadbd4;border-radius:16px">'
        . '<tr><td style="padding:34px"><div style="color:#c56e88;font-weight:bold;margin-bottom:12px">WONWAN WEB</div>'
        . '<h1 style="margin:0 0 8px;color:#005b66;font-size:25px">' . $safeTitle . '</h1>'
        . '<p style="margin:0 0 22px;color:#8b756d">คำสั่งซื้อ #' . $safeNumber . '</p>'
        . '<p style="line-height:1.7">สวัสดี ' . $safeName . '</p><p style="line-height:1.7">' . $safeMessage . '</p>'
        . ($itemRows !== '' ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px"><tr><td colspan="2" style="padding-bottom:7px;color:#005b66;font-weight:bold">รายการสินค้า</td></tr>' . $itemRows . $summaryRows . '</table>' : '')
        . '<p style="margin:28px 0"><a href="' . $url . '" style="display:inline-block;padding:14px 24px;border-radius:9px;background:#006272;color:#fff;text-decoration:none;font-weight:bold">ดูรายละเอียดคำสั่งซื้อ</a></p>'
        . '<p style="margin:26px 0 0;color:#c56e88;font-weight:bold">Wonwan Web</p></td></tr></table></td></tr></table></body></html>';
    try {
        return smtpSend($config, $email, 'Wonwan Web: ' . $title . ' #' . $orderNumber, $html);
    } catch (Throwable $error) {
        error_log('Wonwan order email error: ' . $error->getMessage());
        return false;
    }
}

function wanawanSendLineOrderMessage(string $lineUserId, string $orderNumber, string $title, string $message, array $details = []): bool
{
    $file = __DIR__ . '/line.local.php';
    if (!is_file($file) || $lineUserId === '') return false;
    $config = require $file;
    $token = trim((string) ($config['channel_access_token'] ?? ''));
    if ($token === '' || str_contains($token, 'CHANNEL_ACCESS_TOKEN')) return false;
    $detailText = '';
    foreach ($details['items'] ?? [] as $item) {
        $quantity = (int) $item['quantity'];
        $detailText .= "\n• " . $item['title'] . ' ×' . $quantity . ' — ฿' . number_format((float) $item['price_at_purchase'] * $quantity, 2);
    }
    if ($details) {
        $detailText .= "\n\nยอดสินค้า ฿" . number_format((float) ($details['subtotal'] ?? 0), 2);
        $detailText .= "\nค่าจัดส่ง ฿" . number_format((float) ($details['shipping_fee'] ?? 0), 2);
        foreach ($details['promotions'] ?? [] as $promotion) {
            $detailText .= "\nโปร " . $promotion['promotion_name'] . ' −฿' . number_format((float) $promotion['discount_amount'], 2);
        }
        if ((float) ($details['discount_amount'] ?? 0) > 0 && empty($details['promotions'])) {
            $detailText .= "\nส่วนลด −฿" . number_format((float) $details['discount_amount'], 2);
        }
        $detailText .= "\nยอดสุทธิ ฿" . number_format((float) ($details['total_amount'] ?? 0), 2);
    }
    $payload = json_encode([
        'to' => $lineUserId,
        'messages' => [[
            'type' => 'text',
            'text' => "Wonwan Web\n{$title}\nคำสั่งซื้อ #{$orderNumber}\n{$message}{$detailText}\n\n" . wanawanOrderUrl(),
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $curl = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
    ]);
    curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return $status === 200;
}

function wanawanNotifyOrderBuyer(PDO $pdo, array $order, string $title, string $message, bool $createWeb = true): void
{
    $userId = (int) ($order['user_id'] ?? 0);
    $orderNumber = (string) ($order['order_number'] ?? '');
    if ($userId <= 0 || $orderNumber === '') return;
    if ($createWeb) {
        $insert = $pdo->prepare("INSERT INTO notifications(user_id,title,message,notification_type,is_read,sent_via) VALUES(?,?,?,'order',0,'web')");
        $insert->execute([$userId, $title, 'คำสั่งซื้อ #' . $orderNumber . ' ' . $message]);
    }
    $details = wanawanOrderNotificationDetails($pdo, $order);
    $find = $pdo->prepare("SELECT email,COALESCE(NULLIF(display_name,''),email) display_name,notify_preference,line_user_id FROM users WHERE user_id=?");
    $find->execute([$userId]);
    $user = $find->fetch(PDO::FETCH_ASSOC);
    if (!$user) return;
    $preference = (string) ($user['notify_preference'] ?? 'email');
    if (in_array($preference, ['email', 'both'], true)) {
        wanawanSendOrderEmail((string) $user['email'], (string) $user['display_name'], $orderNumber, $title, $message, $details);
    }
    if (in_array($preference, ['line', 'both'], true) && !empty($user['line_user_id'])) {
        wanawanSendLineOrderMessage((string) $user['line_user_id'], $orderNumber, $title, $message, $details);
    }
}
