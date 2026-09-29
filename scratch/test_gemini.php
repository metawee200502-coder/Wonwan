<?php
$config = require __DIR__ . '/../config/ai.local.php';
$key    = $config['api_key'] ?? '';
$model  = $config['model']   ?? 'gemini-1.5-flash-latest';

echo "Key: " . substr($key, 0, 15) . "...\n";
echo "Model: $model\n\n";

$payload = json_encode([
    'contents' => [['role' => 'user', 'parts' => [['text' => 'Say: hello podcast. Just those words.']]]],
    'generationConfig' => ['maxOutputTokens' => 30],
]);

$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
    . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);
$r = curl_exec($ch);
$s = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$e = curl_error($ch);
curl_close($ch);

echo "HTTP Status: $s\n";
if ($e) echo "cURL Error: $e\n";
echo "Response: " . mb_strimwidth((string)$r, 0, 500) . "\n";

$data = json_decode($r, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
echo "\nExtracted text: $text\n";
