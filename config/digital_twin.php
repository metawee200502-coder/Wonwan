<?php
declare(strict_types=1);

function wanawanDigitalTwinConfig(): array
{
    $path = __DIR__ . '/ai.local.php';
    if (!is_file($path)) return [];
    $config = require $path;
    return is_array($config) ? $config : [];
}

function wanawanDigitalTwinItem(PDO $pdo, string $type, int $id): ?array
{
    if ($type === 'exchange') {
        $stmt = $pdo->prepare("SELECT exchange_id item_id,title,author,category,isbn,story_reason synopsis,wanted_detail,condition_percent,shipping_methods,'exchange' item_type FROM exchanges WHERE exchange_id=? AND status<>'completed' LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT book_id item_id,title,author,category,COALESCE(NULLIF(isbn_13,''),isbn) isbn,synopsis,publisher_name,format,pages,language,publish_date,price,book_type,condition_level,defects_detail,'book' item_type FROM books WHERE book_id=? AND status<>'hidden' LIMIT 1");
    }
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    return $item ?: null;
}

function wanawanDigitalTwinRelatedBooks(PDO $pdo, array $current, string $question): array
{
    $stmt = $pdo->prepare("SELECT book_id,title,author,category,synopsis,publisher_name,price,book_type FROM books WHERE status='available' AND stock_quantity>0 AND book_id<>? ORDER BY (category<=>?) DESC,created_at DESC LIMIT 120");
    $stmt->execute([$current['item_type'] === 'book' ? (int)$current['item_id'] : 0, $current['category'] ?? null]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $questionLower = mb_strtolower($question);
    $wantsCheaper = (bool)preg_match('/ถูกกว่า|ราคาถูก|ประหยัด|งบ(?:ไม่เกิน|น้อย)|ราคาต่ำกว่า/u', $questionLower);
    $maxBudget = null;
    if (preg_match('/(?:ราคา|งบ)?\s*(?:ไม่เกิน|ต่ำกว่า|ไม่เกินงบ)\s*([0-9,]+)|งบ\s*([0-9,]+)/u', $questionLower, $budgetMatch)) {
        $budgetValue = ($budgetMatch[1] ?? '') !== '' ? $budgetMatch[1] : ($budgetMatch[2] ?? '');
        $maxBudget = (float)str_replace(',', '', (string)$budgetValue);
    }
    $currentPrice = isset($current['price']) ? (float)$current['price'] : null;
    $sourceWords = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string)($current['title'] ?? '') . ' ' . (string)($current['synopsis'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $sourceWords = array_values(array_unique(array_filter($sourceWords, fn(string $word): bool => mb_strlen($word) >= 3 && !in_array($word, ['หนังสือ','เรื่องราว','เกี่ยวกับ','และ','หรือ','ของ','ที่','เป็น','การ','ใน','จาก','กับ','เพื่อ','เมื่อ'], true))));
    foreach ($rows as &$row) {
        $score = (($row['category'] ?? '') !== '' && ($row['category'] ?? '') === ($current['category'] ?? '')) ? 10 : 0;
        $candidateText = mb_strtolower((string)$row['title'] . ' ' . (string)$row['author'] . ' ' . (string)$row['synopsis']);
        $overlap = 0;
        foreach ($sourceWords as $word) {
            if (mb_stripos($candidateText, $word) !== false) $overlap++;
        }
        $score += min(16, $overlap * 2);
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $questionLower, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (mb_strlen($word) >= 2 && mb_stripos($candidateText, $word) !== false) $score += 8;
        }
        foreach (['รัก','โรแมนติก','แฟนตาซี','สืบสวน','ระทึก','ดราม่า','วาย','ประวัติศาสตร์','จิตวิทยา','พัฒนาตนเอง','สุขภาพ','การ์ตูน','เด็ก','ครอบครัว'] as $theme) {
            if (mb_stripos($questionLower, $theme) !== false && mb_stripos($candidateText, $theme) !== false) $score += 12;
        }
        if ($currentPrice !== null && (float)$row['price'] < $currentPrice) $score += $wantsCheaper ? 20 : 2;
        if ($currentPrice !== null) $row['_price_difference'] = $currentPrice - (float)$row['price'];
        $row['_score'] = $score;
    }
    unset($row);
    if ($wantsCheaper && $currentPrice !== null) {
        $rows = array_values(array_filter($rows, fn(array $row): bool => (float)$row['price'] < $currentPrice));
    }
    if ($maxBudget !== null && $maxBudget > 0) {
        $rows = array_values(array_filter($rows, fn(array $row): bool => (float)$row['price'] <= $maxBudget));
    }
    usort($rows, function(array $a, array $b) use ($wantsCheaper): int {
        $scoreOrder = $b['_score'] <=> $a['_score'];
        if ($scoreOrder !== 0) return $scoreOrder;
        return $wantsCheaper ? ((float)$a['price'] <=> (float)$b['price']) : 0;
    });
    return array_slice($rows, 0, 15);
}

function wanawanDigitalTwinPrompt(array $item, array $related): string
{
    $facts = [];
    foreach ($item as $key => $value) {
        if ($value !== null && $value !== '' && !in_array($key, ['item_id','item_type'], true)) $facts[] = $key . ': ' . $value;
    }
    $relatedFacts = [];
    foreach ($related as $book) {
        $relatedFacts[] = sprintf(
            '- %s | ผู้เขียน: %s | หมวด: %s | ราคา: ฿%s | ลิงก์: /pages/book_detail.php?id=%d | เรื่องย่อ: %s',
            (string)$book['title'], (string)($book['author'] ?: '-'),
            (string)($book['category'] ?: '-'), number_format((float)$book['price'], 2),
            (int)$book['book_id'],
            mb_strimwidth((string)($book['synopsis'] ?: 'ไม่มีข้อมูล'), 0, 500, '…', 'UTF-8')
        );
    }
    $isDiscover = (int)$item['item_id'] === 0;
    $mainUrl = $isDiscover ? '' : '/pages/book_detail.php?source=' . ($item['item_type'] === 'exchange' ? 'exchange' : 'book') . '&id=' . (int)$item['item_id'];
    $identity = $isDiscover
        ? "คุณคือ Digital Twin ผู้ช่วยค้นหาและพูดคุยเรื่องหนังสือของเว็บไซต์ Wonwan Web คุณมีความรู้กว้างขวางเกี่ยวกับหนังสือและสามารถสนทนาได้อย่างเป็นธรรมชาติ"
        : "คุณคือ Digital Twin ของหนังสือในเว็บไซต์ Wonwan Web เป็นเพื่อนคุยเรื่องหนังสือที่ฉลาด รอบรู้ และสามารถรีวิวหรือเล่าเกร็ดความรู้ได้อย่างไหลลื่น";
    return $identity . "\n\n"
        . "คำแนะนำในการตอบ:\n"
        . "1. ตอบภาษาไทยแบบเป็นธรรมชาติ เป็นมิตร ไหลลื่น เหมือนเพื่อนคุยเรื่องหนังสือด้วยกัน\n"
        . "2. อนุญาตให้ใช้ความรู้รอบตัว (Knowledge) ของคุณในการรีวิว วิเคราะห์ แนะนำ หรือเล่าเกร็ดความรู้เกี่ยวกับหนังสือ ผู้เขียน หรือแนวเรื่องได้อย่างเต็มที่ เพื่อให้การสนทนามีสีสันและมีประโยชน์สูงสุด\n"
        . "3. ไม่ต้องบอกว่า 'ไม่มีข้อมูล' หรือ 'ไม่ได้ค้นเว็บ' หากคุณมีความรู้ในเรื่องนั้น ให้ตอบอย่างมั่นใจและสร้างสรรค์ได้เลย\n"
        . "4. หากลูกค้าขอให้แนะนำหนังสือที่คล้ายกัน หรือมีเงื่อนไขราคา ให้ดึงหนังสือจาก \"รายการคลัง Wonwan\" ด้านล่างมาแนะนำเป็นอันดับแรก พร้อมอธิบายจุดเด่น และราคา\n"
        . "5. **สำคัญมาก:** เวลาแนะนำหนังสือ ให้แนบลิงก์สินค้าซ่อนไว้ในชื่อหนังสือด้วยรูปแบบ Markdown เสมอ (เช่น `[ชื่อหนังสือ](/pages/book_detail.php?id=123)`) **ห้ามพิมพ์หัวข้อ 'ลิงก์สินค้า' หรือแสดง URL แบบดิบๆ ออกมาตรงๆ เด็ดขาด**\n"
        . "6. หากหนังสือที่ลูกค้าถามถึง ไม่มีในรายการ Wonwan ด้านล่าง คุณสามารถวิจารณ์และแนะนำหนังสือจากความรู้ของคุณเองได้ แต่ **ห้ามสร้างลิงก์ Wonwan ให้หนังสือนอกคลังเด็ดขาด**\n"
        . "7. หลีกเลี่ยงการสปอยล์เนื้อหาสำคัญที่ทำลายอรรถรส เว้นแต่ลูกค้าจะร้องขออย่างชัดเจน\n"
        . "8. ตอบให้กระชับพอดี ไม่ต้องยาวเป็นบทความวิชาการ แต่ให้อ่านเพลินและได้ใจความ\n\n"
        . ($isDiscover ? "โหมดนี้ยังไม่ได้เลือกหนังสือเล่มหลัก ให้พิจารณาความต้องการจากคำถามของลูกค้า\n\n" : "ข้อมูลเบื้องต้นของเล่มหลักที่ลูกค้ากำลังดูอยู่:\n" . implode("\n", $facts) . "\nลิงก์เล่มหลัก: {$mainUrl}\n\n")
        . "หนังสือที่เกี่ยวข้องในคลัง Wonwan (ใช้สำหรับแนะนำพร้อมแนบลิงก์):\n" . ($relatedFacts ? implode("\n", $relatedFacts) : '- ไม่พบรายการ') . "\n";
}

function wanawanGeminiReply(array $config, string $systemPrompt, array $history): string
{
    $apiKey = trim((string)($config['api_key'] ?? ''));
    $model = trim((string)($config['model'] ?? 'gemini-3.5-flash-lite'));
    if ($apiKey === '' || $apiKey === 'YOUR_GEMINI_API_KEY') {
        throw new RuntimeException('Digital Twin ยังไม่ได้ตั้งค่ากุญแจ AI');
    }
    $contents = [];
    foreach ($history as $message) {
        $contents[] = [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => (string)$message['content']]],
        ];
    }
    $payload = [
        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents' => $contents,
        'generationConfig' => ['maxOutputTokens' => 1200],
    ];
    if (!array_key_exists('web_search', $config) || !empty($config['web_search'])) {
        $payload['tools'] = [['google_search' => new stdClass()]];
    }
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
    if ($raw === false || $curlError !== '') throw new RuntimeException('ไม่สามารถเชื่อมต่อบริการ AI ได้ กรุณาลองใหม่');
    $data = json_decode((string)$raw, true);
    if ($status < 200 || $status >= 300) {
        error_log('Digital Twin Gemini error: ' . $status . ' ' . mb_strimwidth((string)$raw, 0, 800));
        $errorMessage = (string)($data['error']['message'] ?? '');
        $searchEnabled = !empty($payload['tools']);
        if ($searchEnabled && ($status === 429 || stripos($errorMessage, 'quota') !== false || stripos($errorMessage, 'billing') !== false)) {
            $fallbackConfig = $config;
            $fallbackConfig['web_search'] = false;
            $fallbackPrompt = $systemPrompt . "\n\n(หมายเหตุระบบ: ขณะนี้ระบบค้นหาเว็บภายนอกไม่พร้อมใช้งาน ให้คุณตอบอย่างเป็นธรรมชาติจากความรู้ที่มีอยู่ในโมเดลของคุณเองเป็นหลักโดยไม่ต้องแจ้งให้ลูกค้าทราบ)";
            return wanawanGeminiReply($fallbackConfig, $fallbackPrompt, $history);
        }
        throw new RuntimeException($errorMessage ?: 'บริการ AI ตอบกลับผิดพลาด กรุณาลองใหม่');
    }
    $candidate = $data['candidates'][0] ?? [];
    $parts = $candidate['content']['parts'] ?? [];
    $reply = trim(implode("\n", array_map(fn(array $part): string => (string)($part['text'] ?? ''), $parts)));
    if ($reply === '') throw new RuntimeException('AI ยังไม่สามารถตอบคำถามนี้ได้ กรุณาลองถามใหม่');
    $sources = [];
    foreach (($candidate['groundingMetadata']['groundingChunks'] ?? []) as $chunk) {
        $web = $chunk['web'] ?? [];
        $uri = trim((string)($web['uri'] ?? ''));
        if ($uri === '' || !preg_match('#^https?://#i', $uri)) continue;
        $title = trim((string)($web['title'] ?? 'แหล่งข้อมูล')) ?: 'แหล่งข้อมูล';
        $sources[$uri] = $title;
        if (count($sources) >= 5) break;
    }
    if ($sources) {
        $reply .= "\n\nแหล่งข้อมูลภายนอกที่ใช้ตรวจสอบ:\n";
        foreach ($sources as $uri => $title) $reply .= '- [' . str_replace([']', "\n"], ['', ' '], $title) . '](' . $uri . ")\n";
        $reply = rtrim($reply);
    }
    return $reply;
}
