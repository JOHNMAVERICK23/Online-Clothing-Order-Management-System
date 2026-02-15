<?php
session_start();
require_once 'db_config.php';
require_once 'xendit_config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    exit(json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode([
        'success' => false,
        'message' => 'Invalid request'
    ]));
}

$orderId = intval($_POST['order_id'] ?? 0);

if (!$orderId) {
    exit(json_encode([
        'success' => false,
        'message' => 'Order ID required'
    ]));
}

try {
    // Get payment info from database
    $paymentStmt = $conn->prepare("
        SELECT p.*, o.order_number, o.total_amount, o.payment_status
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        WHERE p.order_id = ?
    ");
    $paymentStmt->bind_param("i", $orderId);
    $paymentStmt->execute();
    $paymentResult = $paymentStmt->get_result();
    
    if ($paymentResult->num_rows === 0) {
        exit(json_encode([
            'success' => false,
            'message' => 'Payment record not found'
        ]));
    }
    
    $payment = $paymentResult->fetch_assoc();
    
    // Check status with Xendit if not already paid
    if ($payment['status'] !== 'paid') {
        $xenditResponse = get_xendit_invoice($payment['xendit_invoice_id']);
        
        if ($xenditResponse['success'] && isset($xenditResponse['data']['status'])) {
            $xenditStatus = strtolower($xenditResponse['data']['status']);
            
            // Map Xendit status to our status
            $statusMap = [
                'paid' => 'paid',
                'expired' => 'expired',
                'pending' => 'pending',
                'failed' => 'failed'
            ];
            
            $newStatus = $statusMap[$xenditStatus] ?? $payment['status'];
            
            // Update if status changed
            if ($newStatus !== $payment['status']) {
                $updateStmt = $conn->prepare("
                    UPDATE payments 
                    SET status = ?, updated_at = NOW()
                    WHERE order_id = ?
                ");
                $updateStmt->bind_param("si", $newStatus, $orderId);
                $updateStmt->execute();
                
                // If paid, update order status
                if ($newStatus === 'paid') {
                    $orderStatus = 'processing';
                    $orderStmt = $conn->prepare("
                        UPDATE orders 
                        SET payment_status = ?, status = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $orderStmt->bind_param("ssi", $newStatus, $orderStatus, $orderId);
                    $orderStmt->execute();
                    
                    $payment['status'] = $newStatus;
                }
            }
        }
    }
    
    exit(json_encode([
        'success' => true,
        'payment' => [
            'order_id' => $payment['order_id'],
            'order_number' => $payment['order_number'],
            'status' => $payment['status'],
            'amount' => $payment['amount'],
            'paid_amount' => $payment['paid_amount'],
            'payment_method' => $payment['payment_method'],
            'created_at' => $payment['created_at'],
            'paid_at' => $payment['paid_at']
        ],
        'message' => 'Status: ' . strtoupper($payment['status'])
    ]));
    
} catch (Exception $e) {
    exit(json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]));
}
?>