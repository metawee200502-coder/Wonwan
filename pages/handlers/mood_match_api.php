<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/digital_twin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$history = $input['history'] ?? [];
$mood = trim((string)($input['mood'] ?? ''));

if ($mood === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'กรุณาระบุอารมณ์ความรู้สึกของคุณ']);
    exit;
}

try {
    // 1. Fetch a diverse catalog context (e.g., top available books across categories)
    $stmt = $pdo->query("
        SELECT book_id, title, author, category, synopsis, image_cover
        FROM books 
        WHERE status = 'available' AND stock_quantity > 0 
        ORDER BY RAND() 
        LIMIT 60
    ");
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($books)) {
        throw new RuntimeException('ไม่พบข้อมูลหนังสือในระบบ');
    }

    $catalogContext = "CATALOG BOOKS:\n";
    foreach ($books as $book) {
        $catalogContext .= sprintf(
            "- [book_id=%d] %s | ผู้เขียน: %s | หมวด: %s | ลิงก์สินค้า: /pages/book_detail.php?source=book&id=%d | เรื่องย่อ: %s\n",
            $book['book_id'],
            $book['title'],
            $book['author'] ?: 'ไม่ระบุ',
            $book['category'] ?: 'ไม่ระบุ',
            $book['book_id'],
            mb_strimwidth($book['synopsis'] ?: 'ไม่มีเรื่องย่อ', 0, 300, '...', 'UTF-8')
        );
    }

    // 2. Prepare Gemini Prompt
    $systemPrompt = "You are an expert empathetic librarian and book recommender named 'Mood Match'.\n"
        . "The user will input their current emotional state, feelings, or mood.\n"
        . "Analyze their input and recommend 2-3 books from our bookstore catalog that best match, soothe, or complement their emotional state.\n"
        . "Reply in a warm, conversational, and empathetic chat format in Thai.\n"
        . "For each book you recommend, you MUST include the title, author, a brief explanation of why it fits their mood, and a markdown link to the book using the exact 'ลิงก์สินค้า' from the CATALOG BOOKS. For example: [ชื่อหนังสือ](/pages/book_detail.php?source=book&id=...)\n"
        . "Do not invent books that are not in the CATALOG BOOKS.\n\n"
        . $catalogContext;

    $config = wanawanDigitalTwinConfig();
    $apiKey = trim((string)($config['api_key'] ?? ''));
    $model = trim((string)($config['model'] ?? 'gemini-3.5-flash-lite'));

    if ($apiKey === '' || $apiKey === 'YOUR_GEMINI_API_KEY') {
        throw new RuntimeException('ยังไม่ได้ตั้งค่ากุญแจ API สำหรับ AI');
    }

    $contents = [];
    if (is_array($history)) {
        foreach ($history as $msg) {
            $role = ($msg['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $text = (string)($msg['content'] ?? '');
            if ($text !== '') {
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $text]]
                ];
            }
        }
    }
    
    // Add the current message
    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $mood]]
    ];

    $payload = [
        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents' => $contents,
        'generationConfig' => [
            'maxOutputTokens' => 1000,
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
        throw new RuntimeException('ไม่สามารถเชื่อมต่อ AI ได้');
    }

    $data = json_decode((string)$raw, true);
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException($data['error']['message'] ?? 'เกิดข้อผิดพลาดจาก AI');
    }

    $replyText = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    if ($replyText === '') {
        throw new RuntimeException('AI ไม่สามารถตอบกลับได้ในขณะนี้');
    }

    $catalogMetadata = [];
    foreach ($books as $book) {
        $cover = $book['image_cover'];
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
        $catalogMetadata[$book['book_id']] = [
            'title' => $book['title'],
            'image_cover' => $cover
        ];
    }

    echo json_encode(['ok' => true, 'reply' => $replyText, 'books' => $catalogMetadata], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Mood Match Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
