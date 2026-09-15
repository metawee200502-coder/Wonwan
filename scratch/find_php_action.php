<?php
$lines = file('C:/Users/metha/.gemini/antigravity-ide/brain/33dbb834-52f0-44af-8044-9c4e5a137c81/.system_generated/logs/transcript_full.jsonl');

foreach ($lines as $line) {
    $data = json_decode($line, true);
    if ($data['type'] === 'PLANNER_RESPONSE' && isset($data['tool_calls'])) {
        foreach ($data['tool_calls'] as $tc) {
            $name = str_replace('default_api:', '', $tc['name']);
            if (in_array($name, ['replace_file_content', 'multi_replace_file_content'])) {
                $args = $tc['args'] ?? $tc['arguments'];
                if (is_string($args)) $args = json_decode($args, true);
                
                if (isset($args['TargetFile']) && strpos($args['TargetFile'], 'qc_exchanges.php') !== false) {
                    if (strpos(json_encode($args), 'submit_qc_report_both') !== false && strpos(json_encode($args), 'INSERT INTO admin_qc_reports') !== false) {
                        file_put_contents('scratch/found_php_action.json', json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        echo "Found PHP action!\n";
                        exit;
                    }
                }
            }
        }
    }
}
echo "Not found.\n";
