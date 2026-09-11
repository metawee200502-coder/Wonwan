<?php
require 'config/config.php';
$pubs = $pdo->query('SELECT company_name, profile_image, company_document_url FROM publishers ORDER BY publisher_id DESC LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
$shops = $pdo->query('SELECT shop_name, profile_image FROM shops ORDER BY shop_id DESC LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(['publishers' => $pubs, 'shops' => $shops]);
