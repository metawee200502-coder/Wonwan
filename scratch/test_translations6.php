<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
for($i=550; $i<=650; $i++){
    if(isset($lines[$i])) echo $i.': '.trim($lines[$i])."\n";
}
