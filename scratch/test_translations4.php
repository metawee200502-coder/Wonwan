<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
foreach($lines as $i => $line){
    if(strpos($line, 'bubble') !== false && strpos($line, 'querySelectorAll') !== false) {
        echo $i.': '.trim($line)."\n";
    }
}
