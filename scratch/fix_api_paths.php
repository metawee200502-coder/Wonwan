<?php
$dir = new RecursiveDirectoryIterator('C:\laragon\www\wanawan_project\pages');
$ite = new RecursiveIteratorIterator($dir);
foreach($ite as $file) {
    if ($file->getExtension() === 'php') {
        $path = $file->getPathname();
        $content = file_get_contents($path);
        $newContent = str_replace("fetch('/api/", "fetch('../api/", $content);
        $newContent = str_replace('fetch("/api/', 'fetch("../api/', $newContent);
        $newContent = str_replace('fetch(`/api/', 'fetch(`../api/', $newContent);
        if ($content !== $newContent) {
            file_put_contents($path, $newContent);
            echo "Updated " . basename($path) . "\n";
        }
    }
}
