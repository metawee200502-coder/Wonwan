<?php
$c=file_get_contents('c:\laragon\www\wanawan_project\pages\chat.php');
if (strpos($c, 'window.onerror = function') === false) {
    $c=str_replace('<head>', "<head><script>window.onerror = function(msg, url, lineNo, columnNo, error) { alert('JS Error: ' + msg + ' at line ' + lineNo); return false; };</script>", $c);
    file_put_contents('c:\laragon\www\wanawan_project\pages\chat.php', $c);
    echo "Injected onerror\n";
} else {
    echo "Already injected\n";
}
