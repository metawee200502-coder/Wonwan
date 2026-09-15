<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// 1. SQL
$old_sql = <<< 'PHP'
        $reqs = $pdo->query("
            SELECT r.*,
                   e_req.title as req_title, e_req.image_cover as req_img, e_req.owner_id as target_owner_id,
                   e_off.title as off_title, e_off.image_cover as off_img, r.requester_id,
                   u1.display_name as target_owner_name, u1.profile_image as target_owner_image, u1.email as target_owner_email,
                   u2.display_name as requester_name, u2.profile_image as requester_image, u2.email as requester_email,
                   c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects,
                   c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects
            FROM exchange_requests r
PHP;

$new_sql = <<< 'PHP'
        $reqs = $pdo->query("
            SELECT r.*,
                   e_req.title as req_title, e_req.image_cover as req_img, e_req.owner_id as target_owner_id,
                   e_off.title as off_title, e_off.image_cover as off_img, r.requester_id,
                   u1.display_name as target_owner_name, u1.profile_image as target_owner_image, u1.email as target_owner_email,
                   u2.display_name as requester_name, u2.profile_image as requester_image, u2.email as requester_email,
                   c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects, c_off.defects_description as off_cond_defects_desc, c_off.notes as off_cond_notes, c_off.video_url as off_cond_video,
                   c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects, c_req.defects_description as req_cond_defects_desc, c_req.notes as req_cond_notes, c_req.video_url as req_cond_video
            FROM exchange_requests r
PHP;
$content = str_replace($old_sql, $new_sql, $content);

// 2. Col 3
$old_col3 = <<< 'PHP'
                                <!-- Column 3: Original Condition -->
                                <td>
                                    <div style="margin-bottom: 15px;">
                                        <div style="font-size:11px; font-weight:bold; color:#334155;">หนังสือของผู้ขอแลก</div>
                                        <div class="book-title" style="font-size:11px; margin-bottom:4px;">📖 <?= htmlspecialchars($r['off_title'] ?? '') ?></div>
                                        <?php if (!empty($r['req_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openCondModal('<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_front'])) ?>', '<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_back'])) ?>', '<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_spine'])) ?>')">🔎 ตรวจสอบสภาพตามรูป</button>
                                        <?php else: ?>
                                            <span style="font-size:11px; color:#94a3b8;">ไม่มีรูปสภาพ</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="border-top:1px dashed #e2e8f0; padding-top:10px;">
                                        <div style="font-size:11px; font-weight:bold; color:#7f1d1d;">หนังสือของผู้รับแลก</div>
                                        <div class="book-title" style="font-size:11px; margin-bottom:4px;">📖 <?= htmlspecialchars($r['req_title'] ?? '') ?></div>
                                        <?php if (!empty($r['off_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openCondModal('<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_front'])) ?>', '<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_back'])) ?>', '<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_spine'])) ?>')">🔎 ตรวจสอบสภาพตามรูป</button>
                                        <?php else: ?>
                                            <span style="font-size:11px; color:#94a3b8;">ไม่มีรูปสภาพ</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
PHP;

$new_col3 = <<< 'PHP'
                                <!-- Column 3: Original Condition -->
                                <td>
                                    <div style="margin-bottom: 15px;">
                                        <div style="font-size:11px; font-weight:bold; color:#334155;">หนังสือของผู้ขอแลก</div>
                                        <div class="book-title" style="font-size:11px; margin-bottom:4px;">📖 <?= htmlspecialchars($r['off_title'] ?? '') ?></div>
                                        <?php if (!empty($r['req_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openDynamicCondModal('req_cond_<?= $r['request_id'] ?>')">🔎 ตรวจสอบฟอร์มสภาพ</button>
                                            
                                            <!-- Hidden Data for Requester Condition Form -->
                                            <div id="req_cond_<?= $r['request_id'] ?>" style="display:none;">
                                                <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                                                    <div style="flex:1; min-width:200px;">
                                                        <p style="margin-bottom:5px; font-weight:600; font-size:14px;">ปกหน้า</p>
                                                        <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openModal(this.src)" src="<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_front'])) ?>">
                                                    </div>
                                                    <div style="flex:1; min-width:200px;">
                                                        <p style="margin-bottom:5px; font-weight:600; font-size:14px;">ปกหลัง</p>
                                                        <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openModal(this.src)" src="<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_back'])) ?>">
                                                    </div>
                                                    <div style="flex:1; min-width:200px;">
                                                        <p style="margin-bottom:5px; font-weight:600; font-size:14px;">สันหนังสือ</p>
                                                        <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openModal(this.src)" src="<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_spine'])) ?>">
                                                    </div>
                                                </div>
                                                
                                                <?php 
                                                $req_defects = json_decode($r['req_cond_defects'] ?? '[]', true) ?: [];
                                                if (!empty($req_defects)): ?>
                                                    <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                                                        <h4 style="margin:0 0 10px 0; font-size:15px; color:#b91c1c;">📸 รูปภาพตำหนิ (<?= count($req_defects) ?> รูป)</h4>
                                                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                                            <?php foreach ($req_defects as $d): ?>
                                                                <img style="width:120px; height:120px; object-fit:cover; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="<?= htmlspecialchars(str_replace('\\', '/', $d)) ?>">
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($r['req_cond_defects_desc'])): ?>
                                                    <div style="margin-top:15px; background:#fffbeb; border-left:4px solid #f59e0b; padding:12px; border-radius:4px; font-size:14px; color:#92400e;">
                                                        <strong>📝 อธิบายตำหนิ:</strong><br>
                                                        <?= nl2br(htmlspecialchars($r['req_cond_defects_desc'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($r['req_cond_notes'])): ?>
                                                    <div style="margin-top:10px; background:#f8fafc; border-left:4px solid #94a3b8; padding:12px; border-radius:4px; font-size:14px; color:#334155;">
                                                        <strong>ℹ️ หมายเหตุเพิ่มเติม:</strong><br>
                                                        <?= nl2br(htmlspecialchars($r['req_cond_notes'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($r['req_cond_video'])): ?>
                                                    <div style="margin-top:15px;">
                                                        <a href="<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_video'])) ?>" target="_blank" style="background:#e0e7ff; color:#4338ca; padding:8px 16px; border-radius:20px; font-size:13px; text-decoration:none; display:inline-block; font-weight:600;">🎥 ดูคลิปวิดีโอสภาพหนังสือ</a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                        <?php else: ?>
                                            <span style="font-size:11px; color:#94a3b8;">ไม่มีรูปสภาพ</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="border-top:1px dashed #e2e8f0; padding-top:10px;">
                                        <div style="font-size:11px; font-weight:bold; color:#7f1d1d;">หนังสือของผู้รับแลก</div>
                                        <div class="book-title" style="font-size:11px; margin-bottom:4px;">📖 <?= htmlspecialchars($r['req_title'] ?? '') ?></div>
                                        <?php if (!empty($r['off_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openDynamicCondModal('off_cond_<?= $r['request_id'] ?>')">🔎 ตรวจสอบฟอร์มสภาพ</button>
                                            
                                            <!-- Hidden Data for Offerer Condition Form -->
                                            <div id="off_cond_<?= $r['request_id'] ?>" style="display:none;">
                                                <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                                                    <div style="flex:1; min-width:200px;">
                                                        <p style="margin-bottom:5px; font-weight:600; font-size:14px;">ปกหน้า</p>
                                                        <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openModal(this.src)" src="<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_front'])) ?>">
                                                    </div>
                                                    <div style="flex:1; min-width:200px;">
                                                        <p style="margin-bottom:5px; font-weight:600; font-size:14px;">ปกหลัง</p>
                                                        <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openModal(this.src)" src="<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_back'])) ?>">
                                                    </div>
                                                    <div style="flex:1; min-width:200px;">
                                                        <p style="margin-bottom:5px; font-weight:600; font-size:14px;">สันหนังสือ</p>
                                                        <img style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer; height:200px; object-fit:cover;" onclick="openModal(this.src)" src="<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_spine'])) ?>">
                                                    </div>
                                                </div>
                                                
                                                <?php 
                                                $off_defects = json_decode($r['off_cond_defects'] ?? '[]', true) ?: [];
                                                if (!empty($off_defects)): ?>
                                                    <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                                                        <h4 style="margin:0 0 10px 0; font-size:15px; color:#b91c1c;">📸 รูปภาพตำหนิ (<?= count($off_defects) ?> รูป)</h4>
                                                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                                            <?php foreach ($off_defects as $d): ?>
                                                                <img style="width:120px; height:120px; object-fit:cover; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="<?= htmlspecialchars(str_replace('\\', '/', $d)) ?>">
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($r['off_cond_defects_desc'])): ?>
                                                    <div style="margin-top:15px; background:#fffbeb; border-left:4px solid #f59e0b; padding:12px; border-radius:4px; font-size:14px; color:#92400e;">
                                                        <strong>📝 อธิบายตำหนิ:</strong><br>
                                                        <?= nl2br(htmlspecialchars($r['off_cond_defects_desc'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($r['off_cond_notes'])): ?>
                                                    <div style="margin-top:10px; background:#f8fafc; border-left:4px solid #94a3b8; padding:12px; border-radius:4px; font-size:14px; color:#334155;">
                                                        <strong>ℹ️ หมายเหตุเพิ่มเติม:</strong><br>
                                                        <?= nl2br(htmlspecialchars($r['off_cond_notes'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($r['off_cond_video'])): ?>
                                                    <div style="margin-top:15px;">
                                                        <a href="<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_video'])) ?>" target="_blank" style="background:#e0e7ff; color:#4338ca; padding:8px 16px; border-radius:20px; font-size:13px; text-decoration:none; display:inline-block; font-weight:600;">🎥 ดูคลิปวิดีโอสภาพหนังสือ</a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                        <?php else: ?>
                                            <span style="font-size:11px; color:#94a3b8;">ไม่มีรูปสภาพ</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
PHP;
$content = str_replace($old_col3, $new_col3, $content);


// 3. Modal HTML
$old_cond_modal = <<< 'PHP'
    <div id="condModal" class="modal" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal-content" style="background:#fff; padding:20px; border-radius:12px; width:90%; max-width:800px; max-height:90vh; overflow-y:auto; margin:5% auto;">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">รูปภาพสภาพหนังสือที่ตกลงกันไว้</h3>
            <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                <div style="flex:1; min-width:200px;">
                    <p style="margin-bottom:5px; font-weight:600;">ปกหน้า</p>
                    <img id="condFront" style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="">
                </div>
                <div style="flex:1; min-width:200px;">
                    <p style="margin-bottom:5px; font-weight:600;">ปกหลัง</p>
                    <img id="condBack" style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="">
                </div>
                <div style="flex:1; min-width:200px;">
                    <p style="margin-bottom:5px; font-weight:600;">สันหนังสือ</p>
                    <img id="condSpine" style="width:100%; border-radius:8px; border:1px solid #ddd; cursor:pointer;" onclick="openModal(this.src)" src="">
                </div>
            </div>
            <div style="text-align:right; margin-top:20px;">
                <button type="button" class="btn-reject" onclick="document.getElementById('condModal').style.display='none'">ปิด</button>
            </div>
        </div>
    </div>
PHP;

$new_cond_modal = <<< 'PHP'
    <div id="condModal" class="modal" onclick="if(event.target===this)this.style.display='none'">
        <div class="modal-content" style="background:#fff; padding:30px; border-radius:12px; width:90%; max-width:900px; max-height:90vh; overflow-y:auto; margin:5% auto; position:relative;">
            <span onclick="document.getElementById('condModal').style.display='none'" style="position:absolute; top:20px; right:20px; cursor:pointer; font-size:24px; color:#999;">&times;</span>
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px; font-size:20px; color:#1f2937;"><i class="fas fa-file-invoice"></i> ข้อมูลฟอร์มสภาพหนังสือที่ตกลงกันไว้</h3>
            
            <div id="condModalBody">
                <!-- Content will be injected here via JS -->
            </div>
            
            <div style="text-align:center; margin-top:30px; border-top:1px solid #eee; padding-top:20px;">
                <button type="button" class="btn-reject" style="padding:10px 24px; font-size:14px;" onclick="document.getElementById('condModal').style.display='none'">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
PHP;
$content = str_replace($old_cond_modal, $new_cond_modal, $content);

// 4. JS Function
$old_js = <<< 'PHP'
        function openCondModal(f, b, s) {
            document.getElementById('condFront').src = f;
            document.getElementById('condBack').src = b;
            document.getElementById('condSpine').src = s;
            document.getElementById('condModal').style.display = 'flex';
        }
PHP;

$new_js = <<< 'PHP'
        function openDynamicCondModal(dataDivId) {
            const dataHtml = document.getElementById(dataDivId).innerHTML;
            document.getElementById('condModalBody').innerHTML = dataHtml;
            document.getElementById('condModal').style.display = 'flex';
        }
PHP;
$content = str_replace($old_js, $new_js, $content);

file_put_contents($file, $content);
echo "Patched dynamically expanding form data modal!";
