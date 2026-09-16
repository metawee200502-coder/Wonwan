<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// 1. Update query to get required_deposit_amount
$search_select = "c_off.deposit_status as off_deposit_status, c_off.deposit_slip_url as off_deposit_slip_url,
           c_req.deposit_status as req_deposit_status, c_req.deposit_slip_url as req_deposit_slip_url";
$replace_select = $search_select . ",
           c_off.required_deposit_amount as off_deposit_amount, c_req.required_deposit_amount as req_deposit_amount";
$content = str_replace($search_select, $replace_select, $content);

// 2. Update getSlipBadgeHtml signature
$search_func = "function getSlipBadgeHtml(\$status, \$url) {
    \$btn = '';
    if (!empty(\$url)) {
        \$btn = ' <button type=\"button\" class=\"qc-slip-view-btn\" onclick=\"openModal(\''.htmlspecialchars(str_replace('\\\\', '/', \$url)).'\')\">ดูสลิป</button>';
    }";
$replace_func = "function getSlipBadgeHtml(\$status, \$url, \$isP2P = false, \$amount = 0, \$reqId = 0, \$side = '') {
    \$btn = '';
    if (!empty(\$url)) {
        \$meta = htmlspecialchars(json_encode([
            'isP2P' => \$isP2P,
            'status' => \$status,
            'amount' => \$amount,
            'request_id' => \$reqId,
            'side' => \$side
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        \$btn = ' <button type=\"button\" class=\"qc-slip-view-btn\" onclick=\"openModal(\''.htmlspecialchars(str_replace('\\\\', '/', \$url)).'\', '.\$meta.')\">ดูสลิป</button>';
    }";
$content = str_replace($search_func, $replace_func, $content);

// 3. Update getSlipBadgeHtml calls
$search_call_req = "<?= getSlipBadgeHtml(\$req_slip_status, \$req_slip_url) ?>";
$replace_call_req = "<?= getSlipBadgeHtml(\$req_slip_status, \$req_slip_url, \$isP2P, \$isP2P ? (\$r['req_deposit_amount'] ?: 0) : 0, \$r['request_id'], 'requester') ?>";
$content = str_replace($search_call_req, $replace_call_req, $content);

$search_call_off = "<?= getSlipBadgeHtml(\$off_slip_status, \$off_slip_url) ?>";
$replace_call_off = "<?= getSlipBadgeHtml(\$off_slip_status, \$off_slip_url, \$isP2P, \$isP2P ? (\$r['off_deposit_amount'] ?: 0) : 0, \$r['request_id'], 'offer') ?>";
$content = str_replace($search_call_off, $replace_call_off, $content);

// 4. Update the P2P Escrow note in the HTML
$search_note = "<?php if (\$req_slip_status === 'reviewing' && \$isP2P): ?>
                                    <div style=\"margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light); text-align:right;\">
                                        <a href=\"escrow.php?tab=p2p\" style=\"font-size:11px; color:#91576d; text-decoration:underline;\">ไปที่เมนู P2P Escrow เพื่ออนุมัติ</a>
                                    </div>";
$replace_note = "";
$content = str_replace($search_note, $replace_note, $content);

$search_note_off = "<?php if (\$off_slip_status === 'reviewing' && \$isP2P): ?>
                                    <div style=\"margin-top:10px; padding-top:10px; border-top:1px dashed var(--border-light); text-align:right;\">
                                        <a href=\"escrow.php?tab=p2p\" style=\"font-size:11px; color:#91576d; text-decoration:underline;\">ไปที่เมนู P2P Escrow เพื่ออนุมัติ</a>
                                    </div>";
$replace_note_off = "";
$content = str_replace($search_note_off, $replace_note_off, $content);

file_put_contents($file, $content);
echo "Patched qc_exchanges part 1\n";
