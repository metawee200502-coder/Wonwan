<?php
$dir = __DIR__ . '/../admin';
$files = glob($dir . '/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    $new_content = preg_replace('/.*require_once __DIR__ \. \'\/\.\.\/components\/header\.php\'; \?>\r?\n?/', '', $content);
    if ($content !== $new_content) {
        file_put_contents($file, $new_content);
        echo "Removed header from " . basename($file) . "\n";
    }
}
