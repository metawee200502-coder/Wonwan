<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
for($i=215; $i<=270; $i++){
    echo $i.': '.trim($lines[$i] ?? '')."\n";
}
