<?php
$file = 'C:/laragon/www/wanawan_project/pages/trade_condition_form.php';
$content = file_get_contents($file);

// 1. Add class "gallery-image" to the images in partner form
$content = str_replace('onclick="openLightbox(this.src)"', 'class="gallery-image" onclick="openLightbox(this.src)"', $content);

// 2. Replace the old lightbox HTML and JS
$search = '<div id="imageLightbox" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(5px);">
    <span style="position:absolute; top:20px; right:30px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer;" onclick="closeLightbox()">&times;</span>
    <img id="lightboxImg" src="" style="max-width:90%; max-height:90%; object-fit:contain; border-radius:8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
</div>
<script>
function openLightbox(src) {
    document.getElementById(\'lightboxImg\').src = src;
    document.getElementById(\'imageLightbox\').style.display = \'flex\';
}
function closeLightbox() {
    document.getElementById(\'imageLightbox\').style.display = \'none\';
    document.getElementById(\'lightboxImg\').src = \'\';
}
// Close on background click
document.getElementById(\'imageLightbox\').addEventListener(\'click\', function(e) {
    if (e.target === this) closeLightbox();
});
</script>';

// Oh wait, there are TWO copies of the old lightbox HTML and JS at the bottom of the file (lines 554-571)!
// Let's just regex replace everything from the first '<div id="imageLightbox"' to the end of the file, then append the new one + </body></html>

$pos = strpos($content, '<div id="imageLightbox"');
if ($pos !== false) {
    $content = substr($content, 0, $pos);
}

$newLightbox = '
<div id="imageLightbox" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:99999; justify-content:center; align-items:center; backdrop-filter:blur(5px); flex-direction:column;">
    <span style="position:absolute; top:20px; right:30px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer; z-index:100000;" onclick="closeLightbox()">&times;</span>
    
    <div style="display:flex; align-items:center; justify-content:center; width:100%; height:100%; position:relative;">
        <!-- Left Arrow -->
        <div onclick="prevImage(event)" style="position:absolute; left:20px; width:50px; height:50px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; justify-content:center; align-items:center; color:#fff; font-size:24px; font-weight:bold; cursor:pointer; user-select:none; z-index:100000; transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.4)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.2)\'">
            &#10094;
        </div>
        
        <img id="lightboxImg" src="" style="max-width:85%; max-height:85%; object-fit:contain; border-radius:8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
        
        <!-- Right Arrow -->
        <div onclick="nextImage(event)" style="position:absolute; right:20px; width:50px; height:50px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; justify-content:center; align-items:center; color:#fff; font-size:24px; font-weight:bold; cursor:pointer; user-select:none; z-index:100000; transition:background 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.4)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.2)\'">
            &#10095;
        </div>
    </div>
    
    <div id="lightboxCounter" style="position:absolute; bottom:20px; color:#fff; font-size:16px; font-weight:500;"></div>
</div>

<script>
let galleryImages = [];
let currentImageIndex = 0;

function initGallery() {
    const imgs = document.querySelectorAll(\'.gallery-image\');
    galleryImages = Array.from(imgs).map(img => img.src);
}

function openLightbox(src) {
    if (galleryImages.length === 0) initGallery();
    currentImageIndex = galleryImages.indexOf(src);
    if(currentImageIndex === -1) currentImageIndex = 0;
    
    updateLightboxImage();
    document.getElementById(\'imageLightbox\').style.display = \'flex\';
}

function updateLightboxImage() {
    if(galleryImages.length === 0) return;
    document.getElementById(\'lightboxImg\').src = galleryImages[currentImageIndex];
    document.getElementById(\'lightboxCounter\').innerText = (currentImageIndex + 1) + " / " + galleryImages.length;
}

function prevImage(e) {
    e.stopPropagation();
    if(galleryImages.length === 0) return;
    currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
    updateLightboxImage();
}

function nextImage(e) {
    e.stopPropagation();
    if(galleryImages.length === 0) return;
    currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
    updateLightboxImage();
}

function closeLightbox() {
    document.getElementById(\'imageLightbox\').style.display = \'none\';
    document.getElementById(\'lightboxImg\').src = \'\';
}

// Close on background click
document.getElementById(\'imageLightbox\').addEventListener(\'click\', function(e) {
    if (e.target === this || e.target.querySelector(\'#lightboxImg\')) {
        closeLightbox();
    }
});

// Keyboard navigation
document.addEventListener(\'keydown\', function(e) {
    if (document.getElementById(\'imageLightbox\').style.display === \'flex\') {
        if (e.key === \'ArrowLeft\') prevImage(e);
        if (e.key === \'ArrowRight\') nextImage(e);
        if (e.key === \'Escape\') closeLightbox();
    }
});
</script>
</body>
</html>
';

$content .= $newLightbox;

file_put_contents($file, $content);
echo "Gallery lightbox updated.";
