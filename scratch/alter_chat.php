<?php
require __DIR__ . '/../config/config.php';
try {
    $pdo->exec("ALTER TABLE chat_messages ADD COLUMN msg_type VARCHAR(50) DEFAULT 'text', ADD COLUMN metadata JSON DEFAULT NULL");
    echo "chat_messages altered successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
