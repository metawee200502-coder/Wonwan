<?php
require __DIR__ . '/../config/config.php';

try {
    $pdo->exec("ALTER TABLE exchange_requests 
        ADD COLUMN requester_slip_url VARCHAR(255) NULL AFTER meetup_location,
        ADD COLUMN offer_slip_url VARCHAR(255) NULL AFTER requester_slip_url,
        ADD COLUMN requester_address TEXT NULL AFTER offer_slip_url,
        ADD COLUMN offer_address TEXT NULL AFTER requester_address
    ");
    echo "Columns added successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
