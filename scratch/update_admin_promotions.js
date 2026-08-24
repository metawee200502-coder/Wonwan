const fs = require('fs');
const file = 'c:/laragon/www/wanawan_project/pages/admin_promotions.php';
let c = fs.readFileSync(file, 'utf8');

c = c.replace(
    /\$name=trim\(\(string\)\(\$_POST\['promotion_name'\]\?\?''\)\);.*?\$end=str_replace\('T',' ',\(string\)\(\$_POST\['ends_at'\]\?\?''\)\);/,
    "$&".replace(
        /\$max=trim\(\(string\)\(\$_POST\['maximum_discount'\]\?\?''\)\);/, 
        "$max=trim((string)($_POST['maximum_discount']??''));$usageLimit=trim((string)($_POST['usage_limit']??''));"
    )
);

c = c.replace(
    /INSERT INTO seller_promotions\([^)]+\) VALUES\([^)]+\)/,
    "INSERT INTO seller_promotions(seller_type,seller_id,promotion_name,description,scope_type,discount_type,discount_value,minimum_quantity,minimum_amount,maximum_discount,usage_limit,starts_at,ends_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
);

c = c.replace(
    /\$stmt->execute\(\[\$sellerType,\$sellerId,\$name,trim\(\(string\)\(\$_POST\['description'\]\?\?''\)\)\?\:null,\$scope,\$type,\$value,\$minQty,\$minAmount,\$max===''\?null:max\(0,\(float\)\$max\),\$start,\$end,\$userId\]\);/,
    "$stmt->execute([$sellerType,$sellerId,$name,trim((string)($_POST['description']??''))?:null,$scope,$type,$value,$minQty,$minAmount,$max===''?null:max(0,(float)$max),$usageLimit===''?null:max(1,(int)$usageLimit),$start,$end,$userId]);"
);

c = c.replace(
    /<div class="field"><label>ลดสูงสุด \(บาท\) — ไม่กำหนดได้<\/label><input type="number" name="maximum_discount" min="0" step="0\.01"><\/div>/,
    '<div class="two"><div class="field"><label>ลดสูงสุด (บาท) — ไม่กำหนดได้</label><input type="number" name="maximum_discount" min="0" step="0.01"></div><div class="field"><label>จำนวนสิทธิ์ (ใบ) — ไม่กำหนดได้</label><input type="number" name="usage_limit" min="1"></div></div>'
);

fs.writeFileSync(file, c);
console.log('Updated admin_promotions.php');
