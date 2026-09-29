<?php
// =============================================================================
// api/generate-podcast.php — AI Book Podcaster (Gemini + ElevenLabs Edition)
//
// Flow:
//   1. Call Gemini API to write a Thai podcast script
//   2. Call ElevenLabs API to synthesize high-quality Thai audio
//   3. Return MP3 stream to frontend
// =============================================================================

// ─────────────────────────────────────────────────────────────────────────────
// 🚧 TEMPORARY DISABLE — เปลี่ยน true → false เพื่อเปิดใช้งานอีกครั้ง
// ─────────────────────────────────────────────────────────────────────────────
define('PODCAST_DISABLED', true);
if (PODCAST_DISABLED) {
    header('Content-Type: application/json');
    http_response_code(503);
    echo json_encode(['error' => 'ฟีเจอร์ AI Podcast ปิดให้บริการชั่วคราว กรุณากลับมาใหม่ในภายหลัง']);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────────

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$aiConfig = require __DIR__ . '/../config/ai.local.php';
$geminiKey = $aiConfig['api_key'] ?? null;
$geminiModel = $aiConfig['model'] ?? 'gemini-1.5-flash-latest';
$elevenLabsKey = $aiConfig['elevenlabs_api_key'] ?? null;

if (empty($geminiKey)) {
    http_response_code(500); echo json_encode(['error' => "Gemini API key not found."]); exit;
}
if (empty($elevenLabsKey)) {
    http_response_code(500); 
    echo json_encode(['error' => "กรุณาใส่ API Key ของ ElevenLabs ในไฟล์ config/ai.local.php เพื่อใช้งานระบบเสียง"]); 
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$bookTitle = trim(strip_tags($body['bookTitle'] ?? ''));
$synopsis  = trim(strip_tags($body['synopsis']  ?? ''));
$bookDNA   = trim(strip_tags($body['bookDNA']   ?? ''));

if ($bookTitle === '' || $synopsis === '' || $bookDNA === '') {
    http_response_code(400); echo json_encode(['error' => 'Missing data.']); exit;
}

// ── 1. Call Gemini for Script ────────────────────────────────────────────────
$systemPrompt = 'You are a lively and popular book podcast host. '
    . 'Write an engaging, bright, and concise book recommendation podcast script in English (under 70 words). '
    . 'It must sound natural, like a radio host speaking. Do not use any markdown formatting.';

$userPrompt = "Write a 30-second English podcast script for:\nBook Title: \"{$bookTitle}\"\nSynopsis: {$synopsis}\nMood: {$bookDNA}\nRules: Briefly tell the story, make it engaging, under 70 words, purely in English.";

$payload = [
    'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
    'contents'          => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
    'generationConfig'  => ['maxOutputTokens' => 150, 'temperature' => 0.85],
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($geminiModel) . ':generateContent?key=' . rawurlencode($geminiKey);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);

$raw = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status < 200 || $status >= 300 || !$raw) {
    http_response_code(502); 
    $geminiErr = json_decode($raw, true);
    $geminiMsg = $geminiErr['error']['message'] ?? 'Unknown Gemini error. Status: ' . $status;
    echo json_encode(['error' => 'Gemini Error: ' . $geminiMsg]); 
    exit;
}

$data = json_decode($raw, true);
$script = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
if (!$script) {
    http_response_code(502); echo json_encode(['error' => 'Gemini returned empty script']); exit;
}

// ── 2. Call ElevenLabs for Audio ──────────────────────────────────────────────
$voiceId = 'EXAVITQu4vr4xnSDxMaL'; // Sarah (Mature, Reassuring) - Works on Free Tier
$ttsUrl = "https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}?output_format=mp3_44100_128";

$ttsPayload = [
    "text" => $script,
    "model_id" => "eleven_multilingual_v2",
    "voice_settings" => [
        "stability" => 0.5,
        "similarity_boost" => 0.75
    ]
];

$ch2 = curl_init($ttsUrl);
curl_setopt_array($ch2, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($ttsPayload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 45,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'xi-api-key: ' . trim($elevenLabsKey),
        'Accept: audio/mpeg'
    ]
]);

$audioData = curl_exec($ch2);
$ttsStatus = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

if ($ttsStatus < 200 || $ttsStatus >= 300 || !$audioData) {
    http_response_code(502); 
    $err = json_decode($audioData, true);
    $msg = is_array($err) && isset($err['detail']['message']) ? $err['detail']['message'] : 'Unknown ElevenLabs error. Raw: ' . substr(strip_tags((string)$audioData), 0, 100);
    // If quota exceeded
    if (stripos($msg, 'quota') !== false) {
        $msg = "ElevenLabs free credits exhausted.";
    }
    echo json_encode(['error' => 'ElevenLabs Error: ' . $msg]);
    exit;
}

// ── 3. Return Audio Stream ────────────────────────────────────────────────────
header('Content-Type: application/json');
echo json_encode([
    'script' => $script,
    'audio_base64' => base64_encode($audioData)
]);
exit;
