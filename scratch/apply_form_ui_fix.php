<?php
$file = 'c:/laragon/www/wanawan_project/pages/trade_condition_form.php';
$content = file_get_contents($file);

// Update CSS for .card-glass
$oldCardGlassCss = <<<'CSS'
        .card-glass {
            background: rgba(255, 255, 255, 0.6);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            margin-bottom: 30px;
            transition: transform 0.2s ease;
        }
CSS;
$newCardGlassCss = <<<'CSS'
        .card-glass {
            background: rgba(255, 255, 255, 0.6);
            border-radius: 16px;
            padding: <?= $isIframe ? '15px' : '25px' ?>;
            border: 1px solid rgba(255, 255, 255, 0.8);
            margin-bottom: <?= $isIframe ? '15px' : '30px' ?>;
            transition: transform 0.2s ease;
        }
CSS;
$content = str_replace($oldCardGlassCss, $newCardGlassCss, $content);

// Update CSS for .upload-grid
$oldUploadGridCss = <<<'CSS'
        .upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
CSS;
$newUploadGridCss = <<<'CSS'
        .upload-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: <?= $isIframe ? '10px' : '20px' ?>;
        }
CSS;
$content = str_replace($oldUploadGridCss, $newUploadGridCss, $content);

// Update CSS for .upload-box
$oldUploadBoxCss = <<<'CSS'
        .upload-box {
            position: relative;
            height: 220px;
            border: 2px dashed #CBD5E1;
CSS;
$newUploadBoxCss = <<<'CSS'
        .upload-box {
            position: relative;
            height: <?= $isIframe ? '100px' : '220px' ?>;
            border: 2px dashed #CBD5E1;
CSS;
$content = str_replace($oldUploadBoxCss, $newUploadBoxCss, $content);

// Replace the HTML sections
$oldHtml = <<<'HTML'
                <div class="card-glass">
                    <h2 class="section-title"><span>1</span> อัปโหลดรูปภาพหลักฐาน</h2>
                    <div class="upload-grid">
                        <label class="upload-box">
                            <input type="file" name="front_cover" accept="image/*">
                            <div class="upload-icon">📸</div>
                            <span style="font-weight:500;">ปกหน้า</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="back_cover" accept="image/*">
                            <div class="upload-icon">📸</div>
                            <span style="font-weight:500;">ปกหลัง</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="spine" accept="image/*">
                            <div class="upload-icon">📚</div>
                            <span style="font-weight:500;">สันหนังสือ</span>
                            <img src="">
                        </label>
                    </div>
                    <h2 class="section-title" style="margin-top:30px;"><span>+</span> รูปเพิ่มเติม (ถ้ามี)</h2>
                    <div class="upload-grid">
                        <label class="upload-box">
                            <input type="file" name="additional_images[]" accept="image/*">
                            <div class="upload-icon">📷</div>
                            <span style="font-weight:500;">รูปเพิ่มเติม 1</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="additional_images[]" accept="image/*">
                            <div class="upload-icon">📷</div>
                            <span style="font-weight:500;">รูปเพิ่มเติม 2</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="additional_images[]" accept="image/*">
                            <div class="upload-icon">📷</div>
                            <span style="font-weight:500;">รูปเพิ่มเติม 3</span>
                            <img src="">
                        </label>
                    </div>

                </div>

                <div class="card-glass">
                    <h2 class="section-title"><span>2</span> รายละเอียดเพิ่มเติม</h2>
                    <label class="form-label">ตำหนิของหนังสือ (ถ้ามี)</label>
                    <textarea name="defects_description" class="form-control" rows="3" placeholder="ตัวอย่าง: รอยยับที่หน้า 45, คราบน้ำมุมล่างขวา..."></textarea>
                    
                    <label class="form-label">หมายเหตุการจัดส่ง</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="ตัวอย่าง: ห่อบับเบิ้ล 3 ชั้น, จัดส่งวันเสาร์นี้..."></textarea>
                    
                    <?php if ($request['trade_option'] === 'direct_escrow'): ?>
                    <label class="form-label" style="color:#d97706; margin-top: 10px;">ยอดเงินมัดจำที่คุณต้องการให้อีกฝ่ายโอน (บาท)</label>
                    <input type="number" name="required_deposit_amount" class="form-control" placeholder="ระบุจำนวนเงิน เช่น 300" min="0" step="1" required style="border-color:#fcd34d; background:#fffbeb;">
                    <p style="font-size:13px; color:#92400e; margin-top:-10px; margin-bottom:20px;">* ประเมินจากมูลค่าหนังสือของคุณ เพื่อใช้เป็นหลักประกันความปลอดภัย</p>
                    <?php endif; ?>
                </div>
HTML;

$newHtml = <<<'HTML'
                <div class="card-glass">
                    <h2 class="section-title" style="<?= $isIframe ? 'margin-bottom:10px; font-size:16px;' : '' ?>"><span>1</span> อัปโหลดรูปภาพหลักฐาน</h2>
                    <div class="upload-grid">
                        <label class="upload-box">
                            <input type="file" name="front_cover" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>">📸</div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">ปกหน้า</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="back_cover" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>">📸</div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">ปกหลัง</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="spine" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>">📚</div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">สันหนังสือ</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="additional_images[]" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>">📷</div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">รูปเพิ่มเติม 1</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="additional_images[]" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>">📷</div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">รูปเพิ่มเติม 2</span>
                            <img src="">
                        </label>
                        <label class="upload-box">
                            <input type="file" name="additional_images[]" accept="image/*">
                            <div class="upload-icon" style="<?= $isIframe ? 'font-size:24px;margin-bottom:5px;' : '' ?>">📷</div>
                            <span style="font-weight:500;<?= $isIframe ? 'font-size:11px;' : '' ?>">รูปเพิ่มเติม 3</span>
                            <img src="">
                        </label>
                    </div>
                </div>

                <div class="card-glass">
                    <h2 class="section-title" style="<?= $isIframe ? 'margin-bottom:10px; font-size:16px;' : '' ?>"><span>2</span> รายละเอียดเพิ่มเติม</h2>
                    <div style="display:flex; gap:<?= $isIframe ? '10px' : '20px' ?>;">
                        <div style="flex:1;">
                            <label class="form-label" style="<?= $isIframe ? 'font-size:12px;margin-bottom:4px;' : '' ?>">ตำหนิของหนังสือ (ถ้ามี)</label>
                            <textarea name="defects_description" class="form-control" rows="<?= $isIframe ? '2' : '3' ?>" placeholder="รอยยับหน้า 45..."></textarea>
                        </div>
                        <div style="flex:1;">
                            <label class="form-label" style="<?= $isIframe ? 'font-size:12px;margin-bottom:4px;' : '' ?>">หมายเหตุการจัดส่ง</label>
                            <textarea name="notes" class="form-control" rows="<?= $isIframe ? '2' : '3' ?>" placeholder="ห่อบับเบิ้ล..."></textarea>
                        </div>
                    </div>
                    <?php if ($request['trade_option'] === 'direct_escrow'): ?>
                    <label class="form-label" style="color:#d97706; margin-top: <?= $isIframe ? '5px' : '10px' ?>; <?= $isIframe ? 'font-size:12px; margin-bottom:4px;' : '' ?>">ยอดเงินมัดจำที่คุณต้องการให้อีกฝ่ายโอน (บาท)</label>
                    <input type="number" name="required_deposit_amount" class="form-control" placeholder="ระบุจำนวนเงิน เช่น 300" min="0" step="1" required style="border-color:#fcd34d; background:#fffbeb; <?= $isIframe ? 'padding:8px;' : '' ?>">
                    <p style="font-size:11px; color:#92400e; margin-top:2px; margin-bottom:<?= $isIframe ? '0' : '20px' ?>;">* ประเมินจากมูลค่าหนังสือของคุณ เพื่อใช้เป็นหลักประกันความปลอดภัย</p>
                    <?php endif; ?>
                </div>
HTML;

$content = str_replace($oldHtml, $newHtml, $content);
file_put_contents($file, $content);
echo "Updated form UI successfully.\n";
