<?php
require 'config/config.php';
$reqs = $pdo->query("
                SELECT r.*,
                       e_req.title as req_title, e_req.image_cover as req_img,
                       e_off.title as off_title, e_off.image_cover as off_img
                FROM exchange_requests r
                LEFT JOIN exchanges e_req ON r.exchange_id = e_req.exchange_id
                LEFT JOIN exchanges e_off ON r.offered_exchange_id = e_off.exchange_id
                WHERE r.status IN ('accepted', 'completed')
                ORDER BY r.updated_at ASC
            ")->fetchAll();
echo "Number of rows: " . count($reqs) . "\n";
