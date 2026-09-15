<?php
require_once __DIR__ . '/../config/config.php';
print_r($pdo->query('SELECT display_name, email FROM users WHERE user_id = 56')->fetch(PDO::FETCH_ASSOC));
