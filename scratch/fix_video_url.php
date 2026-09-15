<?php
$file = 'c:\laragon\www\wanawan_project\admin\qc_exchanges.php';
$content = file_get_contents($file);

// 1. Fix SQL
$old_sql = 'c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects, c_off.defects_description as off_cond_defects_desc, c_off.notes as off_cond_notes, c_off.video_url as off_cond_video,
                   c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects, c_req.defects_description as req_cond_defects_desc, c_req.notes as req_cond_notes, c_req.video_url as req_cond_video';

$new_sql = 'c_off.front_cover_url as off_cond_front, c_off.back_cover_url as off_cond_back, c_off.spine_url as off_cond_spine, c_off.defect_urls as off_cond_defects, c_off.defects_description as off_cond_defects_desc, c_off.notes as off_cond_notes,
                   c_req.front_cover_url as req_cond_front, c_req.back_cover_url as req_cond_back, c_req.spine_url as req_cond_spine, c_req.defect_urls as req_cond_defects, c_req.defects_description as req_cond_defects_desc, c_req.notes as req_cond_notes';

$content = str_replace($old_sql, $new_sql, $content);

file_put_contents($file, $content);
echo "Removed video_url from SQL query!";
