<?php
$file = __DIR__ . '/../pages/admin_returns.php';
$code = file_get_contents($file);

// Replace UI Color adjustments back to pink theme (or matching sidebar #C97B8B)
$code = str_replace('body { font-family: var(--font-family); background: #f1f5f9; color: #1a1a2e; display: flex; min-height: 100vh; }', 'body { font-family: var(--font-family); background: #fdf8f9; color: #1a1a2e; display: flex; min-height: 100vh; }' . "\n" . '        :root { --secondary-color: #C97B8B; }', $code); // Set secondary color and bg

$code = str_replace('#f8fafc', 'linear-gradient(135deg, #fdf2f8, #fce7f3)', $code); // Table head / modal head
$code = str_replace('border-bottom: 1px solid #e2e8f0', 'border-bottom: 1px solid #f5cdd8', $code); // Modal head border
$code = str_replace('color: #475569', 'color: #6b4a56', $code); // Table head text color
$code = str_replace('border: 1px solid #e2e8f0', 'border: 1px solid #f0e4ea', $code); // Table border
$code = str_replace('border: 1.5px solid #e2e8f0', 'border: 1.5px solid #e9d8de', $code); // Inputs border
$code = str_replace('background: #f1f5f9', 'background: #fdf8f9', $code); // Body hover/bg
$code = str_replace('linear-gradient(135deg, #f0fdfa, #ccfbf1)', 'linear-gradient(135deg, #fff1f2, #ffe4e6)', $code); // Info banner bg (pinkish)
$code = str_replace('border: 1.5px solid #99f6e4', 'border: 1.5px solid #fecdd3', $code); // Info banner border
$code = str_replace('color: #0f766e', 'color: #be123c', $code); // Info banner text

file_put_contents($file, $code);
echo "Reverted colors to pink theme.\n";
