<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

// Replace the inline form for Requester
$searchReq = <<<HTML
                                            <?php if (\$r['admin_qc_requester_status'] === 'pending' && !empty(\$r['requester_tracking_to_admin'])): ?>
                                                <form method="POST" style="margin-top:4px; display:flex; gap:4px;">
                                                    <input type="hidden" name="request_id" value="<?= \$r['request_id'] ?>">
                                                    <input type="hidden" name="side" value="requester">
                                                    <input type="hidden" name="action" value="physical_qc">
                                                    <button type="submit" name="qc_result" value="passed" class="badge badge-success" style="border:0;cursor:pointer">ให้ผ่าน</button>
                                                    <button type="submit" name="qc_result" value="failed" class="badge badge-danger" style="border:0;cursor:pointer">ไม่ผ่าน</button>
                                                </form>
                                            <?php endif; ?>
HTML;

$replaceReq = <<<HTML
                                            <?php if (\$r['admin_qc_requester_status'] === 'pending' && !empty(\$r['requester_tracking_to_admin'])): ?>
                                                <button type="button" onclick="openAdminQcForm(<?= \$r['request_id'] ?>, 'requester', <?= \$r['requester_id'] ?>)" class="action-btn" style="background:#0f172a; color:#fff; font-size:11px; padding:2px 6px; margin-top:4px; border:none; border-radius:4px;">ทำแบบฟอร์มตรวจรับ (QC)</button>
                                            <?php elseif (\$r['admin_qc_requester_status'] !== 'pending'): ?>
                                                <span class="badge badge-<?= \$r['requester_qc_acceptance_status'] === 'accepted' ? 'success' : (\$r['requester_qc_acceptance_status'] === 'rejected' ? 'danger' : 'warning') ?>" style="margin-left:4px;">
                                                    ผู้รับ: <?= \$r['requester_qc_acceptance_status'] === 'accepted' ? 'ยอมรับแล้ว' : (\$r['requester_qc_acceptance_status'] === 'rejected' ? 'ปฏิเสธ' : 'รอการยืนยัน') ?>
                                                </span>
                                            <?php endif; ?>
HTML;

// Replace the inline form for Offer
$searchOff = <<<HTML
                                            <?php if (\$r['admin_qc_offer_status'] === 'pending' && !empty(\$r['offer_tracking_to_admin'])): ?>
                                                <form method="POST" style="margin-top:4px; display:flex; gap:4px;">
                                                    <input type="hidden" name="request_id" value="<?= \$r['request_id'] ?>">
                                                    <input type="hidden" name="side" value="offer">
                                                    <input type="hidden" name="action" value="physical_qc">
                                                    <button type="submit" name="qc_result" value="passed" class="badge badge-success" style="border:0;cursor:pointer">ให้ผ่าน</button>
                                                    <button type="submit" name="qc_result" value="failed" class="badge badge-danger" style="border:0;cursor:pointer">ไม่ผ่าน</button>
                                                </form>
                                            <?php endif; ?>
HTML;

$replaceOff = <<<HTML
                                            <?php if (\$r['admin_qc_offer_status'] === 'pending' && !empty(\$r['offer_tracking_to_admin'])): ?>
                                                <button type="button" onclick="openAdminQcForm(<?= \$r['request_id'] ?>, 'offer', <?= \$r['owner_id'] ?>)" class="action-btn" style="background:#0f172a; color:#fff; font-size:11px; padding:2px 6px; margin-top:4px; border:none; border-radius:4px;">ทำแบบฟอร์มตรวจรับ (QC)</button>
                                            <?php elseif (\$r['admin_qc_offer_status'] !== 'pending'): ?>
                                                <span class="badge badge-<?= \$r['offer_qc_acceptance_status'] === 'accepted' ? 'success' : (\$r['offer_qc_acceptance_status'] === 'rejected' ? 'danger' : 'warning') ?>" style="margin-left:4px;">
                                                    ผู้รับ: <?= \$r['offer_qc_acceptance_status'] === 'accepted' ? 'ยอมรับแล้ว' : (\$r['offer_qc_acceptance_status'] === 'rejected' ? 'ปฏิเสธ' : 'รอการยืนยัน') ?>
                                                </span>
                                            <?php endif; ?>
HTML;

$content = str_replace($searchReq, $replaceReq, $content);
$content = str_replace($searchOff, $replaceOff, $content);

file_put_contents($file, $content);
echo "Buttons updated.\n";
