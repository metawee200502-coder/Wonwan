<?php
$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

// 1. Add CSS
$css = <<<CSS
        .upload-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 10px; margin-top: 10px; }
        .upload-box { position: relative; width: 100%; aspect-ratio: 1; border: 2px dashed #CBD5E1; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; overflow: hidden; background: #F8FAFC; transition: all 0.2s ease; text-align: center; padding: 5px; box-sizing: border-box; }
        .upload-box:hover, .upload-box.dragover { border-color: #8B5266; background: #FDF5F7; }
        .upload-box input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        .upload-icon { font-size: 24px; color: #94A3B8; margin-bottom: 5px; }
        .upload-box span { font-size: 11px; color: #64748B; word-break: break-word; }
        .upload-box img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; display: none; z-index: 10; pointer-events: none; }
CSS;

// Insert CSS right before </style>
$content = str_replace('</style>', $css . "\n    </style>", $content);

// 2. Replace req_photos input
$reqInput = '<input type="file" name="req_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>';
$reqGrid = <<<HTML
                            <div class="upload-grid" id="req_upload_grid">
                                <label class="upload-box" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                    <input type="file" name="req_photos[]" accept="image/*" onchange="previewImage(this)">
                                    <div class="upload-icon">📷</div>
                                    <span>อัปโหลดรูป 1</span>
                                    <img src="">
                                </label>
                                <label class="upload-box" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                    <input type="file" name="req_photos[]" accept="image/*" onchange="previewImage(this)">
                                    <div class="upload-icon">📷</div>
                                    <span>อัปโหลดรูป 2</span>
                                    <img src="">
                                </label>
                                <label class="upload-box" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                    <input type="file" name="req_photos[]" accept="image/*" onchange="previewImage(this)">
                                    <div class="upload-icon">📷</div>
                                    <span>อัปโหลดรูป 3</span>
                                    <img src="">
                                </label>
                            </div>
HTML;
$content = str_replace($reqInput, $reqGrid, $content);

// 3. Replace off_photos input
$offInput = '<input type="file" name="off_photos[]" multiple accept="image/*" style="width:100%; font-size:12px;" required>';
$offGrid = <<<HTML
                            <div class="upload-grid" id="off_upload_grid">
                                <label class="upload-box" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                    <input type="file" name="off_photos[]" accept="image/*" onchange="previewImage(this)">
                                    <div class="upload-icon">📷</div>
                                    <span>อัปโหลดรูป 1</span>
                                    <img src="">
                                </label>
                                <label class="upload-box" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                    <input type="file" name="off_photos[]" accept="image/*" onchange="previewImage(this)">
                                    <div class="upload-icon">📷</div>
                                    <span>อัปโหลดรูป 2</span>
                                    <img src="">
                                </label>
                                <label class="upload-box" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)">
                                    <input type="file" name="off_photos[]" accept="image/*" onchange="previewImage(this)">
                                    <div class="upload-icon">📷</div>
                                    <span>อัปโหลดรูป 3</span>
                                    <img src="">
                                </label>
                            </div>
HTML;
$content = str_replace($offInput, $offGrid, $content);

// 4. Add JS for preview and drag-drop
$js = <<<HTML
    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = input.parentElement.querySelector('img');
                img.src = e.target.result;
                img.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            var img = input.parentElement.querySelector('img');
            img.src = "";
            img.style.display = 'none';
        }
    }
    
    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.add('dragover');
    }
    
    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('dragover');
    }
    
    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        e.currentTarget.classList.remove('dragover');
        
        var dt = e.dataTransfer;
        var files = dt.files;
        
        if (files.length > 0) {
            var input = e.currentTarget.querySelector('input[type="file"]');
            input.files = files; // Note: this sets the file input for drag and drop
            previewImage(input);
        }
    }
    </script>
HTML;

// Insert JS right before </body>
$content = str_replace('</body>', $js . "\n</body>", $content);

file_put_contents($file, $content);
echo "Added Drag and Drop UI to Admin QC Modal!\n";
