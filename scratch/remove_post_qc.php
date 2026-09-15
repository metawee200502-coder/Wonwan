<?php
$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

// 1. Change default tab
$content = str_replace("\$tab = \$_GET['tab'] ?? 'posts';", "\$tab = \$_GET['tab'] ?? 'physical';", $content);

// 2. Remove the "posts" tab logic completely
$postsStart = strpos($content, '<?php if ($tab === \'posts\'): ?>');
$physicalStart = strpos($content, '<?php if ($tab === \'physical\'): ?>');

if ($postsStart !== false && $physicalStart !== false) {
    $content = substr_replace($content, '', $postsStart, $physicalStart - $postsStart);
}

// 3. Remove the tabs UI in HTML
$tabsHTMLStart = strpos($content, '<div class="tabs">');
if ($tabsHTMLStart !== false) {
    $tabsHTMLEnd = strpos($content, '</div>', $tabsHTMLStart) + 6;
    $content = substr_replace($content, '', $tabsHTMLStart, $tabsHTMLEnd - $tabsHTMLStart);
}

// 4. Remove the action logic for posts
$actionPostsStart = strpos($content, "if (\$action === 'approve_post' || \$action === 'reject_post') {");
if ($actionPostsStart !== false) {
    $actionPostsEnd = strpos($content, "} elseif (\$action === 'physical_qc') {");
    if ($actionPostsEnd !== false) {
        $content = substr_replace($content, "if (\$action === 'physical_qc') {", $actionPostsStart, $actionPostsEnd - $actionPostsStart + 38);
    }
}

file_put_contents($file, $content);
echo "Removed Post QC tab completely.\n";
