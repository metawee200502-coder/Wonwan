<?php
$file = 'C:/laragon/www/wanawan_project/pages/trade_condition_form.php';
$content = file_get_contents($file);

$search = "
                    <div style=\"margin-top: 15px;\">
                        <label class=\"form-label\">รูปเพิ่มเติม (สูงสุด 3 รูป) (ถ้ามี)</label>
                        <input type=\"file\" name=\"additional_images[]\" accept=\"image/*\" multiple style=\"padding: 10px; border: 1px dashed #CBD5E1; border-radius: 8px; width: 100%; box-sizing: border-box;\">
                        <small style=\"color: #64748B;\">*อัปโหลดได้สูงสุด 3 รูป</small>
                    </div>";

$replace = "
                    <h2 class=\"section-title\" style=\"margin-top:30px;\"><span>+</span> รูปเพิ่มเติม (ถ้ามี)</h2>
                    <div class=\"upload-grid\">
                        <label class=\"upload-box\">
                            <input type=\"file\" name=\"additional_images[]\" accept=\"image/*\">
                            <div class=\"upload-icon\">📷</div>
                            <span style=\"font-weight:500;\">รูปเพิ่มเติม 1</span>
                            <img src=\"\">
                        </label>
                        <label class=\"upload-box\">
                            <input type=\"file\" name=\"additional_images[]\" accept=\"image/*\">
                            <div class=\"upload-icon\">📷</div>
                            <span style=\"font-weight:500;\">รูปเพิ่มเติม 2</span>
                            <img src=\"\">
                        </label>
                        <label class=\"upload-box\">
                            <input type=\"file\" name=\"additional_images[]\" accept=\"image/*\">
                            <div class=\"upload-icon\">📷</div>
                            <span style=\"font-weight:500;\">รูปเพิ่มเติม 3</span>
                            <img src=\"\">
                        </label>
                    </div>";

$content = str_replace($search, $replace, $content);

// Clean up duplicate lightbox if it exists
$lightbox = "<div id=\"imageLightbox\" style=\"display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(5px);\">
    <span style=\"position:absolute; top:20px; right:30px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer;\" onclick=\"closeLightbox()\">&times;</span>
    <img id=\"lightboxImg\" src=\"\" style=\"max-width:90%; max-height:90%; object-fit:contain; border-radius:8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);\">
</div>
<script>
function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('imageLightbox').style.display = 'flex';
}
function closeLightbox() {
    document.getElementById('imageLightbox').style.display = 'none';
    document.getElementById('lightboxImg').src = '';
}
// Close on background click
document.getElementById('imageLightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});
</script>";

// Find position of first lightbox
$pos1 = strpos($content, '<div id="imageLightbox"');
if ($pos1 !== false) {
    // Find position of second lightbox
    $pos2 = strpos($content, '<div id="imageLightbox"', $pos1 + 10);
    if ($pos2 !== false) {
        // Replace everything after pos2 up to </body> with nothing
        $endPos = strpos($content, '</body>', $pos2);
        if ($endPos !== false) {
            $content = substr($content, 0, $pos2) . substr($content, $endPos);
        }
    }
}

file_put_contents($file, $content);
echo "Updated extra images UI to use grid boxes.";
