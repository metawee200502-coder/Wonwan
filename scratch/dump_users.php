<?php
require_once __DIR__ . '/../config/config.php';
$output = [];
$output['schema'] = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);

try {
    $output['roles'] = $pdo->query("SELECT role, COUNT(*) FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $output['roles_error'] = $e->getMessage();
}

try {
    $output['count_all'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $output['count_all_error'] = $e->getMessage();
}

file_put_contents(__DIR__ . '/db_info.txt', print_r($output, true));
echo "Done";
