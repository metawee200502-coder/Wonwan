<?php
$file = 'c:/laragon/www/wanawan_project/admin/qc_exchanges.php';
$content = file_get_contents($file);

// 1. Add CSS class for dragover
$oldCss = <<<'CSS'
        .upload-box input[type="file"] { position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
CSS;

$newCss = <<<'CSS'
        .upload-box input[type="file"] { position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
        .upload-box.dragover { border-color: #175564; background: #e0f2fe; }
CSS;

if (strpos($content, '.upload-box.dragover') === false) {
    $content = str_replace($oldCss, $newCss, $content);
}

// 2. Add drag and drop JS logic
$oldJs = <<<'JS'
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    </script>
JS;

$newJs = <<<'JS'
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });

        // Add drag and drop support
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
JS;

if (strpos($content, 'drop\', function(e)') === false) {
    $content = str_replace($oldJs, $newJs, $content);
}

file_put_contents($file, $content);
echo "Added drag and drop support to QC photo upload UI.\n";
