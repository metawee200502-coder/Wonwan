<?php
require __DIR__ . '/../config/config.php';
$pdo->query("UPDATE wallet_transactions SET type='REFUND' WHERE type='EARNING' AND amount=100");
$pdo->query("UPDATE wallet_transactions SET type='REFUND' WHERE type='EARNING' AND amount=95");
echo "Updated to REFUND";
