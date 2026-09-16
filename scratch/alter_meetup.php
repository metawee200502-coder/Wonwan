<?php
require_once __DIR__ . '/../config/config.php';

try {
    // Add columns if they don't exist
    $pdo->exec("ALTER TABLE exchange_requests ADD COLUMN meetup_requester_received TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE exchange_requests ADD COLUMN meetup_offer_received TINYINT(1) DEFAULT 0");
    echo "Columns added successfully";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
