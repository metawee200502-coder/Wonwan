<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// 1. Update getSlipBadgeHtml signature
$search_func = "function getSlipBadgeHtml(\$status, \$url, \$isP2P = false, \$amount = 0, \$reqId = 0, \$side = '') {
    \$btn = '';
    if (!empty(\$url)) {
        \$meta = htmlspecialchars(json_encode([
            'isP2P' => \$isP2P,
            'status' => \$status,
            'amount' => \$amount,
            'request_id' => \$reqId,
            'side' => \$side
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);";
$replace_func = "function getSlipBadgeHtml(\$status, \$url, \$isP2P = false, \$amount = 0, \$reqId = 0, \$side = '', \$payerName = '', \$createdAt = '') {
    \$btn = '';
    if (!empty(\$url)) {
        \$meta = htmlspecialchars(json_encode([
            'isP2P' => \$isP2P,
            'status' => \$status,
            'amount' => \$amount,
            'request_id' => \$reqId,
            'side' => \$side,
            'payerName' => \$payerName,
            'createdAt' => \$createdAt
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);";
$content = str_replace($search_func, $replace_func, $content);

// 2. Update getSlipBadgeHtml calls
$search_call_req = "<?= getSlipBadgeHtml(\$req_slip_status, \$req_slip_url, \$isP2P, \$isP2P ? (\$r['req_deposit_amount'] ?: 0) : 0, \$r['request_id'], 'requester') ?>";
$replace_call_req = "<?= getSlipBadgeHtml(\$req_slip_status, \$req_slip_url, \$isP2P, \$isP2P ? (\$r['req_deposit_amount'] ?: 0) : 0, \$r['request_id'], 'requester', \$r['requester_name'] ?? 'ไม่มีชื่อ', \$r['created_at']) ?>";
$content = str_replace($search_call_req, $replace_call_req, $content);

$search_call_off = "<?= getSlipBadgeHtml(\$off_slip_status, \$off_slip_url, \$isP2P, \$isP2P ? (\$r['off_deposit_amount'] ?: 0) : 0, \$r['request_id'], 'offer') ?>";
$replace_call_off = "<?= getSlipBadgeHtml(\$off_slip_status, \$off_slip_url, \$isP2P, \$isP2P ? (\$r['off_deposit_amount'] ?: 0) : 0, \$r['request_id'], 'offer', \$r['target_owner_name'] ?? 'ไม่มีชื่อ', \$r['created_at']) ?>";
$content = str_replace($search_call_off, $replace_call_off, $content);

// 3. Update imgModal HTML
$search_modal = "<div id=\"imgModal\" class=\"modal\" onclick=\"if(event.target===this) this.style.display='none'\">
        <div class=\"modal-content\" style=\"background:#fff; padding:20px; border-radius:12px; max-width:600px; max-height:95vh; overflow-y:auto; margin:5% auto; position:relative;\">
            <span onclick=\"document.getElementById('imgModal').style.display='none'\" style=\"position:absolute; top:15px; right:20px; cursor:pointer; font-size:24px; color:#999; z-index:10;\">&times;</span>
            <img id=\"imgModalContent\" style=\"width:100%; height:auto; display:block; border-radius:8px;\">
            <div id=\"imgModalActions\" style=\"display:none; margin-top:20px; padding-top:15px; border-top:1px solid #eee;\">
                <h4 style=\"margin:0 0 10px 0; color:#1f2937;\">ตรวจสอบสลิปเงินมัดจำ (P2P Escrow)</h4>
                <div style=\"background:#fef3c7; color:#92400e; padding:10px; border-radius:6px; margin-bottom:15px; font-size:14px;\">
                    ยอดที่ต้องโอน: <strong style=\"font-size:16px;\">฿<span id=\"imgModalAmount\">0.00</span></strong>
                </div>
                <form method=\"POST\" style=\"display:flex; gap:10px; justify-content:center;\">
                    <input type=\"hidden\" name=\"action\" value=\"approve_p2p_slip\">
                    <input type=\"hidden\" name=\"request_id\" id=\"imgModalReqId\">
                    <input type=\"hidden\" name=\"side\" id=\"imgModalSide\">
                    <button type=\"submit\" name=\"slip_result\" value=\"approved\" style=\"background:#10B981; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; flex:1; font-weight:600;\">✅ อนุมัติสลิป</button>
                    <button type=\"submit\" name=\"slip_result\" value=\"rejected\" style=\"background:#EF4444; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; flex:1; font-weight:600;\">❌ ไม่อนุมัติ</button>
                </form>
            </div>
        </div>
    </div>";

$replace_modal = "<div id=\"imgModal\" class=\"modal\" onclick=\"if(event.target===this) this.style.display='none'\" style=\"background:rgba(0,0,0,0.6);\">
        <div class=\"modal-content\" style=\"background:#fff; border-radius:16px; max-width:800px; max-height:95vh; overflow-y:auto; margin:5% auto; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.2);\">
            
            <!-- Header -->
            <div style=\"background:#fdf2f4; padding:20px 30px; border-bottom:1px solid #f3e6e9; display:flex; justify-content:space-between; align-items:center; border-radius:16px 16px 0 0;\">
                <h3 style=\"margin:0; color:#1f2937; font-size:20px;\">มัดจำคำขอ #<span id=\"imgModalReqIdTxt\">-</span></h3>
                <span id=\"imgModalBadge\" style=\"background:#fef3c7; color:#92400e; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600;\">รอตรวจสอบสลิป</span>
            </div>

            <!-- Body -->
            <div style=\"padding:30px; display:flex; flex-wrap:wrap; gap:30px;\">
                <!-- Left: Image -->
                <div style=\"flex:1; min-width:300px;\">
                    <h4 style=\"margin:0 0 15px 0; color:#147A6B;\">หลักฐานการชำระเงิน</h4>
                    <img id=\"imgModalContent\" style=\"width:100%; height:auto; display:block; border-radius:12px; border:1px solid #eee; box-shadow:0 2px 8px rgba(0,0,0,0.05);\">
                </div>
                
                <!-- Right: Details -->
                <div style=\"flex:1; min-width:300px; display:flex; flex-direction:column; justify-content:space-between;\">
                    <div>
                        <div style=\"color:#64748b; font-size:13px; margin-bottom:5px;\">สั่งซื้อเมื่อ: <span id=\"imgModalDate\">-</span></div>
                        <div style=\"color:#64748b; font-size:13px; margin-bottom:20px;\">อนุมัติเมื่อ: -</div>
                        
                        <h4 style=\"margin:0 0 15px 0; color:#147A6B;\">รายการสินค้า</h4>
                        
                        <!-- Box 1 -->
                        <div style=\"border:1px solid #eaeaea; border-radius:8px; padding:15px; margin-bottom:15px;\">
                            <div style=\"color:#475569; font-size:14px; margin-bottom:5px;\">ยอดมัดจำที่ต้องชำระ:</div>
                            <div style=\"color:#dc2626; font-size:24px; font-weight:bold;\">฿<span id=\"imgModalAmount\">0.00</span></div>
                        </div>

                        <!-- Box 2 -->
                        <div style=\"border:1px solid #eaeaea; border-radius:8px; padding:15px; margin-bottom:20px;\">
                            <div style=\"color:#475569; font-size:14px; margin-bottom:5px;\">ผู้ชำระเงิน:</div>
                            <div style=\"color:#1f2937; font-size:15px;\"><span id=\"imgModalPayer\">-</span></div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div id=\"imgModalActions\" style=\"display:none;\">
                        <form method=\"POST\" style=\"display:flex; gap:10px;\">
                            <input type=\"hidden\" name=\"action\" value=\"approve_p2p_slip\">
                            <input type=\"hidden\" name=\"request_id\" id=\"imgModalReqIdInput\">
                            <input type=\"hidden\" name=\"side\" id=\"imgModalSide\">
                            <button type=\"submit\" name=\"slip_result\" value=\"rejected\" style=\"background:#fff; color:#ef4444; border:1px solid #ef4444; padding:12px; border-radius:6px; cursor:pointer; flex:1; font-weight:600;\">ไม่อนุมัติ (สลิปไม่ถูกต้อง)</button>
                            <button type=\"submit\" name=\"slip_result\" value=\"approved\" style=\"background:#15803d; color:white; border:none; padding:12px; border-radius:6px; cursor:pointer; flex:1.2; font-weight:600;\">✓ อนุมัติสลิปโอนเงินมัดจำ (P2P)</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div style=\"padding:20px 30px; border-top:1px solid #eee; text-align:center; background:#fafafa; border-radius:0 0 16px 16px;\">
                <button type=\"button\" onclick=\"document.getElementById('imgModal').style.display='none'\" style=\"background:#fff; border:1px solid #ddd; color:#91576d; padding:12px 30px; border-radius:6px; cursor:pointer; font-weight:600; width:100%; max-width:300px;\">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>";
$content = str_replace($search_modal, $replace_modal, $content);

// 4. Update JS for new modal structure
$search_js = "        function openModal(src, meta = {}) {
            document.getElementById('imgModalContent').src = src;
            
            const actionsDiv = document.getElementById('imgModalActions');
            if (meta && meta.isP2P && meta.status === 'reviewing') {
                actionsDiv.style.display = 'block';
                document.getElementById('imgModalAmount').innerText = parseFloat(meta.amount || 0).toLocaleString('th-TH', {minimumFractionDigits:2});
                document.getElementById('imgModalReqId').value = meta.request_id;
                document.getElementById('imgModalSide').value = meta.side;
            } else {
                actionsDiv.style.display = 'none';
            }
            
            document.getElementById('imgModal').style.display = 'flex';
        }";

$replace_js = "        function openModal(src, meta = {}) {
            document.getElementById('imgModalContent').src = src;
            
            const actionsDiv = document.getElementById('imgModalActions');
            if (meta && meta.isP2P) {
                document.getElementById('imgModalAmount').innerText = parseFloat(meta.amount || 0).toLocaleString('th-TH', {minimumFractionDigits:2});
                document.getElementById('imgModalReqIdTxt').innerText = meta.request_id;
                document.getElementById('imgModalReqIdInput').value = meta.request_id;
                document.getElementById('imgModalSide').value = meta.side;
                document.getElementById('imgModalPayer').innerText = meta.payerName || '-';
                document.getElementById('imgModalDate').innerText = meta.createdAt ? meta.createdAt : '-';
                
                const badge = document.getElementById('imgModalBadge');
                if (meta.status === 'reviewing') {
                    badge.innerText = 'รอตรวจสอบสลิป';
                    badge.style.background = '#fef3c7';
                    badge.style.color = '#92400e';
                    actionsDiv.style.display = 'flex';
                } else if (meta.status === 'approved') {
                    badge.innerText = 'ตรวจสอบแล้ว';
                    badge.style.background = '#d1fae5';
                    badge.style.color = '#065f46';
                    actionsDiv.style.display = 'none';
                } else if (meta.status === 'rejected') {
                    badge.innerText = 'ไม่อนุมัติ';
                    badge.style.background = '#fee2e2';
                    badge.style.color = '#991b1b';
                    actionsDiv.style.display = 'none';
                } else {
                    badge.innerText = 'รอผู้ใช้ชำระเงิน';
                    badge.style.background = '#f3f4f6';
                    badge.style.color = '#374151';
                    actionsDiv.style.display = 'none';
                }
            } else {
                // Not P2P
                actionsDiv.style.display = 'none';
                document.getElementById('imgModalReqIdTxt').innerText = '-';
            }
            
            document.getElementById('imgModal').style.display = 'flex';
        }";
$content = str_replace($search_js, $replace_js, $content);

file_put_contents($file, $content);
echo "UI updated successfully.\n";
