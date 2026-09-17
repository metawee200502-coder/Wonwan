<?php
$file = 'c:/laragon/www/wanawan_project/pages/exchange_requests.php';
$c = file_get_contents($file);

$c = str_replace(
    '<?php if(trim($request[\'message\'])): ?>',
    '<?php if(trim($request[\'message\'] ?? \'\')): ?>',
    $c
);

file_put_contents($file, $c);
echo "Patched trim(null) warning.\n";
