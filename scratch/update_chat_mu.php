<?php
$file = 'C:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$targetJs = "} else if (data.tradeOption !== 'meetup') {
          const condLink = document.createElement('button');";

$replacementJs = "} else if (data.tradeOption === 'meetup') {
          const muInfo = document.createElement('div');
          muInfo.style = 'margin-top:10px; padding:12px; background:#f0fdf4; color:#166534; border-radius:6px; font-size:13px; border:1px solid #bbf7d0;';
          muInfo.innerHTML = '📅 <strong>นัดรับสำเร็จ</strong><br>วันที่: ' + (data.meetupDate||'') + '<br>เวลา: ' + (data.meetupTime||'') + ' น.<br>สถานที่: ' + (data.meetupLocation||'');
          summary.append(muInfo);
      } else {
          const condLink = document.createElement('button');";

$content = str_replace($targetJs, $replacementJs, $content);
file_put_contents($file, $content);
echo "Updated chat.php with meetup details UI.";
