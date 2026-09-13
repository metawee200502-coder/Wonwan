<?php
require __DIR__ . '/../config/config.php';

// Recalculate vendor_wallets available_balance based on wallet_transactions
$wallets = $pdo->query("SELECT wallet_id FROM vendor_wallets")->fetchAll(PDO::FETCH_ASSOC);
foreach ($wallets as $w) {
    $wallet_id = $w['wallet_id'];
    
    // Sum EARNINGs
    $earnStmt = $pdo->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE wallet_id=? AND type='EARNING' AND status='SUCCESS'");
    $earnStmt->execute([$wallet_id]);
    $earnings = (float)$earnStmt->fetchColumn();
    
    // Sum WITHDRAWALs
    $withStmt = $pdo->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE wallet_id=? AND type='WITHDRAWAL' AND status='SUCCESS'");
    $withStmt->execute([$wallet_id]);
    $withdrawals = (float)$withStmt->fetchColumn();
    
    // Sum REFUNDs or DEDUCTIONs
    $deductStmt = $pdo->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE wallet_id=? AND type IN ('REFUND', 'DEDUCTION') AND status='SUCCESS'");
    $deductStmt->execute([$wallet_id]);
    $deductions = (float)$deductStmt->fetchColumn();
    
    $true_balance = $earnings - $withdrawals - $deductions;
    
    $pdo->prepare("UPDATE vendor_wallets SET available_balance=? WHERE wallet_id=?")->execute([$true_balance, $wallet_id]);
}

echo "Balances synced!";
