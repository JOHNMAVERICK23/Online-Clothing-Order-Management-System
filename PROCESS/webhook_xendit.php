<?php
session_start();
require_once 'db_config.php';
require_once 'xendit_config.php';

// Log webhook for debugging
$webhookLog = fopen(__DIR__ . '/xendit_webhooks.log', 'a');

function logWebhook($message) {
    global $webhookLog;
    fwrite($webhookLog, "[" . date('Y-m-d H:i:s') . "] " . $message . "\n");
}

// Get webhook payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_XENDIT_WEBHOOK_SIGNATURE'] ?? '';

logWebhook("Received webhook: " . substr($payload, 0, 200));

// Verify signature
if (!verify_xendit_webhook($payload, $signature)) {
    logWebhook("Invalid signature");
    http_response_code(403);
    exit('Invalid signature');
}

$data = json_decode($payload, true);

if ($data === null) {
    logWebhook("Invalid JSON payload");
    http_response_code(400);
    exit('Invalid JSON');
}

try {
    // Handle invoice payment callback
    if ($data['event'] === 'invoice.paid') {
        $invoiceId = $data['data']['id'];
        $externalId = $data['data']['external_id'];
        $status = 'paid';
        $paidAmount = $data['data']['paid_amount'];
        $paymentMethod = $data['data']['payment_method'] ?? 'unknown';
        
        // Extract order_id from external_id (format: order_ID_timestamp)
        $parts = explode('_', $externalId);
        $orderId = intval($parts[1] ?? 0);
        
        logWebhook("Processing payment for order: $orderId, Invoice: $invoiceId, Amount: $paidAmount");
        
        if ($orderId > 0) {
            // Update payment status
            $paymentStmt = $conn->prepare("
                UPDATE payments 
                SET status = ?, paid_amount = ?, payment_method = ?, paid_at = NOW(), updated_at = NOW()
                WHERE xendit_invoice_id = ?
            ");
            $paymentStmt->bind_param("sdss", $status, $paidAmount, $paymentMethod, $invoiceId);
            $paymentStmt->execute();
            
            // Update order payment status
            $orderStatus = 'processing';
            $orderStmt = $conn->prepare("
                UPDATE orders 
                SET payment_status = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $orderStmt->bind_param("ssi", $status, $orderStatus, $orderId);
            $orderStmt->execute();
            
            // Log activity
            $action = 'Payment received for order';
            $details = "Invoice: $invoiceId, Amount: $paidAmount";
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $userId = null; // System action
            $logStmt->bind_param("iss", $userId, $action, $details);
            $logStmt->execute();
            
            logWebhook("Payment updated successfully for order $orderId");
            
            // Send confirmation email to customer
            sendPaymentConfirmationEmail($orderId);
        }
    }
    // Handle invoice expiration
    elseif ($data['event'] === 'invoice.expired') {
        $invoiceId = $data['data']['id'];
        $externalId = $data['data']['external_id'];
        
        logWebhook("Invoice expired: $invoiceId, External ID: $externalId");
        
        // Update payment status to expired
        $status = 'expired';
        $paymentStmt = $conn->prepare("
            UPDATE payments 
            SET status = ?, updated_at = NOW()
            WHERE xendit_invoice_id = ?
        ");
        $paymentStmt->bind_param("ss", $status, $invoiceId);
        $paymentStmt->execute();
    }
    
    http_response_code(200);
    exit(json_encode(['success' => true]));
    
} catch (Exception $e) {
    logWebhook("Error processing webhook: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['success' => false, 'error' => $e->getMessage()]));
}

function sendPaymentConfirmationEmail($orderId) {
    global $conn;
    
    $orderStmt = $conn->prepare("SELECT customer_email, customer_name, order_number, total_amount FROM orders WHERE id = ?");
    $orderStmt->bind_param("i", $orderId);
    $orderStmt->execute();
    $order = $orderStmt->get_result()->fetch_assoc();
    
    if ($order) {
        $to = $order['customer_email'];
        $subject = "Payment Confirmation - Order #" . $order['order_number'];
        
        $message = "
        <html>
            <head>
                <title>Payment Confirmation</title>
            </head>
            <body>
                <h2>Payment Confirmed!</h2>
                <p>Dear " . $order['customer_name'] . ",</p>
                <p>We have received your payment for order #" . $order['order_number'] . "</p>
                <p><strong>Amount Paid:</strong> ₱" . number_format($order['total_amount'], 2) . "</p>
                <p>Your order is now being processed and will be shipped soon.</p>
                <p>Thank you for your purchase!</p>
            </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        
        mail($to, $subject, $message, $headers);
    }
}

fclose($webhookLog);
?>