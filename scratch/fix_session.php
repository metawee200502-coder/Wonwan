<?php
$files = glob(__DIR__ . '/../admin/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Remove all session_start(); globally
    $content = str_replace('session_start();', '', $content);
    // Add one session_start() at the top
    $content = preg_replace('/^\<\?php\s*/', "<?php\nsession_start();\n", $content);
    
    // Also, apply the sidebar layout wrap
    if (basename($file) !== 'index.php') {
        $menuMap = [
            'approvals.php' => 'approvals',
            'escrow.php' => 'escrow',
            'disputes.php' => 'disputes',
            'qc_exchanges.php' => 'qc_exchanges'
        ];
        $menu = $menuMap[basename($file)] ?? '';
        
        // Remove breadcrumbs
        $content = preg_replace('/\$breadcrumbs\s*=\s*\[.*?\];/s', "\$active_menu = '$menu';", $content);
        
        // Change body layout
        if (strpos($content, '<div class="admin-main-wrap">') === false) {
            $content = str_replace('<body>', "<body>\n    <?php require_once __DIR__ . '/../components/admin_sidebar.php'; ?>", $content);
            $content = str_replace('<main class="admin-wrap">', "<div class=\"admin-main-wrap\">\n    <main class=\"admin-wrap\">", $content);
            $content = str_replace('</main>', "</main>\n    </div>", $content);
        }
    }

    file_put_contents($file, $content);
}
echo "Fixed";
