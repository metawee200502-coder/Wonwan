<?php
/**
 * Run Migration v2 — return_refund
 * รัน script นี้ครั้งเดียวจาก browser หรือ CLI
 * URL: http://localhost/wanawan_project/database/run_migration_v2.php
 */
session_start();
// ป้องกัน — ต้อง login เป็น super_admin เท่านั้น
require_once __DIR__ . '/../config/config.php';
if (($_SESSION['role'] ?? '') !== 'super_admin') {
    die('Access denied');
}

$steps = [];
$errors = [];

function runSQL(PDO $pdo, string $label, string $sql): void {
    global $steps, $errors;
    try {
        $pdo->exec($sql);
        $steps[] = "✅ $label";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            $steps[] = "⏭️  $label (คอลัมน์มีอยู่แล้ว — ข้าม)";
        } elseif (str_contains($e->getMessage(), 'already exists')) {
            $steps[] = "⏭️  $label (มีอยู่แล้ว — ข้าม)";
        } else {
            $errors[] = "❌ $label — " . $e->getMessage();
        }
    }
}

// ── 1. Add new columns ──
$newCols = [
    'super_admin_reviewed_by' => "INT UNSIGNED NULL COMMENT 'user_id ของ super_admin'",
    'super_admin_reviewed_at' => "DATETIME NULL",
    'shop_received_at'        => "DATETIME NULL",
    'shop_received_by'        => "INT UNSIGNED NULL",
    'refunded_at'             => "DATETIME NULL",
    'refunded_by'             => "INT UNSIGNED NULL",
    'refund_note'             => "TEXT NULL",
];

$existCols = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'return_requests'"
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($newCols as $col => $def) {
    if (in_array($col, $existCols, true)) {
        $steps[] = "⏭️  Column `$col` (มีอยู่แล้ว — ข้าม)";
    } else {
        runSQL($pdo, "Add column `$col`", "ALTER TABLE return_requests ADD COLUMN `$col` $def");
    }
}

// ── 2. user_wallets ──
runSQL($pdo, 'Create user_wallets', "
    CREATE TABLE IF NOT EXISTS user_wallets (
        wallet_id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     INT UNSIGNED NOT NULL,
        balance     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── 3. user_wallet_transactions ──
runSQL($pdo, 'Create user_wallet_transactions', "
    CREATE TABLE IF NOT EXISTS user_wallet_transactions (
        txn_id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        wallet_id   INT UNSIGNED NOT NULL,
        return_id   INT UNSIGNED NULL,
        order_id    INT UNSIGNED NULL,
        type        ENUM('REFUND','TOPUP','WITHDRAWAL','PURCHASE') NOT NULL,
        amount      DECIMAL(12,2) NOT NULL,
        note        TEXT NULL,
        created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_wallet (wallet_id),
        INDEX idx_order  (order_id),
        INDEX idx_return (return_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── 4. return_id column in user_wallet_transactions ──
$txnCols = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_wallet_transactions'"
)->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('return_id', $txnCols, true)) {
    runSQL($pdo, 'Add return_id to user_wallet_transactions',
        "ALTER TABLE user_wallet_transactions ADD COLUMN return_id INT UNSIGNED NULL AFTER wallet_id");
}

// ── 5. note column in wallet_transactions (vendor) ──
$vtCols = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wallet_transactions'"
)->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('note', $vtCols, true)) {
    runSQL($pdo, 'Add note to wallet_transactions',
        "ALTER TABLE wallet_transactions ADD COLUMN note TEXT NULL");
}

// ── 6. Index ──
$idxExists = (bool)$pdo->query(
    "SELECT 1 FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'return_requests'
       AND INDEX_NAME = 'idx_status_seller'"
)->fetchColumn();

if (!$idxExists) {
    runSQL($pdo, 'Add index idx_status_seller',
        "ALTER TABLE return_requests ADD INDEX idx_status_seller (status, seller_type, seller_id)");
}

// ── Final Verify ──
$finalStatus = $pdo->query("SELECT DISTINCT status FROM return_requests")->fetchAll(PDO::FETCH_COLUMN);
$steps[] = "📊 Status values: " . implode(', ', $finalStatus ?: ['(no rows)']);

$finalCols = $pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'return_requests'
     ORDER BY ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_COLUMN);
$steps[] = "📋 return_requests columns: " . implode(', ', $finalCols);

?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Migration v2 Result</title>
<style>
body { font-family: monospace; background: #0f0f1a; color: #e2e8f0; padding: 32px; font-size: 14px; }
h2 { color: #a78bfa; margin-bottom: 20px; }
.step { padding: 6px 0; border-bottom: 1px solid #1e1e2e; }
.error { color: #f87171; }
.footer { margin-top: 24px; color: #64748b; font-size: 12px; }
</style>
</head>
<body>
<h2>🚀 Migration v2 — Return & Refund System</h2>
<?php foreach ($steps as $s): ?>
<div class="step"><?= htmlspecialchars($s) ?></div>
<?php endforeach; ?>
<?php if ($errors): ?>
<h3 style="color:#f87171;margin-top:16px">Errors:</h3>
<?php foreach ($errors as $e): ?>
<div class="step error"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>
<?php else: ?>
<div style="color:#34d399;margin-top:20px;font-size:16px">✅ Migration completed successfully — ไม่มี errors</div>
<?php endif; ?>
<div class="footer">
    <?= date('Y-m-d H:i:s') ?> — <a href="/admin/returns.php" style="color:#a78bfa">ไปที่ Super Admin Returns Dashboard</a>
</div>
</body>
</html>
