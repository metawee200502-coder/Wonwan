<?php
declare(strict_types=1);

require_once __DIR__ . '/password_reset.php';

if (!function_exists('wanawanSendExternalEmailGeneric')) {
    function wanawanSendExternalEmailGeneric(string $email, string $name, string $title, string $message, string $url = ''): bool
    {
        $file = __DIR__ . '/mail.local.php';
        if (!is_file($file)) return false;
        $config = require $file;
        $password = preg_replace('/\s+/', '', (string) ($config['app_password'] ?? ''));
        if (!is_array($config) || $password === '' || str_contains($password, 'APP_PASSWORD')) return false;
        
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        
        $html = '<!doctype html><html lang="th"><body style="margin:0;background:#fff7f2;font-family:Arial,Tahoma,sans-serif;color:#3c302c">'
            . '<table role="presentation" width="100%"><tr><td align="center" style="padding:36px 16px"><table role="presentation" width="100%" style="max-width:580px;background:#fff;border:1px solid #eadbd4;border-radius:16px">'
            . '<tr><td style="padding:34px"><div style="color:#c56e88;font-weight:bold;margin-bottom:12px">WONWAN WEB</div>'
            . '<h1 style="margin:0 0 8px;color:#005b66;font-size:23px">' . $safeTitle . '</h1>'
            . '<p style="line-height:1.7;margin-top:16px">สวัสดี ' . $safeName . '</p><p style="line-height:1.7">' . $safeMessage . '</p>';
            
        if ($url !== '') {
            $html .= '<p style="margin:28px 0"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:14px 24px;border-radius:9px;background:#006272;color:#fff;text-decoration:none;font-weight:bold">ดูรายละเอียดเพิ่มเติม</a></p>';
        } else {
            $html .= '<p style="margin:28px 0"><a href="' . htmlspecialchars(passwordResetBaseUrl() . '/pages/notifications.php', ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:14px 24px;border-radius:9px;background:#006272;color:#fff;text-decoration:none;font-weight:bold">ตรวจสอบการแจ้งเตือน</a></p>';
        }
            
        $html .= '<p style="margin:26px 0 0;color:#c56e88;font-weight:bold">Wonwan Web</p></td></tr></table></td></tr></table></body></html>';
        
        try {
            return smtpSend($config, $email, 'Wonwan Web: ' . $title, $html);
        } catch (Throwable $error) {
            error_log('Wonwan email error: ' . $error->getMessage());
            return false;
        }
    }
}

if (!function_exists('wanawanSendExternalLineGeneric')) {
    function wanawanSendExternalLineGeneric(string $lineUserId, string $title, string $message, string $url = ''): bool
    {
        $file = __DIR__ . '/line.local.php';
        if (!is_file($file) || $lineUserId === '') return false;
        $config = require $file;
        $token = trim((string) ($config['channel_access_token'] ?? ''));
        if ($token === '' || str_contains($token, 'CHANNEL_ACCESS_TOKEN')) return false;
        
        $finalUrl = $url !== '' ? $url : passwordResetBaseUrl() . '/pages/notifications.php';
        
        $payload = json_encode([
            'to' => $lineUserId,
            'messages' => [[
                'type' => 'text',
                'text' => "Wonwan Web\n{$title}\n\n{$message}\n\n{$finalUrl}",
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
}

if (!function_exists('wanawanNotifyUser')) {
    function wanawanNotifyUser(PDO $pdo, int $userId, string $title, string $message, string $type, array $extraColumns = []): void
    {
        if ($userId <= 0) return;
        
        $columns = ['user_id', 'title', 'message', 'notification_type', 'is_read', 'sent_via'];
        $values = [$userId, $title, $message, $type, 0, 'web'];
        $placeholders = ['?', '?', '?', '?', '?', '?'];
        
        foreach (['related_user_id', 'related_review_id', 'chat_conversation_id', 'target_url'] as $key) {
            if (isset($extraColumns[$key])) {
                $columns[] = $key;
                $values[] = $extraColumns[$key];
                $placeholders[] = '?';
            }
        }
        
        $sql = "INSERT INTO notifications(" . implode(',', $columns) . ") VALUES(" . implode(',', $placeholders) . ")";
        $insert = $pdo->prepare($sql);
        $insert->execute($values);
        
        // Fetch user preferences
        $find = $pdo->prepare("SELECT email,COALESCE(NULLIF(display_name,''),email) display_name,notify_preference,line_user_id FROM users WHERE user_id=?");
        $find->execute([$userId]);
        $user = $find->fetch(PDO::FETCH_ASSOC);
        if (!$user) return;
        
        $preference = (string) ($user['notify_preference'] ?? 'email');
        if ($preference === 'none') return;
        
        $url = $extraColumns['target_url'] ?? '';
        if ($url !== '' && str_starts_with($url, '/')) {
            $url = passwordResetBaseUrl() . $url;
        }
        
        if (in_array($preference, ['email', 'both'], true)) {
            wanawanSendExternalEmailGeneric((string)$user['email'], (string)$user['display_name'], $title, $message, $url);
        }
        
        if (in_array($preference, ['line', 'both'], true) && !empty($user['line_user_id'])) {
            wanawanSendExternalLineGeneric((string)$user['line_user_id'], $title, $message, $url);
        }
    }
}
