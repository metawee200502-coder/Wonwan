<?php
$lines = file('c:/laragon/www/wanawan_project/pages/book_detail.php');
foreach($lines as $i => $line){
    if(strpos($line, 'สินค้าหมด') !== false || strpos($line, 'out-of-stock') !== false || strpos($line, 'is_completed') !== false) {
        echo $i.': '.trim($line)."\n";
    }
}
