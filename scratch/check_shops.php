<?php
require __DIR__.'/../config/config.php';
print_r($pdo->query("SELECT * FROM shops")->fetchAll(PDO::FETCH_ASSOC));
