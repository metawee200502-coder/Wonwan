<?php
require 'config/config.php';
$pdo->query("UPDATE orders SET delivered_at = created_at WHERE shipping_status='delivered' AND (delivered_at IS NULL OR delivered_at='')");
echo 'Done';
