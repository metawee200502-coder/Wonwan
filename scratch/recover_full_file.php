<?php
$lines = file('C:/Users/metha/.gemini/antigravity-ide/brain/33dbb834-52f0-44af-8044-9c4e5a137c81/.system_generated/logs/transcript_full.jsonl');
$latest_content = '';

foreach ($lines as $line) {
    $data = json_decode($line, true);
    
    // Check if the agent modified it
    if ($data['type'] === 'PLANNER_RESPONSE' && isset($data['tool_calls'])) {
        foreach ($data['tool_calls'] as $tc) {
            if (in_array($tc['name'], ['default_api:replace_file_content', 'default_api:multi_replace_file_content', 'default_api:write_to_file'])) {
                $args = json_decode($tc['arguments'], true);
                if (isset($args['TargetFile']) && strpos($args['TargetFile'], 'qc_exchanges.php') !== false) {
                    if (isset($args['CodeContent'])) {
                        $latest_content = $args['CodeContent']; // write_to_file full content
                    }
                    // For replace, it doesn't have full content.
                }
            }
        }
    }
    
    // Check if the tool response outputted the file
    if ($data['type'] === 'TOOL_RESPONSE' && isset($data['tool_responses'])) {
        foreach ($data['tool_responses'] as $tr) {
            if ($tr['name'] === 'default_api:view_file') {
                $output = $tr['response']['output'] ?? '';
                if (strpos($output, 'qc_exchanges.php') !== false && strpos($output, 'Total Lines') !== false) {
                    $latest_content = $output;
                }
            }
        }
    }
}

file_put_contents('scratch/recovered_full_file.txt', $latest_content);
echo "Recovered length: " . strlen($latest_content) . "\n";
