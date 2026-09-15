<?php
$lines = file('C:/Users/metha/.gemini/antigravity-ide/brain/33dbb834-52f0-44af-8044-9c4e5a137c81/.system_generated/logs/transcript_full.jsonl');

$foundFiles = [];

foreach ($lines as $line) {
    $data = json_decode($line, true);
    if (!$data) continue;
    
    // Check if the time is around 01:57Z or 08:57+07:00
    if (isset($data['created_at'])) {
        $timestamp = strtotime($data['created_at']);
        $localTime = date('H:i', $timestamp + (7 * 3600)); // UTC+7
        
        if ($data['type'] === 'PLANNER_RESPONSE' && isset($data['tool_calls'])) {
            foreach ($data['tool_calls'] as $tc) {
                $name = str_replace('default_api:', '', $tc['name']);
                if (in_array($name, ['replace_file_content', 'multi_replace_file_content', 'write_to_file'])) {
                    $args = $tc['args'] ?? $tc['arguments'];
                    if (is_string($args)) $args = json_decode($args, true);
                    
                    if (isset($args['TargetFile']) && strpos($args['TargetFile'], 'escrow.php') !== false) {
                        $foundFiles[] = [
                            'time' => $localTime,
                            'utc' => $data['created_at'],
                            'args' => $args
                        ];
                    }
                }
            }
        }
    }
}

file_put_contents('scratch/escrow_history.json', json_encode($foundFiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Found " . count($foundFiles) . " edits to escrow.php.\n";
