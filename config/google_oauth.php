<?php
declare(strict_types=1);

/**
 * สร้าง redirect_uri แบบ dynamic จาก HTTP_HOST จริง ๆ
 * รองรับ localhost, wanawan_project.test และ Cloudflare tunnel (*.trycloudflare.com)
 */
function googleOAuthRedirectUri(): string
{
    // ngrok+host-header ส่ง X-Forwarded-Host = domain จริง ที่ผู้ใช้เห็น
    $host = strtolower((string) (
        $_SERVER['HTTP_X_FORWARDED_HOST'] ??
        $_SERVER['HTTP_HOST'] ??
        'localhost'
    ));
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $scheme = $isHttps ? 'https' : 'http';

    // - vhost mode (wanawan_project.test / ngrok+host-header):
    //   SCRIPT_NAME = /pages/google_login.php → basePath = ''
    // - subdirectory mode (localhost หรือ ngrok ไม่ใช้ host-header):
    //   SCRIPT_NAME = /wanawan_project/pages/google_login.php → basePath = '/wanawan_project'
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = str_contains($scriptName, '/wanawan_project/') ? '/wanawan_project' : '';

    return $scheme . '://' . $host . $basePath . '/pages/google_callback.php';
}

function googleOAuthConfig(): array
{
    $paths = [
        __DIR__ . '/google-oauth.json',
        __DIR__ . '/google-oauth.json.json',
    ];
    $path = null;
    foreach ($paths as $candidate) {
        if (is_file($candidate)) {
            $path = $candidate;
            break;
        }
    }
    if ($path === null) {
        throw new RuntimeException('ยังไม่พบไฟล์ตั้งค่า Google');
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    $client = $decoded['web'] ?? null;
    if (!is_array($client) || empty($client['client_id']) || empty($client['client_secret'])) {
        throw new RuntimeException('ไฟล์ตั้งค่า Google ไม่ถูกต้อง');
    }

    return [
        'client_id'              => (string) $client['client_id'],
        'client_secret'          => (string) $client['client_secret'],
        'redirect_uri'           => googleOAuthRedirectUri(),
        'authorization_endpoint' => (string) ($client['auth_uri'] ?? 'https://accounts.google.com/o/oauth2/auth'),
        'token_endpoint'         => (string) ($client['token_uri'] ?? 'https://oauth2.googleapis.com/token'),
    ];
}

function googleOAuthPost(string $url, array $fields): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Google ไม่สามารถยืนยันการเข้าสู่ระบบได้' . ($error ? ': ' . $error : ''));
    }
    $data = json_decode((string) $body, true);
    if (!is_array($data)) {
        throw new RuntimeException('ข้อมูลตอบกลับจาก Google ไม่ถูกต้อง');
    }
    return $data;
}

function googleOAuthGetJson(string $url): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $data = json_decode((string) $body, true);
    if ($body === false || $status !== 200 || !is_array($data)) {
        throw new RuntimeException('ไม่สามารถตรวจสอบบัญชี Google ได้');
    }
    return $data;
}

