<?php
$text = "[PROPOSE_FULL_TRADE:direct_escrow:3]";
if(preg_match('/\[PROPOSE_FULL_TRADE:([^\]]+)\]/i', $text, $matches)) {
    echo "MATCHED: " . $matches[1] . "\n";
} else {
    echo "NOT MATCHED\n";
}
