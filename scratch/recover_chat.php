<?php
$logPath = 'C:\Users\metha\.gemini\antigravity-ide\brain\e7a78e82-d925-47ea-9abc-2113097870d4\.system_generated\logs\transcript_full.jsonl';
$lines = file($logPath);
$latestContent = null;
foreach ($lines as $line) {
    $data = json_decode($line, true);
    if (isset($data['tool_responses'])) {
        foreach ($data['tool_responses'] as $resp) {
            if ($resp['name'] === 'default_api:view_file' || $resp['name'] === 'default_api:replace_file_content') {
                if (strpos($resp['response']['output'] ?? '', 'pages/chat.php') !== false) {
                    // Let's just find the last time I viewed the full file, or last replace output.
                    // But wait, view_file just shows the content.
                    $latestContent = $resp;
                }
            }
        }
    }
}
file_put_contents('scratch/chat_recover_debug.json', json_encode($latestContent));
echo "Done";
