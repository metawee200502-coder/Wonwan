<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

$lines = explode("\n", $content);
$newLines = [];
$inPhysical = false;

for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], 'if ($tab === \'physical\'):') !== false) {
        $inPhysical = true;
        
        $newLines[] = "        <?php if (\$tab === 'physical'): ?>";
        
        // Inject new table
        $newLines[] = <<<PHP
            <?php
            // Query all exchange requests that are accepted or completed
            \$reqs = \$pdo->query("
                SELECT r.*, 
                       e_req.title as req_title, e_req.image_cover as req_img, 
                       e_off.title as off_title, e_off.image_cover as off_img,
                       u_req.first_name as req_fname, u_req.last_name as req_lname, u_req.email as req_email,
                       u_off.first_name as off_fname, u_off.last_name as off_lname, u_off.email as off_email
                FROM exchange_requests r
                JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
                JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
                LEFT JOIN users u_req ON r.requester_id = u_req.user_id
                LEFT JOIN users u_off ON e_req.owner_id = u_off.user_id
                WHERE r.status IN ('accepted', 'completed')
                ORDER BY r.updated_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Pre-fetch condition forms
            \$formsRaw = \$pdo->query("SELECT * FROM trade_condition_forms")->fetchAll(PDO::FETCH_ASSOC);
            \$formsByReq = [];
            foreach (\$formsRaw as \$f) {
                \$formsByReq[\$f['request_id']][\$f['owner_id']] = \$f;
            }
            ?>
            <?php if (empty(\$reqs)): ?>
                <div class="empty-state">ไม่มีคำสั่งแลกเปลี่ยนในระบบ</div>
            <?php else: ?>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>รหัสคำขอ</th>
                                <th>ข้อมูลคู่แลกเปลี่ยน</th>
                                <th>รูปแบบการแลก</th>
                                <th>สถานะชำระเงิน/ค่าส่ง</th>
                                <th>รายละเอียดสภาพหนังสือ</th>
                                <th>สถานะ / แอคชัน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (\$reqs as \$r): ?>
                                <?php
                                    \$optionMap = ['admin_qc' => 'ส่งผ่าน QC', 'direct_escrow' => 'ส่งตรง (Direct)', 'meetup' => 'นัดรับ'];
                                    \$optionStr = \$optionMap[\$r['trade_option'] ?? ''] ?? (\$r['trade_option'] ?: 'ยังไม่ระบุ');
                                    
                                    // Check payments
                                    \$reqPaid = !empty(\$r['requester_slip_url']);
                                    \$offPaid = !empty(\$r['offer_slip_url']);
                                ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--secondary-color)">#EX-<?= \$r['request_id'] ?></strong><br>
                                        <small style="color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime(\$r['updated_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="admin-user-info">
                                            <div><strong>ผู้ขอแลก:</strong> <?= htmlspecialchars(\$r['req_fname'].' '.\$r['req_lname']) ?></div>
                                            <div><strong>ผู้โพสต์:</strong> <?= htmlspecialchars(\$r['off_fname'].' '.\$r['off_lname']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?= \$optionStr ?></span>
                                    </td>
                                    <td>
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
                                            <span style="color:var(--text-muted); font-size:12px;">- ไม่ต้องชำระ -</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                            \$reqForm = \$formsByReq[\$r['request_id']][\$r['requester_id']] ?? null;
                                            \$offForm = \$formsByReq[\$r['request_id']][\$r['owner_id']] ?? null;
                                        ?>
                                        <?php if (\$reqForm || \$offForm): ?>
                                            <button class="action-btn" onclick="openConditionModal(<?= \$r['request_id'] ?>)">ดูฟอร์มสภาพหนังสือ</button>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:12px;">ยังไม่กรอกฟอร์ม</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (\$r['status'] === 'completed'): ?>
                                            <span class="badge badge-success">เสร็จสิ้น</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">กำลังดำเนินการ</span>
                                            
                                            <?php if (\$r['trade_option'] === 'admin_qc'): ?>
                                                <div style="margin-top:8px; font-size:12px; border-top:1px dashed #ccc; padding-top:8px;">
                                                    <strong>ส่วนกลาง QC:</strong><br>
                                                    ผู้ขอแลก: <span class="badge bg-<?= \$r['admin_qc_requester_status'] ?>"><?= \$r['admin_qc_requester_status'] ?></span> <?= \$r['requester_tracking_to_admin'] ? "({$r['requester_tracking_to_admin']})" : '' ?><br>
                                                    ผู้โพสต์: <span class="badge bg-<?= \$r['admin_qc_offer_status'] ?>"><?= \$r['admin_qc_offer_status'] ?></span> <?= \$r['offer_tracking_to_admin'] ? "({$r['offer_tracking_to_admin']})" : '' ?>
                                                    
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
                                                    
                                                    <?php if (\$r['admin_qc_requester_status'] === 'passed' && \$r['admin_qc_offer_status'] === 'passed' && empty(\$r['admin_tracking_to_requester'])): ?>
                                                        <div style="margin-top:8px; padding:8px; background:#e6f4ea; border-radius:6px;">
                                                            <strong style="color:#155724;">ผ่าน QC ทั้งคู่</strong>
                                                            <form method="POST" style="margin-top:4px;">
                                                                <input type="hidden" name="action" value="admin_ship">
                                                                <input type="hidden" name="request_id" value="<?= \$r['request_id'] ?>">
                                                                <input type="text" name="admin_tracking_to_requester" required placeholder="เลขพัสดุ (ส่งคืนผู้ตั้งโพสต์)" style="width:100%; margin-bottom:4px; padding:4px;">
                                                                <input type="text" name="admin_tracking_to_offer" required placeholder="เลขพัสดุ (ส่งคืนผู้ขอแลก)" style="width:100%; margin-bottom:4px; padding:4px;">
                                                                <button type="submit" class="action-btn" style="width:100%; font-size:11px;">บันทึกจัดส่ง & จบงาน</button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
PHP;
    }
    
    if ($inPhysical) {
        if (strpos($lines[$i], '</main>') !== false) {
            $inPhysical = false;
            $newLines[] = $lines[$i];
        }
    } else {
        $newLines[] = $lines[$i];
    }
}

file_put_contents($file, implode("\n", $newLines));
echo "Physical tab replaced successfully.";
