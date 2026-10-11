<?php
$conds = [
    ['new',  'เหมือนใหม่'],
    ['good', 'สภาพดี'],
    ['fair', 'มีตำหนิเล็กน้อย'],
    ['old',  'สภาพเก่า'],
];
?>
<div class="section-card">
    <div class="section-title" id="conditionPriceTitle">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
            <line x1="7" y1="7" x2="7.01" y2="7"/>
        </svg>
        <span id="conditionPriceTitleText">ราคาขาย</span>
    </div>

    <div class="form-group" style="margin-bottom:16px" id="conditionSection">
        <label>สภาพหนังสือ</label>
        <div class="condition-group" id="conditionGroup">
            <?php foreach ($conds as [$val, $label]): ?>
            <button type="button" class="condition-btn <?= $val === 'new' ? 'active' : '' ?>"
                    onclick="setCondition('<?= $val ?>')">
                <?= $label ?>
            </button>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="condition_level" id="condition_level" value="new">
    </div>

    <div class="form-grid-2" style="margin-bottom:14px" id="priceGrid">
        <div class="form-group">
            <label>ราคาขาย (บาท) <span style="color:var(--accent-red)">*</span></label>
            <input type="number" name="price" id="book_price" value="<?= htmlspecialchars((string)($book['price'] ?? '')) ?>" class="form-control" placeholder="0.00" min="0" step="0.01" required>
        </div>
        <div class="form-group" id="originalPriceGroup">
            <label>ราคาหน้าปก (บาท)
                <span style="font-size:var(--text-xs);color:var(--text-muted)">(สำหรับมือสอง)</span>
            </label>
            <input type="number" name="original_price" id="book_original_price" value="<?= htmlspecialchars((string)($book['original_price'] ?? '')) ?>" class="form-control" placeholder="0.00" min="0" step="0.01">
        </div>
    </div>

    <div class="form-group" id="defectsSection">
        <label>รายละเอียดเพิ่มเติม / ตำหนิ</label>
        <textarea name="defects_detail" class="form-control" rows="3"
                  placeholder="ระบุรายละเอียดสภาพหนังสือ เช่น มีรอยเหลือง รอยพับ หรือความประทับใจฯ"></textarea>
    </div>
</div>