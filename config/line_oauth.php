<?php
declare(strict_types=1);

function wanawanLineConfig(): array
{
    $file = __DIR__ . '/line.local.php';
    if (!is_file($file)) throw new RuntimeException('ยังไม่ได้ตั้งค่า LINE');
    $config = require $file;
    if (
        !is_array($config) ||
        empty($config['login_channel_id']) ||
        empty($config['login_channel_secret']) ||
        str_contains((string) $config['login_channel_secret'], 'CHANNEL_SECRET')
    ) {
        throw new RuntimeException('การตั้งค่า LINE Login ยังไม่สมบูรณ์');
    }
    return $config;
}

function wanawanLinePost(string $url, array $fields): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $data = json_decode((string) $body, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        throw new RuntimeException('LINE ไม่สามารถยืนยันบัญชีได้');
    }
    return $data;
}

