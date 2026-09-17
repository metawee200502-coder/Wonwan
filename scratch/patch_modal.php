<?php
$file = 'c:\\laragon\\www\\wanawan_project\\pages\\exchange_requests.php';
$content = file_get_contents($file);

// 1. Replace the button
$oldBtn = '<a class="btn btn-outline" href="/pages/book_detail.php?source=exchange&id=<?=$request[\'exchange_id\']?>">';
$newBtn = '<button type="button" class="btn btn-outline" onclick="openExchangeDetails(<?=$request[\'request_id\']?>)">';
$content = str_replace($oldBtn, $newBtn, $content);

$oldEndA = "</a>\n                                <a class=\"btn btn-primary\"";
$newEndBtn = "</button>\n                                <a class=\"btn btn-primary\"";
$content = str_replace($oldEndA, $newEndBtn, $content);

// 2. Add Modal HTML and JS
$modalHTML = <<<HTML

<!-- Exchange Details Modal -->
<div id="exchangeDetailsModal" class="modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; backdrop-filter:blur(4px);">
    <div class="modal-content" style="background:#fff; width:100%; max-width:640px; border-radius:24px; padding:32px; max-height:90vh; overflow-y:auto; position:relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <button onclick="closeExchangeDetails()" style="position:absolute; right:24px; top:24px; background:#f3f4f6; border:none; font-size:20px; cursor:pointer; color:#4b5563; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:background 0.2s;">&times;</button>
        <h2 style="margin:0 0 24px 0; font-size:22px; color:#111827; display:flex; align-items:center; gap:10px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#115e59" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            สรุปรายการ <span id="modal-req-id" style="color:#115e59;"></span>
        </h2>
        
        <div style="background:#f8fafc; padding:20px; border-radius:16px; margin-bottom:20px; border: 1px solid #f1f5f9;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div style="flex:1; text-align:center;">
                    <span style="display:inline-block; padding:4px 12px; background:#e0e7ff; color:#3730a3; border-radius:12px; font-size:12px; font-weight:600; margin-bottom:8px;">หนังสือของฉัน</span>
                    <p style="margin:0; font-weight:600; color:#1e293b; font-size:15px;" id="modal-my-book"></p>
                </div>
                <div style="padding:0 24px; color:#94a3b8;">
                    <div style="width:40px; height:40px; background:#fff; border-radius:50%; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#115e59" stroke-width="2"><path d="M8 3L12 7L8 11M16 21L12 17L16 13M12 7H4M20 17H12"></path></svg>
                    </div>
                </div>
                <div style="flex:1; text-align:center;">
                    <span style="display:inline-block; padding:4px 12px; background:#fef3c7; color:#92400e; border-radius:12px; font-size:12px; font-weight:600; margin-bottom:8px;">หนังสือของ <span id="modal-partner-name"></span></span>
                    <p style="margin:0; font-weight:600; color:#1e293b; font-size:15px;" id="modal-partner-book"></p>
                </div>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
            <div style="background:#fff; padding:20px; border-radius:16px; border: 1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
                <h3 style="margin:0 0 12px 0; font-size:15px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    ลำดับเวลา
                </h3>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span style="color:#64748b;">เริ่มเจรจา:</span>
                        <span id="modal-created-at" style="color:#334155; font-weight:500;"></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span style="color:#64748b;">ทำรายการล่าสุด:</span>
                        <span id="modal-updated-at" style="color:#334155; font-weight:500;"></span>
                    </div>
                </div>
            </div>
            
            <div style="background:#fff; padding:20px; border-radius:16px; border: 1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
                <h3 style="margin:0 0 12px 0; font-size:15px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                    การจัดส่งพัสดุ
                </h3>
                <div id="modal-logistics-content" style="display:flex; flex-direction:column; gap:8px; font-size:13px;">
                </div>
            </div>
        </div>

        <div id="modal-qc-section" style="background:linear-gradient(to right, #ecfdf5, #f0fdf4); padding:20px; border-radius:16px; margin-bottom:20px; border:1px solid #a7f3d0; display:none;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h3 style="margin:0 0 6px 0; font-size:15px; color:#065f46; display:flex; align-items:center; gap:6px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path></svg>
                        แบบฟอร์มตรวจสอบสภาพ
                    </h3>
                    <p style="margin:0; font-size:13px; color:#047857;">ดูภาพถ่ายและประเมินสภาพหนังสืออย่างละเอียด</p>
                </div>
                <a href="#" id="modal-qc-link" style="background:#10b981; color:#fff; padding:8px 16px; border-radius:20px; text-decoration:none; font-size:13px; font-weight:600; box-shadow:0 2px 4px rgba(16, 185, 129, 0.2); transition:all 0.2s;">เปิดดูฟอร์ม QC</a>
            </div>
        </div>

        <div style="background:#fff; padding:20px; border-radius:16px; border: 1px solid #e2e8f0; box-shadow:0 1px 2px rgba(0,0,0,0.02);">
            <h3 style="margin:0 0 16px 0; font-size:15px; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                สรุปข้อมูลการเงิน
            </h3>
            <div id="modal-financial-content" style="font-size:14px; color:#334155;">
                <div style="text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;">กำลังโหลด...</div>
            </div>
        </div>

    </div>
</div>

<script>
function openExchangeDetails(requestId) {
    document.getElementById('exchangeDetailsModal').style.display = 'flex';
    document.getElementById('modal-financial-content').innerHTML = '<div style="text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;">กำลังโหลดข้อมูล...</div>';
    
    fetch('/api/get_exchange_details.php?request_id=' + requestId)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('เกิดข้อผิดพลาด: ' + (data.error || 'ไม่สามารถโหลดข้อมูลได้'));
                closeExchangeDetails();
                return;
            }
            
            document.getElementById('modal-req-id').textContent = data.request_id;
            document.getElementById('modal-my-book').textContent = data.my_book;
            document.getElementById('modal-partner-name').textContent = data.partner_name;
            document.getElementById('modal-partner-book').textContent = data.partner_book;
            document.getElementById('modal-created-at').textContent = data.created_at;
            document.getElementById('modal-updated-at').textContent = data.updated_at;
            
            // Logistics
            let logHtml = '';
            if (data.trade_option === 'admin_qc') {
                logHtml += '<div style="display:flex; justify-content:space-between;"><span>พัสดุคุณ -> แอดมิน:</span><span style="font-weight:500;">' + (data.logistics.my_tracking || '-') + '</span></div>';
                logHtml += '<div style="display:flex; justify-content:space-between; margin-top:8px;"><span>พัสดุแอดมิน -> คุณ:</span><span style="font-weight:500;">' + (data.logistics.admin_tracking || '-') + '</span></div>';
            } else if (data.trade_option === 'direct_escrow') {
                logHtml += '<div style="display:flex; justify-content:space-between;"><span>พัสดุคุณ -> คู่แลก:</span><span style="font-weight:500;">' + (data.logistics.my_tracking || '-') + '</span></div>';
                logHtml += '<div style="display:flex; justify-content:space-between; margin-top:8px;"><span>พัสดุคู่แลก -> คุณ:</span><span style="font-weight:500;">' + (data.logistics.partner_tracking || '-') + '</span></div>';
            } else {
                logHtml += '<div style="text-align:center; color:#94a3b8; padding:4px 0;">ไม่มีข้อมูลพัสดุ</div>';
            }
            document.getElementById('modal-logistics-content').innerHTML = logHtml;
            
            // QC Form
            if (data.has_qc) {
                document.getElementById('modal-qc-section').style.display = 'block';
                document.getElementById('modal-qc-link').href = '/pages/trade_condition_form.php?request_id=' + data.raw_request_id;
            } else {
                document.getElementById('modal-qc-section').style.display = 'none';
            }
            
            // Financials
            let finHtml = '';
            if (data.financials) {
                let fin = data.financials;
                
                finHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:12px; padding-bottom:12px; border-bottom:1px dashed #e2e8f0;">';
                if (data.trade_option === 'admin_qc') {
                    finHtml += '<span style="color:#64748b;">ค่าบริการตรวจสอบ QC (ชำระแล้ว):</span><span style="font-weight:600; color:#115e59;">' + fin.subtotal.toLocaleString('en-US', {minimumFractionDigits:2}) + ' บาท</span>';
                } else if (data.trade_option === 'direct_escrow') {
                    finHtml += '<span style="color:#64748b;">ยอดเงินมัดจำ (โอนไว้):</span><span style="font-weight:600; color:#111827;">' + fin.subtotal.toLocaleString('en-US', {minimumFractionDigits:2}) + ' บาท</span>';
                }
                finHtml += '</div>';
                
                if (data.trade_option === 'direct_escrow' && fin.fee > 0) {
                    finHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:12px;">';
                    finHtml += '<span style="color:#ef4444;">หักค่าธรรมเนียมแพลตฟอร์ม:</span><span style="font-weight:600; color:#ef4444;">-' + fin.fee.toLocaleString('en-US', {minimumFractionDigits:2}) + ' บาท</span>';
                    finHtml += '</div>';
                }
                
                if (fin.refund_amount) {
                    finHtml += '<div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:12px; border-radius:8px;">';
                    finHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:4px;">';
                    finHtml += '<span style="color:#166534; font-weight:600;">ยอดเงินที่คืนเข้ากระเป๋า:</span><span style="font-weight:700; color:#059669; font-size:15px;">+' + fin.refund_amount.toLocaleString('en-US', {minimumFractionDigits:2}) + ' บาท</span>';
                    finHtml += '</div>';
                    finHtml += '<div style="display:flex; justify-content:space-between; font-size:12px; color:#047857;">';
                    finHtml += '<span>สถานะ: <span style="background:#059669; color:#fff; padding:2px 6px; border-radius:4px; font-weight:600; font-size:11px;">' + (fin.refund_status === 'SUCCESS' ? 'สำเร็จ' : fin.refund_status) + '</span></span>';
                    finHtml += '<span>เวลา: ' + fin.refund_date + '</span>';
                    finHtml += '</div>';
                    finHtml += '</div>';
                } else if (data.status === 'completed' && data.trade_option === 'direct_escrow') {
                    finHtml += '<div style="text-align:center; padding:10px; color:#d97706; background:#fef3c7; border-radius:8px; font-size:13px;">กำลังดำเนินการคืนเงินมัดจำ...</div>';
                }
            } else {
                finHtml = '<div style="text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;">ไม่มีข้อมูลการโอนเงิน (ทำรายการก่อนมีระบบการเงิน หรือเป็นแบบนัดรับ)</div>';
            }
            document.getElementById('modal-financial-content').innerHTML = finHtml;
            
        })
        .catch(err => {
            document.getElementById('modal-financial-content').innerHTML = '<div style="color:red; text-align:center; padding:16px;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
        });
}

function closeExchangeDetails() {
    document.getElementById('exchangeDetailsModal').style.display = 'none';
}
</script>
HTML;

$content = str_replace('</body>', $modalHTML . "\n</body>", $content);

file_put_contents($file, $content);
echo "Modal added to exchange_requests.php\n";
