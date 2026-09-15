<?php
$f = 'pages/chat.php';
$c = file_get_contents($f);

$startMarker = "<?php\nif (\$exchangeRequestPanel && \$exchangeRequest['status'] === 'accepted') {";
$endMarker = "<?php endif; ?>\n<div class=\"messages\">";

$startPos = strpos($c, $startMarker);
$endPos = strpos($c, $endMarker);

if ($startPos !== false && $endPos !== false) {
    $endPos += strlen("<?php endif; ?>\n");
    
    $newContent = <<<'HTML'
<?php
if ($exchangeRequestPanel && in_array($exchangeRequest['status'], ['accepted', 'completed'])) {
    echo '<div style="background: #fff7f2; border-bottom: 1px solid #f2dbe4; padding: 12px; text-align: center;">';
    
    if (($exchangeRequest['trade_option'] ?? '') === 'admin_qc') {
        if ($exchangeRequest['status'] === 'completed') {
            $myTracking = ($mySide === 'requester') ? $exchangeRequest['admin_tracking_to_requester'] : $exchangeRequest['admin_tracking_to_offer'];
            echo '<strong style="color:#166534; font-size:14px;">🚚 ส่วนกลางจัดส่งสินค้าแล้ว</strong>';
            echo '<p style="margin:4px 0 8px; font-size:12px; color:#166534;">หมายเลขพัสดุของคุณ: <strong>' . htmlspecialchars($myTracking) . '</strong></p>';
        } else if ($exchangeRequestPanel['conditionsAccepted']) {
            if ($qcReportBanner) {
                $photos = json_decode($qcReportBanner['photo_urls'], true) ?: [];
                ?>
                <strong style="color:#b66f68; font-size:14px;">📦 รายงานตรวจสอบสภาพสินค้าจากส่วนกลาง</strong>
                <p style="margin:4px 0 8px; font-size:12px; color:#8b5266;">กรุณาตรวจสอบรายละเอียดและกดยืนยันเพื่อดำเนินการแลกเปลี่ยนต่อ</p>
                
                <div style="background:#fff; padding:10px; border-radius:8px; border:1px solid #f6e7e0; margin:0 auto 12px; font-size:13px; color:#4a4a4a; max-width:400px; text-align:left;">
                    <div style="margin-bottom:6px;"><strong>ผลการตรวจสอบ:</strong> <span style="color:<?= $qcReportBanner['is_match'] ? '#2e7d32' : '#c62828' ?>; font-weight:bold;"><?= $qcReportBanner['is_match'] ? 'ตรงปก / สภาพดี' : 'ไม่ตรงปก / มีตำหนิเพิ่มเติม' ?></span></div>
                    <div><strong>หมายเหตุจากแอดมิน:</strong> <?= nl2br(htmlspecialchars($qcReportBanner['admin_notes'])) ?></div>
                    <?php if ($photos): ?>
                    <div style="margin-top:8px; border-top:1px dashed #eee; padding-top:8px; text-align:center;">
                        <button type="button" onclick="window.open('<?= htmlspecialchars($photos[0]) ?>', '_blank')" style="background:transparent; border:none; color:#a95575; font-size:13px; cursor:pointer; text-decoration:underline; font-weight:bold;">ดูรูปถ่ายจาก QC (<?= count($photos) ?> รูป)</button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form id="qcDecisionForm" method="POST" action="/pages/trade_qc_logistics_form.php" style="display:flex; gap:10px; justify-content:center; max-width:400px; margin:0 auto;">
                    <input type="hidden" name="action" value="user_qc_decision">
                    <input type="hidden" name="request_id" value="<?= $exchangeRequest['request_id'] ?>">
                    <input type="hidden" name="side" value="<?= $mySide ?? '' ?>">
                    <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
                    <input type="hidden" name="decision" id="qcDecisionValue" value="">
                    <input type="hidden" name="reject_reason" id="qcRejectReasonInput" value="">
                    <button type="button" style="flex:1; background:#2e7d32; color:#fff; border:none; padding:8px; border-radius:6px; cursor:pointer; font-weight:bold; font-size:13px;" onclick="showQcConfirmModal('accepted')">ยืนยันรับสินค้า</button>
                    <button type="button" style="flex:1; background:#ef4444; color:#fff; border:none; padding:8px; border-radius:6px; cursor:pointer; font-weight:bold; font-size:13px;" onclick="showQcConfirmModal('rejected')">ปฏิเสธ / ยกเลิก</button>
                </form>
                <?php
            } else if ($exchangeRequest[$myAcceptanceCol] === 'accepted') {
                echo '<strong style="color:#b66f68; font-size:14px;">⏳ รอส่วนกลางจัดส่งพัสดุ</strong>';
                echo '<p style="margin:4px 0 0; font-size:12px; color:#8b5266;">ผู้ใช้ยืนยันการรับสินค้าแล้ว กรุณารอแอดมินดำเนินการจัดส่งและแจ้งเลขพัสดุ</p>';
            } else {
                echo '<strong style="color:#b66f68; font-size:14px;">📦 ขั้นตอนตรวจสอบ QC</strong>';
                echo '<p style="margin:4px 0 8px; font-size:12px; color:#8b5266;">ชำระค่าบริการและดำเนินการจัดส่งสินค้าให้ส่วนกลาง</p>';
                echo '<button id="btnBannerLogistics" onclick="window.openConditionModal(' . (int)$exchangeRequest['request_id'] . ', \'logistics\')" style="background:#b45309; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-family:inherit;">ดำเนินการจัดส่ง / ชำระเงิน</button>';
            }
        }
    } else if (($exchangeRequest['trade_option'] ?? '') && $exchangeRequest['trade_option'] !== 'meetup' && $exchangeRequest['status'] === 'accepted') {
        echo '<strong style="color:#b66f68; font-size:14px;">📝 ถึงขั้นตอนกรอกสภาพสินค้า</strong>';
        echo '<p style="margin:4px 0 8px; font-size:12px; color:#8b5266;">กรุณากรอกฟอร์มยืนยันสภาพสินค้าเพื่อดำเนินการจัดส่ง</p>';
        echo '<button id="btnBannerCond" onclick="window.openConditionModal(' . (int)$exchangeRequest['request_id'] . ', \'condition\')" style="background:#e67e22; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; font-family:inherit;">กรอกฟอร์มยืนยันสภาพสินค้า</button>';
    } else if (($exchangeRequest['trade_option'] ?? '') === 'meetup') {
        if ($exchangeRequest['status'] === 'completed') {
            echo '<strong style="color:#166534; font-size:14px;">✅ แลกเปลี่ยนสำเร็จ</strong>';
        } else if ($exchangeRequest['status'] === 'accepted') {
            echo '<strong style="color:#166534; font-size:14px;">📅 นัดรับสำเร็จ</strong>';
            echo '<p style="margin:4px 0 0; font-size:12px; color:#166534;">กรุณาไปตามสถานที่นัดหมายและกดทำเครื่องหมายแลกเปลี่ยนสำเร็จที่หน้าจัดการคำขอ</p>';
        }
    } else if ($exchangeRequest['status'] === 'accepted') {
        echo '<strong style="color:#b66f68; font-size:14px;">⚠️ สถานะกำลังเจรจา</strong>';
        echo '<p style="margin:4px 0 8px; font-size:12px; color:#8b5266;">กรุณายื่นข้อเสนอหรือรอให้อีกฝ่ายยื่นข้อเสนอวิธีการแลกเปลี่ยน</p>';
    }
    echo '</div>';
}
?>

<!-- QC Confirm Modal -->
<div id="qcConfirmModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:25px; width:90%; max-width:400px; text-align:center; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        <div id="qcConfirmIcon" style="font-size:48px; margin-bottom:15px;">❓</div>
        <h3 id="qcConfirmTitle" style="margin:0 0 10px 0; color:#334155;">ยืนยันการตัดสินใจ</h3>
        <p id="qcConfirmText" style="color:#64748b; font-size:14px; margin:0 0 15px 0;"></p>
        
        <div id="qcRejectReasonBox" style="display:none; margin-bottom:20px; text-align:left;">
            <label style="font-size:13px; font-weight:bold; color:#333; margin-bottom:5px; display:block;">ระบุเหตุผลการปฏิเสธ:</label>
            <textarea id="qcRejectTextarea" style="width:100%; border:1px solid #ccc; border-radius:8px; padding:8px; font-family:inherit; min-height:60px;" placeholder="เช่น สภาพแย่กว่าที่แจ้งไว้..."></textarea>
        </div>
        
        <div style="display:flex; gap:10px;">
            <button onclick="document.getElementById('qcConfirmModal').style.display='none'" style="flex:1; background:#f1f5f9; color:#475569; border:none; padding:10px; border-radius:8px; font-weight:bold; cursor:pointer;">ยกเลิก</button>
            <button id="qcConfirmBtn" onclick="submitQcDecision()" style="flex:1; background:#b66f68; color:#fff; border:none; padding:10px; border-radius:8px; font-weight:bold; cursor:pointer;">ตกลง</button>
        </div>
    </div>
</div>

<script>
function showQcConfirmModal(decision) {
    document.getElementById('qcDecisionValue').value = decision;
    if (decision === 'accepted') {
        document.getElementById('qcConfirmIcon').innerText = '✅';
        document.getElementById('qcConfirmTitle').innerText = 'ยืนยันรับสินค้า?';
        document.getElementById('qcConfirmText').innerText = 'หากกดยืนยันแล้ว ทางส่วนกลางจะดำเนินการส่งสินค้าให้คุณตามที่อยู่นี้ และไม่สามารถเปลี่ยนใจได้';
        document.getElementById('qcConfirmBtn').style.background = '#22c55e';
        document.getElementById('qcRejectReasonBox').style.display = 'none';
    } else {
        document.getElementById('qcConfirmIcon').innerText = '❌';
        document.getElementById('qcConfirmTitle').innerText = 'ปฏิเสธและยกเลิก?';
        document.getElementById('qcConfirmText').innerText = 'การปฏิเสธจะถือเป็นการยกเลิกรายการแลกเปลี่ยนนี้ทันที และหนังสือจะถูกส่งกลับคืนเจ้าของเดิม';
        document.getElementById('qcConfirmBtn').style.background = '#ef4444';
        document.getElementById('qcRejectReasonBox').style.display = 'block';
        document.getElementById('qcRejectTextarea').value = '';
    }
    document.getElementById('qcConfirmModal').style.display = 'flex';
}

function submitQcDecision() {
    if (document.getElementById('qcDecisionValue').value === 'rejected') {
        document.getElementById('qcRejectReasonInput').value = document.getElementById('qcRejectTextarea').value;
    }
    document.getElementById('qcDecisionForm').submit();
}
</script>

<div class="messages">
HTML;
    
    $c = substr_replace($c, $newContent, $startPos, $endPos - $startPos);
    file_put_contents($f, $c);
    echo "Replaced successfully!";
} else {
    echo "Could not find markers.";
}
