<?php
$tips = [
    'เคลือบสินค้าของคุณ',
    'ถ่ายรูปให้แสงเพียงพอ',
    'ระบุตำหนิเพื่อลดปัญหาการขาย',
    'ตั้งราคาที่เหมาะสมกับสภาพหนังสือ',
];
?>
<div class="right-panel">
    <div class="tip-card">
        <div class="tip-title">เคล็ดลับการขาย</div>
        <?php foreach ($tips as $tip): ?>
        <div class="tip-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            <?= $tip ?>
        </div>
        <?php endforeach; ?>
    </div>

    <button type="submit" class="btn-submit" onclick="setFormAction('save_book')">
        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 2L11 13"/>
            <path d="M22 2L15 22 11 13 2 9l20-7z"/>
        </svg>
        ลงขายทันที
    </button>

    <button type="submit" class="btn-draft" onclick="setFormAction('save_draft')">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
            <polyline points="17 21 17 13 7 13 7 21"/>
            <polyline points="7 3 7 8 15 8"/>
        </svg>
        บันทึกร่าง
    </button>
</div>