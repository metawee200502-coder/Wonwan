<?php
$content = file_get_contents('c:/laragon/www/wanawan_project/scratch/recovered_qc_exchanges.php');
// Change default tab
$content = str_replace("\$tab = \$_GET['tab'] ?? 'posts';", "\$tab = \$_GET['tab'] ?? 'physical';", $content);
file_put_contents('c:/laragon/www/wanawan_project/admin/qc_exchanges.php', $content);
echo "Restored admin/qc_exchanges.php from transcript successfully!\n";
