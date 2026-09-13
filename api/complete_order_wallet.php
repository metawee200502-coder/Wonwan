<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = $_POST['order_id'] ?? null;
    
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'Missing order_id']);
        exit;
    }

    try {
        // เริ่มต้น Transaction เพื่อป้องกันข้อมูลพังกรณีมี error
        $pdo->beginTransaction();

        // 1. ดึงข้อมูลออเดอร์
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? FOR UPDATE"); // Lock row
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            throw new Exception('Order not found');
        }

        if ($order['status'] !== 'SHIPPED') {
            throw new Exception('Cannot complete order. Order must be SHIPPED.');
        }

        $shopId = $order['shop_id']; // สมมติว่ามี column shop_id ใน orders
        
        // สมมติค่าต่างๆ ถ้าใน Database ของจริงไม่มีให้ใช้ค่าสำรอง (ทดสอบ)
        $productSubtotal = isset($order['product_subtotal']) ? (float)$order['product_subtotal'] : 0.00;
        $shippingFee = isset($order['shipping_fee']) ? (float)$order['shipping_fee'] : 0.00;
        
        // 2. คำนวณ GP (10%)
        $gpRate = 0.10;
        $gpAmount = $productSubtotal * $gpRate;
        $vendorProductRevenue = $productSubtotal - $gpAmount;
        $netPayout = $vendorProductRevenue + $shippingFee;

        // 3. อัปเดตตาราง orders
        $updateOrderStmt = $pdo->prepare("UPDATE orders SET status = 'COMPLETED', gp_amount = ?, net_payout = ? WHERE order_id = ?");
        $updateOrderStmt->execute([$gpAmount, $netPayout, $orderId]);

        // 4. ตรวจสอบว่าร้านนี้มี wallet หรือยัง
        $walletStmt = $pdo->prepare("SELECT wallet_id FROM shop_wallets WHERE shop_id = ? FOR UPDATE");
        $walletStmt->execute([$shopId]);
        $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);

        $walletId = null;
        if (!$wallet) {
            // สร้าง Wallet ใหม่ถ้าร้านยังไม่มี
            $createWalletStmt = $pdo->prepare("INSERT INTO shop_wallets (shop_id, available_balance) VALUES (?, ?)");
            $createWalletStmt->execute([$shopId, $netPayout]);
            $walletId = $pdo->lastInsertId();
        } else {
            $walletId = $wallet['wallet_id'];
            // อัปเดต Wallet เดิม
            $updateWalletStmt = $pdo->prepare("UPDATE shop_wallets SET available_balance = available_balance + ? WHERE wallet_id = ?");
            $updateWalletStmt->execute([$netPayout, $walletId]);
        }

        // 5. สร้าง Transaction Log
        $txnStmt = $pdo->prepare("INSERT INTO wallet_transactions (wallet_id, order_id, type, amount, status) VALUES (?, ?, 'EARNING', ?, 'SUCCESS')");
        $txnStmt->execute([$walletId, $orderId, $netPayout]);

        // จบ Transaction
        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Order completed and payout transferred to vendor wallet successfully.',
            'payout_amount' => $netPayout
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
