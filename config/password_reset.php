<?php
declare(strict_types=1);

function passwordResetBaseUrl(): string
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'wanawan_project.test'));
    $allowed = ['wanawan_project.test', 'localhost'];
    $hostOnly = preg_replace('/:\d+$/', '', $host);
    if (!in_array($hostOnly, $allowed, true)) {
        return 'http://wanawan_project.test';
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host;
}

function sendPasswordResetEmail(string $email, string $name, string $resetUrl): bool
{
    $configFile = __DIR__ . '/mail.local.php';
    if (!is_file($configFile)) return false;
    $config = require $configFile;
    if (!is_array($config)) return false;
    $password = preg_replace('/\s+/', '', (string) ($config['app_password'] ?? ''));
    if ($password === '' || str_contains($password, 'APP_PASSWORD')) return false;

    $safeName = trim((string) preg_replace('/[\r\n]+/', ' ', $name));
    $subject = 'ตั้งรหัสผ่านใหม่สำหรับบัญชีวนวรรณ';
    $escapedName = htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8');
    $escapedUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $html = '<!doctype html><html lang="th"><body style="margin:0;background:#fff7f2;font-family:Arial,Tahoma,sans-serif;color:#3c302c">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:36px 16px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border:1px solid #eadbd4;border-radius:16px">'
        . '<tr><td style="padding:34px"><h1 style="margin:0 0 18px;color:#005b66;font-size:26px">ตั้งรหัสผ่านใหม่</h1>'
        . '<p style="line-height:1.7">สวัสดี ' . $escapedName . '</p>'
        . '<p style="line-height:1.7">เราได้รับคำขอให้ตั้งรหัสผ่านใหม่สำหรับบัญชีวนวรรณของคุณ กรุณากดปุ่มด้านล่างภายใน 30 นาที</p>'
        . '<p style="margin:28px 0"><a href="' . $escapedUrl . '" style="display:inline-block;padding:14px 24px;border-radius:9px;background:#006272;color:#fff;text-decoration:none;font-weight:bold">ตั้งรหัสผ่านใหม่</a></p>'
        . '<p style="font-size:13px;line-height:1.6;color:#816f67">หากคุณไม่ได้เป็นผู้ขอ สามารถเพิกเฉยต่ออีเมลนี้ได้ ลิงก์จะหมดอายุโดยอัตโนมัติ</p>'
        . '<p style="margin:26px 0 0;color:#c56e88;font-weight:bold">Wonwan Web</p>'
        . '</td></tr></table></td></tr></table></body></html>';

    try {
        return smtpSend($config, $email, $subject, $html);
    } catch (Throwable $error) {
        error_log('Wonwan mail error: ' . $error->getMessage());
        return false;
    }
}

function smtpRead($socket): array
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') break;
    }
    return [(int) substr($response, 0, 3), $response];
}

function smtpCommand($socket, string $command, array $expected): void
{
    if ($command !== '') fwrite($socket, $command . "\r\n");
    [$code, $response] = smtpRead($socket);
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('SMTP ' . $code . ': ' . trim($response));
    }
}

function smtpSend(array $config, string $recipient, string $subject, string $html): bool
{
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) return false;
    $host = (string) ($config['host'] ?? 'smtp.gmail.com');
    $port = (int) ($config['port'] ?? 587);
    $username = trim((string) ($config['username'] ?? ''));
    $password = preg_replace('/\s+/', '', (string) ($config['app_password'] ?? ''));
    $fromEmail = trim((string) ($config['from_email'] ?? $username));
    $fromName = trim((string) ($config['from_name'] ?? 'Wonwan Web'));
    if (!filter_var($username, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) return false;

    $context = stream_context_create(['ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => $host,
    ]]);
    $socket = @stream_socket_client(
        'tcp://' . $host . ':' . $port,
        $errorNumber,
        $errorMessage,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );
    if (!$socket) throw new RuntimeException('เชื่อมต่อ SMTP ไม่สำเร็จ: ' . $errorMessage);
    stream_set_timeout($socket, 15);

    try {
        smtpCommand($socket, '', [220]);
        smtpCommand($socket, 'EHLO wonwan-web.local', [250]);
        smtpCommand($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('เริ่มการเชื่อมต่อ TLS ไม่สำเร็จ');
        }
        smtpCommand($socket, 'EHLO wonwan-web.local', [250]);
        smtpCommand($socket, 'AUTH LOGIN', [334]);
        smtpCommand($socket, base64_encode($username), [334]);
        smtpCommand($socket, base64_encode($password), [235]);
        smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        smtpCommand($socket, 'DATA', [354]);

        $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $messageId = '<' . bin2hex(random_bytes(12)) . '@wonwan-web.local>';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $encodedName . ' <' . $fromEmail . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $encodedSubject,
            'Message-ID: ' . $messageId,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $body = chunk_split(base64_encode($html), 76, "\r\n");
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $data = preg_replace('/(?m)^\./', '..', $data);
        fwrite($socket, $data . "\r\n.\r\n");
        [$code, $response] = smtpRead($socket);
        if ($code !== 250) throw new RuntimeException('SMTP ' . $code . ': ' . trim($response));
        fwrite($socket, "QUIT\r\n");
        return true;
    } finally {
        fclose($socket);
    }
}

function passwordResetIsLocal(): bool
{
    $host = preg_replace('/:\d+$/', '', strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')));
    return in_array($host, ['wanawan_project.test', 'localhost'], true);
}
