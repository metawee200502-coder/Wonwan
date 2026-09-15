<?php
$f = 'admin/qc_exchanges.php';
$c = file_get_contents($f);

// Remove rogue php tag at line 128
$c = preg_replace('/<\?php\s*<div class="admin-main-wrap">/', '<div class="admin-main-wrap">', $c);

// Remove double php tag at line 277
$c = preg_replace('/<\?php\s*<\?php if \(\$tab === \'physical\'\): \?>/', '<?php if ($tab === \'physical\'): ?>', $c);

file_put_contents($f, $c);
echo "Patched admin/qc_exchanges.php via preg_replace.\n";
