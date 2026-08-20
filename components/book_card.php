<?php
/*
 * Card data contract: pages pass one associative array in $book.
 * Keep support for explicitly supplied variables so the component can also
 * be reused by other pages without silently replacing real data with defaults.
 */
if (isset($book) && is_array($book)) {
    $book_id = (int)($book['id'] ?? $book['book_id'] ?? ($book_id ?? 0));
    $title   = (string)($book['title'] ?? ($title ?? ''));
    $author  = (string)($book['author'] ?? ($author ?? ''));
    $price   = (float)($book['price'] ?? ($price ?? 0));
    $badge   = (string)($book['badge'] ?? ($badge ?? ''));
    // Each include is a new card, but PHP keeps variables in the caller scope.
    // Respect an explicitly empty image so a book without a cover cannot reuse
    // the previous card's cover.
    if (array_key_exists('image', $book)) {
        $image = $book['image'];
    } elseif (array_key_exists('image_cover', $book)) {
        $image = $book['image_cover'];
    } else {
        $image = null;
    }
    $wishlisted = !empty($book['wishlisted']);
}
$detail_source = ($card_type ?? '') === 'exchange' ? 'exchange' : 'book';
$activePromotion=null;
if($detail_source==='book'&&!empty($book_id)&&isset($pdo)){require_once __DIR__.'/../config/promotions.php';$activePromotion=wanawanActiveBookPromotion($pdo,(int)$book_id);}
$detail_url = '/pages/book_detail.php?source=' . $detail_source . '&id=' . (int)($book_id ?? 0);
$source_seller_type = (string)($book['seller_type'] ?? '');
$source_verified = !empty($book['seller_verified']);
if ($detail_source === 'exchange') {
    $source_seller_type = 'user';
} elseif ($source_seller_type === '' && !empty($book_id) && isset($pdo)) {
    $GLOBALS['_book_seller_type_cache'] = $GLOBALS['_book_seller_type_cache'] ?? [];
    if (!array_key_exists((int)$book_id, $GLOBALS['_book_seller_type_cache'])) {
        $sourceStmt = $pdo->prepare("SELECT b.seller_type,CASE WHEN b.seller_type='publisher' THEN p.status='approved' WHEN b.seller_type='shop' THEN s.status='approved' ELSE 0 END seller_verified FROM books b LEFT JOIN publishers p ON b.seller_type='publisher' AND p.publisher_id=b.seller_id LEFT JOIN shops s ON b.seller_type='shop' AND s.shop_id=b.seller_id WHERE b.book_id=? LIMIT 1");
        $sourceStmt->execute([(int)$book_id]);
        $sourceRow = $sourceStmt->fetch(PDO::FETCH_ASSOC) ?: ['seller_type'=>'user','seller_verified'=>0];
        $GLOBALS['_book_seller_type_cache'][(int)$book_id] = $sourceRow;
    }
    $source_seller_type = (string)$GLOBALS['_book_seller_type_cache'][(int)$book_id]['seller_type'];
    $source_verified = !empty($GLOBALS['_book_seller_type_cache'][(int)$book_id]['seller_verified']);
}
$sourceTag = match ($source_seller_type) {
    'publisher' => ['สินค้าจากสำนักพิมพ์', 'publisher'],
    'shop' => ['สินค้าจากร้านหนังสือ', 'shop'],
    'user' => ['ผู้ขายทั่วไป', 'user'],
    default => null,
};
?>

<style>
/* ==========================================================
   BOOK CARD COMPONENT
   ใช้ CSS Variables จาก styles.css [1]
   ========================================================== */

.book-card {
    font-family: var(--font-family);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: var(--shadow-card);
    background-color: var(--card-bg);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    width: 100%;
    cursor: pointer;
}
.book-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.09);
}

/* ---------- Zone ภาพ ---------- */
.book-card__img-zone {
    background-color: var(--card-peach);
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 28px 20px 24px;
    min-height: 190px;
}

/* ปกหนังสือ */
.book-card__cover {
    width: 88px;
    height: 124px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    box-shadow: 2px 4px 10px rgba(0, 0, 0, 0.12);
    display: block;
}

/* Placeholder ถ้าไม่มีรูป */
.book-card__placeholder {
    width: 88px;
    height: 124px;
    background-color: rgba(0, 0, 0, 0.05);
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
}
.book-card__placeholder svg {
    opacity: 0.3;
    color: var(--text-muted);
}

/* Badge มาใหม่ */
.book-card__badge {
    position: absolute;
    position: absolute;
    top: 12px;
    left: 12px;
    background-color: var(--accent-pink-light);
    color: var(--text-dark);
    font-size: var(--text-xs);
    font-weight: 500;
    padding: 3px 10px;
    border-radius: 20px;
    font-family: var(--font-family);
}

/* ปุ่ม Wishlist */
.book-card__wish {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #fdf0f4;
    border: 1.5px solid #f0c4d4;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s ease;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 8px rgba(212, 112, 138, 0.15);
}
.book-card__wish svg {
    color: #d4708a;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    transition: all 0.2s ease;
}
.book-card__wish:hover {
    background: #f8dce7;
    border-color: #d4708a;
    box-shadow: 0 3px 12px rgba(212, 112, 138, 0.3);
}
.book-card__wish:hover svg {
    color: #c9506e;
    transform: scale(1.15);
}
.book-card__wish.active { background: #fff5f0; border: 1.5px solid #f0c4c4; box-shadow: 0 2px 10px rgba(232, 84, 106, 0.25); }
.book-card__wish.active svg { color: #e8546a; fill: #e8546a; stroke: #e8546a; }
.book-card__wish.active:hover { background: #fde8ec; box-shadow: 0 3px 14px rgba(232, 84, 106, 0.35); }

.book-card__tag-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:8px}.book-card__tag-row .book-card__source-tag{margin-bottom:0}.book-card__promo{display:inline-flex;align-items:center;background:linear-gradient(135deg,#d85c7f,#b83f63);color:#fff;padding:4px 9px;border-radius:5px;box-shadow:0 2px 7px #8f345233;max-width:100%;font-size:10px;font-weight:700;line-height:1.2;white-space:nowrap}

/* ---------- Zone ข้อมูล ---------- */
/* โทนสีเหมือนกันทุก card_type [1] */
.book-card__info {
    background-color: var(--card-bg);
    padding: 14px 16px 6px;
}
.book-card__source-tag{width:max-content;max-width:100%;display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:5px;font-size:10px;font-weight:700;line-height:1.2;margin-bottom:8px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.book-card__source-tag::before{content:'✓';width:14px;height:14px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:9px;flex:0 0 14px}
.book-card__source-tag--publisher{color:#a14468;background:#f9dce7;border:1px solid #edbfd0}.book-card__source-tag--publisher::before{background:#c65f86}
.book-card__source-tag--shop{color:#176272;background:#dff2f3;border:1px solid #b9dde0}.book-card__source-tag--shop::before{background:#268091}
.book-card__source-tag--user{color:#756a6e;background:#f2eeee;border:1px solid #e1d9dc}.book-card__source-tag--user::before{content:'บุคคล';width:auto;height:auto;background:none;color:#8c7e83;font-size:9px}

.book-card__title-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}
.book-card__title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-dark);
    margin: 0;
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.book-card__price {
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--text-dark);
    white-space: nowrap;
    flex-shrink: 0;
}
.book-card__author {
    font-size: var(--text-sm);
    color: var(--primary-color);
    margin: 0;
    font-weight: 400;
}

/* ---------- Zone ปุ่ม ---------- */
.book-card__actions {
    background-color: var(--card-bg);
    padding: 12px 16px 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.book-card__owner-actions { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.book-card__owner-actions form { margin:0; }
.book-card__owner-btn { min-height:40px;width:100%;border-radius:var(--radius-md);border:1px solid var(--secondary-color);background:#fff;color:var(--secondary-color);font-family:var(--font-family);font-size:var(--text-sm);font-weight:600;cursor:pointer;display:grid;place-items:center;text-decoration:none; }
.book-card__owner-btn--delete { border-color:#c96b67;color:#b84d49; }
.book-card__owner-btn:hover { background:#f9ebe6; }

/* ปุ่ม Add to Cart */
.btn-add-cart {
    width: 100%;
    padding: 10px 0;
    background-color: var(--primary-color);
    color: var(--text-light);
    border: none;
    border-radius: var(--radius-md);
    font-family: var(--font-family);
    font-size: var(--text-sm);
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: background-color 0.2s;
}
.btn-add-cart:hover {
    background-color: var(--primary-hover);
}

/* ปุ่ม แลกเปลี่ยน (exchange) — ทรงเดียวกับ add to cart แต่เป็นโทนเขียว */
.btn-exchange {
    width: 100%;
    padding: 10px 0;
    background-color: var(--secondary-color);
    color: var(--text-light);
    border: none;
    border-radius: var(--radius-md);
    font-family: var(--font-family);
    font-size: var(--text-sm);
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: background-color 0.2s;
}
.btn-exchange:hover {
    background-color: #124453;
}

/* ปุ่ม Digital Twin */
.btn-digital-twin {
    width: 100%;
    padding: 9px 0;
    background-color: transparent;
    color: var(--text-dark);
    border: 1.5px solid var(--border-color);
    border-radius: var(--radius-md);
    font-family: var(--font-family);
    font-size: var(--text-sm);
    font-weight: 400;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    transition: border-color 0.2s, color 0.2s;
}
.btn-digital-twin:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

/* ---------- Footer Label ---------- */
.book-card__footer {
    background-color: var(--card-bg);
    padding: 6px 16px 12px;
    border-top: 1px solid var(--border-color);
    text-align: right;
}
.book-card__label {
    font-size: var(--text-xs);
    color: var(--primary-color);
    font-weight: 500;
    font-family: var(--font-family);
}
</style>

<!-- =============================================
     CARD TEMPLATE
     ============================================= -->
<div class="book-card" role="link" tabindex="0"
     onclick="location.href='<?= htmlspecialchars($detail_url, ENT_QUOTES) ?>'"
     onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); location.href='<?= htmlspecialchars($detail_url, ENT_QUOTES) ?>'; }">

    <!-- Zone ภาพ -->
    <div class="book-card__img-zone">

        <?php if (!empty($badge)): ?>
            <span class="book-card__badge"><?= htmlspecialchars($badge) ?></span>
        <?php endif; ?>

        <button class="book-card__wish <?= !empty($wishlisted)?'active':'' ?>" aria-label="<?= !empty($wishlisted)?'นำออกจากรายการโปรด':'เพิ่มในรายการโปรด' ?>" onclick="event.stopPropagation(); toggleWishlist(<?= (int)$book_id ?>, this, '<?= $detail_source ?>')">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67
                         l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78
                         l1.06 1.06L12 21.23l7.78-7.78
                         1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
        </button>

        <?php if (!empty($image)): ?>
            <img src="<?= htmlspecialchars($image) ?>"
                 alt="<?= htmlspecialchars($title ?? 'ไม่มีชื่อ') ?>"
                 class="book-card__cover">
        <?php else: ?>
            <div class="book-card__placeholder">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.2">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <path d="M3 9h18M9 21V9"/>
                </svg>
            </div>
        <?php endif; ?>

    </div>

    <!-- Zone ข้อมูล -->
    <div class="book-card__info">
        <?php if($sourceTag||$activePromotion):?><div class="book-card__tag-row">
        <?php if ($sourceTag): ?>
            <span class="book-card__source-tag book-card__source-tag--<?= htmlspecialchars($sourceTag[1]) ?>"><?= htmlspecialchars($sourceTag[0]) ?></span>
        <?php endif; ?>
        <?php if($activePromotion):?><span class="book-card__promo" title="<?=htmlspecialchars($activePromotion['promotion_name'].' · '.$activePromotion['display_condition'])?>">โปร <?=htmlspecialchars($activePromotion['display_discount'])?></span><?php endif;?>
        </div><?php endif;?>
        <div class="book-card__title-row">
            <!-- ✅ แก้: เพิ่ม ?? 'ไม่มีชื่อ' ป้องกัน htmlspecialchars(null) -->
            <h3 class="book-card__title">
                <?= htmlspecialchars($title ?? 'ไม่มีชื่อ') ?>
            </h3>
            <!-- ✅ แก้: เพิ่ม ?? 0 ป้องกัน number_format(null) -->
            <span class="book-card__price">
                <?= ($card_type ?? '') === 'exchange'
                    ? 'แลกเปลี่ยน'
                    : '฿ ' . number_format((float)($price ?? 0)) ?>
            </span>
        </div>
        <!-- ✅ แก้: เพิ่ม ?? '-' ป้องกัน htmlspecialchars(null) -->
        <p class="book-card__author">
            <?= htmlspecialchars($author ?? '-') ?>
        </p>
    </div>

    <!-- Zone ปุ่ม -->
    <div class="book-card__actions">

        <?php if (!empty($owner_controls)): ?>
            <div class="book-card__owner-actions" onclick="event.stopPropagation()">
                <a class="book-card__owner-btn" href="<?= $detail_source==='exchange'?'/pages/edit_exchange.php?id=':'/pages/edit_book.php?id=' ?><?= (int)$book_id ?>">แก้ไขรายละเอียด</a>
                <form method="post" action="/pages/profile.php?tab=<?= $detail_source==='exchange'?'exchange':'selling' ?>" onsubmit="return confirm('ต้องการลบโพสต์หนังสือนี้ใช่หรือไม่?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($owner_csrf_token ?? '')) ?>">
                    <input type="hidden" name="<?= $detail_source==='exchange'?'exchange_id':'book_id' ?>" value="<?= (int)$book_id ?>">
                    <button class="book-card__owner-btn book-card__owner-btn--delete" type="submit" name="<?= $detail_source==='exchange'?'delete_exchange':'delete_book' ?>">ลบโพสต์</button>
                </form>
            </div>

        <?php elseif ($card_type === 'exchange'): ?>
            <!-- ปุ่มแลกเปลี่ยน -->
            <button class="btn-exchange"
                    onclick="event.stopPropagation(); location.href='/pages/exchange_request.php?exchange_id=<?= (int)$book_id ?>'">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 16V4m0 0L3 8m4-4l4 4"/>
                    <path d="M17 8v12m0 0l4-4m-4 4l-4-4"/>
                </svg>
                แลกเปลี่ยน
            </button>

        <?php else: ?>
            <!-- ปุ่ม Add to Cart (มือ 1 / มือ 2) -->
            <button class="btn-add-cart"
                    onclick="event.stopPropagation(); addToCart(<?= (int)$book_id ?>)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61
                             h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                Add to Cart
            </button>
        <?php endif; ?>

        <!-- ปุ่ม Digital Twin (ทุก type) -->
        <button class="btn-digital-twin" onclick="event.stopPropagation(); location.href='/pages/digital_twin.php?source=<?= $detail_source ?>&amp;id=<?= (int)$book_id ?>'">
            ✦ Digital Twin
        </button>

    </div>

    <!-- Footer Label -->
    <div class="book-card__footer">
        <span class="book-card__label">
            <?php
            echo match($card_type) {
                'new'      => 'มือหนึ่ง',
                'used'     => 'มือสอง',
                'exchange' => 'แลกเปลี่ยน',
                default    => ''
            };
            ?>
        </span>
    </div>

</div>
