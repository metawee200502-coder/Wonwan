const fs = require('fs');
const file = 'c:/laragon/www/wanawan_project/pages/admin_promotions.php';
let c = fs.readFileSync(file, 'utf8');

c = c.replace(
    /<\?php if\(\$p\['minimum_amount'\]>0\):\?> · ยอดขั้นต่ำ ฿<\?=number_format\(\$p\['minimum_amount'\],2\)\?><\?php endif;\?>/,
    "$&<?php if(isset($p['usage_limit'])):?> · จำกัด <?=number_format($p['usage_limit'])?> สิทธิ์<?php endif;?>"
);

fs.writeFileSync(file, c);
console.log('Updated admin_promotions.php display');
