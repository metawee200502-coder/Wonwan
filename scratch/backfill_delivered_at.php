<?php
require_once __DIR__ . '/../config/config.php';
$pdo->exec("UPDATE orders SET delivered_at=updated_at WHERE shipping_status='delivered' AND delivered_at IS NULL");
echo "Done backfilling delivered_at.";
