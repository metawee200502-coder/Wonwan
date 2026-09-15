<?php
$file = 'C:/laragon/www/wanawan_project/pages/trade_condition_form.php';
$content = file_get_contents($file);

// 1. PHP POST upload logic
$phpSearch = "
        \$paths = [];
        \$imageFields = ['front_cover', 'back_cover', 'spine'];
        foreach (\$imageFields as \$field) {
            if (!empty(\$_FILES[\$field]['name'])) {
                \$ext = pathinfo(\$_FILES[\$field]['name'], PATHINFO_EXTENSION);
                \$filename = \"cond_{\$requestId}_{\$userId}_{\$field}_\" . time() . \".\$ext\";
                move_uploaded_file(\$_FILES[\$field]['tmp_name'], \"\$uploadDir/\$filename\");
                \$paths[\$field] = \"/pages/uploads/conditions/\$filename\";
            }
        }";

$phpReplace = "
        \$paths = [];
        \$imageFields = ['front_cover', 'back_cover', 'spine'];
        foreach (\$imageFields as \$field) {
            if (!empty(\$_FILES[\$field]['name'])) {
                \$ext = pathinfo(\$_FILES[\$field]['name'], PATHINFO_EXTENSION);
                \$filename = \"cond_{\$requestId}_{\$userId}_{\$field}_\" . time() . \".\$ext\";
                move_uploaded_file(\$_FILES[\$field]['tmp_name'], \"\$uploadDir/\$filename\");
                \$paths[\$field] = \"/pages/uploads/conditions/\$filename\";
            }
        }
        
        \$defectUrls = [];
        if (!empty(\$_FILES['additional_images']['name'][0])) {
            \$count = min(3, count(\$_FILES['additional_images']['name']));
            for (\$i = 0; \$i < \$count; \$i++) {
                if (!empty(\$_FILES['additional_images']['name'][\$i])) {
                    \$ext = pathinfo(\$_FILES['additional_images']['name'][\$i], PATHINFO_EXTENSION);
                    \$filename = \"cond_{\$requestId}_{\$userId}_additional_{\$i}_\" . time() . \".\$ext\";
                    move_uploaded_file(\$_FILES['additional_images']['tmp_name'][\$i], \"\$uploadDir/\$filename\");
                    \$defectUrls[] = \"/pages/uploads/conditions/\$filename\";
                }
            }
        }";
        
$phpSearch2 = "\$insertStmt = \$pdo->prepare(\"INSERT INTO trade_condition_forms (request_id, owner_id, front_cover_url, back_cover_url, spine_url, defects_description, notes) VALUES (?, ?, ?, ?, ?, ?, ?)\");
            \$insertStmt->execute([
                \$requestId, 
                \$userId, 
                \$paths['front_cover'], 
                \$paths['back_cover'], 
                \$paths['spine'], 
                \$defectsDesc,
                \$notes
            ]);";
            
$phpReplace2 = "\$insertStmt = \$pdo->prepare(\"INSERT INTO trade_condition_forms (request_id, owner_id, front_cover_url, back_cover_url, spine_url, defect_urls, defects_description, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)\");
            \$insertStmt->execute([
                \$requestId, 
                \$userId, 
                \$paths['front_cover'], 
                \$paths['back_cover'], 
                \$paths['spine'],
                json_encode(\$defectUrls),
                \$defectsDesc,
                \$notes
            ]);";

$content = str_replace($phpSearch, $phpReplace, $content);
$content = str_replace($phpSearch2, $phpReplace2, $content);

// 2. HTML Form UI for Additional Images
$htmlSearch = "
                    <div class=\"upload-grid\">
                        <label class=\"upload-box\">
                            <input type=\"file\" name=\"front_cover\" accept=\"image/*\" required>
                            <div class=\"upload-icon\">📸</div>
                            <span style=\"font-weight:500;\">ปกหน้า</span>
                            <img src=\"\">
                        </label>
                        <label class=\"upload-box\">
                            <input type=\"file\" name=\"back_cover\" accept=\"image/*\" required>
                            <div class=\"upload-icon\">📸</div>
                            <span style=\"font-weight:500;\">ปกหลัง</span>
                            <img src=\"\">
                        </label>
                        <label class=\"upload-box\">
                            <input type=\"file\" name=\"spine\" accept=\"image/*\" required>
                            <div class=\"upload-icon\">📚</div>
                            <span style=\"font-weight:500;\">สันหนังสือ</span>
                            <img src=\"\">
                        </label>
                    </div>";

$htmlReplace = "
                    <div class=\"upload-grid\">
                        <label class=\"upload-box\">
                            <input type=\"file\" name=\"front_cover\" accept=\"image/*\" required>
                            <div class=\"upload-icon\">📸</div>
                            <span style=\"font-weight:500;\">ปกหน้า</span>
                            <img src=\"\">
                        </label>
                        <label class=\"upload-box\">
                            <input type=\"file\" name=\"back_cover\" accept=\"image/*\" required>
                            <div class=\"upload-icon\">📸</div>
                            <span style=\"font-weight:500;\">ปกหลัง</span>
                            <img src=\"\">
                        </label>
                        <label class=\"upload-box\">
                            <input type=\"file\" name=\"spine\" accept=\"image/*\" required>
                            <div class=\"upload-icon\">📚</div>
                            <span style=\"font-weight:500;\">สันหนังสือ</span>
                            <img src=\"\">
                        </label>
                    </div>
                    <div style=\"margin-top: 15px;\">
                        <label class=\"form-label\">รูปเพิ่มเติม (สูงสุด 3 รูป) (ถ้ามี)</label>
                        <input type=\"file\" name=\"additional_images[]\" accept=\"image/*\" multiple style=\"padding: 10px; border: 1px dashed #CBD5E1; border-radius: 8px; width: 100%; box-sizing: border-box;\">
                        <small style=\"color: #64748B;\">*อัปโหลดได้สูงสุด 3 รูป</small>
                    </div>";

$content = str_replace($htmlSearch, $htmlReplace, $content);

// 3. Render partner form extra images and lightbox support
$partnerSearch = "
                <div class=\"upload-grid\">
                    <div class=\"image-preview-card\">
                        <img src=\"<?= htmlspecialchars(\$partnerForm['front_cover_url']) ?>\">
                        <div class=\"label\">ปกหน้า</div>
                    </div>
                    <div class=\"image-preview-card\">
                        <img src=\"<?= htmlspecialchars(\$partnerForm['back_cover_url']) ?>\">
                        <div class=\"label\">ปกหลัง</div>
                    </div>
                    <div class=\"image-preview-card\">
                        <img src=\"<?= htmlspecialchars(\$partnerForm['spine_url']) ?>\">
                        <div class=\"label\">สันหนังสือ</div>
                    </div>
                </div>";

$partnerReplace = "
                <div class=\"upload-grid\">
                    <div class=\"image-preview-card\">
                        <img src=\"<?= htmlspecialchars(\$partnerForm['front_cover_url']) ?>\" onclick=\"openLightbox(this.src)\" style=\"cursor:pointer;\">
                        <div class=\"label\">ปกหน้า</div>
                    </div>
                    <div class=\"image-preview-card\">
                        <img src=\"<?= htmlspecialchars(\$partnerForm['back_cover_url']) ?>\" onclick=\"openLightbox(this.src)\" style=\"cursor:pointer;\">
                        <div class=\"label\">ปกหลัง</div>
                    </div>
                    <div class=\"image-preview-card\">
                        <img src=\"<?= htmlspecialchars(\$partnerForm['spine_url']) ?>\" onclick=\"openLightbox(this.src)\" style=\"cursor:pointer;\">
                        <div class=\"label\">สันหนังสือ</div>
                    </div>
                    <?php 
                    \$partnerExtras = json_decode(\$partnerForm['defect_urls'] ?? '[]', true) ?: [];
                    foreach(\$partnerExtras as \$idx => \$url):
                    ?>
                    <div class=\"image-preview-card\">
                        <img src=\"<?= htmlspecialchars(\$url) ?>\" onclick=\"openLightbox(this.src)\" style=\"cursor:pointer;\">
                        <div class=\"label\">รูปเพิ่มเติม <?= \$idx + 1 ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>";

$content = str_replace($partnerSearch, $partnerReplace, $content);

// 4. Add lightbox modal and JS to bottom
$lightboxHtml = "
<div id=\"imageLightbox\" style=\"display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(5px);\">
    <span style=\"position:absolute; top:20px; right:30px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer;\" onclick=\"closeLightbox()\">&times;</span>
    <img id=\"lightboxImg\" src=\"\" style=\"max-width:90%; max-height:90%; object-fit:contain; border-radius:8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);\">
</div>
<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('imageLightbox').style.display = 'flex';
}
function closeLightbox() {
    document.getElementById('imageLightbox').style.display = 'none';
    document.getElementById('lightboxImg').src = '';
}
// Close on background click
document.getElementById('imageLightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});
</script>
</body>
</html>";

$content = str_replace("</body>\n</html>", $lightboxHtml, $content);

// Also add cursor pointer to myForm images (if rendered? Wait, myForm is NOT rendered, only partner form is rendered, myForm just says "บันทึกสำเร็จ!")

file_put_contents($file, $content);
echo "Successfully updated trade condition form with extra images and lightbox.";
