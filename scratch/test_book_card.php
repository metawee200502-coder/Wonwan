<?php
$lines = file('c:/laragon/www/wanawan_project/components/book_card.php');
for($i=350; $i<=370; $i++){
    if(isset($lines[$i])) echo $i.': '.trim($lines[$i])."\n";
}
