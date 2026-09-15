<?php
$file = __DIR__ . '/../admin/qc_exchanges.php';
$content = file_get_contents($file);

$searchCond = <<<HTML
                                            <?php if (\$r['admin_qc_requester_status'] === 'passed' && \$r['admin_qc_offer_status'] === 'passed' && empty(\$r['admin_tracking_to_requester'])): ?>
HTML;

$replaceCond = <<<HTML
                                            <?php if (\$r['admin_qc_requester_status'] !== 'pending' && \$r['admin_qc_offer_status'] !== 'pending' && empty(\$r['admin_tracking_to_requester'])): ?>
                                                <?php if (\$r['requester_qc_acceptance_status'] === 'rejected' || \$r['offer_qc_acceptance_status'] === 'rejected'): ?>
                                                    <div style="margin-top:8px; padding:8px; background:#fef2f2; border-radius:6px; border:1px solid #fca5a5;">
                                                        <strong style="color:#b91c1c;">ผู้ใช้ปฏิเสธการรับสินค้า</strong><br>
                                                        <span style="font-size:12px; color:#7f1d1d;">(รายการนี้ถูกยกเลิกแล้ว กรุณาส่งสินค้าคืนเจ้าของเดิม)</span>
                                                    </div>
                                                <?php elseif (\$r['requester_qc_acceptance_status'] === 'accepted' && \$r['offer_qc_acceptance_status'] === 'accepted'): ?>
HTML;

$searchEndCond = <<<HTML
                                            <?php endif; ?>
                                        </div>
HTML;

$replaceEndCond = <<<HTML
                                                <?php else: ?>
                                                    <div style="margin-top:8px; padding:8px; background:#f8fafc; border-radius:6px; font-size:12px; color:#64748b; border:1px solid #e2e8f0;">
                                                        ⏳ รอผู้ใช้งานทั้งสองฝ่ายกดยืนยันรับสภาพสินค้าในแชท...
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
HTML;

// We need to be careful with str_replace for the end tag since there are many '<?php endif; ?></div>'
// Let's use string manipulation based on position
$posCond = strpos($content, $searchCond);
if ($posCond !== false) {
    $content = substr_replace($content, $replaceCond, $posCond, strlen($searchCond));
    
    // Find the next occurrence of `<?php endif; ?>\n                                        </div>` after $posCond
    $endSearch = "                                            <?php endif; ?>\n                                        </div>";
    $posEnd = strpos($content, $endSearch, $posCond);
    if ($posEnd !== false) {
        $content = substr_replace($content, $replaceEndCond, $posEnd, strlen($endSearch));
    }
}

file_put_contents($file, $content);
echo "Ship form conditions patched.\n";
