<?php
require __DIR__ . '/../config/config.php';

try {
    $pdo->exec("ALTER TABLE exchange_requests 
        ADD COLUMN meetup_date DATE DEFAULT NULL AFTER shipping_deadline_days,
        ADD COLUMN meetup_time TIME DEFAULT NULL AFTER meetup_date,
        ADD COLUMN meetup_location TEXT DEFAULT NULL AFTER meetup_time
    ");
    echo "Columns added successfully!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
