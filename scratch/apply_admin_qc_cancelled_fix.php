<?php
$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

$oldLogic = <<<'PHP'
                        <!-- Column 4 -->
                        <div class="qc-col-4">
                            <?php if ($r['admin_qc_requester_status'] === 'pending' || $r['admin_qc_offer_status'] === 'pending'): ?>
PHP;

$newLogic = <<<'PHP'
                        <!-- Column 4 -->
                        <div class="qc-col-4">
                            <?php if ($r['status'] === 'cancelled' || $r['status'] === 'rejected'): ?>
                                <div>
                                    <div class="qc-success-row" style="color:#DC2626;">
                                        <span><i class="fas fa-times-circle"></i> การแลกเปลี่ยนถูกยกเลิก</span>
                                        <?php if ($r['admin_qc_requester_status'] !== 'pending'): ?>
                                        <a href="#" onclick="openDynamicCondModal('qc_report_<?= $r['request_id'] ?>'); return false;" style="color:var(--primary); font-size:11px; text-decoration:underline;">ดูรายงาน</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="qc-success-desc" style="color:#DC2626;"><i class="fas fa-ban"></i> ลูกค้าปฏิเสธผล QC หรือยกเลิกคำขอไปแล้ว</div>
                                </div>
                                <div class="qc-step-box" style="border-color:#FECACA; background:#FFF5F5;">
                                    <div class="title" style="color:#DC2626;"><span class="qc-dot red" style="display:inline-block;"></span> กรุณาจัดส่งหนังสือคืนเจ้าของเดิม</div>
                                    <div class="desc" style="color:#EF4444;"><i class="fas fa-undo-alt"></i> ติดต่อลูกค้าเพื่อส่งมอบหนังสือคืน (ค่าจัดส่งลูกค้าต้องชำระตามตกลง)</div>
                                </div>
                            <?php elseif ($r['admin_qc_requester_status'] === 'pending' || $r['admin_qc_offer_status'] === 'pending'): ?>
PHP;

$content = str_replace($oldLogic, $newLogic, $content);
file_put_contents($file, $content);
echo "Successfully patched qc_exchanges.php for cancelled status in Col 4.\n";
