<?php
$lines = file('admin/qc_exchanges.php');
foreach($lines as $i => $l) {
    if(mb_strpos($l, 'เลขพัสดุขาเข้า') !== false) {
        echo ($i+1) . ": " . trim($l) . "\n";
    }
}
