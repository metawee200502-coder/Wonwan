<?php
$ch = curl_init('https://api.elevenlabs.io/v1/voices');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['xi-api-key: sk_613392739f2894dbb84b2c92860566f707f0e7970acc0bcc']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$res = curl_exec($ch);
$voices = json_decode($res, true)['voices'] ?? [];
foreach(array_slice($voices, 0, 5) as $v) {
    echo $v['name'] . " - " . $v['voice_id'] . "\n";
}
