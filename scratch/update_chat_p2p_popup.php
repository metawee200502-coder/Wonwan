<?php
// 1. Update trade_p2p_logistics_form.php
$p2pFile = 'c:\laragon\www\wanawan_project\pages\trade_p2p_logistics_form.php';
$p2pContent = file_get_contents($p2pFile);

// Fix redirect for iframe
$search_redirect = "            // Redirect back to chat
            header(\"Location: /pages/chat.php?conversation=\" . \$conversationId);
            exit;";
$replace_redirect = "            if (!empty(\$_REQUEST['iframe'])) {
                echo \"<script>window.parent.location.reload();</script>\";
                exit;
            }
            // Redirect back to chat
            header(\"Location: /pages/chat.php?conversation=\" . \$conversationId);
            exit;";
$p2pContent = str_replace($search_redirect, $replace_redirect, $p2pContent);

// Add iframe styles
$search_style = "body { font-family: 'Kanit', sans-serif; background: #fdf5f7; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }";
$replace_style = "body { font-family: 'Kanit', sans-serif; background: #fdf5f7; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        <?php if (!empty(\$_REQUEST['iframe'])): ?>
        body { min-height: auto; background: transparent; padding: 10px; }
        .card { box-shadow: none; max-width: 100%; padding: 10px; }
        <?php endif; ?>";
$p2pContent = str_replace($search_style, $replace_style, $p2pContent);

// Hide cancel button if iframe
$search_cancel = "<a href=\"/pages/chat.php?conversation=<?= urlencode(\$conversationId) ?>\" class=\"btn btn-outline\" style=\"display:inline-block; text-align:center;\">ยกเลิก</a>";
$replace_cancel = "<?php if (empty(\$_REQUEST['iframe'])): ?>
            <a href=\"/pages/chat.php?conversation=<?= urlencode(\$conversationId) ?>\" class=\"btn btn-outline\" style=\"display:inline-block; text-align:center;\">ยกเลิก</a>
            <?php endif; ?>";
$p2pContent = str_replace($search_cancel, $replace_cancel, $p2pContent);

// Add iframe param to form submission so it persists
$search_form = "<form method=\"post\">";
$replace_form = "<form method=\"post\">
            <?php if (!empty(\$_REQUEST['iframe'])): ?>
            <input type=\"hidden\" name=\"iframe\" value=\"1\">
            <?php endif; ?>";
$p2pContent = str_replace($search_form, $replace_form, $p2pContent);

file_put_contents($p2pFile, $p2pContent);
echo "Updated trade_p2p_logistics_form.php\n";

// 2. Update chat.php
$chatFile = 'c:\laragon\www\wanawan_project\pages\chat.php';
$chatContent = file_get_contents($chatFile);

$search_chat = "btn.onclick=function(){window.location.href='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId;};";
$replace_chat = "btn.onclick=function(){
        const modal=document.getElementById('conditionModal');const iframe=document.getElementById('conditionIframe');
        if(modal&&iframe){
            modal.querySelector('h3').innerHTML = '<svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M22 11.08V12a10 10 0 1 1-5.93-9.14\"></path><polyline points=\"22 4 12 14.01 9 11.01\"></polyline></svg> แจ้งเลขพัสดุ';
            iframe.src='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId+'&conversation_id='+data.conversationId+'&iframe=1';
            modal.style.display='flex';
        } else {
            window.location.href='/pages/trade_p2p_logistics_form.php?request_id='+data.requestId+'&conversation_id='+data.conversationId;
        }
       };";
$chatContent = str_replace($search_chat, $replace_chat, $chatContent);

file_put_contents($chatFile, $chatContent);
echo "Updated chat.php\n";
