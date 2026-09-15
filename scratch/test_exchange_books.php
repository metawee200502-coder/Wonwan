<?php
$lines = file('c:/laragon/www/wanawan_project/pages/exchange_books.php');
for($i=150; $i<=170; $i++){
    if(isset($lines[$i])) echo $i.': '.trim($lines[$i])."\n";
}
