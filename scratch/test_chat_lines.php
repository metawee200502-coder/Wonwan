<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
for($i=165; $i<=175; $i++){
    echo $i.': '.trim($lines[$i])."\n";
}
