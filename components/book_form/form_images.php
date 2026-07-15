<?php
$img_slots = $img_slots ?? [
    ['image_cover',  'หน้าปก'],
    ['image_back',   'ด้านหลัง'],
    ['image_inside', 'เนื้อหาข้างใน'],
    ['image_edge',   'สันหนังสือ'],
];
?>
<div class="section-card">
    <div class="section-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21 15 16 10 5 21"/>
        </svg>
        รูปภาพหนังสือ
    </div>
    <div class="image-grid">
        <?php foreach ($img_slots as [$field, $label]): ?>
        <label class="image-slot" id="slot_<?= $field ?>">
            <input type="file" name="<?= $field ?>" accept="image/*"
                   onchange="previewImage(this, 'slot_<?= $field ?>', '<?= $field ?>_url')">
            <input type="hidden" name="<?= $field ?>_url" id="<?= $field ?>_url" value="">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2"/>
                <circle cx="8.5" cy="8.5" r="1.5"/>
                <polyline points="21 15 16 10 5 21"/>
            </svg>
            <span class="image-slot-label"><?= $label ?></span>
        </label>
        <?php endforeach; ?>
    </div>
    <p class="image-required-note">* อัปโหลดภาพหน้าปก 1 รูป (บังคับ) เพื่อความน่าเชื่อถือ</p>
</div>