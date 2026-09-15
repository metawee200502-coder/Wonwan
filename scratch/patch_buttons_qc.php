<?php
$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

$search = <<<HTML
                                                    <?php if (\$r['admin_qc_requester_status'] === 'pending' && !empty(\$r['requester_tracking_to_admin'])): ?>
                                                        <form method="POST" style="margin-top:4px; display:flex; gap:4px;">
                                                            <input type="hidden" name="request_id" value="<?= \$r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="requester">
                                                            <input type="hidden" name="action" value="physical_qc">
                                                            <button type="submit" name="qc_result" value="passed" class="badge badge-success" style="border:0;cursor:pointer">ผ่าน</button>
                                                            <button type="submit" name="qc_result" value="failed" class="badge badge-danger" style="border:0;cursor:pointer">ไม่ผ่าน</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (\$r['admin_qc_offer_status'] === 'pending' && !empty(\$r['offer_tracking_to_admin'])): ?>
                                                        <form method="POST" style="margin-top:4px; display:flex; gap:4px;">
                                                            <input type="hidden" name="request_id" value="<?= \$r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="offer">
                                                            <input type="hidden" name="action" value="physical_qc">
                                                            <button type="submit" name="qc_result" value="passed" class="badge badge-success" style="border:0;cursor:pointer">ผ่าน</button>
                                                            <button type="submit" name="qc_result" value="failed" class="badge badge-danger" style="border:0;cursor:pointer">ไม่ผ่าน</button>
                                                        </form>
                                                    <?php endif; ?>
HTML;

$replace = <<<HTML
                                            <?php if (\$r['admin_qc_requester_status'] !== 'pending'): ?>
                                                <div style="font-size:11px; margin-top:2px;">ผู้ขอแลก (ฟอร์มที่แอดมินส่งให้): <span class="badge badge-<?= \$r['requester_qc_acceptance_status'] === 'accepted' ? 'success' : (\$r['requester_qc_acceptance_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= \$r['requester_qc_acceptance_status'] === 'accepted' ? 'ยอมรับแล้ว' : (\$r['requester_qc_acceptance_status'] === 'rejected' ? 'ปฏิเสธ' : 'รอการตอบรับ') ?></span></div>
                                            <?php endif; ?>
                                            
                                            <?php if (\$r['admin_qc_offer_status'] !== 'pending'): ?>
                                                <div style="font-size:11px; margin-top:2px;">ผู้โพสต์ (ฟอร์มที่แอดมินส่งให้): <span class="badge badge-<?= \$r['offer_qc_acceptance_status'] === 'accepted' ? 'success' : (\$r['offer_qc_acceptance_status'] === 'rejected' ? 'danger' : 'warning') ?>"><?= \$r['offer_qc_acceptance_status'] === 'accepted' ? 'ยอมรับแล้ว' : (\$r['offer_qc_acceptance_status'] === 'rejected' ? 'ปฏิเสธ' : 'รอการตอบรับ') ?></span></div>
                                            <?php endif; ?>

                                            <?php 
                                            // Show unified form if either side has tracking info, regardless of pending status (so they can view details)
                                            if (!empty(\$r['requester_tracking_to_admin']) || !empty(\$r['offer_tracking_to_admin'])): 
                                                \$isFormSubmitted = \$r['admin_qc_requester_status'] !== 'pending' || \$r['admin_qc_offer_status'] !== 'pending';
                                            ?>
                                                <button type="button" onclick="openAdminQcForm(<?= \$r['request_id'] ?>, <?= \$r['requester_id'] ?>, <?= \$r['owner_id'] ?>)" class="action-btn" style="background:#b66f68; color:#fff; font-size:12px; padding:6px 10px; margin-top:8px; border:none; border-radius:6px; width:100%; cursor:pointer;">
                                                    <span style="font-size:14px; margin-right:4px;">&#128221;</span> <?= \$isFormSubmitted ? 'ส่งฟอร์มแล้ว (ดูรายละเอียด)' : 'กรอกแบบฟอร์ม QC' ?>
                                                </button>
                                            <?php endif; ?>
HTML;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Replaced buttons in qc_exchanges.php\n";
