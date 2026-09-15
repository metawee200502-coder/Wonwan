<?php
$file = __DIR__ . '/../pages/trade_condition_form.php';
$content = file_get_contents($file);

// Find the target block
$search = <<<PHP
        <?php if (!empty(\$errors)): ?><div class="alert alert-error">⚠ <?= implode('<br>', array_map('htmlspecialchars', \$errors)) ?></div><?php endif; ?>

        <?php if (!\$myForm): ?>
            <!-- 1. กรอกฟอร์มของตัวเอง -->
PHP;

$replace = <<<PHP
        <?php if (!empty(\$errors)): ?><div class="alert alert-error">⚠ <?= implode('<br>', array_map('htmlspecialchars', \$errors)) ?></div><?php endif; ?>

        <?php if (\$isReadonly && \$isAdmin): ?>
            <!-- Admin View: Show Both Forms -->
            <h4 style="text-align:center; color:#8b5266; margin-bottom:20px;">ข้อมูลสภาพหนังสือ (สำหรับส่วนกลางตรวจสอบ)</h4>
            
            <?php 
                // Fetch both forms directly for admin
                \$formsStmt = \$pdo->prepare("SELECT * FROM trade_condition_forms WHERE request_id = ? ORDER BY form_id ASC");
                \$formsStmt->execute([\$requestId]);
                \$allForms = \$formsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty(\$allForms)) {
                    echo "<div class='alert alert-warning' style='text-align:center;'>ยังไม่มีฝ่ายใดกรอกฟอร์มสภาพหนังสือ</div>";
                }
                
                foreach (\$allForms as \$f):
                    \$ownerUser = \$pdo->query("SELECT first_name, last_name, display_name FROM users WHERE user_id = {\$f['owner_id']}")->fetch();
                    \$ownerName = \$ownerUser['display_name'] ?: trim(\$ownerUser['first_name'] . ' ' . \$ownerUser['last_name']);
            ?>
                <div class="form-container" style="margin-bottom:30px; border:2px solid #e2e8f0; border-radius: 12px; overflow:hidden; background:#fff;">
                    <div style="background:#f8fafc; padding:15px; border-bottom:1px solid #e2e8f0; font-weight:bold; color:#0f172a; font-size:16px;">
                        ฟอร์มสภาพหนังสือของ: <span style="color:#FF7B00;"><?= htmlspecialchars(\$ownerName) ?></span>
                    </div>
                    <div style="padding:20px;">
                        <div class="upload-grid" style="margin-bottom:20px;">
                            <?php foreach (['front_cover_url', 'back_cover_url', 'spine_url'] as \$idx => \$imgField): ?>
                                <?php if (!empty(\$f[\$imgField])): ?>
                                    <div class="image-preview-card">
                                        <img src="<?= htmlspecialchars(\$f[\$imgField]) ?>" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                                        <div class="label"><?= ['ปกหน้า', 'ปกหลัง', 'สันหนังสือ'][\$idx] ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php 
                            \$partnerExtras = json_decode(\$f['defect_urls'] ?? '[]', true) ?: [];
                            foreach(\$partnerExtras as \$idx => \$url):
                            ?>
                            <div class="image-preview-card">
                                <img src="<?= htmlspecialchars(\$url) ?>" class="gallery-image" onclick="openLightbox(this.src)" style="cursor:pointer;">
                                <div class="label">รูปเพิ่มเติม <?= \$idx + 1 ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="info-box" style="margin-top:20px;">
                            <h4>📍 ตำหนิที่พบ</h4>
                            <p><?= nl2br(htmlspecialchars(\$f['defects_description'] ?: 'ไม่มีระบุ')) ?></p>
                        </div>
                        
                        <div class="info-box">
                            <h4>📌 หมายเหตุเพิ่มเติม</h4>
                            <p><?= nl2br(htmlspecialchars(\$f['notes'] ?: '-')) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
        <?php elseif (!\$myForm): ?>
            <!-- 1. กรอกฟอร์มของตัวเอง -->
PHP;

if (strpos($content, $search) !== false) {
    $content = str_replace($search, $replace, $content);
    file_put_contents($file, $content);
    echo "Patched successfully.\n";
} else {
    echo "String not found!\n";
}
