<?php
$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

// CSS for upload box
$css = <<<'CSS'
    <style>
        .upload-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 10px; }
        .upload-box { border: 2px dashed #CBD5E1; border-radius: 8px; padding: 15px 5px; text-align: center; cursor: pointer; background: #F8FAFC; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 80px; }
        .upload-box:hover { background: #F1F5F9; border-color: #175564; }
        .upload-box input[type="file"] { position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-box img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; display: none; }
        .upload-icon { color: #175564; margin-bottom: 5px; }
        .upload-box span { font-size: 11px; font-weight: 500; color: #475569; }
    </style>
CSS;
if (strpos($content, '.upload-grid') === false) {
    $content = str_replace('<style>', $css . "\n" . '    <style>', $content);
}

$iconSvg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>';

// 1. Replace Requester upload section
$oldReqUpload = <<<'HTML'
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพสภาพจริง</label>
                            <input type="file" name="req_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
HTML;

$newReqUpload = <<<HTML
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพสภาพจริง</label>
                            <div class="upload-grid">
                                <label class="upload-box">
                                    <input type="file" name="req_photos[]" accept="image/*" required>
                                    <div class="upload-icon">{$iconSvg}</div>
                                    <span>ปกหน้า</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="req_photos[]" accept="image/*" required>
                                    <div class="upload-icon">{$iconSvg}</div>
                                    <span>ปกหลัง</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="req_photos[]" accept="image/*" required>
                                    <div class="upload-icon">{$iconSvg}</div>
                                    <span>สันหนังสือ</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="req_photos[]" accept="image/*">
                                    <div class="upload-icon" style="color:#94a3b8;">{$iconSvg}</div>
                                    <span style="color:#94a3b8;">เพิ่มเติม</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="req_photos[]" accept="image/*">
                                    <div class="upload-icon" style="color:#94a3b8;">{$iconSvg}</div>
                                    <span style="color:#94a3b8;">เพิ่มเติม</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="req_photos[]" accept="image/*">
                                    <div class="upload-icon" style="color:#94a3b8;">{$iconSvg}</div>
                                    <span style="color:#94a3b8;">เพิ่มเติม</span><img src="">
                                </label>
                            </div>
                        </div>
HTML;
$content = str_replace($oldReqUpload, $newReqUpload, $content);

// 2. Replace Offer upload section
$oldOffUpload = <<<'HTML'
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#7F1D1D; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพสภาพจริง</label>
                            <input type="file" name="off_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>
                        </div>
HTML;

$newOffUpload = <<<HTML
                        <div style="margin-bottom:10px;">
                            <label style="font-weight:600; font-size:13px; color:#7F1D1D; display:block; margin-bottom:5px;">3. อัปโหลดรูปภาพสภาพจริง</label>
                            <div class="upload-grid">
                                <label class="upload-box">
                                    <input type="file" name="off_photos[]" accept="image/*" required>
                                    <div class="upload-icon" style="color:#7F1D1D;">{$iconSvg}</div>
                                    <span>ปกหน้า</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="off_photos[]" accept="image/*" required>
                                    <div class="upload-icon" style="color:#7F1D1D;">{$iconSvg}</div>
                                    <span>ปกหลัง</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="off_photos[]" accept="image/*" required>
                                    <div class="upload-icon" style="color:#7F1D1D;">{$iconSvg}</div>
                                    <span>สันหนังสือ</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="off_photos[]" accept="image/*">
                                    <div class="upload-icon" style="color:#fca5a5;">{$iconSvg}</div>
                                    <span style="color:#fca5a5;">เพิ่มเติม</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="off_photos[]" accept="image/*">
                                    <div class="upload-icon" style="color:#fca5a5;">{$iconSvg}</div>
                                    <span style="color:#fca5a5;">เพิ่มเติม</span><img src="">
                                </label>
                                <label class="upload-box">
                                    <input type="file" name="off_photos[]" accept="image/*">
                                    <div class="upload-icon" style="color:#fca5a5;">{$iconSvg}</div>
                                    <span style="color:#fca5a5;">เพิ่มเติม</span><img src="">
                                </label>
                            </div>
                        </div>
HTML;
$content = str_replace($oldOffUpload, $newOffUpload, $content);

// 3. Add JS for image preview
$jsPreview = <<<'JS'
        document.querySelectorAll('.upload-box input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                if(this.files && this.files[0]) {
                    const reader = new FileReader();
                    const img = this.parentElement.querySelector('img');
                    reader.onload = function(e) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    </script>
JS;

if (strpos($content, '.upload-box input[type="file"]') === false) {
    $content = str_replace('</script>', $jsPreview, $content);
}

file_put_contents($file, $content);
echo "Updated admin QC photo upload UI successfully.\n";
