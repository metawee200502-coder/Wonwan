<?php
$f1 = 'pages/trade_qc_logistics_form.php';
$c1 = file_get_contents($f1);
// Remove the duplicate block
$c1 = preg_replace('/if \(\$_SERVER\[\'REQUEST_METHOD\'\] === \'POST\' && isset\(\$_POST\[\'action\'\]\) && \$_POST\[\'action\'\] === \'user_qc_decision\'\) \{.*?exit;\n\}/s', '', $c1, 1);
file_put_contents($f1, $c1);

$f2 = 'admin/qc_exchanges.php';
$c2 = file_get_contents($f2);
$c2 = str_replace('<button type="button" class="action-btn" onclick="openViewQcModal(<?= $r[\'request_id\'] ?>)">ดูรายงาน QC</button>', '<button type="button" class="action-btn" onclick="openViewQcModal(<?= $r[\'request_id\'] ?>)">ส่งฟอร์มแล้ว (ดูรายละเอียด)</button>', $c2);
file_put_contents($f2, $c2);
echo "Fixed";
