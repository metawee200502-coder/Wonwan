<?php
declare(strict_types=1);
require_once __DIR__ . '/password_reset.php';

function wanawanPromotionProfileUrl(string $sellerType, int $sellerId): string
{
    return passwordResetBaseUrl() . '/pages/partner_detail.php?type=' . rawurlencode($sellerType) . '&id=' . $sellerId;
}

function wanawanPromotionEmail(array $mailConfig, array $user, array $promotion, string $sellerName, string $sellerType, int $sellerId): bool
{
    $password = preg_replace('/\s+/', '', (string) ($mailConfig['app_password'] ?? ''));
    if ($password === '' || str_contains($password, 'APP_PASSWORD')) return false;
    $name = htmlspecialchars((string) $user['display_name'], ENT_QUOTES, 'UTF-8');
    $store = htmlspecialchars($sellerName, ENT_QUOTES, 'UTF-8');
    $promoName = htmlspecialchars((string) $promotion['promotion_name'], ENT_QUOTES, 'UTF-8');
    $description = nl2br(htmlspecialchars((string) ($promotion['description'] ?: 'โปรโมชั่นพิเศษสำหรับผู้ติดตามร้าน'), ENT_QUOTES, 'UTF-8'));
    $discount = $promotion['discount_type'] === 'percentage'
        ? 'ลด ' . number_format((float) $promotion['discount_value'], 0) . '%'
        : 'ลด ฿' . number_format((float) $promotion['discount_value'], 2);
    $conditions = [];
    if ((int) $promotion['minimum_quantity'] > 1) $conditions[] = 'ซื้ออย่างน้อย ' . (int) $promotion['minimum_quantity'] . ' เล่ม';
    if ((float) $promotion['minimum_amount'] > 0) $conditions[] = 'ยอดขั้นต่ำ ฿' . number_format((float) $promotion['minimum_amount'], 2);
    if ((float) ($promotion['maximum_discount'] ?? 0) > 0) $conditions[] = 'ลดสูงสุด ฿' . number_format((float) $promotion['maximum_discount'], 2);
    $url = htmlspecialchars(wanawanPromotionProfileUrl($sellerType, $sellerId), ENT_QUOTES, 'UTF-8');
    $html = '<!doctype html><html lang="th"><body style="margin:0;background:#fff7f2;font-family:Arial,Tahoma,sans-serif;color:#3c302c">'
        . '<table role="presentation" width="100%"><tr><td align="center" style="padding:36px 16px"><table role="presentation" width="100%" style="max-width:580px;background:#fff;border:1px solid #eadbd4;border-radius:16px">'
        . '<tr><td style="padding:34px"><div style="color:#c56e88;font-weight:bold">WONWAN WEB · PROMOTION</div>'
        . '<h1 style="margin:14px 0 6px;color:#005b66;font-size:26px">' . $promoName . '</h1>'
        . '<p style="margin:0 0 22px;color:#8b756d">โปรโมชั่นใหม่จาก ' . $store . '</p>'
        . '<p style="line-height:1.7">สวัสดี ' . $name . '</p><p style="line-height:1.7">' . $description . '</p>'
        . '<div style="margin:22px 0;padding:18px;border-radius:12px;background:#fff1f5"><strong style="font-size:22px;color:#c24f78">' . htmlspecialchars($discount) . '</strong>'
        . ($conditions ? '<p style="margin:9px 0 0;line-height:1.6">' . htmlspecialchars(implode(' · ', $conditions)) . '</p>' : '')
        . '<p style="margin:9px 0 0;color:#7d6c65">ใช้ได้ ' . date('d/m/Y H:i', strtotime((string) $promotion['starts_at'])) . ' – ' . date('d/m/Y H:i', strtotime((string) $promotion['ends_at'])) . '</p></div>'
        . '<a href="' . $url . '" style="display:inline-block;padding:14px 24px;border-radius:9px;background:#006272;color:#fff;text-decoration:none;font-weight:bold">เลือกซื้อสินค้าร่วมโปรโมชั่น</a>'
        . '<p style="margin:28px 0 0;color:#c56e88;font-weight:bold">Wonwan Web</p></td></tr></table></td></tr></table></body></html>';
    return smtpSend($mailConfig, (string) $user['email'], 'โปรโมชั่นใหม่จาก ' . $sellerName . ': ' . $promotion['promotion_name'], $html);
}

function wanawanPromotionLine(array $lineConfig, string $lineUserId, array $promotion, string $sellerName, string $sellerType, int $sellerId): bool
{
    $token = trim((string) ($lineConfig['channel_access_token'] ?? ''));
    if ($token === '' || $lineUserId === '') return false;
    $discount = $promotion['discount_type'] === 'percentage'
        ? 'ลด ' . number_format((float) $promotion['discount_value'], 0) . '%'
        : 'ลด ฿' . number_format((float) $promotion['discount_value'], 2);
    $conditions = [];
    if ((int) $promotion['minimum_quantity'] > 1) $conditions[] = 'ซื้อ ' . (int) $promotion['minimum_quantity'] . ' เล่มขึ้นไป';
    if ((float) $promotion['minimum_amount'] > 0) $conditions[] = 'ยอดขั้นต่ำ ฿' . number_format((float) $promotion['minimum_amount'], 2);
    $text = "🎉 โปรโมชั่นใหม่จาก {$sellerName}\n"
        . $promotion['promotion_name'] . "\n"
        . $discount . ($conditions ? "\n" . implode(' · ', $conditions) : '')
        . "\nใช้ได้ " . date('d/m/Y H:i', strtotime((string) $promotion['starts_at'])) . ' – ' . date('d/m/Y H:i', strtotime((string) $promotion['ends_at']))
        . (!empty($promotion['description']) ? "\n\n" . $promotion['description'] : '')
        . "\n\nเลือกซื้อสินค้า: " . wanawanPromotionProfileUrl($sellerType, $sellerId);
    $curl = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['to' => $lineUserId, 'messages' => [['type' => 'text', 'text' => $text]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
    ]);
    curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return $status === 200;
}

function wanawanNotifyPromotionFollowers(PDO $pdo, array $promotion, string $sellerName, string $sellerType, int $sellerId): array
{
    $prefix = $sellerType === 'publisher' ? 'สำนักพิมพ์' : ($sellerType === 'shop' ? 'ร้านค้า' : 'ผู้ขาย');
    if (!preg_match('/^(?:สำนักพิมพ์|ร้านค้า|ผู้ขาย)\s/u', $sellerName)) {
        $sellerName = $prefix . ' ' . trim($sellerName);
    }
    $followers = $pdo->prepare("SELECT u.user_id,u.email,COALESCE(NULLIF(u.display_name,''),u.email) display_name,u.notify_preference,u.line_user_id FROM profile_follows pf JOIN users u ON u.user_id=pf.follower_user_id WHERE pf.target_type=? AND pf.target_id=?");
    $followers->execute([$sellerType, $sellerId]);
    $users = $followers->fetchAll(PDO::FETCH_ASSOC);
    $mailConfig = is_file(__DIR__ . '/mail.local.php') ? require __DIR__ . '/mail.local.php' : [];
    $lineConfig = is_file(__DIR__ . '/line.local.php') ? require __DIR__ . '/line.local.php' : [];
    $result = ['followers' => count($users), 'email' => 0, 'line' => 0];
    $web = $pdo->prepare("INSERT INTO notifications(user_id,title,message,notification_type,is_read,sent_via) VALUES(?,?,?,'system',0,'web')");
    foreach ($users as $user) {
        $web->execute([(int) $user['user_id'], 'โปรโมชั่นใหม่จาก ' . $sellerName, $promotion['promotion_name'] . ' เริ่ม ' . date('d/m/Y H:i', strtotime((string) $promotion['starts_at']))]);
        $preference = (string) ($user['notify_preference'] ?? 'email');
        try {
            if (in_array($preference, ['email', 'both'], true) && wanawanPromotionEmail($mailConfig, $user, $promotion, $sellerName, $sellerType, $sellerId)) $result['email']++;
        } catch (Throwable $error) {
            error_log('Wonwan promotion email error: ' . $error->getMessage());
        }
        try {
            if (in_array($preference, ['line', 'both'], true) && !empty($user['line_user_id']) && wanawanPromotionLine($lineConfig, (string) $user['line_user_id'], $promotion, $sellerName, $sellerType, $sellerId)) $result['line']++;
        } catch (Throwable $error) {
            error_log('Wonwan promotion LINE error: ' . $error->getMessage());
        }
    }
    return $result;
}
