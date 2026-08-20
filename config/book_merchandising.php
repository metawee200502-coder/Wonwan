<?php

function wanawanAnnotateBooks(PDO $pdo, array $books): array
{
    $ids = array_values(array_unique(array_filter(array_map(
        static fn(array $book): int => (int)($book['book_id'] ?? $book['id'] ?? 0), $books
    ))));
    if (!$ids) return $books;

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "WITH sales AS (
                SELECT oi.book_id, SUM(oi.quantity) sold_count
                FROM order_items oi INNER JOIN orders o ON o.order_id=oi.order_id
                WHERE o.payment_status NOT IN ('cancelled','failed') GROUP BY oi.book_id
            ), ranked AS (
                SELECT b.book_id,b.created_at,(b.book_type='new' OR b.condition_level='new') is_new_zone,COALESCE(s.sold_count,0) sold_count,
                       ROW_NUMBER() OVER (PARTITION BY CASE WHEN b.book_type='new' OR b.condition_level='new' THEN 'new' ELSE 'used' END
                           ORDER BY COALESCE(s.sold_count,0) DESC,b.book_id DESC) category_rank
                FROM books b LEFT JOIN sales s ON s.book_id=b.book_id WHERE b.status='available'
            ), interest AS (
                SELECT b.book_id,COUNT(DISTINCT w.wishlist_id) wishlist_count,
                       COALESCE((SELECT SUM(ci.quantity) FROM cart_items ci WHERE ci.book_id=b.book_id),0) cart_count,
                       COUNT(DISTINCT cc.conversation_id) inquiry_count
                FROM books b LEFT JOIN wishlists w ON w.book_id=b.book_id
                LEFT JOIN chat_conversations cc ON cc.referenced_book_id=b.book_id GROUP BY b.book_id
            )
            SELECT r.*,(r.sold_count*10+COALESCE(i.wishlist_count,0)*3+
                   COALESCE(i.cart_count,0)*2+COALESCE(i.inquiry_count,0)*2) recommendation_score
            FROM ranked r LEFT JOIN interest i ON i.book_id=r.book_id
            WHERE r.book_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $metadata = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $metadata[(int)$row['book_id']] = $row;

    foreach ($books as &$book) {
        $id = (int)($book['book_id'] ?? $book['id'] ?? 0);
        $meta = $metadata[$id] ?? null;
        if (!$meta) { $book['badge'] = ''; continue; }
        $sold = (int)$meta['sold_count'];
        $rank = (int)$meta['category_rank'];
        $isNewZone = !empty($meta['is_new_zone']);
        $book['sold_count'] = $sold;
        $book['category_rank'] = $rank;
        $book['recommendation_score'] = (int)$meta['recommendation_score'];
        if ($isNewZone && $sold > 0 && $rank <= 10) $book['badge'] = 'ขายดี Top '.$rank;
        elseif ($isNewZone && $sold > 0 && $rank <= 20) $book['badge'] = 'ขายดี';
        elseif (strtotime((string)$meta['created_at']) >= strtotime('-1 month')) $book['badge'] = 'มาใหม่';
        else $book['badge'] = '';
    }
    unset($book);
    return $books;
}

function wanawanSortRecommendedBooks(array $books): array
{
    usort($books, static fn(array $a,array $b): int =>
        ((int)($b['recommendation_score']??0) <=> (int)($a['recommendation_score']??0))
        ?: ((int)($b['book_id']??0) <=> (int)($a['book_id']??0)));
    return $books;
}

function wanawanSortBestsellerBooks(array $books): array
{
    usort($books, static fn(array $a, array $b): int =>
        ((int)($a['category_rank'] ?? PHP_INT_MAX) <=> (int)($b['category_rank'] ?? PHP_INT_MAX))
        ?: ((int)($b['sold_count'] ?? 0) <=> (int)($a['sold_count'] ?? 0)));
    return $books;
}
