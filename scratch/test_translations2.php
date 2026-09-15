<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
foreach($lines as $i => $line){
    if(strpos($line, 'PROPOSE_FULL_TRADE') !== false) {
        echo $i.': '.trim($line)."\n";
    }
}
