<?php
require 'config/config.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->exec("ALTER TABLE exchange_requests ADD COLUMN requester_slip_status ENUM('pending', 'approved', 'rejected') NULL DEFAULT NULL AFTER requester_slip_url");
    echo "Added requester_slip_status\n";
} catch (Exception $e) {
    echo "Error 1: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE exchange_requests ADD COLUMN offer_slip_status ENUM('pending', 'approved', 'rejected') NULL DEFAULT NULL AFTER offer_slip_url");
    echo "Added offer_slip_status\n";
} catch (Exception $e) {
    echo "Error 2: " . $e->getMessage() . "\n";
}
