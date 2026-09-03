<?php

if (!function_exists('wanawanExpirePromotions')) {
    function wanawanExpirePromotions(PDO $pdo): void {
        // หา promotion ที่หมดอายุหรือครบโควต้าแล้ว แต่ยัง active อยู่
        $stmt = $pdo->query("SELECT promotion_id FROM seller_promotions WHERE is_active = 1 AND (ends_at < NOW() OR (usage_limit IS NOT NULL AND usage_count >= usage_limit))");
        $expiredIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if ($expiredIds) {
            $inClause = implode(',', array_map('intval', $expiredIds));
            // ลบประกาศที่เกิดจากโปรโมชั่นเหล่านี้
            $pdo->query("DELETE FROM shop_posts WHERE promotion_id IN ($inClause)");
            $pdo->query("DELETE FROM publisher_posts WHERE promotion_id IN ($inClause)");
            
            // เปลี่ยนสถานะเป็นปิดใช้งาน
            $pdo->query("UPDATE seller_promotions SET is_active = 0 WHERE promotion_id IN ($inClause)");
        }
    }
}

function wanawanBestSellerPromotion(PDO $pdo,string $sellerType,int $sellerId,float $subtotal,int $quantity,array $items=[]):array
{
    $memberDiscount=round($subtotal*.05,2);
    $applied=[['promotion_id'=>null,'promotion_name'=>'ส่วนลดสมาชิก 5%','discount_amount'=>$memberDiscount,'is_store_promotion'=>false]];
    $result=['promotion_id'=>null,'promotion_name'=>'ส่วนลดสมาชิก 5%','discount_amount'=>$memberDiscount,'is_store_promotion'=>false,'applied_promotions'=>$applied];
    if(!in_array($sellerType,['publisher','shop'],true)||$sellerId<1)return $result;
    try{
        $stmt=$pdo->prepare("SELECT * FROM seller_promotions WHERE seller_type=? AND seller_id=? AND is_active=1
            AND starts_at<=NOW() AND ends_at>=NOW() AND minimum_quantity<=? AND minimum_amount<=?
            AND (usage_limit IS NULL OR usage_count < usage_limit)");
        $stmt->execute([$sellerType,$sellerId,$quantity,$subtotal]);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $promotion){
            $eligibleSubtotal=$subtotal;$eligibleQuantity=$quantity;
            if(($promotion['scope_type']??'all')!=='all'){
                $targetStmt=$pdo->prepare('SELECT target_type,target_value FROM seller_promotion_targets WHERE promotion_id=?');
                $targetStmt->execute([$promotion['promotion_id']]);$targets=$targetStmt->fetchAll(PDO::FETCH_ASSOC);
                $categoryTargets=[];$bookTargets=[];
                foreach($targets as $target){if($target['target_type']==='category')$categoryTargets[]=(string)$target['target_value'];else $bookTargets[]=(int)$target['target_value'];}
                $eligibleSubtotal=0;$eligibleQuantity=0;
                foreach($items as $item){
                    $eligible=($promotion['scope_type']==='category'&&in_array((string)($item['category']??''),$categoryTargets,true))
                        ||($promotion['scope_type']==='books'&&in_array((int)($item['book_id']??0),$bookTargets,true));
                    if($eligible){$eligibleSubtotal+=(float)$item['price']*(int)$item['quantity'];$eligibleQuantity+=(int)$item['quantity'];}
                }
                if($eligibleQuantity<(int)$promotion['minimum_quantity']||$eligibleSubtotal<(float)$promotion['minimum_amount'])continue;
            }
            $discount=$promotion['discount_type']==='percentage'
                ? $eligibleSubtotal*((float)$promotion['discount_value']/100)
                : (float)$promotion['discount_value'];
            if($promotion['maximum_discount']!==null)$discount=min($discount,(float)$promotion['maximum_discount']);
            $discount=round(min($eligibleSubtotal,max(0,$discount)),2);
            if($discount>0)$applied[]=[
                'promotion_id'=>(int)$promotion['promotion_id'],
                'promotion_name'=>(string)$promotion['promotion_name'],
                'discount_amount'=>$discount,
                'is_store_promotion'=>true,
                'eligible_subtotal'=>$eligibleSubtotal,
                'eligible_quantity'=>$eligibleQuantity,
            ];
        }
    }catch(PDOException $e){
        // รองรับช่วง deploy ก่อน migration โดย checkout ยังใช้ส่วนลดสมาชิกเดิมได้
        if($e->getCode()!=='42S02')throw $e;
    }
    // Find the single best promotion
    $bestPromotion = null;
    $maxDiscount = -1;
    foreach($applied as $promotion){
        $amount = round(min($subtotal, (float)$promotion['discount_amount']), 2);
        if($amount > $maxDiscount){
            $maxDiscount = $amount;
            $promotion['discount_amount'] = $amount;
            $bestPromotion = $promotion;
        }
    }
    
    $normalized = $bestPromotion && $bestPromotion['discount_amount'] > 0 ? [$bestPromotion] : [];
    $storePromotions=array_values(array_filter($normalized,static fn(array $p):bool=>!empty($p['promotion_id'])));
    return [
        'promotion_id'=>$storePromotions[0]['promotion_id']??null,
        'promotion_name'=>$normalized[0]['promotion_name']??'',
        'discount_amount'=>$normalized[0]['discount_amount']??0,
        'is_store_promotion'=>(bool)$storePromotions,
        'applied_promotions'=>$normalized,
    ];
}

function wanawanActiveBookPromotion(PDO $pdo,int $bookId):?array
{
    static $cache=[];if(array_key_exists($bookId,$cache))return $cache[$bookId];
    $sql="SELECT p.* FROM books b JOIN seller_promotions p ON p.seller_type=b.seller_type AND p.seller_id=b.seller_id
        WHERE b.book_id=? AND p.is_active=1 AND p.starts_at<=NOW() AND p.ends_at>=NOW()
        AND (p.scope_type='all'
          OR (p.scope_type='category' AND EXISTS(SELECT 1 FROM seller_promotion_targets t WHERE t.promotion_id=p.promotion_id AND t.target_type='category' AND t.target_value=b.category))
          OR (p.scope_type='books' AND EXISTS(SELECT 1 FROM seller_promotion_targets t WHERE t.promotion_id=p.promotion_id AND t.target_type='book' AND CAST(t.target_value AS UNSIGNED)=b.book_id)))
        ORDER BY CASE WHEN p.discount_type='percentage' THEN p.discount_value ELSE 0 END DESC,p.discount_value DESC,p.promotion_id DESC LIMIT 1";
    try{$stmt=$pdo->prepare($sql);$stmt->execute([$bookId]);$promotion=$stmt->fetch(PDO::FETCH_ASSOC)?:null;}
    catch(PDOException $e){if(in_array($e->getCode(),['42S02','42S22'],true))return $cache[$bookId]=null;throw $e;}
    if(!$promotion)return $cache[$bookId]=null;
    $promotion['display_discount']=$promotion['discount_type']==='percentage'?'ลด '.number_format((float)$promotion['discount_value'],0).'%':'ลด ฿'.number_format((float)$promotion['discount_value'],0);
    $conditions=[];if((int)$promotion['minimum_quantity']>1)$conditions[]='ซื้อครบ '.(int)$promotion['minimum_quantity'].' เล่ม';if((float)$promotion['minimum_amount']>0)$conditions[]='ยอดครบ ฿'.number_format((float)$promotion['minimum_amount'],0);
    $promotion['display_condition']=$conditions?implode(' · ',$conditions):'รับส่วนลดทันที';
    return $cache[$bookId]=$promotion;
}
