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
    $image   = $book['image'] ?? $book['image_cover'] ?? ($image ?? null);
}
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
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: rgba(255, 255, 255, 0.75);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
    backdrop-filter: blur(4px);
}
.book-card__wish svg {
    color: #BDBDBD;
    transition: color 0.2s;
}
.book-card__wish:hover {
    background-color: var(--accent-pink-light);
}
.book-card__wish:hover svg {
    color: var(--primary-color);
}

/* ---------- Zone ข้อมูล ---------- */
/* โทนสีเหมือนกันทุก card_type [1] */
.book-card__info {
    background-color: var(--card-bg);
    padding: 14px 16px 6px;
}

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
<div class="book-card">

    <!-- Zone ภาพ -->
    <div class="book-card__img-zone">

        <?php if (!empty($badge)): ?>
            <span class="book-card__badge"><?= htmlspecialchars($badge) ?></span>
        <?php endif; ?>

        <button class="book-card__wish" aria-label="เพิ่มในรายการโปรด">
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
        <div class="book-card__title-row">
            <!-- ✅ แก้: เพิ่ม ?? 'ไม่มีชื่อ' ป้องกัน htmlspecialchars(null) -->
            <h3 class="book-card__title">
                <?= htmlspecialchars($title ?? 'ไม่มีชื่อ') ?>
            </h3>
            <!-- ✅ แก้: เพิ่ม ?? 0 ป้องกัน number_format(null) -->
            <span class="book-card__price">
                ฿ <?= number_format((float)($price ?? 0)) ?>
            </span>
        </div>
        <!-- ✅ แก้: เพิ่ม ?? '-' ป้องกัน htmlspecialchars(null) -->
        <p class="book-card__author">
            <?= htmlspecialchars($author ?? '-') ?>
        </p>
    </div>

    <!-- Zone ปุ่ม -->
    <div class="book-card__actions">

        <?php if ($card_type === 'exchange'): ?>
            <!-- ปุ่มแลกเปลี่ยน -->
            <button class="btn-exchange"
                    onclick="location.href='/pages/exchange_request.php?book_id=<?= (int)$book_id ?>'">
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
                    onclick="addToCart(<?= (int)$book_id ?>)">
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
        <button class="btn-digital-twin">
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
