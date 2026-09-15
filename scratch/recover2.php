<?php
$logPath = 'C:\\Users\\metha\\.gemini\\antigravity-ide\\brain\\e7a78e82-d925-47ea-9abc-2113097870d4\\.system_generated\\logs\\transcript_full.jsonl';
$lines = file($logPath);
$latestContent = null;
foreach ($lines as $line) {
    $data = json_decode($line, true);
    if (isset($data['tool_calls'])) {
        foreach ($data['tool_calls'] as $call) {
            // Find when the previous agent wrote or replaced chat.php
            if (($call['name'] === 'default_api:replace_file_content' || $call['name'] === 'default_api:write_to_file' || $call['name'] === 'default_api:multi_replace_file_content') && 
                isset($call['arguments']['TargetFile']) && strpos($call['arguments']['TargetFile'], 'chat.php') !== false) {
                // Not enough. Wait, replace_file_content only shows diffs in output, but the argument has TargetContent/ReplacementContent.
                // The tool output for view_file contains the full file!
            }
            if ($call['name'] === 'default_api:view_file' && isset($call['arguments']['AbsolutePath']) && strpos($call['arguments']['AbsolutePath'], 'chat.php') !== false) {
                // Good! But view_file response is in tool_responses!
            }
        }
    }
    if (isset($data['tool_responses'])) {
        foreach ($data['tool_responses'] as $resp) {
            if ($resp['name'] === 'default_api:view_file' && strpos($resp['response']['output'] ?? '', 'pages/chat.php') !== false) {
                $latestContent = $resp['response']['output'];
            }
        }
    }
}
if ($latestContent) {
    file_put_contents('scratch/recovered_chat.txt', $latestContent);
    echo "Recovered chat.php from view_file output.\n";
} else {
    echo "Could not find view_file output for chat.php\n";
}
