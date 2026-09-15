<?php
$file = 'C:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

// 1. Add handlers before delete_conversation
$handlers = "
if(\$_SERVER['REQUEST_METHOD']==='POST'&&isset(\$_POST['propose_full_trade'])){
    if(!hash_equals(\$_SESSION['chat_csrf'],\$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
    \$opt = \$_POST['trade_option'];
    \$deadline = \$_POST['deadline_days'] ?? '';
    \$meetupDate = trim(\$_POST['meetup_date'] ?? '');
    \$meetupTime = trim(\$_POST['meetup_time'] ?? '');
    \$meetupLocation = trim(\$_POST['meetup_location'] ?? '');
    
    if(\$opt) {
        if (\$opt === 'meetup' && \$meetupDate && \$meetupTime && \$meetupLocation) {
            \$pdo->prepare('UPDATE exchange_requests SET meetup_date=?, meetup_time=?, meetup_location=? WHERE request_id=? AND status=\"accepted\"')->execute([\$meetupDate, \$meetupTime, \$meetupLocation, (int)\$_POST['request_id']]);
            \$msg = '[PROPOSE_FULL_TRADE:meetup:'.\$meetupDate.':'.\$meetupTime.':'.\$meetupLocation.']';
        } else {
            \$msg = '[PROPOSE_FULL_TRADE:'.\$opt.(\$deadline ? ':'.\$deadline : '').']';
        }
        \$pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([\$conversationId,\$userId,\$msg]);
        \$pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([\$conversationId]);
    }
    header('Location:/pages/chat.php?conversation='.\$conversationId);exit;
}
if(\$_SERVER['REQUEST_METHOD']==='POST'&&isset(\$_POST['accept_full_trade'])){
 if(!hash_equals(\$_SESSION['chat_csrf'],\$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
 \$opt = \$_POST['trade_option'];
 \$deadline = \$_POST['deadline_days'] ?? '';
 \$meetupDate = \$_POST['meetup_date'] ?? '';
 \$meetupTime = \$_POST['meetup_time'] ?? '';
 \$meetupLoc = \$_POST['meetup_location'] ?? '';
 
 if(\$opt === 'meetup') {
     \$pdo->prepare('UPDATE exchange_requests SET trade_option=? WHERE request_id=? AND status=\"accepted\"')->execute([\$opt, (int)\$_POST['request_id']]);
     \$msg = '[ACCEPTED_FULL_TRADE:meetup:'.\$meetupDate.':'.\$meetupTime.':'.\$meetupLoc.']';
 } else if(\$deadline) {
     \$pdo->prepare('UPDATE exchange_requests SET trade_option=?, shipping_deadline_days=? WHERE request_id=? AND status=\"accepted\"')->execute([\$opt, (int)\$deadline, (int)\$_POST['request_id']]);
     \$msg = '[ACCEPTED_FULL_TRADE:'.\$opt.(\$deadline ? ':'.\$deadline : '').']';
 } else {
     \$pdo->prepare('UPDATE exchange_requests SET trade_option=? WHERE request_id=? AND status=\"accepted\"')->execute([\$opt, (int)\$_POST['request_id']]);
     \$msg = '[ACCEPTED_FULL_TRADE:'.\$opt.']';
 }
 
 \$pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([\$conversationId,\$userId,\$msg]);
 \$pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([\$conversationId]);
 header('Location:/pages/chat.php?conversation='.\$conversationId);exit;
}
if(\$_SERVER['REQUEST_METHOD']==='POST'&&isset(\$_POST['reject_full_trade'])){
 if(!hash_equals(\$_SESSION['chat_csrf'],\$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
 \$pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([\$conversationId,\$userId,'[REJECTED_FULL_TRADE]']);
 \$pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([\$conversationId]);
 header('Location:/pages/chat.php?conversation='.\$conversationId);exit;
}
if(\$_SERVER['REQUEST_METHOD']==='POST'&&isset(\$_POST['accept_condition_chat'])){
    if(!hash_equals(\$_SESSION['chat_csrf'],\$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
    \$formId = (int)\$_POST['accept_condition_form_id'];
    \$reqId = (int)\$_POST['request_id'];
    
    \$pdo->prepare(\"UPDATE trade_condition_forms SET is_accepted_by_partner=1, accepted_at=NOW() WHERE form_id=?\")->execute([\$formId]);
    
    \$pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([\$conversationId,\$userId,'[CONDITION_FORM_ACCEPTED:'.\$userId.']']);
    \$pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([\$conversationId]);
    
    \$checkStmt = \$pdo->prepare(\"SELECT COUNT(*) FROM trade_condition_forms WHERE request_id=? AND is_accepted_by_partner=1\");
    \$checkStmt->execute([\$reqId]);
    if (\$checkStmt->fetchColumn() >= 2) {
        \$pdo->prepare(\"UPDATE exchange_requests SET status='accepted' WHERE request_id=?\")->execute([\$reqId]);
    }
    header('Location:/pages/chat.php?conversation='.\$conversationId);exit;
}
if(\$_SERVER['REQUEST_METHOD']==='POST'&&isset(\$_POST['reject_condition_chat'])){
    if(!hash_equals(\$_SESSION['chat_csrf'],\$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
    \$formId = (int)\$_POST['accept_condition_form_id'];
    \$pdo->prepare(\"DELETE FROM trade_condition_forms WHERE form_id=?\")->execute([\$formId]);
    \$pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([\$conversationId,\$userId,'[CONDITION_FORM_REJECTED:'.\$userId.']']);
    \$pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([\$conversationId]);
    header('Location:/pages/chat.php?conversation='.\$conversationId);exit;
}
";

if (strpos($content, 'propose_full_trade') === false) {
    $content = str_replace("if(\$_SERVER['REQUEST_METHOD']==='POST'&&isset(\$_POST['delete_conversation'])){", $handlers . "if(\$_SERVER['REQUEST_METHOD']==='POST'&&isset(\$_POST['delete_conversation'])){", $content);
}


// 2. Replace summary.append in JS
$jsTarget = "summary.append(status,pair,message,manage);oldCard.replaceWith(summary);";
$jsReplacement = "summary.append(status,pair,message,manage);
 
 if (data.rawStatus === 'accepted') {
    if (!data.tradeOption) {
      const propForm = document.createElement('form');
      propForm.method = 'post';
      propForm.style.marginTop = '15px';
      propForm.style.padding = '0 16px 16px 16px';
      
      let html = '<input type=\"hidden\" name=\"csrf_token\" value=\"<?=htmlspecialchars(\$_SESSION['chat_csrf'])?>\">';
      html += '<input type=\"hidden\" name=\"request_id\" value=\"'+data.requestId+'\">';
      html += '<label style=\"font-size:12px; font-weight:600; color:#8b5266; margin-bottom:4px; display:block;\">เลือกรูปแบบการแลกเปลี่ยน</label>';
      html += '<select name=\"trade_option\" id=\"tradeOptionSelect\" style=\"box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;margin-bottom:10px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;box-shadow:0 2px 4px rgba(169,85,117,0.03);transition:border 0.2s;\" onfocus=\"this.style.borderColor=\'#d18aa7\'\" onblur=\"this.style.borderColor=\'#f2dbe4\'\" required>';
      html += '<option value=\"\" disabled selected>-- กรุณาเลือก --</option>';
      html += '<option value=\"direct_escrow\">ส่งตรงให้กัน (P2P Escrow)</option>';
      html += '<option value=\"admin_qc\">ส่งผ่านส่วนกลาง (Admin QC)</option>';
      html += '<option value=\"meetup\">นัดรับ (Meet-up)</option>';
      html += '</select>';
      
      html += '<div id=\"deadlineGroup\" style=\"display:none; box-sizing:border-box; background:#fdf5f7; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #f2dbe4;\">';
      html += '<label style=\"font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;\">ระยะเวลาจัดส่ง (วัน)</label>';
      html += '<select name=\"deadline_days\" style=\"box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;box-shadow:0 2px 4px rgba(169,85,117,0.03);transition:border 0.2s;\" onfocus=\"this.style.borderColor=\'#d18aa7\'\" onblur=\"this.style.borderColor=\'#f2dbe4\'\">';
      html += '<option value=\"3\">ภายใน 3 วัน</option>';
      html += '<option value=\"5\">ภายใน 5 วัน</option>';
      html += '<option value=\"7\">ภายใน 7 วัน</option>';
      html += '</select>';
      html += '</div>';

      html += '<div id=\"meetupGroup\" style=\"display:none; box-sizing:border-box; background:#fdf5f7; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #f2dbe4;\">';
      html += '<div style=\"display:flex;gap:8px;margin-bottom:8px;\">';
      html += '<div style=\"flex:1;\"><label style=\"font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;\">วันที่</label><input type=\"date\" name=\"meetup_date\" style=\"box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;\" min=\"<?=date(\"Y-m-d\")?>\"></div>';
      html += '<div style=\"flex:1;\"><label style=\"font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;\">เวลา</label><input type=\"time\" name=\"meetup_time\" style=\"box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;\"></div>';
      html += '</div>';
      html += '<label style=\"font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;\">สถานที่นัดรับ</label>';
      html += '<input type=\"text\" name=\"meetup_location\" placeholder=\"ระบุสถานที่นัดพบ...\" style=\"box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;\">';
      html += '</div>';
      
      html += '<button name=\"propose_full_trade\" class=\"btn primary\" style=\"box-sizing:border-box;width:100%;font-size:13px;background:linear-gradient(135deg, #d18aa7, #b66f68);border:0;color:#fff;font-weight:600;padding:10px;border-radius:6px;cursor:pointer;box-shadow:0 4px 10px rgba(209,138,167,0.3);transition:transform 0.2s;font-family:inherit;\" onmouseover=\"this.style.transform=\'scale(1.02)\'\" onmouseout=\"this.style.transform=\'scale(1)\'\">ยื่นข้อเสนอ</button>';
      
      propForm.innerHTML = html;
      summary.append(propForm);
      
      const optSelect = propForm.querySelector('#tradeOptionSelect');
      const dlGroup = propForm.querySelector('#deadlineGroup');
      const muGroup = propForm.querySelector('#meetupGroup');
      optSelect.addEventListener('change', function() {
          if(this.value === 'direct_escrow' || this.value === 'admin_qc') {
              dlGroup.style.display = 'block';
              muGroup.style.display = 'none';
          } else if(this.value === 'meetup') {
              dlGroup.style.display = 'none';
              muGroup.style.display = 'block';
          } else {
              dlGroup.style.display = 'none';
              muGroup.style.display = 'none';
          }
      });
      
    } else {
      const needsDeadline = (data.tradeOption === 'direct_escrow' || data.tradeOption === 'admin_qc');
      const hasDeadline = data.shippingDeadline > 0;
      
      if (needsDeadline && !hasDeadline) {
          const waitMsg = document.createElement('div');
          waitMsg.style = 'margin-top:10px; padding:10px; background:#fff3cd; color:#856404; border-radius:5px; font-size:13px; text-align:center; border:1px solid #ffeeba;';
          waitMsg.innerHTML = '⏳ <strong>รอข้อสรุปวันจัดส่ง</strong><br>กรุณาตกลงวันจัดส่งในแชทให้เรียบร้อยก่อนดำเนินการต่อ';
          summary.append(waitMsg);
      } else if (data.tradeOption !== 'meetup') {
          const condLink = document.createElement('button');
          condLink.className = 'exchange-request-manage';
          condLink.type = 'button';
          condLink.textContent = '📝 กรอกฟอร์มยืนยันสภาพสินค้า';
          condLink.style.background = '#e67e22';
          condLink.style.color = '#fff';
          condLink.style.border = 'none';
          condLink.style.cursor = 'pointer';
          condLink.style.display = 'block';
          condLink.style.width = '100%';
          condLink.style.marginTop = '15px';
          condLink.style.padding = '12px';
          condLink.style.borderRadius = '8px';
          condLink.style.fontFamily = 'inherit';
          condLink.style.fontSize = '14px';
          condLink.style.fontWeight = '600';
          
          condLink.onclick = function() {
              const modal = document.getElementById('conditionModal');
              const iframe = document.getElementById('conditionIframe');
              iframe.src = '/pages/trade_condition_form.php?request_id=' + data.requestId + '&iframe=1&conversation_id=<?= \$conversationId ?>';
              modal.style.display = 'flex';
          };
          summary.append(condLink);
      }
    }
 }

 oldCard.replaceWith(summary);";

if (strpos($content, "tradeOptionSelect") === false) {
    $content = str_replace($jsTarget, $jsReplacement, $content);
}

// 3. Add modal HTML at the end
$modalHtml = "
    // Listen for close modal message from iframe
    window.addEventListener('message', function(e) {
        if (e.data && e.data.action === 'close_condition_modal') {
            closeConditionModal();
        }
    });
});
</script>

<!-- Condition Form Modal -->
<div id=\"conditionModal\" style=\"display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);\">
    <div style=\"background:#fff; width:95%; max-width:900px; height:90%; border-radius:16px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 10px 30px rgba(0,0,0,0.2);\">
        <div style=\"padding:15px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#fdf5f7;\">
            <h3 style=\"margin:0; color:#8b5266; font-size:16px;\">กรอกฟอร์มยืนยันสภาพสินค้า</h3>
            <button type=\"button\" onclick=\"closeConditionModal()\" style=\"background:transparent; border:none; font-size:24px; color:#8b5266; cursor:pointer; line-height:1;\">&times;</button>
        </div>
        <div style=\"flex:1; position:relative;\">
            <iframe id=\"conditionIframe\" src=\"\" style=\"width:100%; height:100%; border:none;\"></iframe>
        </div>
    </div>
</div>

<script>
function closeConditionModal() {
    document.getElementById('conditionModal').style.display = 'none';
    document.getElementById('conditionIframe').src = '';
}
</script>

</body></html>";

if (strpos($content, "conditionModal") === false) {
    $content = str_replace("});\n</script>\n</body></html>", $modalHtml, $content);
}

file_put_contents($file, $content);
echo "Restored and updated chat.php";
