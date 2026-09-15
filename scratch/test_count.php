<?php
$c = file_get_contents('c:/laragon/www/wanawan_project/pages/chat.php');
echo "accepted: " . substr_count($c, 'data.rawStatus===\'accepted\'') . "\n";
echo "completed: " . substr_count($c, 'data.rawStatus===\'completed\'') . "\n";
