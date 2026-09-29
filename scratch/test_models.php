<?php
$key = 'AQ.Ab8RN6J0JkccBAgYOMzz2oR5Lw38p9k47AhKuHwtnHc8b6qUnQ';
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=$key";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo "Result:\n$res\n";
