<?php
require_once __DIR__ . '/../config/config.php';
try {
    $pdo->exec('ALTER TABLE exchange_requests DROP INDEX uq_exchange_requester');
    echo 'Dropped index uq_exchange_requester\n';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . '\n';
}
