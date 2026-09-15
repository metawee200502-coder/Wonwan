<?php
$f = 'admin/qc_exchanges.php';
$c = file_get_contents($f);

// Fix rogue php
$c = str_replace(
    "<?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>\n    <?php\n    <div class=\"admin-main-wrap\">",
    "<?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>\n    <div class=\"admin-main-wrap\">",
    $c
);

// Fix double php
$c = str_replace(
    "        <?php\n        <?php if (\$tab === 'physical'): ?>",
    "        <?php if (\$tab === 'physical'): ?>",
    $c
);

// Fix missing endif if any? Wait, I will just run php -l afterwards to check.
file_put_contents($f, $c);
echo "Patched admin/qc_exchanges.php.\n";
