<?php

/**
 * เลือกหนังสือที่เหมาะกับสินค้าในขั้นตอนชำระเงิน
 *
 * โมเดลนี้เป็น recommendation scoring ที่ทำงานในระบบโดยไม่ส่งข้อมูลลูกค้าออกไปภายนอก
 * ใช้ความสัมพันธ์ของหมวดหมู่ ผู้แต่ง สำนักพิมพ์ ร้านค้า ยอดขาย และพฤติกรรมเดิมของลูกค้า
 * จึงใช้งานได้ทันที และสามารถเปลี่ยนส่วน scoring เป็น AI API ภายหลังได้โดยหน้า checkout ไม่ต้องแก้
 */
function wanawanCheckoutRecommendations(PDO $pdo, int $userId, array $cartItems, int $limit = 4, int $refreshRound = 0): array
{
    $cartIds = array_values(array_unique(array_map('intval', array_column($cartItems, 'book_id'))));
    if (!$cartIds) return [];

    $categories = array_values(array_filter(array_unique(array_map(
        static fn(array $item): string => mb_strtolower(trim((string)($item['category'] ?? ''))),
        $cartItems
    ))));
    $authors = array_values(array_filter(array_unique(array_map(
        static fn(array $item): string => mb_strtolower(trim((string)($item['author'] ?? ''))),
        $cartItems
    ))));
    $publishers = array_values(array_filter(array_unique(array_map(
        static fn(array $item): string => mb_strtolower(trim((string)($item['publisher_name'] ?? ''))),
        $cartItems
    ))));
    $sellerKeys = array_fill_keys(array_map(
        static fn(array $item): string => ($item['seller_type'] ?? '').'-'.(int)($item['seller_id'] ?? 0),
        $cartItems
    ), true);

    // ความชอบระยะยาวจากหมวดที่ลูกค้าเคยซื้อ ช่วยให้ผลไม่อิงเฉพาะตะกร้าปัจจุบัน
    $history = $pdo->prepare("SELECT LOWER(TRIM(b.category)) category, SUM(oi.quantity) weight
        FROM orders o JOIN order_items oi ON oi.order_id=o.order_id JOIN books b ON b.book_id=oi.book_id
        WHERE o.user_id=? AND o.payment_status NOT IN ('cancelled','failed')
          AND b.category IS NOT NULL AND b.category<>''
        GROUP BY LOWER(TRIM(b.category))");
    $history->execute([$userId]);
    $historyWeights = [];
    foreach ($history->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $historyWeights[(string)$row['category']] = min(15, (int)$row['weight'] * 3);
    }

    $marks = implode(',', array_fill(0, count($cartIds), '?'));
    $sql = "SELECT b.*,
        COALESCE((SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON o.order_id=oi.order_id
                  WHERE oi.book_id=b.book_id AND o.payment_status NOT IN ('cancelled','failed')),0) sold_count,
        COALESCE((SELECT SUM(ci.quantity) FROM cart_items ci WHERE ci.book_id=b.book_id),0) cart_interest,
        COALESCE((SELECT SUM(related.quantity)
                  FROM order_items seed
                  JOIN order_items related ON related.order_id=seed.order_id AND related.book_id=b.book_id
                  JOIN orders co ON co.order_id=seed.order_id
                  WHERE seed.book_id IN ($marks) AND co.payment_status NOT IN ('cancelled','failed')),0) pair_count
        FROM books b
        WHERE b.status='available' AND b.stock_quantity>0 AND b.book_id NOT IN ($marks)
        ORDER BY pair_count DESC, sold_count DESC, b.created_at DESC
        LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($cartIds, $cartIds));
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($candidates as &$book) {
        $category = mb_strtolower(trim((string)($book['category'] ?? '')));
        $author = mb_strtolower(trim((string)($book['author'] ?? '')));
        $publisher = mb_strtolower(trim((string)($book['publisher_name'] ?? '')));
        $sameCategory = $category !== '' && in_array($category, $categories, true);
        $sameAuthor = $author !== '' && in_array($author, $authors, true);
        $samePublisher = $publisher !== '' && in_array($publisher, $publishers, true);
        $sameSeller = isset($sellerKeys[($book['seller_type'] ?? '').'-'.(int)($book['seller_id'] ?? 0)]);
        $pairCount = (int)$book['pair_count'];
        $sold = (int)$book['sold_count'];
        $interest = (int)$book['cart_interest'];
        $promotionGap = null;
        $candidatePromotion = function_exists('wanawanActiveBookPromotion') ? wanawanActiveBookPromotion($pdo,(int)$book['book_id']) : null;
        if($candidatePromotion){
            $eligibleQuantity=0;$scope=$candidatePromotion['scope_type']??'all';$categoryTargets=[];$bookTargets=[];
            if($scope!=='all'){
                $targetStmt=$pdo->prepare('SELECT target_type,target_value FROM seller_promotion_targets WHERE promotion_id=?');$targetStmt->execute([$candidatePromotion['promotion_id']]);
                foreach($targetStmt->fetchAll(PDO::FETCH_ASSOC) as $target){if($target['target_type']==='category')$categoryTargets[]=(string)$target['target_value'];else $bookTargets[]=(int)$target['target_value'];}
            }
            foreach($cartItems as $cartItem){
                if(($cartItem['seller_type']??'')!==($book['seller_type']??'')||(int)($cartItem['seller_id']??0)!==(int)($book['seller_id']??0))continue;
                $eligible=$scope==='all'||($scope==='category'&&in_array((string)($cartItem['category']??''),$categoryTargets,true))||($scope==='books'&&in_array((int)($cartItem['book_id']??0),$bookTargets,true));
                if($eligible)$eligibleQuantity+=(int)$cartItem['quantity'];
            }
            $promotionGap=max(0,(int)$candidatePromotion['minimum_quantity']-$eligibleQuantity);
        }

        $book['ai_score'] = ($sameCategory ? 42 : 0)
            + ($sameAuthor ? 30 : 0)
            + ($samePublisher ? 12 : 0)
            + ($sameSeller ? 10 : 0)
            + min(35, $pairCount * 7)
            + min(18, $sold * 2)
            + min(8, $interest)
            + ($historyWeights[$category] ?? 0)
            + ($promotionGap===1?90:($promotionGap!==null&&$promotionGap<=3?45:0))
            + ($refreshRound>0?(crc32($userId.'|'.$refreshRound.'|'.$book['book_id'])%31):0);

        if ($candidatePromotion&&$promotionGap===1) $book['ai_reason'] = 'เพิ่มอีก 1 เล่มเพื่อรับโปร '.$candidatePromotion['display_discount'].' · '.$candidatePromotion['promotion_name'];
        elseif($candidatePromotion&&$promotionGap!==null&&$promotionGap>1&&$promotionGap<=3)$book['ai_reason']='เหลืออีก '.$promotionGap.' เล่มจะเข้าเงื่อนไข '.$candidatePromotion['promotion_name'];
        elseif ($pairCount > 0) $book['ai_reason'] = 'ลูกค้าที่เลือกหนังสือคล้ายกันมักซื้อเล่มนี้ด้วย';
        elseif ($sameAuthor) $book['ai_reason'] = 'ผู้แต่งเดียวกับหนังสือที่คุณกำลังเลือก';
        elseif ($sameCategory) $book['ai_reason'] = 'เนื้อหาเข้ากับหมวดที่คุณกำลังสนใจ';
        elseif (($historyWeights[$category] ?? 0) > 0) $book['ai_reason'] = 'คัดจากหมวดหนังสือที่คุณชอบ';
        else $book['ai_reason'] = 'กำลังได้รับความนิยมจากนักอ่าน';

        $price = (float)$book['price'];
        $original = (float)($book['original_price'] ?? 0);
        $book['promo_percent'] = $original > $price && $original > 0
            ? (int)round((1 - $price / $original) * 100)
            : 5;
        $book['promo_label'] = $candidatePromotion
            ? $candidatePromotion['display_discount'].' · '.$candidatePromotion['display_condition']
            : ($original > $price && $original > 0
            ? 'ลด '.$book['promo_percent'].'%'
            : 'ลดสมาชิก 5% เมื่อสั่งซื้อ');
    }
    unset($book);

    usort($candidates, static fn(array $a, array $b): int =>
        ((int)$b['ai_score'] <=> (int)$a['ai_score'])
        ?: ((int)$b['sold_count'] <=> (int)$a['sold_count'])
        ?: ((int)$b['book_id'] <=> (int)$a['book_id'])
    );
    return array_slice($candidates, 0, max(1, $limit));
}
