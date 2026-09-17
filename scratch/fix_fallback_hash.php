<?php
$files = [
    'c:\\laragon\\www\\wanawan_project\\pages\\user_wallet.php',
    'c:\\laragon\\www\\wanawan_project\\pages\\seller_wallet.php'
];

foreach ($files as $f) {
    if (!file_exists($f)) continue;
    $c = file_get_contents($f);
    
    // We already removed # from HTML, so we need to add '#' to the fallback case
    $find = ': htmlspecialchars($tx[\'order_number\'] ?? $tx[\'order_id\'] ?? \'\')';
    $replace = ': \'#\' . htmlspecialchars($tx[\'order_number\'] ?? $tx[\'order_id\'] ?? \'\')';
    $c = str_replace($find, $replace, $c);
    
    file_put_contents($f, $c);
    echo $f . " fixed fallback.\n";
}
