<?php
require __DIR__ . '/../config/config.php';
$pdo->exec("ALTER TABLE wallet_transactions MODIFY COLUMN type ENUM('EARNING', 'WITHDRAWAL', 'DEDUCTION', 'REFUND') NOT NULL");
echo "Altered table\n";
