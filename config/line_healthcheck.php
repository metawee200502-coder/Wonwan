<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$config = require __DIR__ . '/line.local.php';
$loginReady = !empty($config['login_channel_id'])
    && !empty($config['login_channel_secret'])
    && !str_contains((string) $config['login_channel_secret'], 'CHANNEL_SECRET');
echo 'line_login_configured=' . ($loginReady ? 'yes' : 'no') . PHP_EOL;
$token = trim((string) ($config['channel_access_token'] ?? ''));
if ($token === '' || str_contains($token, 'CHANNEL_ACCESS_TOKEN')) {
    fwrite(STDERR, "line_config=missing\n");
    exit(1);
}
$curl = curl_init('https://api.line.me/v2/bot/info');
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
]);
$body = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
curl_close($curl);
$data = json_decode((string) $body, true);
echo 'line_api_status=' . $status . PHP_EOL;
echo 'bot_verified=' . ($status === 200 && !empty($data['basicId']) ? 'yes' : 'no') . PHP_EOL;
if ($status === 200) echo 'bot_name=' . ($data['displayName'] ?? '') . PHP_EOL;
exit($status === 200 ? 0 : 1);
