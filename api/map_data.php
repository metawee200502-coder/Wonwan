<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? 'books';

try {
    if ($type === 'shops') {
        // ดึงพิกัดผู้ใช้และร้านค้าแบบไม่ซ้ำ (หนึ่งผู้ใช้/ร้านค้า = หนึ่งพิกัด)
        $stmt = $pdo->query("
            SELECT 
                id, 
                MAX(name) as name, 
                MAX(profile_image) as profile_image, 
                MAX(latitude) as latitude, 
                MAX(longitude) as longitude, 
                MAX(followers_count) as followers_count, 
                partner_type 
            FROM (
                SELECT 
                    user_id as id, 
                    COALESCE(NULLIF(display_name, ''), email) as name, 
                    profile_image, 
                    latitude, 
                    longitude, 
                    followers_count, 
                    'user' as partner_type
                FROM users 
                WHERE latitude IS NOT NULL AND longitude IS NOT NULL
                
                UNION
                
                SELECT 
                    shop_id as id, 
                    shop_name as name, 
                    profile_image, 
                    latitude, 
                    longitude, 
                    followers_count, 
                    'shop' as partner_type
                FROM shops 
                WHERE status = 'approved' AND latitude IS NOT NULL AND longitude IS NOT NULL
                
                UNION

                -- ดึงพิกัดเพิ่มเติมจากหนังสือที่ผู้ใช้/ร้านค้าเคยลงขาย กรณีไม่ได้ตั้งค่าพิกัดโปรไฟล์
                SELECT 
                    b.seller_id as id,
                    COALESCE(NULLIF(u.display_name, ''), u.email) as name,
                    u.profile_image,
                    b.seller_lat as latitude,
                    b.seller_lng as longitude,
                    u.followers_count,
                    'user' as partner_type
                FROM books b
                JOIN users u ON b.seller_type = 'user' AND b.seller_id = u.user_id
                WHERE b.seller_lat IS NOT NULL AND b.seller_lng IS NOT NULL AND u.latitude IS NULL

                UNION

                SELECT 
                    b.seller_id as id,
                    s.shop_name as name,
                    s.profile_image,
                    b.seller_lat as latitude,
                    b.seller_lng as longitude,
                    s.followers_count,
                    'shop' as partner_type
                FROM books b
                JOIN shops s ON b.seller_type = 'shop' AND b.seller_id = s.shop_id
                WHERE s.status = 'approved' AND b.seller_lat IS NOT NULL AND b.seller_lng IS NOT NULL AND s.latitude IS NULL
            ) as all_locations
            GROUP BY partner_type, id
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $results]);
    } else {
        // ดึงพิกัดหนังสือ
        $stmt = $pdo->query("
            SELECT 
                b.book_id, 
                b.title, 
                b.price, 
                b.image_cover, 
                b.seller_lat as latitude, 
                b.seller_lng as longitude, 
                b.seller_type, 
                b.seller_id,
                b.condition_level,
                CASE 
                    WHEN b.seller_type = 'user' THEN COALESCE(NULLIF(u.display_name, ''), u.email)
                    WHEN b.seller_type = 'shop' THEN s.shop_name
                    ELSE 'ไม่ทราบชื่อ'
                END as seller_name
            FROM books b
            LEFT JOIN users u ON b.seller_type = 'user' AND b.seller_id = u.user_id
            LEFT JOIN shops s ON b.seller_type = 'shop' AND b.seller_id = s.shop_id
            WHERE b.status = 'available' AND b.seller_lat IS NOT NULL AND b.seller_lng IS NOT NULL
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // เราควรทำ group หนังสือที่พิกัดเดียวกันหรือเปล่า? (สำหรับ prototype เอาแบบง่ายๆ ก่อน Leaflet มี Spiderfy ถ้าพิกัดทับกัน)
        echo json_encode(['status' => 'success', 'data' => $results]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
