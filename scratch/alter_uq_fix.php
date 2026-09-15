<?php
require_once __DIR__ . '/../config/config.php';
try {
    $pdo->exec('ALTER TABLE exchange_requests ADD INDEX idx_exchange_target (exchange_id)');
    $pdo->exec('ALTER TABLE exchange_requests DROP INDEX uq_exchange_requester');
    echo 'Successfully dropped unique index and created normal index\n';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . '\n';
}
