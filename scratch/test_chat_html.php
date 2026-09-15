<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
for($i=450; $i<=550; $i++){
    echo $i.': '.trim($lines[$i] ?? '')."\n";
}
