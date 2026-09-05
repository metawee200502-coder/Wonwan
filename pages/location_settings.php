<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /pages/login.php');
    exit;
}
require_once __DIR__ . '/../config/config.php';
$userId = (int)$_SESSION['user_id'];
$errors = [];
$success = '';

$stmt = $pdo->prepare('SELECT latitude, longitude FROM users WHERE user_id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $lng = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    
    if ($lat && $lng) {
        $pdo->prepare('UPDATE users SET latitude=?, longitude=? WHERE user_id=?')->execute([$lat, $lng, $userId]);
        $success = 'อัพเดทตำแหน่งสำเร็จ';
        $user['latitude'] = $lat;
        $user['longitude'] = $lng;
    } else {
        $errors[] = 'ไม่สามารถดึงข้อมูลตำแหน่งได้';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ตั้งค่าตำแหน่ง - วนวรรณ</title>
    <link rel="stylesheet" href="../assets/css/styles.css?v=<?= filemtime(__DIR__.'/../assets/css/styles.css') ?>">
    <style>
        * { box-sizing: border-box; }
        .account { display: flex; max-width: 1280px; margin: auto; }
        .content { flex: 1; padding: 48px 70px 90px; }
        .form-card { max-width: 600px; margin: auto; border: 1px solid var(--secondary-color); border-radius: 20px; padding: 38px 60px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .field { margin: 18px 0; }
        .field label { display: block; font-weight: 600; color: var(--text-muted); margin-bottom: 7px; white-space: nowrap; }
        .field input { width: 100%; border: 1px solid #ccd3c5; border-radius: 8px; padding: 13px; font: inherit; background: #faf9f8; }
        .security { color: var(--secondary-color); margin-top: 0; margin-bottom: 15px; }
        .error { background: #fde5e1; color: #9b443c; padding: 14px; border-radius: 9px; margin-bottom: 20px; }
        .success { background: #eaf5e1; color: #2d5a27; padding: 14px; border-radius: 9px; margin-bottom: 20px; }
        .btn-fetch { background: var(--secondary-color); color: #fff; border: 0; height: 52px; margin-bottom: 20px; padding: 0 15px; border-radius: 8px; font-family: inherit; font-weight: 600; cursor: pointer; transition: 0.2s; width: 100%; font-size: 16px; }
        .btn-fetch:hover { opacity: 0.9; }
        .btn-fetch:disabled { opacity: 0.7; cursor: not-allowed; }
        @media(max-width:1024px) { .account { display: block; } .content { padding: 30px 16px; } .form-card { padding: 28px 20px; } .grid { grid-template-columns: 1fr; } }
        
        #map { width: 100%; height: 300px; border-radius: 8px; margin-top: 15px; border: 1px solid #ccd3c5; z-index: 1; }
    </style>
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
</head>
<body>
    <?php require __DIR__.'/../components/header.php'; ?>
    <div class="account">
        <?php $profile_active = 'location'; require __DIR__.'/../components/profile_sidebar.php'; ?>
        <main class="content">
            <form class="form-card" method="post" id="locationForm">
                <?php if($errors): ?>
                    <div class="error"><?=implode('<br>', array_map('htmlspecialchars', $errors))?></div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="success"><?=htmlspecialchars($success)?></div>
                <?php endif; ?>

                <h2 class="security" style="display: flex; align-items: center; gap: 8px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    ตำแหน่งปัจจุบันของคุณ
                </h2>
                <p style="color:var(--text-muted);font-size:14px;margin:0 0 25px;line-height:1.6;">
                    พิกัดนี้จะใช้คำนวณระยะทางในระบบแลกเปลี่ยนและนัดรับหนังสือมือสอง ช่วยให้เพื่อนบ้านค้นหาหนังสือของคุณได้ง่ายขึ้น<br>
                    <strong>หมายเหตุ:</strong> ระบบจะจดจำตำแหน่งนี้เป็นจุดอ้างอิงหลักสำหรับร้านหนังสือของคุณ
                </p>

                <div class="grid">
                    <div class="field">
                        <label>ละติจูด (Latitude)</label>
                        <input type="text" name="latitude" id="inputLat" value="<?=htmlspecialchars($user['latitude']??'')?>" readonly placeholder="ยังไม่ได้ระบุ">
                    </div>
                    <div class="field">
                        <label>ลองจิจูด (Longitude)</label>
                        <input type="text" name="longitude" id="inputLng" value="<?=htmlspecialchars($user['longitude']??'')?>" readonly placeholder="ยังไม่ได้ระบุ">
                    </div>
                </div>

                <div id="addressDisplay" style="margin-top:15px; padding:15px; background:#f0f7f4; border:1px solid #cce0d6; border-radius:8px; display:none; align-items:flex-start; gap:10px; color:#3a5a4c; font-size:14px; line-height:1.5;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; margin-top:2px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <div>
                        <strong style="display:block; margin-bottom:4px;">สถานที่โดยประมาณ:</strong>
                        <span id="addressText">กำลังค้นหาสถานที่...</span>
                        <div style="font-size:12px; color:#666; margin-top:6px;">* หากตำแหน่งไม่ตรง สามารถลากหมุดบนแผนที่เพื่อปรับแก้ได้</div>
                    </div>
                </div>
                
                <!-- Map Container -->
                <div id="map" style="display: none;"></div>

                <div style="margin-top: 30px;" id="actionButtons">
                    <button type="button" class="btn-fetch" id="btnFetch" onclick="getLocationForReview()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-5px; margin-right:6px;">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        อัพเดทตำแหน่งล่าสุดของฉัน
                    </button>
                    <button type="button" class="btn-fetch" id="btnSubmit" onclick="document.getElementById('locationForm').submit()" style="display:none; background:#2d5a27;">
                        ยืนยันและบันทึกตำแหน่งนี้
                    </button>
                    <button type="button" class="btn-fetch" id="btnCancel" onclick="location.reload()" style="display:none; background:#f3f4f6; color:var(--text-dark); border:1px solid #ccd3c5;">
                        ยกเลิก
                    </button>
                </div>
            </form>
        </main>
    </div>
    <?php require __DIR__.'/../components/footer.php'; ?>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    let map = null;
    let marker = null;

    function initMap(lat, lng) {
        document.getElementById('map').style.display = 'block';
        
        if (!map) {
            map = L.map('map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            marker = L.marker([lat, lng], { draggable: true }).addTo(map);

            // When marker is dragged, update inputs and geocode again
            marker.on('dragend', function(e) {
                const position = marker.getLatLng();
                document.getElementById('inputLat').value = position.lat.toFixed(8);
                document.getElementById('inputLng').value = position.lng.toFixed(8);
                geocodeLatLng(position.lat, position.lng);
                
                // Show save buttons
                document.getElementById('btnFetch').style.display = 'none';
                document.getElementById('btnSubmit').style.display = 'block';
                document.getElementById('btnCancel').style.display = 'block';
            });
        } else {
            map.setView([lat, lng], 15);
            marker.setLatLng([lat, lng]);
            setTimeout(() => map.invalidateSize(), 200);
        }
    }

    function geocodeLatLng(lat, lng) {
        document.getElementById('addressDisplay').style.display = 'flex';
        document.getElementById('addressText').innerText = 'กำลังประมวลผลสถานที่...';
        
        fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=th`)
            .then(res => res.json())
            .then(data => {
                if (data && data.address) {
                    let addressParts = [];
                    if (data.address.suburb || data.address.village || data.address.hamlet) {
                        addressParts.push(data.address.suburb || data.address.village || data.address.hamlet);
                    }
                    if (data.address.town || data.address.city_district || data.address.district || data.address.county) {
                        addressParts.push(data.address.town || data.address.city_district || data.address.district || data.address.county);
                    }
                    if (data.address.state || data.address.province) {
                        addressParts.push(data.address.state || data.address.province);
                    }
                    
                    let addressString = addressParts.join(', ');
                    
                    if (addressString) {
                        document.getElementById('addressText').innerText = addressString;
                    } else {
                        document.getElementById('addressText').innerText = data.display_name || 'ระบุสถานที่ได้ไม่ชัดเจน แต่อยู่ในพิกัดที่ถูกต้อง';
                    }
                } else {
                    document.getElementById('addressText').innerText = 'ไม่สามารถระบุชื่อสถานที่ได้ (ใช้พิกัดแทน)';
                }
            })
            .catch(err => {
                console.error('Geocoding error:', err);
                document.getElementById('addressText').innerText = 'ไม่สามารถเชื่อมต่อระบบแผนที่ได้ในขณะนี้';
            });
    }

    // เมื่อโหลดหน้า ให้แสดงสถานที่ปัจจุบันถ้ามี
    document.addEventListener('DOMContentLoaded', function() {
        const lat = document.getElementById('inputLat').value;
        const lng = document.getElementById('inputLng').value;
        if (lat && lng) {
            geocodeLatLng(lat, lng);
            initMap(lat, lng);
        }
    });

    function getLocationForReview() {
        const btn = document.getElementById('btnFetch');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'กำลังดึงตำแหน่ง...';
        btn.disabled = true;
        
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(position => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                document.getElementById('inputLat').value = lat.toFixed(8);
                document.getElementById('inputLng').value = lng.toFixed(8);
                
                // สลับปุ่ม
                btn.style.display = 'none';
                document.getElementById('btnSubmit').style.display = 'block';
                document.getElementById('btnCancel').style.display = 'block';
                
                // ดึงชื่อสถานที่ และอัพเดทแผนที่
                geocodeLatLng(lat, lng);
                initMap(lat, lng);
                
            }, error => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                let msg = 'ไม่สามารถดึงตำแหน่งได้ ';
                if(error.code === 1) msg += 'กรุณาอนุญาตให้เบราว์เซอร์เข้าถึงตำแหน่ง (Location Permission)';
                alert(msg);
            });
        } else {
            alert('เบราว์เซอร์ของคุณไม่รองรับการดึงตำแหน่งที่ตั้ง');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    </script>
</body>
</html>
