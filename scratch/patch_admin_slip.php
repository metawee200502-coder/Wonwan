<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

// 1. Add PHP handler for verify_slip
$handler = <<<PHP
    } elseif (\$action === 'verify_slip') {
        \$request_id = (int)\$_POST['request_id'];
        \$side = \$_POST['side'];
        \$slip_status = \$_POST['slip_status'];
        
        \$col = (\$side === 'requester') ? 'requester_slip_status' : 'offer_slip_status';
        \$stmt = \$pdo->prepare("UPDATE exchange_requests SET {\$col} = ? WHERE request_id = ?");
        \$stmt->execute([\$slip_status, \$request_id]);
        
        logAdminAction(\$pdo, \$_SESSION['email'] ?? 'unknown', 'VERIFY_SLIP', (string)\$request_id, "บันทึกผลตรวจสอบสลิปของ{\$side} ว่า {\$slip_status} สำหรับคำขอ {\$request_id}");
        
        \$_SESSION['flash_msg'] = "ตรวจสอบสลิปเรียบร้อยแล้ว";
PHP;
$content = str_replace("    } elseif (\$action === 'physical_qc') {", $handler . "\n    } elseif (\$action === 'physical_qc') {", $content);


// 2. Modify Slip display in the table
$searchSlip = <<<PHP
                                    <?php if (in_array(\$r['trade_option'], ['admin_qc', 'direct_escrow'])): ?>
                                        <div style="font-size:12px; margin-bottom:4px;">
                                            ผู้ขอแลก: <?= \$reqPaid ? '<span class="badge badge-success">ชำระแล้ว</span>' : '<span class="badge badge-warning">ยังไม่ชำระ</span>' ?>
                                            <?php if (\$reqPaid): ?> <a href="<?= htmlspecialchars(\$r['requester_slip_url']) ?>" target="_blank" style="font-size:11px;">[ดูสลิป]</a> <?php endif; ?>
                                        </div>
                                        <div style="font-size:12px;">
                                            ผู้โพสต์: <?= \$offPaid ? '<span class="badge badge-success">ชำระแล้ว</span>' : '<span class="badge badge-warning">ยังไม่ชำระ</span>' ?>
                                            <?php if (\$offPaid): ?> <a href="<?= htmlspecialchars(\$r['offer_slip_url']) ?>" target="_blank" style="font-size:11px;">[ดูสลิป]</a> <?php endif; ?>
                                        </div>
                                    <?php else: ?>
PHP;

$replaceSlip = <<<PHP
                                    <?php if (in_array(\$r['trade_option'], ['admin_qc', 'direct_escrow'])): ?>
                                        <div style="font-size:12px; margin-bottom:4px;">
                                            ผู้ขอแลก: 
                                            <?php if (\$reqPaid): ?> 
                                                <span class="badge badge-<?= \$r['requester_slip_status'] == 'approved' ? 'success' : (\$r['requester_slip_status'] == 'rejected' ? 'danger' : 'warning') ?>"><?= \$r['requester_slip_status'] ?: 'รอตรวจ' ?></span>
                                                <button onclick="openSlipModal('requester', <?= \$r['request_id'] ?>, '<?= htmlspecialchars(\$r['requester_slip_url']) ?>')" class="action-btn" style="font-size:11px; padding:2px 6px; margin-left:4px;">ตรวจสลิป</button>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">ยังไม่ชำระ</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size:12px;">
                                            ผู้โพสต์: 
                                            <?php if (\$offPaid): ?> 
                                                <span class="badge badge-<?= \$r['offer_slip_status'] == 'approved' ? 'success' : (\$r['offer_slip_status'] == 'rejected' ? 'danger' : 'warning') ?>"><?= \$r['offer_slip_status'] ?: 'รอตรวจ' ?></span>
                                                <button onclick="openSlipModal('offer', <?= \$r['request_id'] ?>, '<?= htmlspecialchars(\$r['offer_slip_url']) ?>')" class="action-btn" style="font-size:11px; padding:2px 6px; margin-left:4px;">ตรวจสลิป</button>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">ยังไม่ชำระ</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
PHP;
$content = str_replace($searchSlip, $replaceSlip, $content);

// 3. Add Slip Modal
$slipModal = <<<HTML

    <!-- Slip Verification Modal -->
    <div id="slipAdminModal" class="modal">
        <div class="modal-content" style="background:#fff; border-radius:12px; width:90%; max-width:400px; padding:20px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:15px;">
                <h3 style="margin:0; color:#8b5266;">ตรวจสอบสลิป</h3>
                <span onclick="document.getElementById('slipAdminModal').style.display='none'" style="cursor:pointer; font-size:24px;">&times;</span>
            </div>
            <div style="text-align:center; margin-bottom:15px; background:#f9f9f9; padding:10px; border-radius:8px;">
                <img id="slipImgModal" src="" style="max-width:100%; max-height:400px; border-radius:8px; cursor:pointer;" onclick="window.open(this.src, '_blank')">
            </div>
            <div style="background:#e0f2fe; color:#0369a1; padding:12px; border-radius:8px; margin-bottom:15px; font-size:14px;">
                <strong>ยอดเงินที่ต้องชำระ: 100 บาท</strong><br>
                - ค่าบริการส่วนกลาง 50 บาท<br>
                - ค่าส่งสินค้ากลับ 50 บาท
            </div>
            <form method="POST" style="display:flex; gap:10px;">
                <input type="hidden" name="action" value="verify_slip">
                <input type="hidden" name="request_id" id="slip_request_id">
                <input type="hidden" name="side" id="slip_side">
                <button type="submit" name="slip_status" value="approved" class="btn-approve" style="flex:1;">✅ อนุมัติสลิป</button>
                <button type="submit" name="slip_status" value="rejected" class="btn-reject" style="flex:1;">❌ ปฏิเสธ</button>
            </form>
        </div>
    </div>
    
    <script>
    function openSlipModal(side, reqId, url) {
        document.getElementById('slip_request_id').value = reqId;
        document.getElementById('slip_side').value = side;
        document.getElementById('slipImgModal').src = url;
        document.getElementById('slipAdminModal').style.display = 'flex';
    }
    </script>
HTML;

$content = str_replace('</body>', $slipModal . "\n</body>", $content);

// 4. Update the condition form button to pass both users if needed.
// Since trade_condition_form.php can just read from the DB without needing query params if we change how it works.
// Actually, let's keep `openConditionModal(reqId)` and just change `trade_condition_form.php`.

file_put_contents($file, $content);
echo "Updated qc_exchanges.php with slip verification modal.\n";
