<?php
$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

$js = <<<'JS'
    <script>
        // Image preview logic
        document.querySelectorAll('.upload-box input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                if(this.files && this.files[0]) {
                    const reader = new FileReader();
                    const img = this.parentElement.querySelector('img');
                    reader.onload = function(e) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });

        // Drag and drop logic
        document.querySelectorAll('.upload-box').forEach(box => {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                box.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                box.addEventListener(eventName, () => {
                    box.classList.add('dragover');
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                box.addEventListener(eventName, () => {
                    box.classList.remove('dragover');
                }, false);
            });
            
            box.addEventListener('drop', function(e) {
                let dt = e.dataTransfer;
                let files = dt.files;
                let input = box.querySelector('input[type="file"]');
                if (files.length) {
                    input.files = files;
                    const event = new Event('change', { bubbles: true });
                    input.dispatchEvent(event);
                }
            }, false);
        });
    </script>
</body>
JS;

// Remove any existing injection if we accidentally did it
$content = preg_replace('/<script>\s*\/\/\s*Image preview logic.*?<\/script>\s*<\/body>/is', '</body>', $content);

// Inject properly
$content = str_replace('</body>', $js, $content);
file_put_contents($file, $content);
echo "Successfully injected JS preview and drag-drop logic.\n";
