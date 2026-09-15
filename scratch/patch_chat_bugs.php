<?php
$chatPhpPath = __DIR__ . '/../pages/chat.php';
$content = file_get_contents($chatPhpPath);

// Fix $conditionsAccepted logic
$oldConditionsAccepted = "\$conditionsAccepted = ((int)\$q->fetchColumn() === 2);";
$newConditionsAccepted = "\$conditionsAccepted = ((int)\$q->fetchColumn() >= 2);";
$content = str_replace($oldConditionsAccepted, $newConditionsAccepted, $content);

// Fix myCondition and partnerCondition to ORDER BY is_accepted_by_partner DESC, form_id DESC
$oldMyStmt = "\$myConditionStmt = \$pdo->prepare('SELECT required_deposit_amount, deposit_slip_url, deposit_status, deposit_approved_at, tracking_number, is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=?');";
$newMyStmt = "\$myConditionStmt = \$pdo->prepare('SELECT required_deposit_amount, deposit_slip_url, deposit_status, deposit_approved_at, tracking_number, is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=? ORDER BY is_accepted_by_partner DESC, form_id DESC LIMIT 1');";
$content = str_replace($oldMyStmt, $newMyStmt, $content);

$oldPartnerStmt = "\$partnerConditionStmt = \$pdo->prepare('SELECT required_deposit_amount, deposit_slip_url, deposit_status, deposit_approved_at, tracking_number, is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=?');";
$newPartnerStmt = "\$partnerConditionStmt = \$pdo->prepare('SELECT required_deposit_amount, deposit_slip_url, deposit_status, deposit_approved_at, tracking_number, is_received FROM trade_condition_forms WHERE request_id=? AND owner_id=? ORDER BY is_accepted_by_partner DESC, form_id DESC LIMIT 1');";
$content = str_replace($oldPartnerStmt, $newPartnerStmt, $content);

// Add conditionModal HTML and listener before closing body tag
if (strpos($content, 'id="conditionModal"') === false) {
    $modalHtml = <<<HTML
<div id="conditionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:#fff; width:90%; max-width:600px; height:80vh; border-radius:12px; display:flex; flex-direction:column; overflow:hidden; position:relative;">
        <div style="padding:15px; background:#f5f5f5; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:16px; color:#333;">ฟอร์มยืนยันสภาพสินค้า</h3>
            <button onclick="document.getElementById('conditionModal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer; color:#666;">&times;</button>
        </div>
        <iframe id="conditionIframe" src="" style="width:100%; flex:1; border:none;"></iframe>
    </div>
</div>
<script>
window.addEventListener('message', function(e) {
    if (e.data && e.data.action === 'close_condition_modal') {
        document.getElementById('conditionModal').style.display = 'none';
        window.location.reload();
    }
});
</script>
</body>
HTML;
    $content = str_replace('</body>', $modalHtml, $content);
}

// Add a fallback listener so the chat header banner appears properly even if there are JS errors elsewhere
file_put_contents($chatPhpPath, $content);
echo "Patched chat.php";
