<?php
$args = json_decode(file_get_contents('scratch/found_action.json'), true);
$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

if (isset($args['ReplacementChunks'])) {
    foreach ($args['ReplacementChunks'] as $chunk) {
        $content = str_replace($chunk['TargetContent'], $chunk['ReplacementContent'], $content);
    }
} else {
    $content = str_replace($args['TargetContent'], $args['ReplacementContent'], $content);
}

file_put_contents($file, $content);
echo "Applied HTML action to qc_exchanges.php!\n";
