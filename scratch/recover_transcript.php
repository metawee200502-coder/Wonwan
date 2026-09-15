<?php
$lines = file('C:/Users/metha/.gemini/antigravity-ide/brain/33dbb834-52f0-44af-8044-9c4e5a137c81/.system_generated/logs/transcript_full.jsonl');
foreach ($lines as $line) {
    $data = json_decode($line, true);
    if ($data['type'] === 'PLANNER_RESPONSE' && isset($data['tool_calls'])) {
        foreach ($data['tool_calls'] as $tc) {
            if ($tc['name'] === 'default_api:replace_file_content' || $tc['name'] === 'default_api:multi_replace_file_content' || $tc['name'] === 'default_api:write_to_file') {
                $args = json_decode($tc['arguments'], true);
                if (isset($args['TargetFile']) && strpos($args['TargetFile'], 'admin\qc_exchanges.php') !== false) {
                    echo "Found edit to qc_exchanges.php!\n";
                    if (isset($args['ReplacementContent'])) {
                        if (strpos($args['ReplacementContent'], 'ondrop') !== false || strpos($args['ReplacementContent'], 'ondragover') !== false) {
                            echo "Found drag and drop!\n";
                            file_put_contents('scratch/recovered_drag_drop.txt', $args['ReplacementContent']);
                            echo "Saved to scratch/recovered_drag_drop.txt\n";
                        }
                    }
                    if (isset($args['ReplacementChunks'])) {
                        foreach ($args['ReplacementChunks'] as $chunk) {
                            if (strpos($chunk['ReplacementContent'], 'ondrop') !== false || strpos($chunk['ReplacementContent'], 'ondragover') !== false) {
                                echo "Found drag and drop in chunk!\n";
                                file_put_contents('scratch/recovered_drag_drop.txt', $chunk['ReplacementContent']);
                                echo "Saved to scratch/recovered_drag_drop.txt\n";
                            }
                        }
                    }
                }
            }
        }
    }
}
