<?php
session_start();
require_once '../PROCESS/db_config.php';

$orderId = $_GET['order_id'] ?? '';
$orderNumber = $_GET['order_number'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? '';

if (!$orderId) {
    header('Location: store.html');
    exit;
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND order_number = ?");
$stmt->bind_param("is", $orderId, $orderNumber);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Order not found');
}

$order = $result->fetch_assoc();

// Get order items
$itemsStmt = $conn->prepare("SELECT oi.*, p.product_name FROM order_items oi 
                            LEFT JOIN products p ON oi.product_id = p.id 
                            WHERE oi.order_id = ?");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}

// Clear cart from localStorage (will be handled by JavaScript)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed - Clothing Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 0; }
        .confirmation-container { max-width: 900px; margin: 0 auto; }
        .confirmation-card { background: white; border-radius: 15px; padding: 50px 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); text-align: center; }
        .success-icon { font-size: 80px; color: #10b981; margin-bottom: 20px; animation: bounce 1s infinite; }
        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
        h1 { color: #1a1a1a; font-weight: 700; margin-bottom: 10px; }
        .confirmation-text { color: #666; font-size: 16px; margin-bottom: 30px; }
        .order-reference { background: #f0f0f0; padding: 20px; border-radius: 10px; margin: 30px 0; }
        .order-number-display { font-size: 24px; font-weight: 700; color: #667eea; font-family: 'Courier New', monospace; }
        .order-summary { background: #f9f9f9; padding: 20px; border-radius: 10px; margin: 20px 0; text-align: left; }
        .summary-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
        .summary-item:last-child { border-bottom: none; }
        .summary-item.total { font-weight: 700; font-size: 18px; color: #667eea; border-top: 2px solid #e0e0e0; padding-top: 15px; }
        .action-buttons { display: flex; gap: 15px; justify-content: center; margin-top: 40px; flex-wrap: wrap; }
        .btn-custom { padding: 12px 30px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
        .btn-primary-custom { background: #667eea; color: white; border: none; }
        .btn-primary-custom:hover { background: #5568d3; }
        .btn-secondary-custom { background: white; color: #667eea; border: 2px solid #667eea; }
        .btn-secondary-custom:hover { background: #f8f9ff; }
        .info-box { background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 20px 0; border-radius: 5px; text-align: left; }
        .info-box strong { color: #0c5460; display: block; margin-bottom: 8px; }
        .timeline { text-align: left; margin: 30px 0; }
        .timeline-item { display: flex; margin-bottom: 20px; }
        .timeline-icon { width: 40px; height: 40px; border-radius: 50%; background: #e8f4f8; display: flex; align-items: center; justify-content: center; margin-right: 15px; color: #3498db; font-size: 18px; flex-shrink: 0; }
        .timeline-icon.completed { background: #d4edda; color: #155724; }
        .timeline-content { flex-grow: 1; }
        .timeline-title { font-weight: 600; color: #1a1a1a; margin-bottom: 3px; }
        .timeline-desc { font-size: 13px; color: #666; }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <!-- Success Icon & Message -->
            <div class="success-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            
            <h1>Order Confirmed!</h1>
            <p class="confirmation-text">
                Thank you for your purchase! Your order has been successfully placed and we're excited to get it to you.
            </p>

            <!-- Order Reference -->
            <div class="order-reference">
                <small style="color: #999;">YOUR ORDER NUMBER</small>
                <div class="order-number-display"><?php echo htmlspecialchars($order['order_number']); ?></div>
            </div>

            <!-- Shipping Information -->
            <div class="info-box">
                <strong><i class="bi bi-geo-alt"></i> Shipping To</strong>
                <div style="font-size: 14px;">
                    <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                    <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                    <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h5 style="text-align: center; margin-bottom: 15px;">Order Summary</h5>
                
                <?php foreach ($items as $item): ?>
                    <div class="summary-item">
                        <span><?php echo htmlspecialchars($item['product_name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                        <span>₱<?php echo number_format($item['subtotal'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
                
                <?php
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += $item['subtotal'];
                }
                $shippingCost = 100;
                $tax = $subtotal * 0.12;
                ?>
                
                <div class="summary-item">
                    <span>Shipping</span>
                    <span>₱<?php echo number_format($shippingCost, 2); ?></span>
                </div>
                <div class="summary-item">
                    <span>Tax (12%)</span>
                    <span>₱<?php echo number_format($tax, 2); ?></span>
                </div>
                <div class="summary-item total">
                    <span>Total Amount</span>
                    <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>

            <!-- Payment & Status Information -->
            <div class="info-box">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
                    <div>
                        <strong><i class="bi bi-credit-card"></i> Payment Method</strong>
                        <div style="font-size: 14px; margin-top: 5px;">
                            <?php 
                            if ($paymentMethod === 'cod') {
                                echo 'Cash on Delivery';
                            } elseif ($paymentMethod === 'gcash') {
                                echo 'GCash';
                            } elseif ($paymentMethod === 'paymaya') {
                                echo 'PayMaya';
                            } else {
                                echo 'Online Payment';
                            }
                            ?>
                        </div>
                    </div>
                    <div>
                        <strong><i class="bi bi-info-circle"></i> Payment Status</strong>
                        <div style="font-size: 14px; margin-top: 5px;">
                            <span class="badge bg-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                <?php echo strtoupper($order['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What Happens Next Timeline -->
            <div class="timeline">
                <h5 style="text-align: center; margin-bottom: 20px;">What Happens Next</h5>
                
                <div class="timeline-item">
                    <div class="timeline-icon completed">
                        <i class="bi bi-check"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Order Confirmed</div>
                        <div class="timeline-desc">Your order has been received and is being processed</div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Processing</div>
                        <div class="timeline-desc">Your items are being prepared for shipment (1-2 business days)</div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Shipped</div>
                        <div class="timeline-desc">Your package is on its way! We'll send you tracking information</div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">Delivered</div>
                        <div class="timeline-desc">Your order arrives at your doorstep. Enjoy your purchase!</div>
                    </div>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="info-box">
                <strong><i class="bi bi-info-circle"></i> Important Information</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px; font-size: 14px;">
                    <li>A confirmation email has been sent to <strong><?php echo htmlspecialchars($order['customer_email']); ?></strong></li>
                    <li>You can track your order using your order number above</li>
                    <li>Estimated delivery: 3-5 business days</li>
                    <li>If you have any questions, contact us at support@clothingstore.com</li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="receipt.php?order_id=<?php echo $orderId; ?>" class="btn-custom btn-primary-custom">
                    <i class="bi bi-file-text"></i> View Receipt
                </a>
                <a href="store.html" class="btn-custom btn-secondary-custom">
                    <i class="bi bi-shop"></i> Continue Shopping
                </a>
            </div>

            <!-- Footer Message -->
            <p style="margin-top: 40px; color: #999; font-size: 13px; border-top: 1px solid #f0f0f0; padding-top: 20px;">
                Thank you for choosing us! We appreciate your business and hope you enjoy your purchase.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Clear cart from localStorage
        document.addEventListener('DOMContentLoaded', function() {
            localStorage.removeItem('cart');
        });
    </script>
</body>
</html>
