<?php
$lines = file('c:/laragon/www/wanawan_project/config/config.php');
for($i=0; $i<=20; $i++){
    if(isset($lines[$i])) echo $i.': '.trim($lines[$i])."\n";
}
