<?php
$file = 'c:\laragon\www\wanawan_project\pages\chat.php';
$c = file_get_contents($file);
$original_len = strlen($c);

// Fix 1: Add getCondBanner alias right after makeCondBtn closes (the line ends with "return w;};")
// The makeCondBtn line ends with: return w;};
// We add const getCondBanner=makeCondBtn; after it on the SAME line
$find1 = "};w.append(btn);return w;};";
$replace1 = "};w.append(btn);return w;};\n const getCondBanner=makeCondBtn;";
$c1 = str_replace($find1, $replace1, $c);
if ($c1 === $c) {
    echo "Fix 1 FAILED - string not found\n";
    exit(1);
}
$c = $c1;
echo "Fix 1 OK: getCondBanner alias added\n";

// Fix 2: Fix insertBanner to check computed style visibility  
$find2 = "if(ctxBar)ctxBar.after(el);else if(chatHead)chatHead.after(el);";
$replace2 = "const ctxVisible=ctxBar&&getComputedStyle(ctxBar).display!=='none';if(ctxVisible)ctxBar.after(el);else if(chatHead)chatHead.after(el);";
$c2 = str_replace($find2, $replace2, $c);
if ($c2 === $c) {
    echo "Fix 2 FAILED - string not found\n";
    exit(1);
}
$c = $c2;
echo "Fix 2 OK: insertBanner visibility check added\n";

// Verify nothing broken
if (substr_count($c, 'doctype') < 1) { echo "ERROR: doctype missing!\n"; exit(1); }
if (substr_count($c, 'getCondBanner') < 2) { echo "ERROR: getCondBanner not properly set up!\n"; exit(1); }
if (strlen($c) < $original_len) { echo "ERROR: file got shorter, something wrong!\n"; exit(1); }

file_put_contents($file, $c);
echo "All done! File size: " . strlen($c) . " (was $original_len), diff: +" . (strlen($c)-$original_len) . " bytes\n";
echo "getCondBanner refs: " . substr_count($c, 'getCondBanner') . "\n";
echo "window.onerror: " . substr_count($c, 'window.onerror') . "\n";
