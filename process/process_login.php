<?php
session_start();
require_once '../config/config.php';

// ตรวจ base path: vhost → '' | subdirectory (ngrok/localhost) → '/wanawan_project'
$_scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$_basePath   = str_contains($_scriptName, '/wanawan_project/') ? '/wanawan_project' : '';
$_loginUrl   = $_basePath . '/pages/login.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        // ดึงข้อมูลผู้ใช้ (รองรับทั้งอีเมลและ Username)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email OR username = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // ตรวจสอบว่าพบผู้ใช้หรือไม่
        if ($user) {
            // ตรวจสอบการแบน
            $is_banned = (bool)$user['is_banned'];
            $ban_until = $user['ban_until'] ? strtotime($user['ban_until']) : null;
            if ($is_banned || ($ban_until && $ban_until > time())) {
                header("Location: ../pages/login.php?error=banned");
                exit;
            }

            // ตรวจสอบรหัสผ่าน
            if (password_verify($password, $user['password_hash'])) {
                // ล็อกอินสำเร็จ
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['display_name'] = $user['display_name'] ?: $user['email'];
                $_SESSION['avatar'] = !empty($user['profile_image']) ? '/pages/' . ltrim($user['profile_image'], '/') : '';

                if (empty($user['latitude']) || empty($user['longitude'])) {
                    $_SESSION['needs_location'] = true;
                }

                // แยกทางไปหน้าต่างๆ ตาม role
                switch ($user['role']) {
                    case 'publisher_admin':
                        // เช็คว่า user คนนี้ถูกผูกกับสำนักพิมพ์ไหนหรือยัง
                        $stmtPub = $pdo->prepare(
                            "SELECT p.publisher_id, p.company_name, p.status 
                             FROM publisher_staff ps
                             JOIN publishers p ON ps.publisher_id = p.publisher_id
                             WHERE ps.user_id = :uid LIMIT 1"
                        );
                        $stmtPub->execute(['uid' => $user['user_id']]);
                        $publisher = $stmtPub->fetch(PDO::FETCH_ASSOC);

                        if ($publisher) {
                            if ($publisher['status'] === 'pending') {
                                // ยังไม่อนุมัติ ล้าง session ที่สร้างไปแล้ว
                                session_destroy();
                                header("Location: ../pages/login.php?error=pending");
                                exit;
                            }
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
                            "SELECT s.shop_id, s.shop_name, s.status 
                             FROM shop_staff ss
                             JOIN shops s ON ss.shop_id = s.shop_id
                             WHERE ss.user_id = :uid LIMIT 1"
                        );
                        $stmtShop->execute(['uid' => $user['user_id']]);
                        $shop = $stmtShop->fetch(PDO::FETCH_ASSOC);

                        if ($shop) {
                            if ($shop['status'] === 'pending') {
                                // ยังไม่อนุมัติ ล้าง session ที่สร้างไปแล้ว
                                session_destroy();
                                header("Location: ../pages/login.php?error=pending");
                                exit;
                            }
                            $_SESSION['shop_id'] = $shop['shop_id'];
                            header("Location: ../pages/dashboard.php");
                        } else {
                            // ยังไม่มีข้อมูลร้านผูกกับ user นี้
                            header("Location: ../pages/shop_setup.php");
                        }
                        break;

                    case 'super_admin':
                        header("Location: ../admin/index.php");
                        break;

                    default:
                        // reader ทั่วไป
                        $redirect = (string)($_SESSION['login_redirect'] ?? '');
                        unset($_SESSION['login_redirect']);
                        if ($redirect !== '' && str_starts_with($redirect, '/') && !str_starts_with($redirect, '//')) {
                            header('Location: ' . $redirect);
                        } else {
                            header("Location: ../pages/home.php");
                        }
                        break;
                }
                exit();
            } else {
                header('Location: ' . $_loginUrl . '?error=wrong_password');
                exit;
            }
        } else {
            // ตรวจสอบในตาราง admins (Sub-Admin)
            $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE email = :email OR username = :email LIMIT 1");
            $stmtAdmin->execute(['email' => $email]);
            $adminUser = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

            if ($adminUser) {
                if ($adminUser['status'] === 'suspended') {
                    header("Location: ../pages/login.php?error=banned");
                    exit;
                }
                
                if (password_verify($password, $adminUser['password_hash'])) {
                    $_SESSION['user_id'] = 'ADMIN_' . $adminUser['admin_id'];
                    $_SESSION['role']    = 'super_admin';
                    $_SESSION['email']   = $adminUser['email'];
                    $_SESSION['display_name'] = $adminUser['name'];
                    $_SESSION['avatar'] = '';
                    
                    header("Location: ../admin/index.php");
                    exit;
                } else {
                    header('Location: ' . $_loginUrl . '?error=wrong_password');
                    exit;
                }
            } else {
                header('Location: ' . $_loginUrl . '?error=not_found');
                exit;
            }
        }
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาด: " . $e->getMessage());
    }
}
?>
