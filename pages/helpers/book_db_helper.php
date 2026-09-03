<?php
function normalizeBookPublishDate(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    foreach (['!d-m-Y', '!Y-m-d'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $date->format('Y-m-d');
        }
    }
    return null;
}

function collectBookFields(string $seller_name): array {
    // Sanitise GPS inputs — accept only numeric-ish values
    $rawLat = trim($_POST['seller_lat'] ?? '');
    $rawLng = trim($_POST['seller_lng'] ?? '');
    $lat = is_numeric($rawLat) ? (float)$rawLat : null;
    $lng = is_numeric($rawLng) ? (float)$rawLng : null;
    // Clamp to valid WGS84 range
    if ($lat !== null && ($lat < -90  || $lat > 90))  $lat = null;
    if ($lng !== null && ($lng < -180 || $lng > 180)) $lng = null;
    if ($lat === null || $lng === null) { $lat = null; $lng = null; }
    $province = ($lat !== null) ? mb_substr(trim($_POST['seller_province'] ?? ''), 0, 100) : null;
    return [
        'title'           => trim($_POST['title'] ?? ''),
        'author'          => trim($_POST['author'] ?? ''),
        'isbn'            => trim($_POST['isbn'] ?? ''),
        'isbn_13'         => trim($_POST['isbn_13'] ?? ''),
        'publisher_name'  => trim($_POST['publisher_name'] ?? $seller_name),
        'category'        => trim($_POST['category'] ?? ''),
        'format'          => trim($_POST['format'] ?? ''),
        'pages'           => (int)($_POST['pages'] ?? 0),
        'dimensions'      => trim($_POST['dimensions'] ?? ''),
        'weight_grams'    => (int)($_POST['weight_grams'] ?? 0),
        'language'        => trim($_POST['language'] ?? 'ไทย'),
        'publish_date'    => normalizeBookPublishDate((string)($_POST['publish_date'] ?? '')),
        'synopsis'        => trim($_POST['synopsis'] ?? ''),
        'condition_level' => $_POST['condition_level'] ?? 'new',
        'defects_detail'  => trim($_POST['defects_detail'] ?? ''),
        'price'           => (float)($_POST['price'] ?? 0),
        'original_price'  => (float)($_POST['original_price'] ?? 0),
        'stock_quantity'  => (int)($_POST['stock_quantity'] ?? 1),
        'book_type'       => $_POST['book_type'] ?? 'new',
        'shipping_json'   => json_encode($_POST['shipping'] ?? []),
        // GPS
        'seller_lat'      => $lat,
        'seller_lng'      => $lng,
        'seller_province' => $province ?: null,
    ];
}

function insertBook(PDO $pdo, array $f, array $imgs, int $seller_id, string $seller_type, string $status): void {
    $table = $seller_type === 'shop' ? 'shops' : 'users';
    $idCol = $seller_type === 'shop' ? 'shop_id' : 'user_id';
    
    $stmtLoc = $pdo->prepare("SELECT latitude, longitude FROM $table WHERE $idCol = ?");
    $stmtLoc->execute([$seller_id]);
    $sellerLoc = $stmtLoc->fetch(PDO::FETCH_ASSOC);
    
    if ($sellerLoc && $sellerLoc['latitude'] !== null && $sellerLoc['longitude'] !== null) {
        $f['seller_lat'] = $sellerLoc['latitude'];
        $f['seller_lng'] = $sellerLoc['longitude'];
    } elseif ($f['seller_lat'] !== null && $f['seller_lng'] !== null) {
        $updateStmt = $pdo->prepare("UPDATE $table SET latitude = ?, longitude = ? WHERE $idCol = ?");
        $updateStmt->execute([$f['seller_lat'], $f['seller_lng'], $seller_id]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO books (
            seller_id, seller_type, isbn, isbn_13, title, author, publisher_name,
            category, format, pages, dimensions, weight_grams, language,
            publish_date, synopsis, condition_level, defects_detail,
            price, stock_quantity, original_price,
            image_cover, image_back, image_inside, image_edge,
            shipping_methods, seller_lat, seller_lng, seller_province,
            status, book_type, created_at
        ) VALUES (
            :seller_id, :seller_type, :isbn, :isbn_13, :title, :author, :publisher_name,
            :category, :format, :pages, :dimensions, :weight_grams, :language,
            :publish_date, :synopsis, :condition_level, :defects_detail,
            :price, :stock_quantity, :original_price,
            :image_cover, :image_back, :image_inside, :image_edge,
            :shipping_methods, :seller_lat, :seller_lng, :seller_province,
            :status, :book_type, NOW()
        )
    ");
    $stmt->execute([
        ':seller_id'        => $seller_id,
        ':seller_type'      => $seller_type,
        ':isbn'             => $f['isbn'] ?: null,
        ':isbn_13'          => $f['isbn_13'] ?: null,
        ':title'            => $f['title'],
        ':author'           => $f['author'] ?: null,
        ':publisher_name'   => $f['publisher_name'] ?: null,
        ':category'         => $f['category'] ?: null,
        ':format'           => $f['format'] ?: null,
        ':pages'            => $f['pages'] ?: null,
        ':dimensions'       => $f['dimensions'] ?: null,
        ':weight_grams'     => $f['weight_grams'] ?: null,
        ':language'         => $f['language'],
        ':publish_date'     => $f['publish_date'],
        ':synopsis'         => $f['synopsis'] ?: null,
        ':condition_level'  => $f['condition_level'],
        ':defects_detail'   => $f['defects_detail'] ?: null,
        ':price'            => $f['price'] ?: null,
        ':stock_quantity'   => $f['stock_quantity'],
        ':original_price'   => $f['original_price'] ?: null,
        ':image_cover'      => $imgs['image_cover'],
        ':image_back'       => $imgs['image_back'],
        ':image_inside'     => $imgs['image_inside'],
        ':image_edge'       => $imgs['image_edge'],
        ':shipping_methods' => $f['shipping_json'],
        ':seller_lat'       => $f['seller_lat'] ?? null,
        ':seller_lng'       => $f['seller_lng'] ?? null,
        ':seller_province'  => $f['seller_province'] ?? null,
        ':status'           => $status,
        ':book_type'        => $f['book_type'],
    ]);
}
