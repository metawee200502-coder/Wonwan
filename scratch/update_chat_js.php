<?php
$file = 'C:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

// 1. Update propose_full_trade backend logic
$targetPhp = "    if(!hash_equals(\$_SESSION['chat_csrf'],\$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
    \$opt = \$_POST['trade_option'];
    \$deadline = \$_POST['deadline_days'] ?? '';
    if(\$opt) {
        \$msg = '[PROPOSE_FULL_TRADE:'.\$opt.(\$deadline ? ':'.\$deadline : '').']';
        \$pdo->prepare('INSERT INTO chat_messages(conversation_id,sender_user_id,message_text) VALUES(?,?,?)')->execute([\$conversationId,\$userId,\$msg]);
        \$pdo->prepare('UPDATE chat_conversations SET last_message_at=NOW() WHERE conversation_id=?')->execute([\$conversationId]);
    }";

$replacementPhp = "    if(!hash_equals(\$_SESSION['chat_csrf'],\$_POST['csrf_token']??''))exit('คำขอไม่ถูกต้อง');
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
    }";

$content = str_replace($targetPhp, $replacementPhp, $content);


// 2. Update JS to add meetup inputs
$targetJs = "      html += '</select>';
      html += '</div>';";

$replacementJs = "      html += '</select>';
      html += '</div>';

      html += '<div id=\"meetupGroup\" style=\"display:none; box-sizing:border-box; background:#fdf5f7; padding:10px; border-radius:6px; margin-bottom:10px; border:1px solid #f2dbe4;\">';
      html += '<div style=\"display:flex;gap:8px;margin-bottom:8px;\">';
      html += '<div style=\"flex:1;\"><label style=\"font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;\">วันที่</label><input type=\"date\" name=\"meetup_date\" style=\"box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;\" min=\"<?=date(\"Y-m-d\")?>\"></div>';
      html += '<div style=\"flex:1;\"><label style=\"font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;\">เวลา</label><input type=\"time\" name=\"meetup_time\" style=\"box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;\"></div>';
      html += '</div>';
      html += '<label style=\"font-size:12px; font-weight:600; color:#8b5266; display:block; margin-bottom:4px;\">สถานที่นัดรับ</label>';
      html += '<input type=\"text\" name=\"meetup_location\" placeholder=\"ระบุสถานที่นัดพบ...\" style=\"box-sizing:border-box;width:100%;padding:8px 10px;border:1px solid #f2dbe4;border-radius:6px;font-size:13px;color:#6b3c4a;background:#fff;outline:none;font-family:inherit;\">';
      html += '</div>';";

$content = str_replace($targetJs, $replacementJs, $content);

$targetJs2 = "      const dlGroup = propForm.querySelector('#deadlineGroup');
      optSelect.addEventListener('change', function() {
          if(this.value === 'direct_escrow' || this.value === 'admin_qc') {
              dlGroup.style.display = 'block';
          } else {
              dlGroup.style.display = 'none';
          }
      });";

$replacementJs2 = "      const dlGroup = propForm.querySelector('#deadlineGroup');
      const muGroup = propForm.querySelector('#meetupGroup');
      optSelect.addEventListener('change', function() {
          if(this.value === 'direct_escrow' || this.value === 'admin_qc') {
              dlGroup.style.display = 'block';
              if(muGroup) muGroup.style.display = 'none';
          } else if (this.value === 'meetup') {
              dlGroup.style.display = 'none';
              if(muGroup) muGroup.style.display = 'block';
          } else {
              dlGroup.style.display = 'none';
              if(muGroup) muGroup.style.display = 'none';
          }
      });";

$content = str_replace($targetJs2, $replacementJs2, $content);

file_put_contents($file, $content);
echo "Updated chat.php successfully.";
