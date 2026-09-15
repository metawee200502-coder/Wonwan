<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
for($i=550; $i<=640; $i++){
    if(isset($lines[$i]) && strpos($lines[$i], 'P2P_') !== false) {
        echo $i.': '.trim($lines[$i])."\n";
    }
}
