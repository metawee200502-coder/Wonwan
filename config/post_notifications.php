<?php
declare(strict_types=1);
require_once __DIR__ . '/password_reset.php';

function wanawanPostSellerLabel(string $sellerType, string $sellerName): string
{
    $prefix = $sellerType === 'publisher' ? 'สำนักพิมพ์' : ($sellerType === 'shop' ? 'ร้านค้า' : 'ผู้ขาย');
    return preg_match('/^(?:สำนักพิมพ์|ร้านค้า|ผู้ขาย)\s/u', $sellerName) ? $sellerName : $prefix . ' ' . trim($sellerName);
}

function wanawanNotifyPostFollowers(PDO $pdo, string $sellerType, int $sellerId, string $sellerName, string $content): array
{
    $sellerLabel = wanawanPostSellerLabel($sellerType, $sellerName);
    $profileUrl = passwordResetBaseUrl() . '/pages/partner_detail.php?type=' . rawurlencode($sellerType) . '&id=' . $sellerId;
    $preview = trim($content) !== '' ? trim($content) : 'มีประกาศพร้อมรูปภาพใหม่';
    $followers = $pdo->prepare("SELECT u.user_id,u.email,COALESCE(NULLIF(u.display_name,''),u.email) display_name,u.notify_preference,u.line_user_id FROM profile_follows pf JOIN users u ON u.user_id=pf.follower_user_id WHERE pf.target_type=? AND pf.target_id=?");
    $followers->execute([$sellerType, $sellerId]);
    $users = $followers->fetchAll(PDO::FETCH_ASSOC);
    $mailConfig = is_file(__DIR__ . '/mail.local.php') ? require __DIR__ . '/mail.local.php' : [];
    $lineConfig = is_file(__DIR__ . '/line.local.php') ? require __DIR__ . '/line.local.php' : [];
    $result = ['followers' => count($users), 'email' => 0, 'line' => 0];
    $web = $pdo->prepare("INSERT INTO notifications(user_id,title,message,notification_type,target_url,is_read,sent_via) VALUES(?,?,?,'system',?,0,'web')");
    $targetUrl = '/pages/partner_detail.php?type=' . rawurlencode($sellerType) . '&id=' . $sellerId;
    foreach ($users as $user) {
        $web->execute([(int) $user['user_id'], 'ประกาศใหม่จาก ' . $sellerLabel, mb_strimwidth($preview, 0, 240, '…', 'UTF-8'), $targetUrl]);
        $preference = (string) ($user['notify_preference'] ?? 'email');
        if (in_array($preference, ['email', 'both'], true) && $mailConfig) {
            try {
                $safeName = htmlspecialchars((string) $user['display_name'], ENT_QUOTES, 'UTF-8');
                $safeSeller = htmlspecialchars($sellerLabel, ENT_QUOTES, 'UTF-8');
                $safeContent = nl2br(htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'));
                $safeUrl = htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8');
                $html = '<!doctype html><html lang="th"><body style="margin:0;background:#fff7f2;font-family:Arial,Tahoma,sans-serif;color:#3c302c"><table role="presentation" width="100%"><tr><td align="center" style="padding:36px 16px"><table role="presentation" width="100%" style="max-width:580px;background:#fff;border:1px solid #eadbd4;border-radius:16px"><tr><td style="padding:34px">'
                    . '<div style="color:#c56e88;font-weight:bold">WONWAN WEB · ANNOUNCEMENT</div><h1 style="margin:14px 0 6px;color:#005b66;font-size:26px">ประกาศใหม่</h1>'
                    . '<p style="margin:0 0 22px;color:#8b756d">จาก ' . $safeSeller . '</p><p style="line-height:1.7">สวัสดี ' . $safeName . '</p>'
                    . '<div style="margin:20px 0;padding:20px;border-radius:12px;background:#fff1f5;line-height:1.75">' . $safeContent . '</div>'
                    . '<a href="' . $safeUrl . '" style="display:inline-block;padding:14px 24px;border-radius:9px;background:#006272;color:#fff;text-decoration:none;font-weight:bold">ดูประกาศและหน้าร้าน</a>'
                    . '<p style="margin:28px 0 0;color:#c56e88;font-weight:bold">Wonwan Web</p></td></tr></table></td></tr></table></body></html>';
                if (smtpSend($mailConfig, (string) $user['email'], 'ประกาศใหม่จาก ' . $sellerLabel, $html)) $result['email']++;
            } catch (Throwable $error) {
                error_log('Wonwan post email error: ' . $error->getMessage());
            }
        }
        if (in_array($preference, ['line', 'both'], true) && !empty($user['line_user_id']) && $lineConfig) {
            try {
                $token = trim((string) ($lineConfig['channel_access_token'] ?? ''));
                $text = "📣 ประกาศใหม่จาก {$sellerLabel}\n\n" . mb_strimwidth($preview, 0, 1500, '…', 'UTF-8') . "\n\nดูประกาศ: {$profileUrl}";
                $curl = curl_init('https://api.line.me/v2/bot/message/push');
                curl_setopt_array($curl, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode(['to' => $user['line_user_id'], 'messages' => [['type' => 'text', 'text' => $text]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
                ]);
                curl_exec($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                curl_close($curl);
                if ($status === 200) $result['line']++;
            } catch (Throwable $error) {
                error_log('Wonwan post LINE error: ' . $error->getMessage());
            }
        }
    }
    return $result;
}
