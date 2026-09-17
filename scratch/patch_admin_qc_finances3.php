<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

// 1. Inject the trade option display block in the HTML
$htmlOld = '<p style="margin:0; font-weight:600; color:#1e293b; font-size:15px;" id="modal-partner-book"></p>
                </div>
            </div>
        </div>';

$htmlNew = '<p style="margin:0; font-weight:600; color:#1e293b; font-size:15px;" id="modal-partner-book"></p>
                </div>
            </div>
            
            <div style="text-align:center; margin-top:16px; padding-top:16px; border-top:1px solid #e2e8f0;">
                <span style="color:#64748b; font-size:13px;">รูปแบบการแลกเปลี่ยน: </span><strong style="color:#115e59; font-size:14px;" id="modal-trade-option"></strong>
            </div>
        </div>';

$c = str_replace($htmlOld, $htmlNew, $c);

// 2. Inject JS to set trade option text
$jsOld = 'document.getElementById(\'modal-updated-at\').textContent = data.updated_at;';
$jsNew = 'document.getElementById(\'modal-updated-at\').textContent = data.updated_at;
            
            let tradeOptionText = \'\';
            if (data.trade_option === \'admin_qc\') tradeOptionText = \'แลกเปลี่ยนผ่านระบบกลาง (QC)\';
            else if (data.trade_option === \'direct_escrow\' || data.trade_option === \'p2p\') tradeOptionText = \'ส่งพัสดุถึงกันโดยตรง (ระบบคนกลาง)\';
            else if (data.trade_option === \'meetup\') tradeOptionText = \'นัดรับสินค้า (ไม่ผ่านระบบ)\';
            else tradeOptionText = data.trade_option;
            
            let optEl = document.getElementById(\'modal-trade-option\');
            if (optEl) optEl.textContent = tradeOptionText;';

$c = str_replace($jsOld, $jsNew, $c);

// 3. Always show 100 THB breakdown for admin_qc, even if financials is null
// The logic currently is:
// let finHtml = '';
// if (data.financials) { ... } else { if (data.trade_option === 'meetup') {...} else { finHtml = 'ไม่มีข้อมูล...' } }
//
// We will replace the whole financials JS block to be robust!
$regex = '/\/\/ Financials\s*let finHtml = \'\';.*?document\.getElementById\(\'modal-financial-content\'\)\.innerHTML = finHtml;/s';

$replacement = <<<JS
// Financials
            let finHtml = '';
            
            // For admin_qc, it's a fixed 100 THB rate (50 service + 50 shipping), no refund.
            if (data.trade_option === 'admin_qc') {
                finHtml += '<div style="display:flex; flex-direction:column; gap:8px; width:100%;">';
                finHtml += '<div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">ค่าบริการตรวจเช็คสภาพ:</span><span style="font-weight:500; color:#334155;">50.00 บาท</span></div>';
                finHtml += '<div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">ค่าจัดส่งพัสดุ:</span><span style="font-weight:500; color:#334155;">50.00 บาท</span></div>';
                finHtml += '<div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:8px; border-top:1px dashed #e2e8f0;"><span style="color:#0f172a; font-weight:600;">ยอดชำระแล้ว:</span><span style="font-weight:700; color:#115e59;">100.00 บาท</span></div>';
                finHtml += '</div>';
            } 
            else if (data.trade_option === 'meetup') {
                finHtml = '<div style="text-align:center; padding:16px; color:#047857; background:#f0fdf4; border-radius:12px; border:1px solid #bbf7d0;"><div style="font-weight:600; margin-bottom:4px;">ฟรีค่าธรรมเนียม</div><div style="font-size:13px;">การแลกเปลี่ยนแบบนัดรับไม่มีค่าใช้จ่ายและไม่ต้องวางมัดจำ</div></div>';
            }
            else if (data.financials) {
                let fin = data.financials;
                
                finHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:12px; padding-bottom:12px; border-bottom:1px dashed #e2e8f0; width:100%;">';
                finHtml += '<span style="color:#64748b;">ยอดเงินมัดจำ (โอนไว้):</span><span style="font-weight:600; color:#111827;">' + fin.subtotal.toLocaleString('en-US', {minimumFractionDigits:2}) + ' บาท</span>';
                finHtml += '</div>';
                
                if (fin.fee > 0) {
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
                } else if (data.status === 'completed') {
                    finHtml += '<div style="text-align:center; padding:10px; color:#d97706; background:#fef3c7; border-radius:8px; font-size:13px;">กำลังดำเนินการคืนเงินมัดจำ...</div>';
                }
            } else {
                finHtml = '<div style="text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;">ไม่มีข้อมูลการโอนเงิน (อาจทำรายการก่อนมีระบบการเงิน)</div>';
            }
            
            document.getElementById('modal-financial-content').innerHTML = finHtml;
JS;

$c = preg_replace($regex, $replacement, $c);
file_put_contents($file, $c);
echo "Patched admin_qc finances logic.\n";
