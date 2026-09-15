<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
for($i=271; $i<=320; $i++){
    echo $i.': '.trim($lines[$i] ?? '')."\n";
}
