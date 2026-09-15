<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
for($i=105; $i<=107; $i++){
    echo $i.': '.trim($lines[$i])."\n";
}
