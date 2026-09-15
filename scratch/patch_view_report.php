<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// 1. Add fetching of qc reports
$fetch_code = <<< 'PHP'
// Data Fetching
$reqs = $pdo->query("
    SELECT r.*,
           e_req.title as req_title, e_req.image_cover as req_img, e_req.owner_id as target_owner_id,
           e_off.title as off_title, e_off.image_cover as off_img, r.requester_id,
           u1.display_name as target_owner_name, u1.profile_image as target_owner_image, u1.email as target_owner_email,
           u2.display_name as requester_name, u2.profile_image as requester_image, u2.email as requester_email,
           c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects, c_off.defects_description as off_cond_defects_desc, c_off.notes as off_cond_notes,
           c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects, c_req.defects_description as req_cond_defects_desc, c_req.notes as req_cond_notes
    FROM exchange_requests r
    LEFT JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
    LEFT JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
    LEFT JOIN users u1 ON e_req.owner_id = u1.user_id
    LEFT JOIN users u2 ON r.requester_id = u2.user_id
    LEFT JOIN trade_condition_forms c_off ON c_off.request_id = r.request_id AND c_off.owner_id = e_req.owner_id
    LEFT JOIN trade_condition_forms c_req ON c_req.request_id = r.request_id AND c_req.owner_id = r.requester_id
    WHERE r.status IN ('accepted', 'completed')
    ORDER BY r.updated_at ASC
")->fetchAll();

$qc_reports = [];
if (!empty($reqs)) {
    $req_ids = array_column($reqs, 'request_id');
    $in_ids = implode(',', array_map('intval', $req_ids));
    $reports = $pdo->query("SELECT * FROM admin_qc_reports WHERE request_id IN ($in_ids)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reports as $rp) {
        $qc_reports[$rp['request_id']][$rp['item_owner_id']] = $rp;
    }
}
PHP;

// Find the exact old string
$old_fetch_code = <<< 'PHP'
// Data Fetching
$reqs = $pdo->query("
    SELECT r.*,
           e_req.title as req_title, e_req.image_cover as req_img, e_req.owner_id as target_owner_id,
           e_off.title as off_title, e_off.image_cover as off_img, r.requester_id,
           u1.display_name as target_owner_name, u1.profile_image as target_owner_image, u1.email as target_owner_email,
           u2.display_name as requester_name, u2.profile_image as requester_image, u2.email as requester_email,
           c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects, c_off.defects_description as off_cond_defects_desc, c_off.notes as off_cond_notes,
           c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects, c_req.defects_description as req_cond_defects_desc, c_req.notes as req_cond_notes
    FROM exchange_requests r
    LEFT JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
    LEFT JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
    LEFT JOIN users u1 ON e_req.owner_id = u1.user_id
    LEFT JOIN users u2 ON r.requester_id = u2.user_id
    LEFT JOIN trade_condition_forms c_off ON c_off.request_id = r.request_id AND c_off.owner_id = e_req.owner_id
    LEFT JOIN trade_condition_forms c_req ON c_req.request_id = r.request_id AND c_req.owner_id = r.requester_id
    WHERE r.status IN ('accepted', 'completed')
    ORDER BY r.updated_at ASC
")->fetchAll();
PHP;

$content = str_replace($old_fetch_code, $fetch_code, $content);

// 2. Replace the anchor links for 'ดูรายงาน'
$old_anchor1 = '                                        <a href="#" style="color:var(--primary); font-size:11px;">ดูรายงาน</a>';
$new_anchor1 = '                                        <a href="#" onclick="openDynamicCondModal(\'qc_report_'.$r['request_id'].'\'); return false;" style="color:var(--primary); font-size:11px;">ดูรายงาน</a>';
// I need to use regex or string replace with placeholder
// But actually I can just do a str_replace on that specific line, since it's identical in both places.

// Let's do it carefully with regex so I don't break PHP variable context.
$content = preg_replace(
    '/<a href="#" style="color:var\(--primary\); font-size:11px;">ดูรายงาน<\/a>/',
    '<a href="#" onclick="openDynamicCondModal(\'qc_report_<?= $r[\'request_id\'] ?>\'); return false;" style="color:var(--primary); font-size:11px; text-decoration:underline;">ดูรายงาน</a>',
    $content
);

// 3. Inject the hidden div at the end of Column 4
$hidden_div = <<< 'PHP'
                            <?php endif; ?>
                            
                            <!-- Hidden QC Report Data -->
                            <?php 
                            $rep_req = $qc_reports[$r['request_id']][$r['requester_id']] ?? null;
                            $rep_off = $qc_reports[$r['request_id']][$r['target_owner_id']] ?? null;
                            if ($rep_req || $rep_off): 
                            ?>
                            <div id="qc_report_<?= $r['request_id'] ?>" style="display:none;">
                                <div style="display:flex; gap:20px; flex-wrap:wrap; margin-top:20px;">
                                    <?php if ($rep_req): ?>
                                    <div style="flex:1; min-width:300px; background:#F8FAFC; border:1px solid #CBD5E1; border-radius:12px; padding:15px;">
                                        <h4 style="margin:0 0 10px 0; color:#0F172A; font-size:15px; border-bottom:2px solid #E2E8F0; padding-bottom:8px;">รายงานฝั่ง <span style="color:#059669;">[ผู้ขอแลก]</span></h4>
                                        <div style="margin-bottom:10px;">
                                            <strong>สถานะ:</strong> <?= $rep_req['is_match'] ? '<span style="color:#059669;">✅ ตรงปก (ผ่าน)</span>' : '<span style="color:#DC2626;">❌ ไม่ตรงปก (ไม่ผ่าน)</span>' ?>
                                        </div>
                                        <div style="margin-bottom:10px; background:#fff; padding:10px; border-radius:6px; border:1px solid #E2E8F0;">
                                            <strong>หมายเหตุจาก Admin:</strong><br>
                                            <?= nl2br(htmlspecialchars($rep_req['admin_notes'] ?? '-')) ?>
                                        </div>
                                        <?php 
                                        $req_photos = json_decode($rep_req['photo_urls'] ?? '[]', true) ?: [];
                                        if (!empty($req_photos)): ?>
                                        <div>
                                            <strong>รูปภาพแนบ:</strong>
                                            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;">
                                                <?php foreach ($req_photos as $img): ?>
                                                <img src="<?= htmlspecialchars(str_replace('\\', '/', $img)) ?>" style="width:80px; height:80px; object-fit:cover; border-radius:8px; cursor:pointer; border:1px solid #cbd5e1;" onclick="openImgModal(this.src)">
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($rep_off): ?>
                                    <div style="flex:1; min-width:300px; background:#FEF2F2; border:1px solid #FECACA; border-radius:12px; padding:15px;">
                                        <h4 style="margin:0 0 10px 0; color:#7F1D1D; font-size:15px; border-bottom:2px solid #FCA5A5; padding-bottom:8px;">รายงานฝั่ง <span style="color:#DC2626;">[ผู้รับแลก]</span></h4>
                                        <div style="margin-bottom:10px;">
                                            <strong>สถานะ:</strong> <?= $rep_off['is_match'] ? '<span style="color:#059669;">✅ ตรงปก (ผ่าน)</span>' : '<span style="color:#DC2626;">❌ ไม่ตรงปก (ไม่ผ่าน)</span>' ?>
                                        </div>
                                        <div style="margin-bottom:10px; background:#fff; padding:10px; border-radius:6px; border:1px solid #FECACA;">
                                            <strong>หมายเหตุจาก Admin:</strong><br>
                                            <?= nl2br(htmlspecialchars($rep_off['admin_notes'] ?? '-')) ?>
                                        </div>
                                        <?php 
                                        $off_photos = json_decode($rep_off['photo_urls'] ?? '[]', true) ?: [];
                                        if (!empty($off_photos)): ?>
                                        <div>
                                            <strong>รูปภาพแนบ:</strong>
                                            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:8px;">
                                                <?php foreach ($off_photos as $img): ?>
                                                <img src="<?= htmlspecialchars(str_replace('\\', '/', $img)) ?>" style="width:80px; height:80px; object-fit:cover; border-radius:8px; cursor:pointer; border:1px solid #fca5a5;" onclick="openImgModal(this.src)">
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
PHP;

$content = str_replace("                            <?php endif; ?>\n                        </div>", $hidden_div . "\n                        </div>", $content);

// 4. Change Modal Title slightly to be generic, or add a script override
$old_modal_title = '<h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px; font-size:20px; color:#1f2937;"><i class="fas fa-file-invoice"></i> ข้อมูลฟอร์มสภาพหนังสือ</h3>';
$new_modal_title = '<h3 id="condModalTitle" style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:15px; font-size:20px; color:#1f2937;"><i class="fas fa-file-invoice"></i> ข้อมูลรายละเอียด</h3>';
$content = str_replace($old_modal_title, $new_modal_title, $content);

$old_js = '        function openDynamicCondModal(dataDivId) {';
$new_js = <<< 'JS'
        function openDynamicCondModal(dataDivId) {
            if (dataDivId.includes('qc_report')) {
                document.getElementById('condModalTitle').innerHTML = '<i class="fas fa-clipboard-check"></i> รายงานการตรวจสภาพหนังสือ (QC Report)';
            } else {
                document.getElementById('condModalTitle').innerHTML = '<i class="fas fa-file-invoice"></i> ข้อมูลฟอร์มสภาพหนังสือดั้งเดิม';
            }
JS;
$content = str_replace($old_js, $new_js, $content);

file_put_contents($file, $content);
echo "Patched report viewer!";
