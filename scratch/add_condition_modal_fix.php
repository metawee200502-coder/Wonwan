<?php
$file = 'C:/laragon/www/wanawan_project/pages/chat.php';
$content = file_get_contents($file);

$modalHtml = "
<!-- Condition Form Modal -->
<div id=\"conditionModal\" style=\"display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; backdrop-filter:blur(4px);\">
    <div style=\"background:#fff; width:95%; max-width:900px; height:90%; border-radius:16px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 10px 30px rgba(0,0,0,0.2);\">
        <div style=\"padding:15px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#fdf5f7;\">
            <h3 style=\"margin:0; color:#8b5266; font-size:16px;\">กรอกฟอร์มยืนยันสภาพสินค้า</h3>
            <button type=\"button\" onclick=\"closeConditionModal()\" style=\"background:transparent; border:none; font-size:24px; color:#8b5266; cursor:pointer; line-height:1;\">&times;</button>
        </div>
        <div style=\"flex:1; position:relative;\">
            <iframe id=\"conditionIframe\" src=\"\" style=\"width:100%; height:100%; border:none;\"></iframe>
        </div>
    </div>
</div>

<script>
function closeConditionModal() {
    document.getElementById('conditionModal').style.display = 'none';
    document.getElementById('conditionIframe').src = '';
}
window.addEventListener('message', function(e) {
    if (e.data && e.data.action === 'close_condition_modal') {
        closeConditionModal();
    }
});
</script>
</body></html>";

if (strpos($content, "conditionModal") === false) {
    // using str_replace
    $content = str_replace("</body></html>", $modalHtml, $content);
    // fallback if no </body></html> in same line
    if (strpos($content, "conditionModal") === false) {
        $content = str_replace("</body>\r\n</html>", $modalHtml, $content);
    }
    if (strpos($content, "conditionModal") === false) {
        $content = str_replace("</body>\n</html>", $modalHtml, $content);
    }
    file_put_contents($file, $content);
    echo "Added conditionModal to chat.php";
} else {
    echo "conditionModal already exists!";
}
