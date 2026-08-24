<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/digital_twin.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON']);
    exit;
}

$dna = $input['dna'] ?? ['pace' => 50, 'complexity' => 50, 'tone' => 50];
$attention = trim($input['attention'] ?? '');
$context = $input['context'] ?? ['time' => 'Unknown', 'environment' => 'Unknown'];
$target_book_id = (int)($input['target_book_id'] ?? 0);

if ($target_book_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Missing target_book_id']);
    exit;
}

// Labels for DNA scores (0-100)
$paceLabel = (int)$dna['pace'] < 40 ? 'ค่อยเป็นค่อยไป (Slow-burn)' : ((int)$dna['pace'] > 60 ? 'เดินเรื่องเร็ว (Fast-paced)' : 'ปานกลาง (Balanced Pace)');
$complexityLabel = (int)$dna['complexity'] < 40 ? 'อ่านง่าย สบายๆ (Simple & Easy)' : ((int)$dna['complexity'] > 60 ? 'ซับซ้อน ลุ่มลึก (Complex & Deep)' : 'ระดับมาตรฐาน (Standard)');
$toneLabel = (int)$dna['tone'] < 40 ? 'หม่น เศร้า หรือจริงจัง (Dark / Heavy)' : ((int)$dna['tone'] > 60 ? 'สว่าง สดใส ตลก (Light / Fluffy)' : 'สมดุล (Balanced Tone)');

try {
    // Fetch ONLY the requested book
    $stmt = $pdo->prepare('
        SELECT book_id, title, author, synopsis, category 
        FROM books 
        WHERE book_id = ?
    ');
    $stmt->execute([$target_book_id]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$book) {
        throw new Exception('ไม่พบข้อมูลหนังสือเล่มนี้');
    }

    $synopsis = mb_substr((string)$book['synopsis'], 0, 800);
    $catalogText = "=== TARGET BOOK ===\n";
    $catalogText .= "- ID: {$book['book_id']} | ชื่อเรื่อง: {$book['title']} | ผู้เขียน: {$book['author']} | หมวดหมู่: {$book['category']}\n";
    $catalogText .= "  เรื่องย่อ: {$synopsis}...\n";

    $systemInstruction = <<<TEXT
You are the 'Book Genome Engine'. You must decode the reader's DNA and analyze how it matches the TARGET BOOK.

# READER'S CURRENT PROFILE:
- Reading DNA (Pacing): {$paceLabel}
- Reading DNA (Complexity): {$complexityLabel}
- Reading DNA (Tone): {$toneLabel}
- Current Attention / Focus: "{$attention}"
- Environmental Context: Time = {$context['time']}, Environment = {$context['environment']}

# YOUR TASK:
Analyze the TARGET BOOK in relation to the Reader's Profile.
You MUST reply with a raw JSON object containing the following keys EXACTLY:
- "book_id": The ID of the TARGET BOOK (integer).
- "metrics": Object with 4 keys (values 0-100 integers): "emotional_depth", "intellectual_rigor", "narrative_pace", "interiority". These should reflect the book's true DNA.
- "insight_quote": A short, profound 1-sentence quote (in English or Thai) summarizing the book's core essence.
- "insight_text": A 1-2 paragraph analysis in Thai explaining WHY this book matches the user's DNA and Current Attention (write as if you are recommending this specific book to them).
- "tags": Array of 3-4 short string tags (e.g. ["#ปรัชญา", "#ฮีลใจ", "#พัฒนาตัวเอง"]).

IMPORTANT: Output ONLY valid JSON. No markdown backticks, no extra text.
TEXT;

    $prompt = "วิเคราะห์ DNA ของฉันและสร้างบทวิเคราะห์สำหรับหนังสือเล่มนี้ (ส่งเป็น JSON เท่านั้น)\n\n" . $catalogText;

    $config = wanawanDigitalTwinConfig();
    $apiKey = trim((string)($config['api_key'] ?? ''));
    $model = trim((string)($config['model'] ?? 'gemini-3.5-flash-lite'));

    if ($apiKey === '' || $apiKey === 'YOUR_GEMINI_API_KEY') {
        throw new Exception('ยังไม่ได้ตั้งค่ากุญแจ API สำหรับ AI');
    }

    $payload = [
        'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [['text' => $prompt]]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 1500,
            'responseMimeType' => 'application/json'
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    
    $raw = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($raw === false || $curlError !== '') {
        throw new Exception('ไม่สามารถเชื่อมต่อ AI ได้');
    }

    $data = json_decode((string)$raw, true);
    if ($status < 200 || $status >= 300) {
        throw new Exception($data['error']['message'] ?? 'เกิดข้อผิดพลาดจาก AI');
    }

    $response = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($response)) {
        throw new Exception('AI ไม่สามารถตอบกลับได้ในขณะนี้');
    }

    // Try parsing JSON
    $aiData = json_decode($response, true);
    if (!$aiData || !isset($aiData['book_id'])) {
        throw new Exception('รูปแบบข้อมูลที่ AI ส่งกลับมาไม่ถูกต้อง');
    }

    $bookId = (int)$aiData['book_id'];
    $booksMetadata = [];

    $metaStmt = $pdo->prepare("SELECT book_id, title, author, image_cover FROM books WHERE book_id = ?");
    $metaStmt->execute([$bookId]);
    $row = $metaStmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $booksMetadata[$row['book_id']] = [
            'title' => $row['title'],
            'author' => $row['author'],
            'image_cover' => str_starts_with($row['image_cover'] ?? '', 'http') 
                ? $row['image_cover'] 
                : '/assets/image/books/' . ($row['image_cover'] ?: 'placeholder.png')
        ];
    } else {
        throw new Exception('ไม่พบหนังสือที่ AI แนะนำในระบบ');
    }

    echo json_encode([
        'ok' => true, 
        'ai_data' => $aiData,
        'book' => $booksMetadata[$bookId]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
