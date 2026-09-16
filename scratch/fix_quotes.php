<?php
$content = file_get_contents('C:/laragon/www/wanawan_project/pages/chat.php');
$content = str_replace("\'", "'", $content);
file_put_contents('C:/laragon/www/wanawan_project/pages/chat.php', $content);
echo "Fixed JS quotes\n";
?>
