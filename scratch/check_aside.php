<?php
$html = file_get_contents(__DIR__ . '/chat_output.html');
$start = strpos($html, '<aside class="product">');
if ($start !== false) {
    $end = strpos($html, '</aside>', $start);
    echo substr($html, $start, $end - $start + 8);
}
