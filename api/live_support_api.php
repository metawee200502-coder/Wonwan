<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper function to get or generate token
function getSupportToken() {
    if (!empty($_COOKIE['wanawan_support_token'])) {
        return $_COOKIE['wanawan_support_token'];
    }
    $token = bin2hex(random_bytes(16));
    setcookie('wanawan_support_token', $token, time() + (86400 * 30), '/');
    return $token;
}

// Bot AI Response Logic
function getBotReply(string $text, string $userRole): array {
    $q = mb_strtolower(trim($text), 'UTF-8');
    
    // ----------------------------------------------------
    // 1. Direct request to talk to human / Super Admin
    // ----------------------------------------------------
    if (preg_match('/(แอดมิน|เจ้าหน้าที่|มนุษย์|ติดต่อคน|พนักงาน|super\s*admin|คนตอบ|คนจริง|ขอคุยกับคน)/u', $q)) {
        return [
            'reply' => "หากท่านต้องการคุยกับเจ้าหน้าที่ Super Admin โดยตรง สามารถกดปุ่ม **“คุยกับ Super Admin”** ด้านบนหรือปุ่มลัดด้านล่างนี้ได้เลยค่ะ ระบบจะนำท่านเข้าสู่คิวรอรับบริการทันทีค่ะ 🛎️",
            'suggest_admin' => true
        ];
    }

    // ----------------------------------------------------
    // 2. DEEP / COMPLEX ISSUES: Bot cannot handle, recommends Admin
    // ----------------------------------------------------
    // 2.1 Individual Financial / Account Transactions / Missing Money
    if (preg_match('/(เงินไม่เข้า|โอนแล้วไม่เข้า|สลิปผิด|แนบสลิปผิด|ยอดไม่ปรับ|ไม่ตัดเงิน|หักเงินซ้ำ|โอนผิดบัญชี|แก้เลขบัญชี|ตามเงิน|เงินหาย|ถอนเงินไม่ได้|เงินไม่เข้าบัญชี)/u', $q)) {
        return [
            'reply' => "⚠️ **เรื่องการตรวจสอบยอดเงิน / ธุรกรรมเฉพาะบุคคล:**\nเนื่องจากเป็นข้อมูลทางการเงินส่วนบุคคลเชิงลึกที่ต้องเข้าถึงระบบบัญชีธนาคารและฐานข้อมูลหลังบ้านจริง น้องพิงค์ AI ไม่สามารถตรวจสอบหรือแก้ไขยอดเงินในระบบให้ได้ค่ะ 🌸\n\n👉 **แนะนำให้กดปุ่ม “คุยกับ Super Admin”** ด้านล่าง เพื่อให้เจ้าหน้าที่ฝ่ายบัญชีตรวจสอบสลิปและปรับยอดให้โดยตรงนะคะ 🛎️",
            'suggest_admin' => true
        ];
    }

    // 2.2 Disputes / Fraud / Reports / Account Ban
    if (preg_match('/(โดนโกง|โกง|แจ้งความ|ร้องเรียน|แบน|ระงับบัญชี|ปลดแบน|ถูกระงับ|ของปลอม|หลอกลวง|ดำเนินคดี|ไกล่เกลี่ย|แจ้งจับ)/u', $q)) {
        return [
            'reply' => "⚠️ **เรื่องการร้องเรียน / ข้อพิพาท / บัญชีถูกระงับ:**\nเรื่องนี้เป็นกรณีพิเศษที่มีผลกระทบต่อความปลอดภัยและข้อกำหนดการใช้งาน ซึ่งต้องได้รับการพิจารณาและตรวจสอบหลักฐานโดย Super Admin เท่านั้นค่ะ\n\n👉 **แนะนำให้กดปุ่ม “คุยกับ Super Admin”** ด้านล่าง เพื่อให้เจ้าหน้าที่เปิดเคสตรวจสอบและคุ้มครองความปลอดภัยให้ท่านค่ะ 🛡️",
            'suggest_admin' => true
        ];
    }

    // 2.3 Backend Technical / Database / Bug / Server Outage
    if (preg_match('/(ฐานข้อมูล|database|api|บั๊ก|bug|error\s*500|server\s*down|เว็บพัง|ระบบล่ม|ซอร์สโค้ด|error\s*404|เข้าเว็บไม่ได้)/u', $q)) {
        return [
            'reply' => "🔧 **ปัญหาเชิงเทคนิคระบบหลังบ้าน:**\nกรณีระบบขัดข้องหรือเกิดข้อผิดพลาดทางเทคนิคเชิงลึก น้องพิงค์ AI ไม่สามารถเข้าถึงเซิร์ฟเวอร์หรือแก้โค้ดระบบหลังบ้านได้ค่ะ\n\n👉 **แนะนำให้กดปุ่ม “คุยกับ Super Admin”** ด้านล่าง เพื่อให้ทีมผู้ดูแลระบบตรวจสอบ log และแก้ไขให้อย่างเร่งด่วนนะคะ 💻",
            'suggest_admin' => true
        ];
    }

    // 2.4 Tax Invoices / Legal Contracts / Special Publisher Agreements
    if (preg_match('/(ใบกำกับภาษี|หัก\s*ณ\s*ที่จ่าย|ขอใบเสร็จภาษี|vat|สัญญาตัวแทน|เปิดสำนักพิมพ์ทางการ|นิติบุคคล|สัญญาทางการ)/u', $q)) {
        return [
            'reply' => "📑 **เอกสารภาษี / ข้อตกลงสัญญาพิเศษ:**\nข้อมูลด้านเอกสารภาษีหรือสิทธิพิเศษระดับองค์กร เป็นเรื่องเฉพาะทางที่ต้องออกเอกสารโดยฝ่ายบริหาร\n\n👉 **แนะนำให้กดปุ่ม “คุยกับ Super Admin”** ด้านล่าง เพื่อประสานงานกับฝ่ายที่เกี่ยวข้องโดยตรงนะคะ 🏢",
            'suggest_admin' => true
        ];
    }

    // ----------------------------------------------------
    // 3. BASIC WEBSITE INFORMATION (ข้อมูลพื้นฐานเกี่ยวกับเว็บ)
    // ----------------------------------------------------
    // 3.1 About Wanawan Website / Platform Overview
    if (preg_match('/(วนวรรณ|wanawan|คืออะไร|เว็บอะไร|เกี่ยวกับเว็บ|ทำอะไรได้|เกี่ยวกับเรา|แนะนำเว็บ|แพลตฟอร์ม|จุดเด่น|ประวัติเว็บ)/u', $q)) {
        return [
            'reply' => "🌿 **ยินดีต้อนรับสู่วนวรรณ (Wanawan)!**\nวนวรรณ คือ แพลตฟอร์มตลาดหนังสือครบวงจรสำหรับคนรักการอ่าน 📖\n\n✨ **บริการหลักของเว็บไซต์:**\n• **ซื้อ-ขายหนังสือ:** รวบรวมหนังสือมือหนึ่งและมือสองสภาพดีจากร้านค้าและสำนักพิมพ์ชั้นนำ\n• **ลานแลกเปลี่ยน (Exchange):** พื้นที่แลกเปลี่ยนหนังสือระหว่างนักอ่าน พร้อมตัวเลือกระบบ QC กลางตรวจเช็คสภาพหนังสือก่อนส่งมอบ\n• **ระบบ Wanawan Escrow:** ระบบคนกลางถือเงิน ปลอดภัย 100% เงินจะไม่ถูกโอนจนกว่าผู้ซื้อจะได้รับของถูกต้อง\n\nเว็บไซต์เปิดให้บริการตลอด 24 ชั่วโมง สอบถามข้อมูลอื่นๆ เพิ่มเติมได้เลยนะคะ 🌸",
            'suggest_admin' => false
        ];
    }

    // 3.2 Registration / Login / Account / Password
    if (preg_match('/(สมัคร|สมัครสมาชิก|register|เข้าสู่ระบบ|login|ล็อกอิน|ลืมรหัส|ลืมรหัสผ่าน|เปลี่ยนรหัส|โปรไฟล์|บัญชีผู้ใช้|แก้ไขโปรไฟล์)/u', $q)) {
        return [
            'reply' => "👤 **ข้อมูลการใช้งานบัญชีสมาชิก:**\n• **สมัครสมาชิก:** กดปุ่ม **“สมัครสมาชิก”** ที่แถบเมนูด้านบน กรอกอีเมล ชื่อผู้ใช้ และรหัสผ่าน\n• **เข้าสู่ระบบ:** ใช้อีเมลและรหัสผ่านเพื่อเข้าสู่ระบบได้ทันที\n• **แก้ไขโปรไฟล์:** ไปที่เมนู **“โปรไฟล์ของฉัน”** เพื่อเปลี่ยนรูปภาพ ที่อยู่จัดส่ง หรือเบอร์โทรศัพท์\n• **ลืมรหัสผ่าน:** หากเข้าสู่ระบบไม่ได้ สามารถกดปุ่ม **“คุยกับ Super Admin”** เพื่อให้เจ้าหน้าที่ช่วยรีเซ็ตรหัสผ่านได้ค่ะ 🔑",
            'suggest_admin' => false
        ];
    }

    // 3.3 Operating Hours / Contact Channels
    if (preg_match('/(เวลาทำการ|เวลาเปิด|เปิดกี่โมง|ช่องทางติดต่อ|เบอร์โทร|ติดต่อเรา|เวลาบริการ|ที่อยู่เว็บ|เวลาดูแล|ติดต่อทีมงาน)/u', $q)) {
        return [
            'reply' => "⏰ **เวลาทำการและการติดต่อ:**\n• **เว็บไซต์วนวรรณ:** เปิดให้เลือกชม สั่งซื้อ และแลกเปลี่ยนหนังสือได้ตลอด 24 ชั่วโมง ทุกวันค่ะ\n• **เจ้าหน้าที่ Super Admin:** พร้อมให้บริการดูแลและช่วยเหลือผ่าน Live Chat ทุกวัน\n• สามารถกดปุ่ม **“คุยกับ Super Admin”** เพื่อติดต่อเจ้าหน้าที่หรือเข้าคิวรอได้ตลอดเวลาค่ะ 🌸",
            'suggest_admin' => false
        ];
    }

    // 3.4 Book Categories / Genres
    if (preg_match('/(หมวดหมู่|ประเภทหนังสือ|มีหนังสืออะไร|ขายหนังสืออะไร|นิยาย|มังงะ|การ์ตูน|ไลท์โนเวล|วรรณกรรม|หนังสือเรียน|ประเภท)/u', $q)) {
        return [
            'reply' => "📚 **หมวดหมู่หนังสือในวนวรรณ:**\nเรามีหนังสือหลากหลายประเภท ตอบโจทย์ทุกกลุ่มนักอ่าน เช่น:\n• นวนิยาย / วรรณกรรมไทยและแปล / ไลท์โนเวล\n• มังงะ / การ์ตูนญี่ปุ่น / คอมมิคส์\n• พัฒนาตนเอง / จิตวิทยา / ธุรกิจการเงิน\n• การศึกษา / หนังสือเรียน / ภาษาศาสตร์\n• หนังสือเด็กและเยาวชน / สารคดีทั่วไป\n\nท่านสามารถพิมพ์ค้นหาหรือเลือกดูตามหมวดหมู่ที่หน้าแรกได้เลยค่ะ 🔍",
            'suggest_admin' => false
        ];
    }

    // 3.5 Security / Escrow Guarantee
    if (preg_match('/(ปลอดภัยไหม|ปลอดภัยมั้ย|โกงไหม|เชื่อถือได้ไหม|escrow|การันตี|ความปลอดภัย|ปลอดภัยหรือเปล่า)/u', $q)) {
        return [
            'reply' => "🛡️ **ความปลอดภัยด้วยระบบ Wanawan Escrow:**\nวนวรรณมีระบบคุ้มครองผู้ซื้อ-ผู้ขายอย่างรัดกุม:\n1. เมื่อทำการสั่งซื้อ ยอดเงินจะถูกพักไว้ที่ระบบตัวกลาง ยังไม่โอนให้ผู้ขายทันที\n2. เมื่อผู้ซื้อได้รับสินค้า ตรวจสอบสภาพถูกต้องแล้ว กดยืนยัน ระบบจึงจะโอนเงินให้ร้านค้า\n3. หากสินค้าชำรุด สูญหาย หรือไม่ตรงปก สามารถกดขอคืนเงินได้ เงินของท่านจึงปลอดภัยแน่นอน 100% ค่ะ ✨",
            'suggest_admin' => false
        ];
    }

    // 3.6 Book Search / How to find books
    if (preg_match('/(ค้นหา|หาหนังสือ|ค้นหายังไง|เสิร์ช|search|isbn|กรองหนังสือ)/u', $q)) {
        return [
            'reply' => "🔍 **วิธีค้นหาหนังสือบนเว็บไซต์:**\n• พิมพ์ชื่อเรื่อง ชื่อผู้แต่ง สำนักพิมพ์ หรือรหัส ISBN ในช่องค้นหาด้านบน\n• สามารถกรองตามหมวดหมู่ สภาพหนังสือ (มือหนึ่ง/มือสอง) และช่วงราคาได้ค่ะ\n• หากหนังสือที่ต้องการหมด สามารถกดบันทึกลงรายการที่อยากได้ (Wishlist) ไว้ได้ค่ะ 📖",
            'suggest_admin' => false
        ];
    }

    // 3.7 User Roles in System
    if (preg_match('/(บทบาท|ระดับผู้ใช้|role|ผู้อ่าน|reader|shop|publisher|สิทธิ์ผู้ใช้)/u', $q)) {
        return [
            'reply' => "👥 **บทบาทผู้ใช้งานในวนวรรณ:**\n• **ผู้อ่าน (Reader):** สมาชิกทั่วไป สามารถเลือกซื้อ แลกเปลี่ยนหนังสือ และรีวิวได้\n• **ร้านค้า (Shop Admin):** ร้านหนังสือทั่วไป สามารถเปิดร้าน ลงขายหนังสือ และจัดการสต็อก\n• **สำนักพิมพ์ (Publisher):** บัญชีทางการของสำนักพิมพ์ มีระบบจัดการหนังสือและรายงานเฉพาะทาง\n• **Super Admin:** ทีมงานดูแลระบบ ความปลอดภัย และฝ่ายสนับสนุนลูกค้า 🛡️",
            'suggest_admin' => false
        ];
    }

    // 3.8 How to purchase / Cart
    if (preg_match('/(สั่งซื้อ|ซื้อยังไง|วิธีซื้อ|ตะกร้า|หยิบใส่ตะกร้า|ขั้นตอนสั่งซื้อ)/u', $q)) {
        return [
            'reply' => "🛒 **ขั้นตอนการสั่งซื้อหนังสือ:**\n1. เลือกหนังสือที่สนใจแล้วกด **“เพิ่มลงรถเข็น”** หรือ **“ซื้อทันที”**\n2. ไปที่รูปรถเข็นเพื่อตรวจสอบรายการและที่อยู่จัดส่ง\n3. สแกนโอนเงินผ่าน PromptPay QR Code ที่ระบบแสดง\n4. แนบหลักฐานสลิปโอนเงิน แล้วรอร้านค้าตรวจสอบและจัดส่งสินค้าค่ะ 📦",
            'suggest_admin' => false
        ];
    }

    // 3.9 Shipping and Tracking
    if (preg_match('/(จัดส่ง|ส่งของ|พัสดุ|แทร็ก|tracking|เช็คเลข|ค่าส่ง|ส่งกี่วัน|ขนส่ง|flash|kerry|ไปรษณีย์)/u', $q)) {
        return [
            'reply' => "🚚 **การจัดส่งและติดตามพัสดุ:**\n• ร้านค้าจะจัดส่งสินค้าผ่านขนส่งมาตรฐานภายใน 1-3 วันทำการ\n• ตรวจสอบหมายเลขพัสดุได้ที่เมนู **“คำสั่งซื้อของฉัน”** > กดดูรายละเอียดคำสั่งซื้อ\n• ค่าจัดส่งจะแสดงชัดเจนก่อนกดยืนยันสั่งซื้อค่ะ 📦",
            'suggest_admin' => false
        ];
    }

    // 3.10 Payment / PromptPay
    if (preg_match('/(สลิป|ชำระเงิน|จ่ายเงิน|promptpay|โอนเงิน|คิวอาร์โค้ด|qr\s*code|พร้อมเพย์)/u', $q)) {
        return [
            'reply' => "💳 **ช่องทางการชำระเงิน:**\n• ระบบรองรับการสแกน QR Code PromptPay สะดวก รวดเร็ว และไม่มีค่าธรรมเนียม\n• หลังโอนเงิน ให้อัปโหลดภาพสลิปในหน้ารายละเอียดคำสั่งซื้อ เพื่อให้ทางร้านตรวจสอบและจัดส่งหนังสือค่ะ\n• หากมีปัญหาเกี่ยวกับสลิป สามารถกด **“คุยกับ Super Admin”** ได้เลยค่ะ 🌸",
            'suggest_admin' => false
        ];
    }

    // 3.11 Return / Refund
    if (preg_match('/(คืน|คืนเงิน|คืนสินค้า|ยกเลิก|ชำรุด|ไม่ตรงปก|เปลี่ยนของ)/u', $q)) {
        return [
            'reply' => "🛡️ **การขอคืนเงิน / คืนสินค้า:**\n• หากได้รับหนังสือชำรุด หรือไม่ตรงตามที่ระบุ สามารถกด **“ขอคืนสินค้า/คืนเงิน”** ได้ที่หน้ารายละเอียดคำสั่งซื้อ ภายในระยะเวลาที่กำหนด\n• ระบบ Escrow จะระงับการโอนเงินให้ผู้ขายไว้ทันทีเพื่อความปลอดภัย\n• หากต้องการให้ทีมงานช่วยตรวจสอบ สามารถกดปุ่ม **“คุยกับ Super Admin”** ได้เลยค่ะ 🛎️",
            'suggest_admin' => true
        ];
    }

    // 3.12 Book Exchange / QC
    if (preg_match('/(แลก|แลกเปลี่ยน|exchange|qc|ลานแลกเปลี่ยน|แลกหนังสือ)/u', $q)) {
        return [
            'reply' => "🔄 **ระบบแลกเปลี่ยนหนังสือ (Exchange):**\n• ท่านสามารถนำหนังสือที่อ่านจบแล้วมาโพสต์แลกเปลี่ยนกับนักอ่านท่านอื่นได้ที่เมนู **“ลานแลกเปลี่ยน”**\n• มี 2 รูปแบบ:\n  1. **Direct P2P:** ส่งแลกกันโดยตรงระหว่างผู้ใช้\n  2. **ผ่าน QC กลาง:** ส่งผ่านแอดมินวนวรรณเพื่อตรวจสอบสภาพหนังสือก่อนส่งมอบ มั่นใจสภาพตรงปก 100% ค่ะ ✨",
            'suggest_admin' => false
        ];
    }

    // ----------------------------------------------------
    // 4. BOOKSTORE / SELLER / PUBLISHER QUESTIONS
    // ----------------------------------------------------
    if (preg_match('/(gp|ค่า\s*gp|หัก\s*gp|ค่าธรรมเนียม|เปอร์เซ็นต์)/u', $q)) {
        return [
            'reply' => "💡 **ข้อมูลค่าธรรมเนียม GP ร้านค้า:**\n• ระบบวนวรรณคิดค่าธรรมเนียม GP 15% จากยอดจำหน่าย เพื่อสนับสนุนระบบ Escrow ตัวกลางตรวจสอบความปลอดภัย และระบบคลังสินค้า\n• ร้านค้าสามารถดูสรุปยอดขายสุทธิและการหัก GP ได้ที่หน้า **Dashboard > รายงานการขาย** ค่ะ",
            'suggest_admin' => false
        ];
    }
    
    if (preg_match('/(excel|csv|ส่งออก|ดาวน์โหลดรายงาน|รายงานยอดขาย|โหลดชีท)/u', $q)) {
        return [
            'reply' => "📊 **การดาวน์โหลดรายงานยอดขาย Excel:**\n• ไปที่เมนู **“แผงควบคุม (Dashboard)”** หรือ **“ประวัติคำสั่งซื้อ”**\n• กดปุ่ม **“ส่งออกข้อมูล (Excel)”** เลือกส่งออกคำสั่งซื้อหรือรายการสต็อกสินค้า\n• ไฟล์ .xlsx จะถูกดาวน์โหลดทันที พร้อมสรุปตัวเลขให้อัตโนมัติค่ะ!",
            'suggest_admin' => false
        ];
    }
    
    if (preg_match('/(ลงขาย|เพิ่มหนังสือ|คลัง|สต็อก|inventory|แก้ราคา)/u', $q)) {
        return [
            'reply' => "📚 **การจัดการหนังสือและสต็อก:**\n• ร้านค้าสามารถเพิ่มหนังสือใหม่ได้ที่เมนู **“จัดการคลังสินค้า (Inventory)” > “เพิ่มหนังสือ”**\n• กำหนดราคา สต็อก หมวดหมู่ และสภาพหนังสือได้ตามจริง\n• สามารถแก้ไขสต็อกและราคาได้ตลอดเวลาค่ะ",
            'suggest_admin' => false
        ];
    }

    if (preg_match('/(ถอนเงิน|กระเป๋าเงิน|รายได้|ยอดเงิน|wallet|payout)/u', $q)) {
        return [
            'reply' => "💰 **รายได้และการถอนเงินร้านค้า:**\n• เมื่อคำสั่งซื้อจัดส่งสำเร็จและผ่านการตรวจสอบ ยอดเงินสุทธิจะเข้าสู่ **“รายได้ทั้งหมด (Seller Wallet)”**\n• ร้านค้าสามารถตรวจสอบยอดคงเหลือ ประวัติธุรกรรม และขอกดถอนเงินเข้าบัญชีธนาคารได้ค่ะ",
            'suggest_admin' => false
        ];
    }

    if (preg_match('/(โปรโมชั่น|คูปอง|ส่วนลด|promotion|ลดราคา)/u', $q)) {
        return [
            'reply' => "🎁 **การจัดโปรโมชั่นร้านค้า:**\n• ไปที่เมนู **“จัดการโปรโมชั่น”** เพื่อสร้างส่วนลดแบบเปอร์เซ็นต์ (%) หรือระบุจำนวนเงิน (บาท)\n• กำหนดเงื่อนไขขั้นต่ำ วันเริ่มต้น-สิ้นสุด และจำนวนสิทธิ์ได้ ระบบจะคำนวณส่วนลดที่ดีที่สุดให้ลูกค้าอัตโนมัติค่ะ",
            'suggest_admin' => false
        ];
    }

    if (preg_match('/(ที่อยู่คืนสินค้า|คืนของร้าน|return\s*address)/u', $q)) {
        return [
            'reply' => "📍 **ที่อยู่สำหรับรับสินค้าคืน:**\n• ร้านค้าสามารถตั้งค่าที่อยู่รับพัสดุตีกลับได้ที่เมนูโปรไฟล์ร้าน หรือเมนูตั้งค่าที่อยู่คืนสินค้า เพื่อให้ลูกค้าส่งของคืนได้ถูกต้องค่ะ",
            'suggest_admin' => false
        ];
    }

    // ----------------------------------------------------
    // 5. GREETINGS & THANKS
    // ----------------------------------------------------
    if (preg_match('/(สวัสดี|ดีครับ|ดีค่ะ|hello|hi|หวัดดี)/u', $q)) {
        $greeting = $userRole === 'shop_admin' || $userRole === 'publisher_admin'
            ? "สวัสดีค่ะแอดมินร้านค้า! 😊 น้องพิงค์พร้อมช่วยเหลือเรื่องสต็อกหนังสือ ค่า GP คำสั่งซื้อ หรือข้อมูลทั่วไปเกี่ยวกับเว็บค่ะ หากต้องการคุยกับ Super Admin กดปุ่ม **“คุยกับ Super Admin”** ได้เลยนะคะ!"
            : "สวัสดีค่ะ! ยินดีต้อนรับสู่วนวรรณ (Wanawan) 😊 มีเรื่องเกี่ยวกับหนังสือ การสั่งซื้อ ลานแลกเปลี่ยน หรือข้อมูลทั่วไปเกี่ยวกับเว็บให้น้องพิงค์ช่วยแนะนำไหมคะ? หรือหากต้องการคุยกับเจ้าหน้าที่ Super Admin กดติดต่อได้เลยนะคะ!";
        return [
            'reply' => $greeting,
            'suggest_admin' => false
        ];
    }
    
    if (preg_match('/(ขอบคุณ|ขอบใจ|thanks|thank you)/u', $q)) {
        return [
            'reply' => "ยินดีให้บริการเสมอค่ะ! ขอให้มีความสุขกับการอ่านหนังสือบนวนวรรณนะคะ 🌿✨ หากมีข้อสงสัยเพิ่มเติม ทักมาได้ตลอดเวลาเลยค่ะ 🌸",
            'suggest_admin' => false
        ];
    }
    
    // ----------------------------------------------------
    // 6. FALLBACK (เมื่อคำถามลึกเกินไป หรือ อยู่นอกเหนือข้อมูลพื้นฐาน)
    // ----------------------------------------------------
    return [
        'reply' => "ขออภัยด้วยนะคะ เรื่องนี้เป็นรายละเอียดเชิงลึกเฉพาะทาง ซึ่งเกินขอบเขตที่น้องพิงค์ AI จะตอบได้อย่างถูกต้องครบถ้วนค่ะ 🌸\n\n👉 **แนะนำให้กดปุ่ม “คุยกับ Super Admin”** ด้านล่าง เพื่อให้เจ้าหน้าที่ดูแลและช่วยเหลือท่านโดยตรงได้เลยนะคะ 🛎️",
        'suggest_admin' => true
    ];
}

// Session resolution helpers
function findUserActiveSession(PDO $pdo, string $token, ?int $userId): ?array {
    if ($userId) {
        $stmt = $pdo->prepare("SELECT * FROM live_support_sessions WHERE user_id = ? AND status IN ('bot', 'queued', 'active') ORDER BY session_id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($s) return $s;
    }
    $stmt = $pdo->prepare("SELECT * FROM live_support_sessions WHERE session_token = ? AND status IN ('bot', 'queued', 'active') ORDER BY session_id DESC LIMIT 1");
    $stmt->execute([$token]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
    return $s ?: null;
}

function findUserLatestSession(PDO $pdo, string $token, ?int $userId): ?array {
    if ($userId) {
        $stmt = $pdo->prepare("SELECT * FROM live_support_sessions WHERE user_id = ? ORDER BY session_id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($s) return $s;
    }
    $stmt = $pdo->prepare("SELECT * FROM live_support_sessions WHERE session_token = ? ORDER BY session_id DESC LIMIT 1");
    $stmt->execute([$token]);
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
    return $s ?: null;
}

function createNewLiveSession(PDO $pdo, string $token, ?int $userId, string $userName, string $userRole): array {
    $stmtIns = $pdo->prepare("INSERT INTO live_support_sessions (user_id, session_token, user_name, user_role, status) VALUES (?, ?, ?, ?, 'bot')");
    $stmtIns->execute([$userId, $token, $userName, $userRole]);
    $sessionId = (int)$pdo->lastInsertId();
    
    // Greeting tailored by role
    if ($userRole === 'shop_admin') {
        $welcomeText = "สวัสดีค่ะคุณ {$userName} (ร้านค้าวนวรรณ)! น้องพิงค์ ยินดีดูแลด้วยใจค่ะ 🌸\nพร้อมรับฟังและให้ข้อมูลเกี่ยวกับการจัดการร้านค้า คลังสินค้า ค่า GP หรือรายงานยอดขายค่ะ\n\nหากท่านต้องการคุยกับเจ้าหน้าที่ Super Admin สามารถกดปุ่ม **“คุยกับ Super Admin”** ได้เลยนะคะ 😊";
    } elseif ($userRole === 'publisher_admin') {
        $welcomeText = "สวัสดีค่ะคุณ {$userName} (สำนักพิมพ์)! น้องพิงค์ ยินดีดูแลด้วยใจค่ะ 🌸\nพร้อมรับฟังและให้ข้อมูลเกี่ยวกับระบบสำนักพิมพ์ คำสั่งซื้อ และค่า GP ค่ะ\n\nหากท่านต้องการคุยกับเจ้าหน้าที่ Super Admin สามารถกดปุ่ม **“คุยกับ Super Admin”** ได้ตลอดเวลาเลยนะคะ 😊";
    } else {
        $welcomeText = "สวัสดีค่ะคุณ {$userName}! น้องพิงค์ ยินดีดูแลด้วยใจค่ะ 🌸\nพร้อมรับฟังและให้ข้อมูลเกี่ยวกับการสั่งซื้อ จัดส่ง คืนเงิน หรือระบบร้านค้าค่ะ\n\nหากท่านต้องการคุยกับเจ้าหน้าที่ Super Admin สามารถกดปุ่ม **“คุยกับ Super Admin”** ได้เลยนะคะ 😊";
    }
    
    $stmtMsg = $pdo->prepare("INSERT INTO live_support_messages (session_id, sender_type, sender_name, message_text) VALUES (?, 'bot', 'น้องพิงค์ (AI Support)', ?)");
    $stmtMsg->execute([$sessionId, $welcomeText]);
    
    $stmt = $pdo->prepare("SELECT * FROM live_support_sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getQueuePositionInfo(PDO $pdo, int $sessionId): array {
    $stmt = $pdo->prepare("SELECT queued_at FROM live_support_sessions WHERE session_id = ? AND status = 'queued'");
    $stmt->execute([$sessionId]);
    $queuedAt = $stmt->fetchColumn();
    if (!$queuedAt) {
        return ['position' => 0, 'ahead' => 0, 'total' => 0];
    }
    
    // Exact FIFO position ordered strictly by queued_at ASC, session_id ASC
    $qStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM live_support_sessions 
        WHERE status = 'queued' 
          AND (queued_at < ? OR (queued_at = ? AND session_id <= ?))
    ");
    $qStmt->execute([$queuedAt, $queuedAt, $sessionId]);
    $position = (int)$qStmt->fetchColumn();
    if ($position < 1) $position = 1;
    
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM live_support_sessions WHERE status = 'queued'");
    $total = (int)$totalStmt->fetchColumn();
    
    $ahead = max(0, $position - 1);
    
    return [
        'position' => $position,
        'ahead' => $ahead,
        'total' => $total
    ];
}

try {
    switch ($action) {
        case 'init': {
            $token = getSupportToken();
            
            // Check if user is logged in
            $userId = !empty($_SESSION['user_id']) ? (is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null) : null;
            $userName = $_SESSION['display_name'] ?? ($_SESSION['name'] ?? 'ผู้ใช้งาน');
            $userRole = $_SESSION['role'] ?? 'guest';
            
            // Look up existing active session first
            $session = findUserActiveSession($pdo, $token, $userId);
            
            if (!$session) {
                // Check if there was a previous closed session (to show history)
                $latest = findUserLatestSession($pdo, $token, $userId);
                if ($latest && $latest['status'] === 'closed') {
                    $session = $latest;
                } else {
                    $session = createNewLiveSession($pdo, $token, $userId, $userName, $userRole);
                }
            } else {
                // If user logged in or updated info, refresh session metadata
                if ($userId && ($session['user_id'] !== $userId || $session['user_name'] !== $userName || $session['user_role'] !== $userRole)) {
                    $upd = $pdo->prepare("UPDATE live_support_sessions SET user_name = ?, user_role = ?, user_id = ? WHERE session_id = ?");
                    $upd->execute([$userName, $userRole, $userId, $session['session_id']]);
                    $session['user_name'] = $userName;
                    $session['user_role'] = $userRole;
                    $session['user_id'] = $userId;
                }
            }
            
            $sessionId = (int)$session['session_id'];
            
            // Calculate accurate queue position if status is queued
            $qInfo = ['position' => 0, 'ahead' => 0, 'total' => 0];
            if ($session['status'] === 'queued') {
                $qInfo = getQueuePositionInfo($pdo, $sessionId);
            }
            
            // Fetch messages
            $msgStmt = $pdo->prepare("SELECT message_id, sender_type, sender_name, message_text, created_at FROM live_support_messages WHERE session_id = ? ORDER BY message_id ASC");
            $msgStmt->execute([$sessionId]);
            $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'status' => $session['status'],
                'is_closed' => ($session['status'] === 'closed'),
                'queue_position' => $qInfo['position'],
                'queue_ahead' => $qInfo['ahead'],
                'queue_total' => $qInfo['total'],
                'assigned_admin_name' => $session['assigned_admin_name'],
                'user_name' => $session['user_name'],
                'user_role' => $session['user_role'],
                'messages' => $messages
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        case 'start_new_session': {
            $token = getSupportToken();
            $userId = !empty($_SESSION['user_id']) ? (is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null) : null;
            $userName = $_SESSION['display_name'] ?? ($_SESSION['name'] ?? 'ผู้ใช้งาน');
            $userRole = $_SESSION['role'] ?? 'guest';
            
            $session = createNewLiveSession($pdo, $token, $userId, $userName, $userRole);
            $sessionId = (int)$session['session_id'];
            
            $msgStmt = $pdo->prepare("SELECT message_id, sender_type, sender_name, message_text, created_at FROM live_support_messages WHERE session_id = ? ORDER BY message_id ASC");
            $msgStmt->execute([$sessionId]);
            $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'status' => 'bot',
                'is_closed' => false,
                'queue_position' => 0,
                'assigned_admin_name' => null,
                'user_name' => $userName,
                'user_role' => $userRole,
                'messages' => $messages
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        case 'send_message': {
            $token = getSupportToken();
            $userId = !empty($_SESSION['user_id']) ? (is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null) : null;
            $userName = $_SESSION['display_name'] ?? ($_SESSION['name'] ?? 'ผู้ใช้งาน');
            $userRole = $_SESSION['role'] ?? 'guest';
            
            $text = trim($_POST['text'] ?? '');
            if ($text === '') {
                echo json_encode(['success' => false, 'error' => 'Message is empty']);
                exit;
            }
            
            // Find active session
            $session = findUserActiveSession($pdo, $token, $userId);
            
            // If session not found or previous session was closed, automatically start a new bot session!
            if (!$session || $session['status'] === 'closed') {
                $session = createNewLiveSession($pdo, $token, $userId, $userName, $userRole);
            }
            
            $sessionId = (int)$session['session_id'];
            $senderDisplayName = !empty($_SESSION['display_name']) ? $_SESSION['display_name'] : $session['user_name'];
            
            // Insert user message
            $stmtMsg = $pdo->prepare("INSERT INTO live_support_messages (session_id, sender_type, sender_name, message_text) VALUES (?, 'user', ?, ?)");
            $stmtMsg->execute([$sessionId, $senderDisplayName, $text]);
            $userMsgId = (int)$pdo->lastInsertId();
            
            // Update session activity
            $pdo->prepare("UPDATE live_support_sessions SET last_activity_at = NOW() WHERE session_id = ?")->execute([$sessionId]);
            
            $botResponse = null;
            // If in bot mode, trigger bot reply
            if ($session['status'] === 'bot') {
                $replyData = getBotReply($text, $session['user_role'] ?: $userRole);
                $stmtBot = $pdo->prepare("INSERT INTO live_support_messages (session_id, sender_type, sender_name, message_text) VALUES (?, 'bot', 'น้องพิงค์ (AI Support)', ?)");
                $stmtBot->execute([$sessionId, $replyData['reply']]);
                $botMsgId = (int)$pdo->lastInsertId();
                
                $botResponse = [
                    'message_id' => $botMsgId,
                    'sender_type' => 'bot',
                    'sender_name' => 'น้องพิงค์ (AI Support)',
                    'message_text' => $replyData['reply'],
                    'suggest_admin' => $replyData['suggest_admin'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'status' => $session['status'],
                'user_message' => [
                    'message_id' => $userMsgId,
                    'sender_type' => 'user',
                    'sender_name' => $senderDisplayName,
                    'message_text' => $text,
                    'created_at' => date('Y-m-d H:i:s')
                ],
                'bot_response' => $botResponse
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        case 'request_admin': {
            $token = getSupportToken();
            $userId = !empty($_SESSION['user_id']) ? (is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null) : null;
            $userName = $_SESSION['display_name'] ?? ($_SESSION['name'] ?? 'ผู้ใช้งาน');
            $userRole = $_SESSION['role'] ?? 'guest';
            
            $session = findUserActiveSession($pdo, $token, $userId);
            
            // If no active session or closed, start a new session before queuing
            if (!$session || $session['status'] === 'closed') {
                $session = createNewLiveSession($pdo, $token, $userId, $userName, $userRole);
            }
            
            $sessionId = (int)$session['session_id'];
            
            // If already active with admin
            if ($session['status'] === 'active') {
                echo json_encode([
                    'success' => true,
                    'session_id' => $sessionId,
                    'status' => 'active',
                    'assigned_admin_name' => $session['assigned_admin_name']
                ]);
                exit;
            }
            
            // Transition to queued (only update queued_at if not already queued)
            if ($session['status'] !== 'queued' || empty($session['queued_at'])) {
                $upd = $pdo->prepare("UPDATE live_support_sessions SET status = 'queued', queued_at = NOW(), last_activity_at = NOW() WHERE session_id = ?");
                $upd->execute([$sessionId]);
            } else {
                $pdo->prepare("UPDATE live_support_sessions SET last_activity_at = NOW() WHERE session_id = ?")->execute([$sessionId]);
            }
            
            // Queue number based on strict FIFO
            $queueInfo = getQueuePositionInfo($pdo, $sessionId);
            $queuePos = $queueInfo['position'];
            $queueAhead = $queueInfo['ahead'];
            
            if ($queueAhead === 0) {
                $queueNotice = "🛎️ **คุณอยู่ในคิวรอการติดต่อ ลำดับที่ 1 (ถึงคิวของคุณแล้ว)**\nระบบได้ส่งสัญญาณแจ้งเตือนไปยังเจ้าหน้าที่ Super Admin เรียบร้อยแล้ว กำลังเตรียมเข้าร่วมห้องสนทนาสักครู่ครับ...";
            } else {
                $queueNotice = "🛎️ **คุณอยู่ในคิวรอการติดต่อ ลำดับที่ {$queuePos}** (มีคิวรอข้างหน้า {$queueAhead} คิว)\nระบบจัดคิวตามลำดับเวลาจริง เจ้าหน้าที่จะให้บริการตามคิว กรุณารอสักครู่นะครับ 🌸";
            }
            
            $stmtMsg = $pdo->prepare("INSERT INTO live_support_messages (session_id, sender_type, sender_name, message_text) VALUES (?, 'system', 'ระบบวนวรรณ', ?)");
            $stmtMsg->execute([$sessionId, $queueNotice]);
            
            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'status' => 'queued',
                'queue_position' => $queuePos,
                'queue_ahead' => $queueAhead,
                'queue_total' => $queueInfo['total'],
                'system_message' => $queueNotice
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
            
        case 'cancel_queue': {
            $token = getSupportToken();
            $userId = !empty($_SESSION['user_id']) ? (is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null) : null;
            $session = findUserActiveSession($pdo, $token, $userId) ?? findUserLatestSession($pdo, $token, $userId);
            
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'Session not found']);
                exit;
            }
            
            $sessionId = (int)$session['session_id'];
            
            // Only cancel if status is queued
            if ($session['status'] === 'queued') {
                $upd = $pdo->prepare("UPDATE live_support_sessions SET status = 'bot', queued_at = NULL, last_activity_at = NOW() WHERE session_id = ?");
                $upd->execute([$sessionId]);
                
                $cancelNotice = "🚫 **ยกเลิกการรอคิวเรียบร้อยแล้ว**\nคุณสามารถสอบถามข้อมูลกับน้องพิงค์ AI ได้ตามปกติ หรือกดขอรับคิวใหม่ได้ทุกเมื่อค่ะ 🌸";
                $stmtMsg = $pdo->prepare("INSERT INTO live_support_messages (session_id, sender_type, sender_name, message_text) VALUES (?, 'system', 'ระบบวนวรรณ', ?)");
                $stmtMsg->execute([$sessionId, $cancelNotice]);
                $newMsgId = (int)$pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'status' => 'bot',
                    'system_message' => [
                        'message_id' => $newMsgId,
                        'sender_type' => 'system',
                        'sender_name' => 'ระบบวนวรรณ',
                        'message_text' => $cancelNotice,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            echo json_encode(['success' => true, 'status' => $session['status']]);
            exit;
        }

        case 'poll': {
            $token = getSupportToken();
            $userId = !empty($_SESSION['user_id']) ? (is_numeric($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null) : null;
            $session = findUserActiveSession($pdo, $token, $userId) ?? findUserLatestSession($pdo, $token, $userId);
            
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'Session not found']);
                exit;
            }
            
            $sessionId = (int)$session['session_id'];
            $lastId = (int)($_GET['last_id'] ?? 0);
            
            // Calculate accurate queue position if queued
            $qInfo = ['position' => 0, 'ahead' => 0, 'total' => 0];
            if ($session['status'] === 'queued') {
                $qInfo = getQueuePositionInfo($pdo, $sessionId);
            }
            
            // Fetch messages newer than last_id
            $msgStmt = $pdo->prepare("SELECT message_id, sender_type, sender_name, message_text, created_at FROM live_support_messages WHERE session_id = ? AND message_id > ? ORDER BY message_id ASC");
            $msgStmt->execute([$sessionId, $lastId]);
            $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'status' => $session['status'],
                'queue_position' => $qInfo['position'],
                'queue_ahead' => $qInfo['ahead'],
                'queue_total' => $qInfo['total'],
                'assigned_admin_name' => $session['assigned_admin_name'],
                'messages' => $messages
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ==========================================
        // SUPER ADMIN ACTIONS
        // ==========================================
        case 'admin_get_queue': {
            if (empty($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            // Fetch queued sessions (strict FIFO by queued_at ASC, session_id ASC)
            $queued = $pdo->query("
                SELECT s.*, 
                       (SELECT message_text FROM live_support_messages m WHERE m.session_id = s.session_id ORDER BY m.message_id DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM live_support_messages m WHERE m.session_id = s.session_id ORDER BY m.message_id DESC LIMIT 1) as last_message_time,
                       TIMESTAMPDIFF(MINUTE, s.queued_at, NOW()) as wait_minutes
                FROM live_support_sessions s
                WHERE s.status = 'queued'
                ORDER BY s.queued_at ASC, s.session_id ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch active sessions
            $active = $pdo->query("
                SELECT s.*, 
                       (SELECT message_text FROM live_support_messages m WHERE m.session_id = s.session_id ORDER BY m.message_id DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM live_support_messages m WHERE m.session_id = s.session_id ORDER BY m.message_id DESC LIMIT 1) as last_message_time
                FROM live_support_sessions s
                WHERE s.status = 'active'
                ORDER BY s.last_activity_at DESC
                LIMIT 50
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            // Fetch history: bot + closed sessions (recent 50)
            $bot = $pdo->query("
                SELECT s.*, 
                       (SELECT message_text FROM live_support_messages m WHERE m.session_id = s.session_id ORDER BY m.message_id DESC LIMIT 1) as last_message,
                       (SELECT created_at FROM live_support_messages m WHERE m.session_id = s.session_id ORDER BY m.message_id DESC LIMIT 1) as last_message_time
                FROM live_support_sessions s
                WHERE s.status IN ('bot', 'closed')
                ORDER BY s.last_activity_at DESC
                LIMIT 50
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'queued' => $queued,
                'active' => $active,
                'bot' => $bot,
                'admin_name' => $_SESSION['display_name'] ?? 'Super Admin'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        case 'admin_claim': {
            if (empty($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $adminName = $_SESSION['display_name'] ?? 'Admin Wonwan';
            $adminId = (string)($_SESSION['user_id'] ?? 'admin');
            
            $upd = $pdo->prepare("UPDATE live_support_sessions SET status = 'active', assigned_admin_id = ?, assigned_admin_name = ?, connected_at = NOW(), last_activity_at = NOW() WHERE session_id = ?");
            $upd->execute([$adminId, $adminName, $sessionId]);
            
            // Post system announcement
            $sysMsg = "🔔 **เจ้าหน้าที่ Super Admin: {$adminName}** ได้เข้าร่วมการสนทนาแล้ว ยินดีให้บริการครับ!";
            $stmtMsg = $pdo->prepare("INSERT INTO live_support_messages (session_id, sender_type, sender_name, message_text) VALUES (?, 'system', 'ระบบวนวรรณ', ?)");
            $stmtMsg->execute([$sessionId, $sysMsg]);
            
            echo json_encode([
                'success' => true,
                'session_id' => $sessionId,
                'assigned_admin_name' => $adminName
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        case 'admin_send_message': {
            if (empty($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $text = trim($_POST['text'] ?? '');
            $adminName = $_SESSION['display_name'] ?? 'Admin Wonwan';
            $adminId = (string)($_SESSION['user_id'] ?? 'admin');
            
            if ($text === '') {
                echo json_encode(['success' => false, 'error' => 'Text is empty']);
                exit;
            }
            
            // Update admin assignment if not set
            $pdo->prepare("UPDATE live_support_sessions SET status = 'active', assigned_admin_id = ?, assigned_admin_name = ?, last_activity_at = NOW() WHERE session_id = ?")
                ->execute([$adminId, $adminName, $sessionId]);
            
            $stmtMsg = $pdo->prepare("INSERT INTO live_support_messages (session_id, sender_type, sender_name, message_text) VALUES (?, 'admin', ?, ?)");
            $stmtMsg->execute([$sessionId, $adminName, $text]);
            $msgId = (int)$pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message_id' => $msgId,
                'sender_name' => $adminName,
                'message_text' => $text,
                'created_at' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        case 'admin_close_session': {
            if (empty($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $adminName = $_SESSION['display_name'] ?? 'Super Admin';
            
            $pdo->prepare("UPDATE live_support_sessions SET status = 'closed', last_activity_at = NOW() WHERE session_id = ?")->execute([$sessionId]);
            
            $closeMsg = "✨ การสนทนากับเจ้าหน้าที่ Super Admin: {$adminName} สิ้นสุดลงแล้ว ขอขอบคุณที่ใช้บริการวนวรรณครับ หากมีข้อสงสัยเพิ่มเติมสามารถทักแชทใหม่ได้เสมอครับ 🙏";
            $stmtMsg = $pdo->prepare("INSERT INTO live_support_messages (session_id, sender_type, sender_name, message_text) VALUES (?, 'system', 'ระบบวนวรรณ', ?)");
            $stmtMsg->execute([$sessionId, $closeMsg]);
            
            echo json_encode(['success' => true]);
            exit;
        }

        case 'admin_get_messages': {
            if (empty($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                exit;
            }
            
            $sessionId = (int)($_GET['session_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM live_support_sessions WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$session) {
                echo json_encode(['success' => false, 'error' => 'Not found']);
                exit;
            }
            
            $msgStmt = $pdo->prepare("SELECT * FROM live_support_messages WHERE session_id = ? ORDER BY message_id ASC");
            $msgStmt->execute([$sessionId]);
            $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'session' => $session,
                'messages' => $messages
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            exit;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
