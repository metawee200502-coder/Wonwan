<?php
function handleSaveBook(PDO $pdo, int $seller_id, string $seller_type, string $seller_name, string $status): array {
    require_once __DIR__ . '/../helpers/book_db_helper.php';
    require_once __DIR__ . '/../helpers/book_image_helper.php';

    $f = collectBookFields($seller_name);
    $f['publisher_name'] = $seller_name;

    if (!$f['title']) {
        return ['error' => 'กรุณากรอกชื่อหนังสืออย่างน้อย 1 ช่อง'];
    }
    if ($status === 'available' && !$f['price']) {
        return ['error' => 'กรุณากรอกราคาขายให้ครบ'];
    }
    if (trim((string)($_POST['publish_date'] ?? '')) !== '' && $f['publish_date'] === null) {
        return ['error' => 'วันที่พิมพ์ต้องเป็นรูปแบบ DD-MM-YYYY เช่น 15-07-2026'];
    }

    try {
        // path จาก pages/ ขึ้น 1 ระดับไปที่ root แล้วเข้า uploads/books/
        $upload_base = dirname(__DIR__, 1) . '/uploads/books/';
        if (!is_dir($upload_base)) mkdir($upload_base, 0755, true);
        $imgs = handleBookImages(['image_cover','image_back','image_inside','image_edge'], $upload_base);
        insertBook($pdo, $f, $imgs, $seller_id, $seller_type, $status);
        return ['success' => $status === 'draft' ? 'บันทึกร่างเรียบร้อย!' : 'เพิ่มหนังสือสำเร็จ!'];
    } catch (PDOException $e) {
        return ['error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
}
