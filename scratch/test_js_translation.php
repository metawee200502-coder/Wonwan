<?php
$lines = file('c:/laragon/www/wanawan_project/assets/js/chat-read-status.js');
foreach($lines as $i => $line){
    if(strpos($line, 'PROPOSE') !== false || strpos($line, 'P2P_') !== false || strpos($line, 'bubble') !== false) {
        echo $i.': '.trim($line)."\n";
    }
}
