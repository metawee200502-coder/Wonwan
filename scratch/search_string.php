<?php
$dir = new RecursiveDirectoryIterator('c:/laragon/www/wanawan_project');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$matches = [];
foreach($files as $file) {
    $content = file_get_contents($file[0]);
    if (strpos($content, 'อยู่ระหว่างดำเนินการ') !== false || strpos($content, '100') !== false && strpos($content, 'กระเป๋าบริษัท') !== false) {
        $matches[] = $file[0];
    }
}
print_r($matches);
