<?php
$c=file_get_contents('c:\laragon\www\wanawan_project\pages\chat.php');
$c=preg_replace('/document\.addEventListener\(\'DOMContentLoaded\',\s*\(\)\s*=>\s*\{\s*document\.addEventListener\(\'DOMContentLoaded\',\s*\(\)\s*=>\s*\{/', "document.addEventListener('DOMContentLoaded', () => {", $c);
file_put_contents('c:\laragon\www\wanawan_project\pages\chat.php', $c);
echo "Fixed duplicate DOMContentLoaded\n";
