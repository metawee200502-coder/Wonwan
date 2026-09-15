<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

// 1. Add table CSS
$css = <<<CSS
        .admin-table-wrap { overflow-x: auto; background: #fff; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 24px; }
        .admin-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        .admin-table th, .admin-table td { padding: 16px 20px; border-bottom: 1px solid var(--border-color); }
        .admin-table th { background: #fdfdfd; color: var(--secondary-color); font-weight: 600; white-space: nowrap; }
        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tbody tr:hover { background: #fdfaf8; }
        .badge { display: inline-block; padding: 4px 8px; font-size: 12px; font-weight: 600; border-radius: 6px; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #e0f2fe; color: #075985; }
        .action-btn { background: #fff; border: 1px solid #d3948b; color: #b66f68; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
        .action-btn:hover { background: #fffaf7; }
        .admin-user-info { display: flex; flex-direction: column; gap: 4px; font-size: 13px; }
        .admin-user-info strong { color: var(--secondary-color); }
CSS;

if (strpos($content, '.admin-table-wrap') === false) {
    $content = str_replace('.empty-state {', $css . "\n        .empty-state {", $content);
}

// 2. Change the Physical tab title to Exchange Orders
$content = str_replace('ตรวจสอบสภาพหนังสือจริง (Physical QC)', 'คำสั่งแลกเปลี่ยน (Exchange Orders)', $content);

// 3. Rewrite the $tab === 'physical' section
$search = "<?php\n\n            // Physical QC handles requests where status = accepted";
$replace = <<<PHP
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
                                <th>ข้อมูลผู้แลกเปลี่ยน</th>
                                <th>รูปแบบการแลก</th>
                                <th>สถานะชำระเงิน</th>
                                <th>รายละเอียด</th>
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
                                    <td><strong style="color:var(--secondary-color)">#EX-<?= \$r['request_id'] ?></strong><br><small style="color:var(--text-muted)"><?= date('d/m/Y H:i', strtotime(\$r['updated_at'])) ?></small></td>
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
                                                    ผู้ขอแลก: <?= \$r['admin_qc_requester_status'] ?> <?= \$r['requester_tracking_to_admin'] ? "({$r['requester_tracking_to_admin']})" : '' ?><br>
                                                    ผู้โพสต์: <?= \$r['admin_qc_offer_status'] ?> <?= \$r['offer_tracking_to_admin'] ? "({$r['offer_tracking_to_admin']})" : '' ?>
                                                    
                                                    <?php if (\$r['admin_qc_requester_status'] === 'pending' && !empty(\$r['requester_tracking_to_admin'])): ?>
                                                        <form method="POST" style="margin-top:4px;">
                                                            <input type="hidden" name="request_id" value="<?= \$r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="requester">
                                                            <input type="hidden" name="action" value="physical_qc">
                                                            <button type="submit" name="qc_result" value="passed" class="badge badge-success" style="border:0;cursor:pointer">ให้ผ่าน</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (\$r['admin_qc_offer_status'] === 'pending' && !empty(\$r['offer_tracking_to_admin'])): ?>
                                                        <form method="POST" style="margin-top:4px;">
                                                            <input type="hidden" name="request_id" value="<?= \$r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="offer">
                                                            <input type="hidden" name="action" value="physical_qc">
                                                            <button type="submit" name="qc_result" value="passed" class="badge badge-success" style="border:0;cursor:pointer">ให้ผ่าน</button>
                                                        </form>
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
PHP;

// Find the section to replace
$startTag = "<?php\n\n            // Physical QC handles requests where status = accepted";
$endTag = "<!-- Admin Modal -->";

$startPos = strpos($content, $startTag);
$endPos = strpos($content, $endTag);

if ($startPos !== false && $endPos !== false) {
    $content = substr_replace($content, $replace . "\n\n        ", $startPos, $endPos - $startPos);
}

// 4. Add the Condition Modal HTML & JS before closing body
$modalHtml = <<<HTML

    <!-- Condition Modal -->
    <div id="conditionAdminModal" class="modal">
        <div class="modal-content" style="background:#fff; border-radius:12px; width:90%; max-width:800px; height:80vh; display:flex; flex-direction:column; overflow:hidden;">
            <div style="padding:15px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; background:#fdf5f7;">
                <h3 style="margin:0; color:#8b5266;">ฟอร์มสภาพหนังสือ</h3>
                <span onclick="document.getElementById('conditionAdminModal').style.display='none'" style="cursor:pointer; font-size:24px; color:#8b5266;">&times;</span>
            </div>
            <iframe id="conditionIframe" src="" style="width:100%; flex:1; border:none;"></iframe>
        </div>
    </div>
    
    <script>
    function openConditionModal(reqId) {
        document.getElementById('conditionIframe').src = '/pages/trade_condition_form.php?request_id=' + reqId + '&readonly=1';
        document.getElementById('conditionAdminModal').style.display = 'flex';
    }
    </script>
HTML;

if (strpos($content, 'conditionAdminModal') === false) {
    $content = str_replace('</body>', $modalHtml . "\n</body>", $content);
}

file_put_contents($file, $content);
echo "Updated admin/qc_exchanges.php successfully.";
