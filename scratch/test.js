
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
            
            let tradeOptionText = '';
            if (data.trade_option === 'admin_qc') tradeOptionText = 'แลกเปลี่ยนผ่านระบบกลาง (QC)';
            else if (data.trade_option === 'direct_escrow' || data.trade_option === 'p2p') tradeOptionText = 'ส่งพัสดุถึงกันโดยตรง (ระบบคนกลาง)';
            else if (data.trade_option === 'meetup') tradeOptionText = 'นัดรับสินค้า (ไม่ผ่านระบบ)';
            else tradeOptionText = data.trade_option;
            
            let optEl = document.getElementById('modal-trade-option');
            if (optEl) optEl.textContent = tradeOptionText;
            
            // Logistics
            let logHtml = '';
            if (data.trade_option === 'admin_qc') {
                let secTitle = document.querySelector('#modal-logistics-content').previousElementSibling;
                if(secTitle) secTitle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg> การจัดส่งพัสดุ';
                logHtml += '<div style="display:flex; justify-content:space-between;"><span>พัสดุคุณ -> แอดมิน:</span><span style="font-weight:500;">' + (data.logistics.my_tracking || '-') + '</span></div>';
                logHtml += '<div style="display:flex; justify-content:space-between; margin-top:8px;"><span>พัสดุแอดมิน -> คุณ:</span><span style="font-weight:500;">' + (data.logistics.admin_tracking || '-') + '</span></div>';
            } else if (data.trade_option === 'direct_escrow') {
                let secTitle = document.querySelector('#modal-logistics-content').previousElementSibling;
                if(secTitle) secTitle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg> การจัดส่งพัสดุ';
                logHtml += '<div style="display:flex; justify-content:space-between;"><span>พัสดุคุณ -> คู่แลก:</span><span style="font-weight:500;">' + (data.logistics.my_tracking || '-') + '</span></div>';
                logHtml += '<div style="display:flex; justify-content:space-between; margin-top:8px;"><span>พัสดุคู่แลก -> คุณ:</span><span style="font-weight:500;">' + (data.logistics.partner_tracking || '-') + '</span></div>';
            } else if (data.trade_option === 'meetup') {
                let secTitle = document.querySelector('#modal-logistics-content').previousElementSibling;
                if(secTitle) secTitle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> ข้อมูลการนัดรับ';
                let d = data.logistics.meetup_date;
                let dateStr = "ยังไม่ระบุ";
                if(d) {
                    let dateObj = new Date(d);
                    dateStr = dateObj.toLocaleDateString('th-TH', {year:'numeric', month:'short', day:'numeric'});
                }
                logHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#64748b;">วันที่:</span><span style="font-weight:500;">' + dateStr + '</span></div>';
                logHtml += '<div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#64748b;">เวลา:</span><span style="font-weight:500;">' + (data.logistics.meetup_time ? data.logistics.meetup_time.substr(0,5) + ' น.' : 'ยังไม่ระบุ') + '</span></div>';
                logHtml += '<div style="display:flex; flex-direction:column; margin-top:4px;"><span style="color:#64748b;">สถานที่:</span><span style="font-weight:500; margin-top:2px;">' + (data.logistics.meetup_location || 'ยังไม่ระบุ') + '</span></div>';
            } else {
                let secTitle = document.querySelector('#modal-logistics-content').previousElementSibling;
                if(secTitle) secTitle.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg> การจัดส่งพัสดุ';
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
            
        })
        .catch(err => {
            document.getElementById('modal-financial-content').innerHTML = '<div style="color:red; text-align:center; padding:16px;">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
        });
}

function closeExchangeDetails() {
    document.getElementById('exchangeDetailsModal').style.display = 'none';
}
