<?php
$shipping_opts = [
    ['standard',      'Standard Delivery (Flash/Kerry/J&T)', 'ระบบจะคำนวณตามน้ำหนักโดยอัตโนมัติ'],
    ['thailand_post', 'ไปรษณีย์ไทย (ลงทะเบียน/EMS)',        'ราคาตามกรมไปรษณีย์ไทย'],
    ['pickup',        'นัดรับสินค้า (Pick-up)',               'นัดหมายผ่านระบบแชท'],
];
?>
<div class="section-card">
    <div class="section-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="3" width="15" height="13"/>
            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
            <circle cx="5.5" cy="18.5" r="2.5"/>
            <circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
        วิธีการจัดส่ง
    </div>
    <?php foreach ($shipping_opts as [$val, $label, $sub]): ?>
    <label class="shipping-option">
        <input type="checkbox" name="shipping[]" value="<?= $val ?>" <?= $val === 'standard' ? 'checked' : '' ?>>
        <div>
            <div class="shipping-label"><?= $label ?></div>
            <div class="shipping-sub"><?= $sub ?></div>
        </div>
    </label>
    <?php endforeach; ?>
</div>