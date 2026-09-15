<?php
require __DIR__ . '/../config/config.php';
$file = file_get_contents(__DIR__ . '/../pages/chat.php');
if (strpos($file, 'conditionsAccepted') !== false) {
    echo "conditionsAccepted is present in chat.php.\n";
} else {
    echo "conditionsAccepted is MISSING from chat.php.\n";
}

preg_match('/\$conditionsAccepted = (.*?);/', $file, $matches);
if (!empty($matches)) {
    echo "Code for conditionsAccepted: " . $matches[1] . "\n";
}

preg_match('/\'conditionsAccepted\'=>(.*?),/', $file, $matches);
if (!empty($matches)) {
    echo "Array assignment: " . $matches[1] . "\n";
}
