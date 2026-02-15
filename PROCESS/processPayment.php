<?php
session_start();
require_once 'db_config.php';
require_once 'xendit_config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    exit(json_encode([
        'success' => false,
        'message' => 'Please log in to process payment'
    ]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]));
}

$orderId = intval($_POST['order_id'] ?? 0);
$paymentMethod = trim($_POST['payment_method'] ?? '');

if (!$orderId) {
    exit(json_encode([
        'success' => false,
        'message' => 'Order ID is required'
    ]));
}

// Verify payment method
$validMethods = ['credit_card', 'bank_transfer', 'ewallet', 'retail'];
if (!in_array($paymentMethod, $validMethods)) {
    exit(json_encode([
        'success' => false,
        'message' => 'Invalid payment method'
    ]));
}

try {
    // Get order details
    $orderStmt = $conn->prepare("
        SELECT o.*, 
               GROUP_CONCAT(CONCAT(p.product_name, ' x', oi.quantity) SEPARATOR ', ') as items
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE o.id = ?
        GROUP BY o.id
    ");
    $orderStmt->bind_param("i", $orderId);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    
    if ($orderResult->num_rows === 0) {
        exit(json_encode([
            'success' => false,
            'message' => 'Order not found'
        ]));
    }
    
    $order = $orderResult->fetch_assoc();
    
    // Check if order already has payment in progress
    if ($order['payment_status'] === 'paid') {
        exit(json_encode([
            'success' => false,
            'message' => 'This order has already been paid'
        ]));
    }
    
    // Create Xendit Invoice
    $description = "Order #" . $order['order_number'] . " - " . $order['items'];
    
    $invoiceResponse = create_xendit_invoice(
        $orderId,
        $order['customer_name'],
        $order['customer_email'],
        $order['total_amount'],
        $description
    );
    
    if (!$invoiceResponse['success']) {
        exit(json_encode([
            'success' => false,
            'message' => 'Failed to create payment invoice: ' . ($invoiceResponse['error'] ?? 'Unknown error'),
            'debug' => $invoiceResponse
        ]));
    }
    
    $invoiceData = $invoiceResponse['data'];
    $invoiceId = $invoiceData['id'];
    $paymentUrl = $invoiceData['invoice_url'];
    $externalId = $invoiceData['external_id'];
    
    // Store payment information in database
    // First, create a payments table if it doesn't exist (see schema below)
    $paymentStmt = $conn->prepare("
        INSERT INTO payments (order_id, xendit_invoice_id, external_id, amount, payment_method, status, xendit_response)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        xendit_invoice_id = VALUES(xendit_invoice_id),
        status = VALUES(status),
        xendit_response = VALUES(xendit_response),
        updated_at = NOW()
    ");
    
    $status = 'pending';
    $response = json_encode($invoiceData);
    
    $paymentStmt->bind_param("issdsss", $orderId, $invoiceId, $externalId, $order['total_amount'], $paymentMethod, $status, $response);
    $paymentStmt->execute();
    
    // Log activity
    $action = 'Initiated payment for order: ' . $order['order_number'];
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    $logStmt->bind_param("iss", $_SESSION['user_id'], $action, $paymentMethod);
    $logStmt->execute();
    
    exit(json_encode([
        'success' => true,
        'message' => 'Payment invoice created successfully',
        'payment_url' => $paymentUrl,
        'invoice_id' => $invoiceId,
        'external_id' => $externalId
    ]));
    
} catch (Exception $e) {
    error_log('Payment Error: ' . $e->getMessage());
    exit(json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]));
}
?>