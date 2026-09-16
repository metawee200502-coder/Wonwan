<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 1) { echo json_encode([]); exit; }

$like = '%' . $q . '%';
$results = [];

try {
    // Books (new + used)
    $stmt = $pdo->prepare("
        SELECT book_id AS id, title, author, image_cover,
               CASE WHEN book_type='new' OR condition_level='new' THEN 'new' ELSE 'used' END AS cond,
               'book' AS type
        FROM books
        WHERE status='available'
          AND (title LIKE ? OR author LIKE ?)
        ORDER BY CASE WHEN title=? THEN 0 WHEN title LIKE ? THEN 1 ELSE 2 END, book_id DESC
        LIMIT 6
    ");
    $stmt->execute([$like, $like, $q, $q . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Exchanges (fill up to 8 total)
    $limit = max(0, 8 - count($results));
    if ($limit > 0) {
        $exStmt = $pdo->prepare("
            SELECT exchange_id AS id, title, author, image_cover,
                   'exchange' AS cond, 'exchange' AS type
            FROM exchanges
            WHERE status<>'hidden' AND (title LIKE ? OR author LIKE ?)
            ORDER BY CASE WHEN title=? THEN 0 WHEN title LIKE ? THEN 1 ELSE 2 END, exchange_id DESC
            LIMIT $limit
        ");
        $exStmt->execute([$like, $like, $q, $q . '%']);
        $results = array_merge($results, $exStmt->fetchAll(PDO::FETCH_ASSOC));
    }
} catch (Throwable $e) {
    echo json_encode([]);
    exit;
}

function acImg(?string $v): string {
    if (!$v) return '/assets/image/minilogo.png';
    if (preg_match('#^https?://#i', $v)) return $v;
    $v = ltrim(str_replace('\\', '/', $v), '/');
    if (str_starts_with($v, 'pages/') || str_starts_with($v, 'uploads/')) return '/pages/' . $v;
    return '/assets/image/books/' . $v;
}

$out = [];
foreach ($results as $row) {
    $out[] = [
        'id'     => (int)$row['id'],
        'title'  => (string)$row['title'],
        'author' => (string)($row['author'] ?? ''),
        'image'  => acImg($row['image_cover'] ?? null),
        'cond'   => (string)$row['cond'],
        'type'   => (string)$row['type'],
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
