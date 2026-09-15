<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// Fix Requester Slip
$old_req_slip = <<< 'PHP'
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <strong>สลิป:</strong> <?= getSlipStatusBadge($r['requester_slip_status']) ?>
                                                    <?php if ($r['requester_slip_status'] === 'pending' && !empty($r['requester_slip_url'])): ?>
                                                        <form method="POST" style="display:inline-flex; gap:2px;">
                                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="requester">
                                                            <input type="hidden" name="action" value="approve_slip">
                                                            <a href="#" onclick="openModal('<?= htmlspecialchars($r['requester_slip_url']) ?>'); return false;" style="color:#0284c7; text-decoration:none; margin-right:4px;">[ดูรูป]</a>
                                                            <button type="submit" name="slip_result" value="approved" class="btn-approve" style="padding:2px 6px; font-size:10px;">อนุมัติ</button>
                                                            <button type="submit" name="slip_result" value="rejected" class="btn-reject" style="padding:2px 6px; font-size:10px;">ไม่อนุมัติ</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
PHP;

$new_req_slip = <<< 'PHP'
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <strong>สลิป:</strong> <?= getSlipStatusBadge($r['requester_slip_status']) ?>
                                                    <?php if (!empty($r['requester_slip_url'])): ?>
                                                        <a href="#" onclick="openModal('<?= htmlspecialchars(str_replace('\\', '/', $r['requester_slip_url'])) ?>'); return false;" style="color:#0284c7; text-decoration:none; margin-right:4px;">[ดูสลิป]</a>
                                                    <?php endif; ?>
                                                    <?php if ($r['requester_slip_status'] === 'pending' && !empty($r['requester_slip_url'])): ?>
                                                        <form method="POST" style="display:inline-flex; gap:2px;">
                                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="requester">
                                                            <input type="hidden" name="action" value="approve_slip">
                                                            <button type="submit" name="slip_result" value="approved" class="btn-approve" style="padding:2px 6px; font-size:10px;">อนุมัติ</button>
                                                            <button type="submit" name="slip_result" value="rejected" class="btn-reject" style="padding:2px 6px; font-size:10px;">ไม่อนุมัติ</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
PHP;
$content = str_replace($old_req_slip, $new_req_slip, $content);

// Fix Offer Slip
$old_off_slip = <<< 'PHP'
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <strong>สลิป:</strong> <?= getSlipStatusBadge($r['offer_slip_status']) ?>
                                                    <?php if ($r['offer_slip_status'] === 'pending' && !empty($r['offer_slip_url'])): ?>
                                                        <form method="POST" style="display:inline-flex; gap:2px;">
                                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="offer">
                                                            <input type="hidden" name="action" value="approve_slip">
                                                            <a href="#" onclick="openModal('<?= htmlspecialchars($r['offer_slip_url']) ?>'); return false;" style="color:#0284c7; text-decoration:none; margin-right:4px;">[ดูรูป]</a>
                                                            <button type="submit" name="slip_result" value="approved" class="btn-approve" style="padding:2px 6px; font-size:10px;">อนุมัติ</button>
                                                            <button type="submit" name="slip_result" value="rejected" class="btn-reject" style="padding:2px 6px; font-size:10px;">ไม่อนุมัติ</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
PHP;

$new_off_slip = <<< 'PHP'
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <strong>สลิป:</strong> <?= getSlipStatusBadge($r['offer_slip_status']) ?>
                                                    <?php if (!empty($r['offer_slip_url'])): ?>
                                                        <a href="#" onclick="openModal('<?= htmlspecialchars(str_replace('\\', '/', $r['offer_slip_url'])) ?>'); return false;" style="color:#0284c7; text-decoration:none; margin-right:4px;">[ดูสลิป]</a>
                                                    <?php endif; ?>
                                                    <?php if ($r['offer_slip_status'] === 'pending' && !empty($r['offer_slip_url'])): ?>
                                                        <form method="POST" style="display:inline-flex; gap:2px;">
                                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="offer">
                                                            <input type="hidden" name="action" value="approve_slip">
                                                            <button type="submit" name="slip_result" value="approved" class="btn-approve" style="padding:2px 6px; font-size:10px;">อนุมัติ</button>
                                                            <button type="submit" name="slip_result" value="rejected" class="btn-reject" style="padding:2px 6px; font-size:10px;">ไม่อนุมัติ</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
PHP;
$content = str_replace($old_off_slip, $new_off_slip, $content);

// Fix Req Cond Front
$old_req_cond = <<< 'PHP'
                                        <?php if (!empty($r['req_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openCondModal('<?= htmlspecialchars($r['req_cond_front']) ?>', '<?= htmlspecialchars($r['req_cond_back']) ?>', '<?= htmlspecialchars($r['req_cond_spine']) ?>')">🔎 ตรวจสอบสภาพตามรูป</button>
                                        <?php else: ?>
PHP;

$new_req_cond = <<< 'PHP'
                                        <?php if (!empty($r['req_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openCondModal('<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_front'])) ?>', '<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_back'])) ?>', '<?= htmlspecialchars(str_replace('\\', '/', $r['req_cond_spine'])) ?>')">🔎 ตรวจสอบสภาพตามรูป</button>
                                        <?php else: ?>
PHP;
$content = str_replace($old_req_cond, $new_req_cond, $content);

// Fix Off Cond Front
$old_off_cond = <<< 'PHP'
                                        <?php if (!empty($r['off_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openCondModal('<?= htmlspecialchars($r['off_cond_front']) ?>', '<?= htmlspecialchars($r['off_cond_back']) ?>', '<?= htmlspecialchars($r['off_cond_spine']) ?>')">🔎 ตรวจสอบสภาพตามรูป</button>
                                        <?php else: ?>
PHP;

$new_off_cond = <<< 'PHP'
                                        <?php if (!empty($r['off_cond_front'])): ?>
                                            <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openCondModal('<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_front'])) ?>', '<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_back'])) ?>', '<?= htmlspecialchars(str_replace('\\', '/', $r['off_cond_spine'])) ?>')">🔎 ตรวจสอบสภาพตามรูป</button>
                                        <?php else: ?>
PHP;
$content = str_replace($old_off_cond, $new_off_cond, $content);

file_put_contents($file, $content);
echo "Patched fix_modals!";
