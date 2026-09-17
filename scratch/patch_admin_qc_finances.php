<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

$jsFinOld = 'if (data.trade_option === \'admin_qc\') {
                    finHtml += \'<span style="color:#64748b;">ค่าบริการตรวจสอบ QC (ชำระแล้ว):</span><span style="font-weight:600; color:#115e59;">\' + fin.subtotal.toLocaleString(\'en-US\', {minimumFractionDigits:2}) + \' บาท</span>\';
                } else if (data.trade_option === \'direct_escrow\') {
                    finHtml += \'<span style="color:#64748b;">ยอดเงินมัดจำ (โอนไว้):</span><span style="font-weight:600; color:#111827;">\' + fin.subtotal.toLocaleString(\'en-US\', {minimumFractionDigits:2}) + \' บาท</span>\';
                }
                finHtml += \'</div>\';
                
                if (data.trade_option === \'direct_escrow\' && fin.fee > 0) {
                    finHtml += \'<div style="display:flex; justify-content:space-between; margin-bottom:12px;">\';
                    finHtml += \'<span style="color:#ef4444;">หักค่าธรรมเนียมแพลตฟอร์ม:</span><span style="font-weight:600; color:#ef4444;">-\' + fin.fee.toLocaleString(\'en-US\', {minimumFractionDigits:2}) + \' บาท</span>\';
                    finHtml += \'</div>\';
                }
                
                if (fin.refund_amount) {';

$jsFinNew = 'if (data.trade_option === \'admin_qc\') {
                    finHtml += \'<div style="display:flex; flex-direction:column; gap:8px; width:100%;">\';
                    finHtml += \'<div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">ค่าบริการตรวจเช็คสภาพ:</span><span style="font-weight:500; color:#334155;">50.00 บาท</span></div>\';
                    finHtml += \'<div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">ค่าจัดส่งพัสดุ:</span><span style="font-weight:500; color:#334155;">50.00 บาท</span></div>\';
                    finHtml += \'<div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:8px; border-top:1px solid #e2e8f0;"><span style="color:#0f172a; font-weight:600;">ยอดชำระแล้ว:</span><span style="font-weight:700; color:#115e59;">\' + fin.subtotal.toLocaleString(\\'en-US\\', {minimumFractionDigits:2}) + \' บาท</span></div>\';
                    finHtml += \'</div></div>\';
                } else if (data.trade_option === \'direct_escrow\') {
                    finHtml += \'<span style="color:#64748b;">ยอดเงินมัดจำ (โอนไว้):</span><span style="font-weight:600; color:#111827;">\' + fin.subtotal.toLocaleString(\'en-US\', {minimumFractionDigits:2}) + \' บาท</span>\';
                    finHtml += \'</div>\';
                }
                
                if (data.trade_option === \'direct_escrow\' && fin.fee > 0) {
                    finHtml += \'<div style="display:flex; justify-content:space-between; margin-bottom:12px;">\';
                    finHtml += \'<span style="color:#ef4444;">หักค่าธรรมเนียมแพลตฟอร์ม:</span><span style="font-weight:600; color:#ef4444;">-\' + fin.fee.toLocaleString(\'en-US\', {minimumFractionDigits:2}) + \' บาท</span>\';
                    finHtml += \'</div>\';
                }
                
                if (data.trade_option === \'direct_escrow\' && fin.refund_amount) {';

// Need to match exactly what is in the file. Wait, in my previous write, what was it?
// Let's use regex to be safe.
$regex = '/if \(data\.trade_option === \'admin_qc\'\) \{.*?if \(fin\.refund_amount\) \{/s';
$replacement = 'if (data.trade_option === \'admin_qc\') {
                    finHtml += \'<div style="display:flex; flex-direction:column; gap:8px; width:100%;">\';
                    finHtml += \'<div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">ค่าบริการตรวจเช็คสภาพ:</span><span style="font-weight:500; color:#334155;">50.00 บาท</span></div>\';
                    finHtml += \'<div style="display:flex; justify-content:space-between;"><span style="color:#64748b;">ค่าจัดส่งพัสดุ:</span><span style="font-weight:500; color:#334155;">50.00 บาท</span></div>\';
                    finHtml += \'<div style="display:flex; justify-content:space-between; margin-top:4px; padding-top:8px; border-top:1px solid #e2e8f0;"><span style="color:#0f172a; font-weight:600;">ยอดชำระแล้ว:</span><span style="font-weight:700; color:#115e59;">\' + fin.subtotal.toLocaleString(\'en-US\', {minimumFractionDigits:2}) + \' บาท</span></div>\';
                    finHtml += \'</div></div>\';
                } else if (data.trade_option === \'direct_escrow\') {
                    finHtml += \'<span style="color:#64748b;">ยอดเงินมัดจำ (โอนไว้):</span><span style="font-weight:600; color:#111827;">\' + fin.subtotal.toLocaleString(\'en-US\', {minimumFractionDigits:2}) + \' บาท</span>\';
                    finHtml += \'</div>\';
                }
                
                if (data.trade_option === \'direct_escrow\' && fin.fee > 0) {
                    finHtml += \'<div style="display:flex; justify-content:space-between; margin-bottom:12px;">\';
                    finHtml += \'<span style="color:#ef4444;">หักค่าธรรมเนียมแพลตฟอร์ม:</span><span style="font-weight:600; color:#ef4444;">-\' + fin.fee.toLocaleString(\'en-US\', {minimumFractionDigits:2}) + \' บาท</span>\';
                    finHtml += \'</div>\';
                }
                
                if (data.trade_option === \'direct_escrow\' && fin.refund_amount) {';

$newC = preg_replace($regex, $replacement, $c);
file_put_contents($file, $newC);
echo "Patched admin_qc finances.\n";
