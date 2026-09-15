<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

$old_req_slip = <<< 'PHP'
                                              <div style="display:flex; align-items:center; gap:6px;">
                                                  <strong>สลิป:</strong> <?= getSlipStatusBadge($r['requester_slip_status']) ?>
                                                  <?php if ($r['requester_slip_status'] === 'pending' && !empty($r['requester_slip_url'])): ?>
                                                      <form method="POST" style="display:inline-flex; gap:2px;">
                                                          <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                          <input type="hidden" name="side" value="requester">
                                                          <input type="hidden" name="action" value="approve_slip">
                                                          <a href="#" onclick="openModal('<?= htmlspecialchars($r['requester_slip_url']) ?>'); return false;" style="color:#0284c7; text-decoration:none; margin-right:4px;">[ดูสลิป]</a>
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
                                                      <a href="#" onclick="openModal('<?= htmlspecialchars(str_replace('\\', '/', $r['requester_slip_url'])) ?>'); return false;" style="color:#0284c7; text-decoration:none; font-size:12px; font-weight:bold; background:#e0f2fe; padding:2px 6px; border-radius:4px; border:1px solid #bae6fd;">[🔎 ดูสลิป]</a>
                                                  <?php endif; ?>
                                                  
                                                  <?php if ($r['requester_slip_status'] === 'pending' && !empty($r['requester_slip_url'])): ?>
                                                      <form method="POST" style="display:inline-flex; gap:2px; margin-left:4px;">
                                                          <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                          <input type="hidden" name="side" value="requester">
                                                          <input type="hidden" name="action" value="approve_slip">
                                                          <button type="submit" name="slip_result" value="approved" class="btn-approve" style="padding:2px 6px; font-size:10px;">อนุมัติ</button>
                                                          <button type="submit" name="slip_result" value="rejected" class="btn-reject" style="padding:2px 6px; font-size:10px;">ไม่อนุมัติ</button>
                                                      </form>
                                                  <?php endif; ?>
                                              </div>
                                              <div style="margin-top:4px; font-size:11px; color:#475569; padding:4px; background:#f1f5f9; border-radius:4px; display:inline-block;">
                                                  <strong>ยอดเรียกเก็บ:</strong> 100 บาท (ค่าบริการ QC 50 บ. + ค่าส่ง 50 บ.)
                                              </div>
PHP;

$old_off_slip = <<< 'PHP'
                                              <div style="display:flex; align-items:center; gap:6px;">
                                                  <strong>สลิป:</strong> <?= getSlipStatusBadge($r['offer_slip_status']) ?>
                                                  <?php if ($r['offer_slip_status'] === 'pending' && !empty($r['offer_slip_url'])): ?>
                                                      <form method="POST" style="display:inline-flex; gap:2px;">
                                                          <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                          <input type="hidden" name="side" value="offer">
                                                          <input type="hidden" name="action" value="approve_slip">
                                                          <a href="#" onclick="openModal('<?= htmlspecialchars($r['offer_slip_url']) ?>'); return false;" style="color:#0284c7; text-decoration:none; margin-right:4px;">[ดูสลิป]</a>
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
                                                      <a href="#" onclick="openModal('<?= htmlspecialchars(str_replace('\\', '/', $r['offer_slip_url'])) ?>'); return false;" style="color:#0284c7; text-decoration:none; font-size:12px; font-weight:bold; background:#e0f2fe; padding:2px 6px; border-radius:4px; border:1px solid #bae6fd;">[🔎 ดูสลิป]</a>
                                                  <?php endif; ?>
                                                  
                                                  <?php if ($r['offer_slip_status'] === 'pending' && !empty($r['offer_slip_url'])): ?>
                                                      <form method="POST" style="display:inline-flex; gap:2px; margin-left:4px;">
                                                          <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                          <input type="hidden" name="side" value="offer">
                                                          <input type="hidden" name="action" value="approve_slip">
                                                          <button type="submit" name="slip_result" value="approved" class="btn-approve" style="padding:2px 6px; font-size:10px;">อนุมัติ</button>
                                                          <button type="submit" name="slip_result" value="rejected" class="btn-reject" style="padding:2px 6px; font-size:10px;">ไม่อนุมัติ</button>
                                                      </form>
                                                  <?php endif; ?>
                                              </div>
                                              <div style="margin-top:4px; font-size:11px; color:#475569; padding:4px; background:#f1f5f9; border-radius:4px; display:inline-block;">
                                                  <strong>ยอดเรียกเก็บ:</strong> 100 บาท (ค่าบริการ QC 50 บ. + ค่าส่ง 50 บ.)
                                              </div>
PHP;

$content = str_replace($old_req_slip, $new_req_slip, $content);
$content = str_replace($old_off_slip, $new_off_slip, $content);

file_put_contents($file, $content);
echo "Patched slip buttons!";
