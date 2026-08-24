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

// Labels for DNA scores (0-100)
$paceLabel = (int)$dna['pace'] < 40 ? 'ค่อยเป็นค่อยไป (Slow-burn)' : ((int)$dna['pace'] > 60 ? 'เดินเรื่องเร็ว (Fast-paced)' : 'ปานกลาง (Balanced Pace)');
$complexityLabel = (int)$dna['complexity'] < 40 ? 'อ่านง่าย สบายๆ (Simple & Easy)' : ((int)$dna['complexity'] > 60 ? 'ซับซ้อน ลุ่มลึก (Complex & Deep)' : 'ระดับมาตรฐาน (Standard)');
$toneLabel = (int)$dna['tone'] < 40 ? 'หม่น เศร้า หรือจริงจัง (Dark / Heavy)' : ((int)$dna['tone'] > 60 ? 'สว่าง สดใส ตลก (Light / Fluffy)' : 'สมดุล (Balanced Tone)');

try {
    // Fetch random ~100 available books
    $stmt = $pdo->query('
        SELECT book_id, title, author, synopsis, category 
        FROM books 
        WHERE status = "available" 
        ORDER BY RAND() 
        LIMIT 100
    ');
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $catalogText = "=== CATALOG BOOKS ===\n";
    foreach ($books as $b) {
        $synopsis = mb_substr((string)$b['synopsis'], 0, 200);
        $catalogText .= "- ID: {$b['book_id']} | ชื่อเรื่อง: {$b['title']} | ผู้เขียน: {$b['author']} | หมวดหมู่: {$b['category']}\n";
        $catalogText .= "  เรื่องย่อ: {$synopsis}...\n";
    }

    $systemInstruction = <<<TEXT
You are the 'Book Genome Engine'. You must decode the reader's DNA and find the best matching books from the CATALOG BOOKS.

# READER'S CURRENT PROFILE:
- Reading DNA (Pacing): {$paceLabel}
- Reading DNA (Complexity): {$complexityLabel}
- Reading DNA (Tone): {$toneLabel}
- Current Attention / Focus: "{$attention}"
- Environmental Context: Time = {$context['time']}, Environment = {$context['environment']}

# YOUR TASK:
Select EXACTLY 12 books from the CATALOG BOOKS that best match the reader's profile.
You MUST reply with a raw JSON array of integers containing ONLY the 12 matched book_ids.
Example: [1, 45, 12, 89, ...]

IMPORTANT: Output ONLY a valid JSON array of numbers. No markdown backticks, no extra text.
TEXT;

    $prompt = "วิเคราะห์ DNA ของฉันและแนะนำหนังสือที่ตรงที่สุด 12 เล่ม (ส่งเป็น JSON Array เท่านั้น)\n\n" . $catalogText;

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
            'maxOutputTokens' => 500,
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

    // Try parsing JSON array
    $matchedIds = json_decode($response, true);
    if (!is_array($matchedIds) || empty($matchedIds)) {
        throw new Exception('รูปแบบข้อมูลที่ AI ส่งกลับมาไม่ถูกต้อง');
    }

    // Fetch full book details for these IDs
    $booksMetadata = [];
    $inQuery = implode(',', array_fill(0, count($matchedIds), '?'));
    $metaStmt = $pdo->prepare("
        SELECT book_id, title, author, image_cover 
        FROM books 
        WHERE book_id IN ($inQuery)
    ");
    $metaStmt->execute($matchedIds);
    
    while ($row = $metaStmt->fetch(PDO::FETCH_ASSOC)) {
        $cover = $row['image_cover'];
        if ($cover) {
            if (!preg_match('#^https?://#i', $cover)) {
                $cover = ltrim(str_replace('\\', '/', $cover), '/');
                if (str_starts_with($cover, 'pages/') || str_starts_with($cover, 'uploads/')) {
                    $cover = '/' . $cover;
                } else {
                    $cover = '/assets/image/books/' . $cover;
                }
            }
        } else {
            $cover = '/assets/image/minilogo.png';
        }
        
        $booksMetadata[] = [
            'book_id' => $row['book_id'],
            'title' => $row['title'],
            'author' => $row['author'],
            'image_cover' => $cover
        ];
    }

    echo json_encode([
        'ok' => true, 
        'books' => $booksMetadata
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
