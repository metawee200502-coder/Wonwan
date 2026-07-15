<?php
function handleImportCsv(PDO $pdo, int $seller_id, string $seller_type): array {
    if (empty($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'ไม่พบไฟล์ที่อัปโหลด'];
    }
    $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        return ['error' => 'รองรับเฉพาะไฟล์ .csv เท่านั้น'];
    }

    $handle        = fopen($_FILES['excel_file']['tmp_name'], 'r');
    $header        = fgetcsv($handle);
    $imported      = 0;
    $skipped       = 0;
    $import_errors = [];
    $row_num       = 1;

    while (($row = fgetcsv($handle)) !== false) {
        $row_num++;
        if (count($row) < 5) {
            $skipped++;
            $import_errors[] = "แถวที่ {$row_num}: จำนวนคอลัมน์ไม่ครบ";
            continue;
        }
        [$csv_title, $csv_author, $csv_isbn, $csv_price, $csv_stock,
         $csv_category, $csv_book_type,
         $csv_image_cover, $csv_image_back, $csv_image_inside, $csv_image_edge]
            = array_pad($row, 11, '');

        $csv_image_cover  = trim($csv_image_cover);
        $csv_image_back   = trim($csv_image_back);
        $csv_image_inside = trim($csv_image_inside);
        $csv_image_edge   = trim($csv_image_edge);

        $valid_url = fn($u) => $u === '' || preg_match('#^https?://#i', $u);
        if (!$valid_url($csv_image_cover) || !$valid_url($csv_image_back)
            || !$valid_url($csv_image_inside) || !$valid_url($csv_image_edge)) {
            $skipped++;
            $import_errors[] = "แถวที่ {$row_num}: ลิงก์รูปต้องขึ้นต้นด้วย http:// หรือ https://";
            continue;
        }
        if (!$csv_title || !$csv_price) {
            $skipped++;
            $import_errors[] = "แถวที่ {$row_num}: ไม่มีชื่อหนังสือหรือราคา";
            continue;
        }
        if (!$csv_image_cover) {
            $skipped++;
            $import_errors[] = "แถวที่ {$row_num}: ต้องใส่ลิงก์รูปหน้าปก";
            continue;
        }
        try {
            $stmt = $pdo->prepare("
                INSERT INTO books (
                    seller_id, seller_type, title, author, isbn, price,
                    stock_quantity, category, book_type, condition_level,
                    image_cover, image_back, image_inside, image_edge,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available', NOW())
            ");
            $book_type_val = in_array(trim($csv_book_type), ['new','used']) ? trim($csv_book_type) : 'used';
            $stmt->execute([
                $seller_id, $seller_type,
                trim($csv_title), trim($csv_author),
                trim($csv_isbn) ?: null,
                (float)$csv_price,
                (int)($csv_stock ?: 1),
                trim($csv_category) ?: null,
                $book_type_val,
                $book_type_val === 'new' ? 'new' : 'good',
                $csv_image_cover,
                $csv_image_back   ?: null,
                $csv_image_inside ?: null,
                $csv_image_edge   ?: null,
            ]);
            $imported++;
        } catch (PDOException $e) {
            $skipped++;
            $import_errors[] = "แถวที่ {$row_num}: " . $e->getMessage();
        }
    }
    fclose($handle);

    $msg = "นำเข้าสำเร็จ {$imported} รายการ!" . ($skipped ? " (ข้าม {$skipped} แถว)" : "");
    if ($skipped && $imported === 0) {
        return ['error' => $msg . "\n" . implode("\n", array_slice($import_errors, 0, 5))];
    }
    return ['success' => $msg];
}