<?php
$lines = file('c:/laragon/www/wanawan_project/pages/chat.php');
for($i=540; $i<=555; $i++){
    if(isset($lines[$i])) echo $i.': '.trim($lines[$i])."\n";
}
