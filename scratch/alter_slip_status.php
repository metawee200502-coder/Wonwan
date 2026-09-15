<?php
require 'config/config.php';

$pdo->exec("ALTER TABLE exchange_requests 
ADD COLUMN requester_slip_status ENUM('pending', 'approved', 'rejected') NULL DEFAULT NULL AFTER requester_slip_url,
ADD COLUMN offer_slip_status ENUM('pending', 'approved', 'rejected') NULL DEFAULT NULL AFTER offer_slip_url");

echo "Database altered successfully.\n";
