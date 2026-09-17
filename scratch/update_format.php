<?php
require __DIR__ . '/../config/config.php';
$files = [
    'pages/exchange_requests.php',
    'pages/trade_condition_form.php',
    'admin/qc_exchanges.php',
    'pages/user_wallet.php',
    'pages/seller_wallet.php'
];

foreach ($files as $f) {
    $path = __DIR__ . '/../' . $f;
    if (!file_exists($path)) continue;
    
    $c = file_get_contents($path);
    
    // Replace in exchange_requests.php
    $c = str_replace(
        '#EX-<?=date(\'Y-md\', strtotime($request[\'created_at\']))?>',
        '<?=formatExchangeOrderId($request[\'request_id\'])?>',
        $c
    );
    
    // Replace in admin/qc_exchanges.php
    $c = str_replace(
        '#EX-<?= $r[\'request_id\'] ?>',
        '<?=formatExchangeOrderId($r[\'request_id\'])?>',
        $c
    );
    
    // Replace in trade_condition_form.php
    $c = str_replace(
        '#<?= $requestId ?>',
        '<?=formatExchangeOrderId($requestId)?>',
        $c
    );
    
    // Replace in wallet pages
    $find = 'htmlspecialchars($tx[\'order_number\'] ?? $tx[\'order_id\'] ?? \'\')';
    $replace = 'strpos($tx[\'order_number\'] ?? \'\', \'EXC-FEE-\') === 0 ? formatExchangeOrderId((int)explode(\'-\', $tx[\'order_number\'])[2]) : htmlspecialchars($tx[\'order_number\'] ?? $tx[\'order_id\'] ?? \'\')';
    $c = str_replace($find, $replace, $c);
    
    // In case the old one without ?? '' is there
    $find2 = 'htmlspecialchars($tx[\'order_number\'] ?? $tx[\'order_id\'])';
    $replace2 = 'strpos($tx[\'order_number\'] ?? \'\', \'EXC-FEE-\') === 0 ? formatExchangeOrderId((int)explode(\'-\', $tx[\'order_number\'])[2]) : htmlspecialchars($tx[\'order_number\'] ?? $tx[\'order_id\'] ?? \'\')';
    $c = str_replace($find2, $replace2, $c);

    file_put_contents($path, $c);
    echo $f . " updated.\n";
}
