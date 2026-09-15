<?php
$file = __DIR__ . '/../pages/trade_condition_form.php';
$content = file_get_contents($file);

// Replace the condition logic where it displays the form
$search = <<<PHP
        <?php if (\$myForm): ?>
            <!-- ฝั่งเรากรอกแล้ว -->
            <div class="form-container">
PHP;

$replace = <<<PHP
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
                <div class="form-container" style="margin-bottom:30px; border:2px solid #e2e8f0;">
                    <div style="background:#f8fafc; padding:10px 15px; border-bottom:1px solid #e2e8f0; font-weight:bold; color:#0f172a;">
                        ฟอร์มสภาพหนังสือของ: <?= htmlspecialchars(\$ownerName) ?>
                    </div>
                    <div style="padding:20px;">
                        <div class="gallery-container">
                            <?php foreach (['image_cover', 'image_back', 'image_inside', 'image_edge', 'image_defect_1', 'image_defect_2', 'image_defect_3'] as \$imgField): ?>
                                <?php if (!empty(\$f[\$imgField])): ?>
                                    <img src="<?= htmlspecialchars(\$f[\$imgField]) ?>" class="gallery-image" onclick="openLightbox(this.src)">
                                <?php endif; ?>
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
            
        <?php elseif (\$myForm): ?>
            <!-- ฝั่งเรากรอกแล้ว -->
            <div class="form-container">
PHP;

$content = str_replace($search, $replace, $content);

file_put_contents($file, $content);
echo "Updated trade_condition_form.php for admin.\n";
