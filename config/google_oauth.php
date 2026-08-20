<?php
declare(strict_types=1);

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
        'client_id' => (string) $client['client_id'],
        'client_secret' => (string) $client['client_secret'],
        'redirect_uri' => 'http://localhost/wanawan_project/pages/google_callback.php',
        'authorization_endpoint' => (string) ($client['auth_uri'] ?? 'https://accounts.google.com/o/oauth2/auth'),
        'token_endpoint' => (string) ($client['token_uri'] ?? 'https://oauth2.googleapis.com/token'),
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

