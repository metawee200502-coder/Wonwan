<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);
$content = str_replace("document.getElementById('condModal').style.display = 'block';", "document.getElementById('condModal').style.display = 'flex';", $content);
file_put_contents($file, $content);
echo "Fixed JS block to flex!";
