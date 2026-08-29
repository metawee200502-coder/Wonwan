<?php
/**
 * process/get_book_distances.php
 *
 * รับพิกัดของผู้ดู (lat/lng) + รายการ book_id หรือ exchange_id
 * ส่งกลับ JSON: { "b_{id}": { "km": 12.5, "province": "กรุงเทพมหานคร" }, ... }
 *
 * ใช้ Haversine formula — ไม่ต้องใช้ external API
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Validate viewer coordinates ────────────────────────────────────────────
$viewerLat = isset($_GET['lat']) && is_numeric($_GET['lat']) ? (float)$_GET['lat'] : null;
$viewerLng = isset($_GET['lng']) && is_numeric($_GET['lng']) ? (float)$_GET['lng'] : null;

if ($viewerLat === null || $viewerLng === null
    || $viewerLat < -90 || $viewerLat > 90
    || $viewerLng < -180 || $viewerLng > 180
) {
    echo json_encode(['error' => 'invalid_coords']);
    exit;
}

// ── Parse requested IDs ────────────────────────────────────────────────────
// book_ids[]=1&book_ids[]=2   or   exchange_ids[]=5
$bookIds     = array_filter(array_map('intval', (array)($_GET['book_ids']     ?? [])));
$exchangeIds = array_filter(array_map('intval', (array)($_GET['exchange_ids'] ?? [])));

if (empty($bookIds) && empty($exchangeIds)) {
    echo json_encode([]);
    exit;
}

// ── Haversine (km) ─────────────────────────────────────────────────────────
function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371.0; // Earth radius km
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)**2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
    return $R * 2 * asin(sqrt($a));
}

// ── Human-readable distance label ─────────────────────────────────────────
function distanceLabel(float $km): string {
    if ($km < 1) {
        return round($km * 1000) . ' ม.';
    }
    if ($km < 10) {
        return number_format($km, 1) . ' กม.';
    }
    return number_format(round($km)) . ' กม.';
}

require_once __DIR__ . '/../config/config.php';

$result = [];

// ── Query books ────────────────────────────────────────────────────────────
if (!empty($bookIds)) {
    $in  = implode(',', array_fill(0, count($bookIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT book_id, seller_lat, seller_lng, seller_province
           FROM books
          WHERE book_id IN ($in)
            AND seller_lat IS NOT NULL AND seller_lng IS NOT NULL"
    );
    $stmt->execute(array_values($bookIds));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $km = haversineKm($viewerLat, $viewerLng, (float)$row['seller_lat'], (float)$row['seller_lng']);
        $result['b_' . $row['book_id']] = [
            'km'       => round($km, 1),
            'label'    => distanceLabel($km),
            'province' => (string)$row['seller_province'],
        ];
    }
}

// ── Query exchanges ────────────────────────────────────────────────────────
if (!empty($exchangeIds)) {
    $in  = implode(',', array_fill(0, count($exchangeIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT exchange_id, owner_lat, owner_lng, owner_province
           FROM exchanges
          WHERE exchange_id IN ($in)
            AND owner_lat IS NOT NULL AND owner_lng IS NOT NULL"
    );
    $stmt->execute(array_values($exchangeIds));
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $km = haversineKm($viewerLat, $viewerLng, (float)$row['owner_lat'], (float)$row['owner_lng']);
        $result['e_' . $row['exchange_id']] = [
            'km'       => round($km, 1),
            'label'    => distanceLabel($km),
            'province' => (string)$row['owner_province'],
        ];
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
