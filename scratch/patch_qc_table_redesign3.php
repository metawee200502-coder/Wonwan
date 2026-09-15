<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

$new_physical_table = <<< 'HTML'
            <?php if (empty($reqs)): ?>
                <div class="empty-state">ไม่มีพัสดุรอตรวจสภาพจริง</div>
            <?php else: ?>
                <div class="tablebox">
                    <table class="orders" style="min-width: 1000px;">
                        <thead>
                            <tr>
                                <th style="width: 120px;">รหัสคำขอ</th>
                                <th>1. ข้อมูล & พัสดุขาเข้า (จากลูกค้า)</th>
                                <th style="width: 200px;">2. สภาพสินค้าดั้งเดิม</th>
                                <th style="width: 220px;">3. ผลตรวจสอบ (ส่วนกลาง)</th>
                                <th style="width: 250px;">4. จัดส่งขาออก (ถึงลูกค้า)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reqs as $r): ?>
                                <tr>
                                    <!-- Column 1: ID & Method -->
                                    <td>
                                        <div style="font-weight: 600; color: #00796B; font-size:15px;">#EX-<?= $r['request_id'] ?></div>
                                        <div style="margin-top:6px;">
                                            <span class="action-badge badge-blue" style="font-size:11px; padding:4px 8px;"><?= formatTradeOption($r['trade_option']) ?></span>
                                        </div>
                                    </td>
                                    
                                    <!-- Column 2: Inbound Logistics & Slips -->
                                    <td>
                                        <!-- ผู้ขอแลก -->
                                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; margin-bottom:10px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                                <div class="user-profile" style="margin:0;">
                                                    <img src="<?= chatImage($r['requester_image']) ?>" class="user-avatar" style="width:24px;height:24px;" alt="Avatar">
                                                    <div class="user-info-text">
                                                        <span class="user-name" style="font-size:12px;">ผู้ขอแลก: <?= htmlspecialchars($r['requester_name'] ?? 'ไม่มีชื่อ') ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div style="font-size:11px; color:#475569; display:flex; flex-direction:column; gap:5px;">
                                                <div><strong>ที่อยู่:</strong> <?= htmlspecialchars($r['requester_address'] ?? 'ยังไม่ระบุ') ?></div>
                                                <div><strong>เลขพัสดุขาเข้า:</strong> <span class="tracking-number"><?= htmlspecialchars($r['requester_tracking_to_admin'] ?? '-') ?></span></div>
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <strong>สลิป:</strong> <?= getSlipStatusBadge($r['requester_slip_status']) ?>
                                                    <?php if ($r['requester_slip_status'] === 'pending' && !empty($r['requester_slip_url'])): ?>
                                                        <form method="POST" style="display:inline-flex; gap:2px;">
                                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="requester">
                                                            <input type="hidden" name="action" value="approve_slip">
                                                            <a href="<?= htmlspecialchars($r['requester_slip_url']) ?>" target="_blank" style="color:#0284c7; text-decoration:none; margin-right:4px;">[ดูรูป]</a>
                                                            <button type="submit" name="slip_result" value="approved" class="btn-approve" style="padding:2px 6px; font-size:10px;">อนุมัติ</button>
                                                            <button type="submit" name="slip_result" value="rejected" class="btn-reject" style="padding:2px 6px; font-size:10px;">ไม่อนุมัติ</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ผู้รับแลก -->
                                        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px;">
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                                <div class="user-profile" style="margin:0;">
                                                    <img src="<?= chatImage($r['target_owner_image']) ?>" class="user-avatar" style="width:24px;height:24px;" alt="Avatar">
                                                    <div class="user-info-text">
                                                        <span class="user-name" style="font-size:12px;">ผู้รับแลก: <?= htmlspecialchars($r['target_owner_name'] ?? 'ไม่มีชื่อ') ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div style="font-size:11px; color:#475569; display:flex; flex-direction:column; gap:5px;">
                                                <div><strong>ที่อยู่:</strong> <?= htmlspecialchars($r['offer_address'] ?? 'ยังไม่ระบุ') ?></div>
                                                <div><strong>เลขพัสดุขาเข้า:</strong> <span class="tracking-number"><?= htmlspecialchars($r['offer_tracking_to_admin'] ?? '-') ?></span></div>
                                                <div style="display:flex; align-items:center; gap:6px;">
                                                    <strong>สลิป:</strong> <?= getSlipStatusBadge($r['offer_slip_status']) ?>
                                                    <?php if ($r['offer_slip_status'] === 'pending' && !empty($r['offer_slip_url'])): ?>
                                                        <form method="POST" style="display:inline-flex; gap:2px;">
                                                            <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                            <input type="hidden" name="side" value="offer">
                                                            <input type="hidden" name="action" value="approve_slip">
                                                            <a href="<?= htmlspecialchars($r['offer_slip_url']) ?>" target="_blank" style="color:#0284c7; text-decoration:none; margin-right:4px;">[ดูรูป]</a>
                                                            <button type="submit" name="slip_result" value="approved" class="btn-approve" style="padding:2px 6px; font-size:10px;">อนุมัติ</button>
                                                            <button type="submit" name="slip_result" value="rejected" class="btn-reject" style="padding:2px 6px; font-size:10px;">ไม่อนุมัติ</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Column 3: Original Condition -->
                                    <td>
                                        <div style="margin-bottom: 15px;">
                                            <div style="font-size:11px; font-weight:bold; color:#334155;">หนังสือของผู้ขอแลก</div>
                                            <div class="book-title" style="font-size:11px; margin-bottom:4px;">📖 <?= htmlspecialchars($r['off_title'] ?? '') ?></div>
                                            <?php if (!empty($r['req_cond_front'])): ?>
                                                <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openCondModal('<?= htmlspecialchars($r['req_cond_front']) ?>', '<?= htmlspecialchars($r['req_cond_back']) ?>', '<?= htmlspecialchars($r['req_cond_spine']) ?>')">🔎 ตรวจสอบสภาพตามรูป</button>
                                            <?php else: ?>
                                                <span style="font-size:11px; color:#94a3b8;">ไม่มีรูปสภาพ</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div style="border-top:1px dashed #e2e8f0; padding-top:10px;">
                                            <div style="font-size:11px; font-weight:bold; color:#7f1d1d;">หนังสือของผู้รับแลก</div>
                                            <div class="book-title" style="font-size:11px; margin-bottom:4px;">📖 <?= htmlspecialchars($r['req_title'] ?? '') ?></div>
                                            <?php if (!empty($r['off_cond_front'])): ?>
                                                <button type="button" class="btn-approve" style="background:#f0f9ff; color:#0284c7; border:1px solid #bae6fd; font-size:11px; padding:4px 8px;" onclick="openCondModal('<?= htmlspecialchars($r['off_cond_front']) ?>', '<?= htmlspecialchars($r['off_cond_back']) ?>', '<?= htmlspecialchars($r['off_cond_spine']) ?>')">🔎 ตรวจสอบสภาพตามรูป</button>
                                            <?php else: ?>
                                                <span style="font-size:11px; color:#94a3b8;">ไม่มีรูปสภาพ</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- Column 4: Admin QC Result -->
                                    <td style="text-align:center;">
                                        <?php if ($r['admin_qc_requester_status'] === 'pending' || $r['admin_qc_offer_status'] === 'pending'): ?>
                                            <button type="button" class="btn-approve" style="padding:10px 12px; font-size:12px; width:100%; background:#00796B; margin-bottom: 8px;" onclick="openAdminQcForm(<?= $r['request_id'] ?>, <?= (int)$r['requester_id'] ?>, <?= (int)$r['target_owner_id'] ?>)"><i class="fas fa-camera"></i> ส่งฟอร์มรายงาน QC</button>
                                            <div style="font-size:11px; color:#64748b; line-height: 1.4;">* กรอกฟอร์มเพื่อส่งรายงานภาพสภาพจริงปัจจุบันให้ลูกค้าตรวจสอบและตัดสินใจ</div>
                                        <?php else: ?>
                                            <!-- Already QC'd -->
                                            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:8px; margin-bottom:8px; text-align:left;">
                                                <div style="font-size:11px; font-weight:bold; color:#334155; margin-bottom:4px;">ผลตรวจฝั่งผู้ขอแลก: <span class="qc-status-badge bg-<?= htmlspecialchars($r['admin_qc_requester_status'] ?? '') ?>"><?= strtoupper($r['admin_qc_requester_status'] ?? '') ?></span></div>
                                                <div style="font-size:10px; margin-top:4px;">การตัดสินใจของลูกค้า: <br> 
                                                    <span style="color: <?= $r['requester_qc_acceptance_status']==='accepted' ? '#155724' : ($r['requester_qc_acceptance_status']==='rejected' ? '#721c24' : '#856404') ?>; font-weight:bold;">
                                                        <?= strtoupper($r['requester_qc_acceptance_status']) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:8px; text-align:left;">
                                                <div style="font-size:11px; font-weight:bold; color:#7f1d1d; margin-bottom:4px;">ผลตรวจฝั่งผู้รับแลก: <span class="qc-status-badge bg-<?= htmlspecialchars($r['admin_qc_offer_status'] ?? '') ?>"><?= strtoupper($r['admin_qc_offer_status'] ?? '') ?></span></div>
                                                <div style="font-size:10px; margin-top:4px;">การตัดสินใจของลูกค้า: <br>
                                                    <span style="color: <?= $r['offer_qc_acceptance_status']==='accepted' ? '#155724' : ($r['offer_qc_acceptance_status']==='rejected' ? '#721c24' : '#856404') ?>; font-weight:bold;">
                                                        <?= strtoupper($r['offer_qc_acceptance_status']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Column 5: Outbound Logistics -->
                                    <td>
                                        <?php if ($r['admin_qc_requester_status'] === 'pending' || $r['admin_qc_offer_status'] === 'pending'): ?>
                                            <div style="font-size:11px; color:#94a3b8; text-align:center; padding-top:20px;">
                                                รอทำบันทึกผล QC<br>และรอลูกค้ายอมรับสภาพ
                                            </div>
                                        <?php elseif ($r['requester_qc_acceptance_status'] === 'accepted' && $r['offer_qc_acceptance_status'] === 'accepted'): ?>
                                            <!-- Both accepted, show shipping form -->
                                            <?php if (empty($r['admin_tracking_to_requester'])): ?>
                                                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px;">
                                                    <div style="font-size:12px; font-weight:bold; color:#166534; margin-bottom:10px; display:flex; align-items:center; gap:6px;"><i class="fas fa-check-circle"></i> ลูกค้ายอมรับแล้ว ส่งของได้</div>
                                                    <form method="POST" style="display:flex; flex-direction:column; gap:8px;">
                                                        <input type="hidden" name="action" value="admin_ship">
                                                        <input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
                                                        
                                                        <div>
                                                            <label style="font-size:11px; color:#374151; font-weight: 600;">ผู้ขอแลก (ผู้รับ: <?= htmlspecialchars($r['requester_name'] ?? '') ?>)</label>
                                                            <input type="text" name="admin_tracking_to_requester" placeholder="ระบุขนส่งและเลขพัสดุ (เช่น Flash: TH1234)" required style="width:100%; padding:6px; font-size:11px; border:1px solid #cbd5e1; border-radius:4px; box-sizing:border-box; margin-top: 4px;">
                                                        </div>
                                                        <div>
                                                            <label style="font-size:11px; color:#374151; font-weight: 600;">ผู้รับแลก (ผู้รับ: <?= htmlspecialchars($r['target_owner_name'] ?? '') ?>)</label>
                                                            <input type="text" name="admin_tracking_to_offer" placeholder="ระบุขนส่งและเลขพัสดุ (เช่น Flash: TH1234)" required style="width:100%; padding:6px; font-size:11px; border:1px solid #cbd5e1; border-radius:4px; box-sizing:border-box; margin-top: 4px;">
                                                        </div>
                                                        <button type="submit" class="btn-approve" style="padding:8px; margin-top:4px; width:100%;">บันทึกจัดส่ง & ปิดงาน</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <div style="text-align:center;">
                                                    <span class="action-badge badge-green">จัดส่งและปิดงานเรียบร้อย</span>
                                                    <div style="font-size:11px; margin-top:10px; text-align:left; background:#f8fafc; padding:8px; border-radius:6px; border:1px solid #e2e8f0;">
                                                        <strong>ส่งให้ผู้ขอแลก:</strong><br><span style="color:#00796B; font-weight: 600;"><?= htmlspecialchars($r['admin_tracking_to_requester'] ?? '') ?></span><br><br>
                                                        <strong>ส่งให้ผู้รับแลก:</strong><br><span style="color:#00796B; font-weight: 600;"><?= htmlspecialchars($r['admin_tracking_to_offer'] ?? '') ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div style="font-size:11px; color:#d97706; text-align:center; padding:15px; background:#fffbeb; border-radius:8px; border:1px solid #fef3c7;">
                                                <i class="fas fa-hourglass-half" style="font-size: 16px; margin-bottom: 6px;"></i><br>รอลูกค้าพิจารณาตัดสินใจ<br>ยอมรับสภาพสินค้า
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php echo "<?php endif; ?>"; ?>
HTML;

$startMarker = "<?php if (empty(\$reqs)): ?>";
$endStr = "            <?php echo \"<?php endif; ?>\"; ?>\n        <?php echo \"<?php endif; ?>\"; ?>\n    </div>";
// Let's use a smarter replacement strategy to avoid ?> closing tag issues inside php files!

$pieces = explode("            <?php if (empty(\$reqs)): ?>", $content);
if (count($pieces) >= 3) { // It appears at least twice (posts tab, physical tab)
    // We want to replace everything in the second instance (index 2)
    // Find the end of the physical tab which is before "<!-- Modal -->"
    $modalPos = strpos($pieces[2], "<!-- Modal -->");
    if ($modalPos !== false) {
        $beforeModal = substr($pieces[2], 0, $modalPos);
        // $beforeModal ends with "    </div>\n\n    "
        $closingTags = "            <?php endif; ?>\n        <?php endif; ?>\n    </div>\n\n    ";
        
        $new_chunk = $new_physical_table . "\n        <?php endif; ?>\n    </div>\n\n    ";
        
        // Reassemble
        $content = $pieces[0] . "            <?php if (empty(\$reqs)): ?>" . $pieces[1] . $new_chunk . substr($pieces[2], $modalPos);
        
        // Remove the extra echo we added for safety in the template string
        $content = str_replace("<?php echo \"<?php endif; ?>\"; ?>", "<?php endif; ?>", $content);
        
        file_put_contents($file, $content);
        echo "Successfully patched!";
    } else {
        echo "Modal not found!";
    }
} else {
    echo "Marker not found twice!";
}
