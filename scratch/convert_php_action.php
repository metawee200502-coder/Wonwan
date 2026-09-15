<?php
$data = json_decode(file_get_contents('scratch/found_php_action.json'), true);
file_put_contents('scratch/patch_qc_unified_action_raw.json', json_encode($data));
