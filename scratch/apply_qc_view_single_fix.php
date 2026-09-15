<?php
$file = 'c:/laragon/www/wanawan_project/pages/trade_qc_report_view.php';
$content = file_get_contents($file);

$oldLogic = <<<'PHP'
$stmt = $pdo->prepare("SELECT * FROM admin_qc_reports WHERE request_id = ?");
$stmt->execute([$request_id]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($reports)) {
    die("ไม่พบรายงาน QC");
}

$reqStmt = $pdo->prepare("
    SELECT er.*, t.owner_id as target_owner_id 
    FROM exchange_requests er
    JOIN exchanges t ON er.exchange_id = t.exchange_id
    WHERE er.request_id = ?
");
$reqStmt->execute([$request_id]);
$er = $reqStmt->fetch(PDO::FETCH_ASSOC);

$requester_id = $er['requester_id'];
$offer_id = $er['target_owner_id']; // The owner of the target book

$reqReport = null;
$offReport = null;

foreach ($reports as $rp) {
    if ($rp['item_owner_id'] == $offer_id) {
        $reqReport = $rp; // The one submitted by requester (their book is offer_id? No, wait)
    } else {
        $offReport = $rp;
    }
}
PHP;

$newLogic = <<<'PHP'
$viewer_id = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM admin_qc_reports WHERE request_id = ? AND item_owner_id = ?");
$stmt->execute([$request_id, $viewer_id]);
$reportToView = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reportToView) {
    die("ไม่พบรายงาน QC สำหรับคุณ");
}

$reqStmt = $pdo->prepare("
    SELECT er.*, t.owner_id as target_owner_id 
    FROM exchange_requests er
    JOIN exchanges t ON er.exchange_id = t.exchange_id
    WHERE er.request_id = ?
");
$reqStmt->execute([$request_id]);
$er = $reqStmt->fetch(PDO::FETCH_ASSOC);

$requester_id = $er['requester_id'];
$offer_id = $er['target_owner_id'];
PHP;

$content = str_replace($oldLogic, $newLogic, $content);

$oldHtml = <<<'PHP'
        <div class="qc-grid">
            <?php foreach (['req' => $reqReport, 'off' => $offReport] as $type => $report): 
                if (!$report) continue;
                $isMatch = (bool)$report['is_match'];
                $photos = json_decode($report['photo_urls'] ?? '[]', true) ?: [];
                $title = ($type === 'req') ? 'ฝั่งผู้ขอแลก (Requester)' : 'ฝั่งผู้รับแลก (Offer)';
            ?>
            <div class="qc-section">
                <h3><?php echo $title; ?></h3>
                
                <div class="result-badge <?php echo $isMatch ? 'result-pass' : 'result-fail'; ?>">
                    <?php echo $isMatch ? '✅ สภาพตรงตามที่แจ้ง (ผ่าน)' : '❌ สภาพไม่ตรงตามที่แจ้ง (ไม่ผ่าน)'; ?>
                </div>
                
                <div style="font-size:13px; font-weight:600; color:#475569; margin-bottom:4px;">หมายเหตุจากแอดมิน:</div>
                <div class="qc-notes">
                    <?php echo nl2br(htmlspecialchars($report['admin_notes'] ?: '-')); ?>
                </div>
                
                <div style="font-size:13px; font-weight:600; color:#475569; margin-bottom:8px;">ภาพถ่ายสภาพจริง:</div>
                <div class="photo-grid">
                    <?php 
                    $labels = ['ปกหน้า', 'ปกหลัง', 'สันหนังสือ', 'เพิ่มเติม 1', 'เพิ่มเติม 2', 'เพิ่มเติม 3'];
                    foreach ($photos as $i => $url): 
                    ?>
                    <div class="photo-item" onclick="openFullscreen('<?php echo htmlspecialchars($url); ?>')">
                        <img src="<?php echo htmlspecialchars($url); ?>" alt="QC Photo">
                        <div class="photo-label"><?php echo $labels[$i] ?? 'รูปภาพ'; ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($photos)): ?>
                    <div style="grid-column: 1 / -1; text-align:center; padding:15px; background:#f1f5f9; border-radius:6px; font-size:12px; color:#94a3b8;">
                        ไม่มีรูปภาพแนบ
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
PHP;

$newHtml = <<<'PHP'
        <div class="qc-grid" style="grid-template-columns: 1fr;">
            <?php 
                $isMatch = (bool)$reportToView['is_match'];
                $photos = json_decode($reportToView['photo_urls'] ?? '[]', true) ?: [];
                $title = 'สภาพหนังสือที่คุณจะได้รับ';
            ?>
            <div class="qc-section">
                <h3><?php echo $title; ?></h3>
                
                <div class="result-badge <?php echo $isMatch ? 'result-pass' : 'result-fail'; ?>">
                    <?php echo $isMatch ? '✅ สภาพตรงตามที่แจ้ง (ผ่าน)' : '❌ สภาพไม่ตรงตามที่แจ้ง (ไม่ผ่าน)'; ?>
                </div>
                
                <div style="font-size:13px; font-weight:600; color:#475569; margin-bottom:4px;">หมายเหตุจากแอดมิน:</div>
                <div class="qc-notes">
                    <?php echo nl2br(htmlspecialchars($reportToView['admin_notes'] ?: '-')); ?>
                </div>
                
                <div style="font-size:13px; font-weight:600; color:#475569; margin-bottom:8px;">ภาพถ่ายสภาพจริง:</div>
                <div class="photo-grid" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
                    <?php 
                    $labels = ['ปกหน้า', 'ปกหลัง', 'สันหนังสือ', 'เพิ่มเติม 1', 'เพิ่มเติม 2', 'เพิ่มเติม 3'];
                    foreach ($photos as $i => $url): 
                    ?>
                    <div class="photo-item" onclick="openFullscreen('<?php echo htmlspecialchars($url); ?>')">
                        <img src="<?php echo htmlspecialchars($url); ?>" alt="QC Photo">
                        <div class="photo-label"><?php echo $labels[$i] ?? 'รูปภาพ'; ?></div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($photos)): ?>
                    <div style="grid-column: 1 / -1; text-align:center; padding:15px; background:#f1f5f9; border-radius:6px; font-size:12px; color:#94a3b8;">
                        ไม่มีรูปภาพแนบ
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
PHP;

$content = str_replace($oldHtml, $newHtml, $content);
file_put_contents($file, $content);
echo "Patched trade_qc_report_view.php\n";
