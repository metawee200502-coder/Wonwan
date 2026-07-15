<?php
session_start();
// เรียกไฟล์ config.php เพื่อเชื่อมต่อฐานข้อมูล
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // ดึงข้อมูลผู้ใช้
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // ตรวจสอบว่าพบผู้ใช้หรือไม่
        if ($user) {
            // ตรวจสอบรหัสผ่าน
            if (password_verify($password, $user['password_hash'])) {
                // ล็อกอินสำเร็จ
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['email']   = $user['email'];

                // แยกทางไปหน้าต่างๆ ตาม role
                switch ($user['role']) {
                    case 'publisher_admin':
                        // เช็คว่า user คนนี้ถูกผูกกับสำนักพิมพ์ไหนหรือยัง
                        $stmtPub = $pdo->prepare(
                            "SELECT p.publisher_id, p.company_name 
                             FROM publisher_staff ps
                             JOIN publishers p ON ps.publisher_id = p.publisher_id
                             WHERE ps.user_id = :uid LIMIT 1"
                        );
                        $stmtPub->execute(['uid' => $user['user_id']]);
                        $publisher = $stmtPub->fetch(PDO::FETCH_ASSOC);

                        if ($publisher) {
                            $_SESSION['publisher_id'] = $publisher['publisher_id'];
                            header("Location: ../pages/dashboard.php");
                        } else {
                            // ยังไม่มีข้อมูลสำนักพิมพ์ผูกกับ user นี้
                            header("Location: ../pages/publisher_setup.php");
                        }
                        break;

                    case 'shop_admin':
                        // เช็คว่า user คนนี้ถูกผูกกับร้านไหนหรือยัง
                        $stmtShop = $pdo->prepare(
                            "SELECT s.shop_id, s.shop_name 
                             FROM shop_staff ss
                             JOIN shops s ON ss.shop_id = s.shop_id
                             WHERE ss.user_id = :uid LIMIT 1"
                        );
                        $stmtShop->execute(['uid' => $user['user_id']]);
                        $shop = $stmtShop->fetch(PDO::FETCH_ASSOC);

                        if ($shop) {
                            $_SESSION['shop_id'] = $shop['shop_id'];
                            header("Location: ../pages/dashboard.php");
                        } else {
                            // ยังไม่มีข้อมูลร้านผูกกับ user นี้
                            header("Location: ../pages/shop_setup.php");
                        }
                        break;

                    default:
                        // reader ทั่วไป
                        header("Location: ../pages/home.php");
                        break;
                }
                exit();
            } else {
                echo "<script>alert('รหัสผ่านไม่ถูกต้อง'); window.location='login.php';</script>";
            }
        } else {
            echo "<script>alert('ไม่พบอีเมลนี้ในระบบ'); window.location='login.php';</script>";
        }
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}
?>