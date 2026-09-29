<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];

// Mock php://input
$mockInput = json_encode(['bookTitle'=>'Test','synopsis'=>'Test','bookDNA'=>'Test']);
// override file_get_contents inside generate-podcast? Actually just hit it via CURL locally.

$ch = curl_init('http://localhost/wanawan_project/api/generate-podcast.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $mockInput);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "Status: " . $info['http_code'] . "\n";
echo "Result starts with: \n" . substr($result, 0, 100) . "\n";
