<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

// 1. Fix the SQL query to ORDER BY request_id DESC
$content = str_replace(
    'ORDER BY er.updated_at DESC LIMIT 1',
    'ORDER BY er.request_id DESC LIMIT 1',
    $content
);

// 2. Fix the JavaScript logic for exchangeRequestPanel
// We just want to insert the logic right before `oldCard.replaceWith(summary);`
$target = "summary.append(status,pair,message,manage);oldCard.replaceWith(summary);";
$escrowLogic = <<<JS
      summary.append(status,pair,message,manage);

      if (data.rawStatus === 'accepted') {
          const needsDeadline = (data.tradeOption === 'direct_escrow' || data.tradeOption === 'admin_qc');
          const hasDeadline = data.shippingDeadline > 0;
          
          if (needsDeadline && !hasDeadline) {
              const waitMsg = document.createElement('div');
              waitMsg.style.marginTop = '10px'; waitMsg.style.padding = '10px'; waitMsg.style.background = '#fff3cd'; waitMsg.style.color = '#856404'; waitMsg.style.borderRadius = '5px'; waitMsg.style.fontSize = '13px'; waitMsg.style.textAlign = 'center'; waitMsg.style.border = '1px solid #ffeeba';
              waitMsg.innerHTML = '⏳ <strong>รอข้อสรุปวันจัดส่ง</strong><br>กรุณาตกลงวันจัดส่งในแชทให้เรียบร้อยก่อนดำเนินการต่อ';
              summary.append(waitMsg);
          } else if (data.tradeOption === 'direct_escrow' && data.conditionsAccepted) {
              // Escrow Logic
              var myStatus = (data.myCondition && data.myCondition.deposit_status) ? data.myCondition.deposit_status : 'pending';
              var partnerStatus = (data.partnerCondition && data.partnerCondition.deposit_status) ? data.partnerCondition.deposit_status : 'pending';
              var myRequiredDeposit = (data.myCondition && data.myCondition.required_deposit_amount !== null && data.myCondition.required_deposit_amount !== undefined) ? data.myCondition.required_deposit_amount : null;
              var partnerRequiredDeposit = (data.partnerCondition && data.partnerCondition.required_deposit_amount !== null && data.partnerCondition.required_deposit_amount !== undefined) ? data.partnerCondition.required_deposit_amount : null;

              const chatHeaderBanner = document.createElement('div');
              chatHeaderBanner.style.margin = '15px'; chatHeaderBanner.style.marginBottom = '0';
              
              if (myRequiredDeposit === null) {
                  const propBanner = document.createElement('div');
                  propBanner.style.padding = '15px'; propBanner.style.background = '#fff5eb'; propBanner.style.color = '#c05621'; propBanner.style.borderRadius = '8px'; propBanner.style.fontSize = '14px'; propBanner.style.textAlign = 'center'; propBanner.style.border = '1px solid #fbd38d'; propBanner.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)'; propBanner.style.marginBottom = '10px';
                  propBanner.innerHTML = '💰 <strong>กำหนดเงินมัดจำ</strong><br>กรุณาระบุมูลค่าของสินค้าคุณ เพื่อเรียกเก็บเงินมัดจำจากอีกฝ่าย<br><br>';
                  const propBtn = document.createElement('button');
                  propBtn.className = 'btn primary'; propBtn.textContent = 'เสนอมัดจำ'; propBtn.style.padding = '8px 16px'; propBtn.style.background = '#dd6b20'; propBtn.style.borderColor = '#dd6b20';
                  propBtn.onclick = function() {
                      let amt = prompt('กรุณากรอกมูลค่าของสินค้าคุณ (บาท)\\nเพื่อเรียกเก็บเป็นเงินมัดจำ:');
                      if (amt !== null && amt.trim() !== '') {
                          amt = parseFloat(amt);
                          if (!isNaN(amt) && amt >= 0) {
                              const form = document.createElement('form');
                              form.method = 'post'; form.action = '/pages/handlers/trade_submit_logistics.php';
                              form.innerHTML = '<input type="hidden" name="action" value="set_deposit"><input type="hidden" name="request_id" value="' + data.requestId + '"><input type="hidden" name="amount" value="' + amt + '">';
                              document.body.append(form); form.submit();
                          } else {
                              alert('กรุณากรอกตัวเลขที่ถูกต้อง');
                          }
                      }
                  };
                  propBanner.append(propBtn); chatHeaderBanner.append(propBanner);
              } else if (partnerRequiredDeposit === null) {
                  const waitPropBanner = document.createElement('div');
                  waitPropBanner.style.padding = '15px'; waitPropBanner.style.background = '#fff5eb'; waitPropBanner.style.color = '#c05621'; waitPropBanner.style.borderRadius = '8px'; waitPropBanner.style.fontSize = '14px'; waitPropBanner.style.textAlign = 'center'; waitPropBanner.style.border = '1px solid #fbd38d'; waitPropBanner.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)'; waitPropBanner.style.marginBottom = '10px';
                  waitPropBanner.innerHTML = '⏳ <strong>รออีกฝ่ายประเมินมูลค่าสินค้า</strong><br>เพื่อกำหนดเงินมัดจำที่คุณต้องชำระ';
                  chatHeaderBanner.append(waitPropBanner);
              } else {
                  var myDepositDone = (parseFloat(partnerRequiredDeposit) === 0) || (myStatus === 'approved');
                  var partnerDepositDone = (parseFloat(myRequiredDeposit) === 0) || (partnerStatus === 'approved');
                  
                  if (!myDepositDone) {
                      const depositBanner = document.createElement('div');
                      depositBanner.style.padding = '15px'; depositBanner.style.background = '#fff5f5'; depositBanner.style.color = '#c53030'; depositBanner.style.borderRadius = '8px'; depositBanner.style.fontSize = '14px'; depositBanner.style.textAlign = 'center'; depositBanner.style.border = '1px solid #feb2b2'; depositBanner.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)'; depositBanner.style.marginBottom = '10px';
                      
                      if (myStatus === 'pending_verification') {
                          depositBanner.innerHTML = '<i class="fas fa-hourglass-half"></i> <strong>รอแอดมินตรวจสอบสลิปเงินมัดจำ</strong><br>จำนวนเงิน: ฿' + parseFloat(partnerRequiredDeposit).toFixed(2);
                      } else {
                          depositBanner.innerHTML = '⚠️ <strong>คุณต้องชำระเงินมัดจำ</strong><br>จำนวนเงิน: ฿' + parseFloat(partnerRequiredDeposit).toFixed(2) + '<br><br>';
                          const payBtn = document.createElement('button');
                          payBtn.className = 'btn primary'; payBtn.textContent = 'แนบสลิปชำระเงิน'; payBtn.style.padding = '8px 16px';
                          payBtn.onclick = function() {
                              window.location.href = '/pages/trade_upload_slip.php?request_id=' + data.requestId;
                          };
                          depositBanner.append(payBtn);
                      }
                      chatHeaderBanner.append(depositBanner);
                  }
                  if (!partnerDepositDone) {
                      const waitBanner = document.createElement('div');
                      waitBanner.style.padding = '15px'; waitBanner.style.background = '#ebf4ff'; waitBanner.style.color = '#2b6cb0'; waitBanner.style.borderRadius = '8px'; waitBanner.style.fontSize = '14px'; waitBanner.style.textAlign = 'center'; waitBanner.style.border = '1px solid #bee3f8'; waitBanner.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)'; waitBanner.style.marginBottom = '10px';
                      waitBanner.innerHTML = '<i class="fas fa-clock"></i> <strong>รออีกฝ่ายชำระเงินมัดจำ</strong><br>จำนวนเงิน: ฿' + parseFloat(myRequiredDeposit).toFixed(2);
                      chatHeaderBanner.append(waitBanner);
                  }
                  
                  if (myDepositDone && partnerDepositDone) {
                      const shipBanner = document.createElement('div');
                      shipBanner.style.padding = '15px'; shipBanner.style.background = '#f0fff4'; shipBanner.style.color = '#276749'; shipBanner.style.borderRadius = '8px'; shipBanner.style.fontSize = '14px'; shipBanner.style.textAlign = 'center'; shipBanner.style.border = '1px solid #c6f6d5'; shipBanner.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)'; shipBanner.style.marginBottom = '10px';
                  
                      var myApprovedAt = (data.myCondition) ? data.myCondition.deposit_approved_at : null;
                      var partnerApprovedAt = (data.partnerCondition) ? data.partnerCondition.deposit_approved_at : null;
                      var approvedAt = myApprovedAt || partnerApprovedAt || new Date().toISOString();
                      
                      var d = new Date(approvedAt);
                      d.setDate(d.getDate() + parseInt(data.shippingDeadline));
                      var deadlineStr = d.toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                      
                      var myTracking = (data.myCondition) ? data.myCondition.tracking_number : null;
                      var partnerTracking = (data.partnerCondition) ? data.partnerCondition.tracking_number : null;
                      var partnerReceived = (data.partnerCondition) ? data.partnerCondition.is_received : 0;
                      var myReceived = (data.myCondition) ? data.myCondition.is_received : 0;

                      if (!myTracking) {
                          shipBanner.innerHTML = '✅ <strong>การวางมัดจำเสร็จสิ้น!</strong><br>กรุณาจัดส่งสินค้าภายในวันที่ ' + deadlineStr + '<br><br>';
                          const shipBtn = document.createElement('button');
                          shipBtn.className = 'btn primary'; shipBtn.textContent = '📦 แจ้งเลขพัสดุ'; shipBtn.style.padding = '8px 16px';
                          shipBtn.onclick = function() {
                              window.location.href = '/pages/trade_p2p_logistics_form.php?request_id=' + data.requestId;
                          };
                          shipBanner.append(shipBtn);
                      } else if (!partnerTracking) {
                          shipBanner.innerHTML = '📦 <strong>จัดส่งสินค้าแล้ว</strong><br>รออีกฝ่ายจัดส่งสินค้า<br><br>';
                      } else if (!partnerReceived) {
                          shipBanner.innerHTML = '📦 <strong>อีกฝ่ายจัดส่งสินค้าแล้ว!</strong><br>เลขพัสดุ: ' + partnerTracking + '<br><br>';
                          const recvBtn = document.createElement('button');
                          recvBtn.className = 'btn'; recvBtn.style.background = '#48bb78'; recvBtn.style.color = '#fff'; recvBtn.textContent = '✅ ยืนยันได้รับสินค้าแล้ว'; recvBtn.style.padding = '8px 16px';
                          recvBtn.onclick = function() {
                              if(confirm('คุณยืนยันว่าได้รับสินค้าในสภาพที่ตกลงกันไว้แล้วใช่หรือไม่?')) {
                                  const form = document.createElement('form');
                                  form.method = 'post'; form.action = '/pages/handlers/trade_submit_logistics.php';
                                  form.innerHTML = '<input type="hidden" name="action" value="receive_item"><input type="hidden" name="request_id" value="' + data.requestId + '">';
                                  document.body.append(form); form.submit();
                              }
                          };
                          shipBanner.append(recvBtn);
                      } else if (!myReceived) {
                          shipBanner.innerHTML = '✅ <strong>คุณได้รับสินค้าแล้ว</strong><br>รออีกฝ่ายกดยืนยันรับสินค้าที่คุณจัดส่งให้<br><br>';
                      } else {
                          shipBanner.innerHTML = '🎉 <strong>แลกเปลี่ยนสำเร็จ!</strong>';
                      }
                      chatHeaderBanner.append(shipBanner);
                  }
              }
              
              setTimeout(function() {
                  const chatHead = document.querySelector('.chat-head');
                  const ctxBar = document.querySelector('.chat-context-bar');
                  if (ctxBar) { ctxBar.after(chatHeaderBanner); }
                  else if (chatHead) { chatHead.after(chatHeaderBanner); }
                  else { summary.append(chatHeaderBanner); }
              }, 100);
          }
      } else if (data.tradeOption !== 'meetup' && (!data.conditionsAccepted || data.tradeOption !== 'direct_escrow')) {
          const condLink = document.createElement('button');
          condLink.className = 'exchange-request-manage';
          condLink.type = 'button';
          condLink.textContent = '📝 กรอกฟอร์มยืนยันสภาพสินค้า';
          condLink.style.background = '#e67e22'; condLink.style.color = '#fff'; condLink.style.border = 'none'; condLink.style.cursor = 'pointer'; condLink.style.display = 'block'; condLink.style.width = 'calc(100% - 28px)'; condLink.style.margin = '0 14px 14px 14px'; condLink.style.padding = '12px'; condLink.style.borderRadius = '8px'; condLink.style.fontFamily = 'inherit'; condLink.style.fontSize = '14px'; condLink.style.fontWeight = '600';
          
          condLink.onclick = function() {
              const modal = document.getElementById('conditionModal');
              const iframe = document.getElementById('conditionIframe');
              if (modal && iframe) {
                  iframe.src = '/pages/trade_condition_form.php?request_id=' + data.requestId + '&iframe=1';
                  modal.style.display = 'flex';
              } else {
                  window.location.href = '/pages/trade_condition_form.php?request_id=' + data.requestId;
              }
          };
          summary.append(condLink);
      }

      oldCard.replaceWith(summary);
JS;

$content = str_replace($target, $escrowLogic, $content);
file_put_contents($file, $content);
echo "Refactored chat.php JS completely and properly.\n";
