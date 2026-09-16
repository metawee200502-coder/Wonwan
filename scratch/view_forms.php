<?php
require_once __DIR__ . '/../config/config.php';
$q = $pdo->query("SELECT form_id, request_id, owner_id, required_deposit_amount, deposit_status FROM trade_condition_forms");
print_r($q->fetchAll(PDO::FETCH_ASSOC));
