<?php
$file = 'c:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$oldStr = "if(data.rawStatus==='accepted'){";
$newStr = "if(data.rawStatus==='accepted' || data.rawStatus==='completed'){";

$content = str_replace($oldStr, $newStr, $content);
file_put_contents($file, $content);
echo "Successfully patched chat.php to allow banner in completed status.\n";
