<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

// Replace logistics
$jsOld = '} else {
                logHtml += \'<div style="text-align:center; color:#94a3b8; padding:4px 0;">ไม่มีข้อมูลพัสดุ</div>\';
            }';

$jsNew = '} else if (data.trade_option === \'meetup\') {
                let secTitle = document.querySelector(\'#modal-logistics-content\').previousElementSibling;
                if(secTitle) secTitle.innerHTML = \'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ข้อมูลการนัดรับ\';
                
                let d = data.logistics.meetup_date;
                let dateStr = "ยังไม่ระบุ";
                if(d) {
                    let dateObj = new Date(d);
                    dateStr = dateObj.toLocaleDateString(\'th-TH\', {year:\'numeric\', month:\'short\', day:\'numeric\'});
                }
                
                logHtml += \'<div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#64748b;">วันที่:</span><span style="font-weight:500;">\' + dateStr + \'</span></div>\';
                logHtml += \'<div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#64748b;">เวลา:</span><span style="font-weight:500;">\' + (data.logistics.meetup_time ? data.logistics.meetup_time.substr(0,5) + \' น.\' : \'ยังไม่ระบุ\') + \'</span></div>\';
                logHtml += \'<div style="display:flex; flex-direction:column; margin-top:4px;"><span style="color:#64748b;">สถานที่:</span><span style="font-weight:500; margin-top:2px;">\' + (data.logistics.meetup_location || \'ยังไม่ระบุ\') + \'</span></div>\';
            } else {
                let secTitle = document.querySelector(\'#modal-logistics-content\').previousElementSibling;
                if(secTitle) secTitle.innerHTML = \'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg> การจัดส่งพัสดุ\';
                logHtml += \'<div style="text-align:center; color:#94a3b8; padding:4px 0;">ไม่มีข้อมูลพัสดุ</div>\';
            }';

$c = str_replace($jsOld, $jsNew, $c);

// Replace financials
$jsFinOld = '} else {
                finHtml = \'<div style="text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;">ไม่มีข้อมูลการโอนเงิน (ทำรายการก่อนมีระบบการเงิน หรือเป็นแบบนัดรับ)</div>\';
            }';

$jsFinNew = '} else {
                if (data.trade_option === \'meetup\') {
                    finHtml = \'<div style="text-align:center; padding:16px; color:#047857; background:#f0fdf4; border-radius:12px; border:1px solid #bbf7d0;"><div style="font-weight:600; margin-bottom:4px;">ฟรีค่าธรรมเนียม</div><div style="font-size:13px;">การแลกเปลี่ยนแบบนัดรับไม่มีค่าใช้จ่ายและไม่ต้องวางมัดจำ</div></div>\';
                } else {
                    finHtml = \'<div style="text-align:center; padding:16px; color:#94a3b8; background:#f8fafc; border-radius:12px;">ไม่มีข้อมูลการโอนเงิน</div>\';
                }
            }';

$c = str_replace($jsFinOld, $jsFinNew, $c);

file_put_contents($file, $c);
echo "JS patched.\n";
