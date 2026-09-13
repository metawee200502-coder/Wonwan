<?php
require_once __DIR__ . '/../config/config.php';

try {
    $pdo->beginTransaction();

    // 1. Create vendor_bank_accounts table
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS vendor_bank_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_type ENUM('shop', 'publisher') NOT NULL,
        seller_id INT NOT NULL,
        bank_name VARCHAR(255) NOT NULL,
        account_name VARCHAR(255) NOT NULL,
        account_number VARCHAR(100) NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(seller_type, seller_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Add withdrawal_bank_info to wallet_transactions
    // Check if column exists first
    $cols = $pdo->query("SHOW COLUMNS FROM wallet_transactions LIKE 'withdrawal_bank_info'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE wallet_transactions ADD COLUMN withdrawal_bank_info TEXT DEFAULT NULL AFTER amount");
    }

    // 3. Migrate data from shops
    $shops = $pdo->query("SELECT shop_id, bank_name, bank_account_name, bank_account_number FROM shops WHERE bank_name IS NOT NULL AND bank_name != ''")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("INSERT INTO vendor_bank_accounts (seller_type, seller_id, bank_name, account_name, account_number, is_default) VALUES ('shop', ?, ?, ?, ?, 1)");
    foreach($shops as $s) {
        // Only insert if not already there
        $exists = $pdo->prepare("SELECT 1 FROM vendor_bank_accounts WHERE seller_type='shop' AND seller_id=?");
        $exists->execute([$s['shop_id']]);
        if(!$exists->fetchColumn()) {
            $stmt->execute([$s['shop_id'], $s['bank_name'], $s['bank_account_name'], $s['bank_account_number']]);
        }
    }

    // 4. Migrate data from publishers
    $publishers = $pdo->query("SELECT publisher_id, bank_name, bank_account_name, bank_account_number FROM publishers WHERE bank_name IS NOT NULL AND bank_name != ''")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("INSERT INTO vendor_bank_accounts (seller_type, seller_id, bank_name, account_name, account_number, is_default) VALUES ('publisher', ?, ?, ?, ?, 1)");
    foreach($publishers as $p) {
        // Only insert if not already there
        $exists = $pdo->prepare("SELECT 1 FROM vendor_bank_accounts WHERE seller_type='publisher' AND seller_id=?");
        $exists->execute([$p['publisher_id']]);
        if(!$exists->fetchColumn()) {
            $stmt->execute([$p['publisher_id'], $p['bank_name'], $p['bank_account_name'], $p['bank_account_number']]);
        }
    }

    $pdo->commit();
    echo "Migration completed successfully!";
} catch(Exception $e) {
    $pdo->rollBack();
    echo "Migration failed: " . $e->getMessage();
}
