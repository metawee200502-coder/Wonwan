<?php
if(function_exists('opcache_reset')) {
    opcache_reset();
    echo "Opcache reset successfully!";
} else {
    echo "Opcache not enabled.";
}
