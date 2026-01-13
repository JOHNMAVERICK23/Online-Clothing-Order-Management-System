<?php
session_start();
require_once '../PROCESS/db_config.php';

$orderId = $_GET['order_id'] ?? '';

if (!$orderId) {
    die('Order ID is required');
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Order not found');
}

$order = $result->fetch_assoc();

// Get order items
$itemsStmt = $conn->prepare("SELECT oi.*, p.product_name, p.category FROM order_items oi 
                            LEFT JOIN products p ON oi.product_id = p.id 
                            WHERE oi.order_id = ?");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}

// Calculate totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['subtotal'];
}
$shippingCost = 100;
$tax = $subtotal * 0.12;
$total = $subtotal + $shippingCost + $tax;

// Handle PDF Download
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    require_once 'PROCESS/vendor/autoload.php';
    // If TCPDF not installed, use basic PDF generation
    generatePDF($order, $items, $subtotal, $shippingCost, $tax, $total);
    exit;
}

// Handle Print
if (isset($_GET['print'])) {
    $printMode = true;
} else {
    $printMode = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        @media print {
            body { margin: 0; padding: 0; }
            .print-hide { display: none !important; }
            .receipt-container { box-shadow: none !important; }
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .receipt-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #333 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .company-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .company-tagline {
            font-size: 13px;
            opacity: 0.8;
            margin-bottom: 20px;
        }

        .receipt-content {
            padding: 30px;
        }

        .receipt-section {
            margin-bottom: 30px;
        }

        .receipt-section-title {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            color: #666;
            letter-spacing: 1px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .order-info-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 15px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 14px;
            color: #1a1a1a;
            font-weight: 500;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table thead {
            background: #f9f9f9;
        }

        .items-table th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }

        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        .items-table tr:last-child td {
            border-bottom: 2px solid #f0f0f0;
        }

        .amount-right {
            text-align: right;
        }

        .totals-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .total-row.final {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a1a;
            border-top: 2px solid #e0e0e0;
            padding-top: 12px;
            margin-top: 12px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-paid { background: #d4edda; color: #155724; }
        .status-processing { background: #cfe2ff; color: #084298; }
        .status-shipped { background: #cff4fc; color: #055160; }
        .status-delivered { background: #d1e7dd; color: #0f5132; }

        .footer-section {
            border-top: 2px solid #f0f0f0;
            padding-top: 20px;
            text-align: center;
            font-size: 13px;
            color: #999;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            padding: 20px;
            border-top: 1px solid #f0f0f0;
        }

        .btn-action {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-print { background: #667eea; color: white; }
        .btn-print:hover { background: #5568d3; }

        .btn-download { background: #10b981; color: white; }
        .btn-download:hover { background: #059669; }

        .btn-back { background: #f0f0f0; color: #1a1a1a; }
        .btn-back:hover { background: #e0e0e0; }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            opacity: 0.05;
            z-index: -1;
        }
    </style>
</head>
<body>
    <div class="watermark">RECEIPT</div>

    <div class="receipt-container">
        <!-- Header -->
        <div class="receipt-header">
            <div class="company-name">ALAS ACE</div>
            <div class="company-tagline">Clothing & Fashion Store</div>
            <div style="font-size: 13px; opacity: 0.8;">
                <div>📍 123 Fashion Street, Manila, Philippines</div>
                <div>📞 +63 917 123 4567 | 📧 support@alascape.com</div>
            </div>
        </div>

        <!-- Receipt Content -->
        <div class="receipt-content">
            <!-- Order Status -->
            <div class="receipt-section">
                <div class="order-info-row">
                    <div class="info-item">
                        <div class="info-label">Order Number</div>
                        <div class="info-value" style="font-size: 16px; font-weight: 700;">
                            <?php echo htmlspecialchars($order['order_number']); ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Order Date</div>
                        <div class="info-value">
                            <?php echo date('M d, Y H:i A', strtotime($order['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <div class="order-info-row">
                    <div class="info-item">
                        <div class="info-label">Order Status</div>
                        <div>
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php echo strtoupper($order['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Payment Status</div>
                        <div>
                            <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                                <?php echo strtoupper($order['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="receipt-section">
                <div class="receipt-section-title">Customer Information</div>
                <div class="order-info-row">
                    <div class="info-item">
                        <div class="info-label">Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Shipping Address</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['shipping_address']); ?></div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="receipt-section">
                <div class="receipt-section-title">Order Items</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="text-align: center;">Qty</th>
                            <th class="amount-right">Unit Price</th>
                            <th class="amount-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                                <td class="amount-right">₱<?php echo number_format($item['price'], 2); ?></td>
                                <td class="amount-right">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="receipt-section">
                <div class="totals-section">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Shipping Cost:</span>
                        <span>₱<?php echo number_format($shippingCost, 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Tax (12% VAT):</span>
                        <span>₱<?php echo number_format($tax, 2); ?></span>
                    </div>
                    <div class="total-row final">
                        <span>Total Amount:</span>
                        <span>₱<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Method & Notes -->
            <div class="receipt-section">
                <div class="order-info-row">
                    <div class="info-item">
                        <div class="info-label">Payment Method</div>
                        <div class="info-value">
                            <?php 
                            $method = 'Not specified';
                            if (strpos($_SERVER['HTTP_REFERER'] ?? '', 'payment') !== false) {
                                $method = 'Online (GCash/PayMaya)';
                            } else {
                                $method = 'Cash on Delivery';
                            }
                            echo $method;
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Order Placed</div>
                        <div class="info-value">
                            <?php echo date('F d, Y', strtotime($order['created_at'])); ?>
                        </div>
                    </div>
                </div>

                <?php if ($order['notes']): ?>
                    <div class="info-item">
                        <div class="info-label">Special Notes</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['notes']); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Footer -->
            <div class="receipt-section footer-section">
                <p style="margin-bottom: 10px;">
                    <strong>Thank you for your purchase!</strong>
                </p>
                <p style="margin-bottom: 10px;">
                    Your order is being processed. You will receive a confirmation email shortly.<br>
                    Track your order status anytime by visiting our website.
                </p>
                <p style="margin-bottom: 0; border-top: 1px solid #f0f0f0; padding-top: 15px;">
                    <small>This is an electronic receipt. Please keep this for your records.<br>
                    If you have any questions, please don't hesitate to contact us.</small>
                </p>
            </div>
        </div>

        <!-- Action Buttons (Print Hide) -->
        <div class="action-buttons print-hide">
            <button class="btn-action btn-print" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Receipt
            </button>
            <a href="?order_id=<?php echo $orderId; ?>&download=pdf" class="btn-action btn-download">
                <i class="bi bi-file-pdf"></i> Download PDF
            </a>
            <a href="store.html" class="btn-action btn-back">
                <i class="bi bi-arrow-left"></i> Back to Store
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Basic PDF generation function (without TCPDF library)
function generatePDF($order, $items, $subtotal, $shippingCost, $tax, $total) {
    // Set headers for PDF download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $order['order_number'] . '_Receipt.pdf"');
    
    // For production, use TCPDF library
    // This is a simple text-based approach
    $pdf = "ORDER RECEIPT\n";
    $pdf .= "ALAS ACE - Clothing Store\n";
    $pdf .= "123 Fashion Street, Manila, Philippines\n";
    $pdf .= "+63 917 123 4567 | support@alascape.com\n\n";
    
    $pdf .= "ORDER #: " . $order['order_number'] . "\n";
    $pdf .= "DATE: " . date('M d, Y H:i A', strtotime($order['created_at'])) . "\n";
    $pdf .= "STATUS: " . strtoupper($order['status']) . "\n";
    $pdf .= "PAYMENT: " . strtoupper($order['payment_status']) . "\n\n";
    
    $pdf .= "CUSTOMER INFORMATION\n";
    $pdf .= "Name: " . $order['customer_name'] . "\n";
    $pdf .= "Email: " . $order['customer_email'] . "\n";
    $pdf .= "Phone: " . $order['customer_phone'] . "\n";
    $pdf .= "Address: " . $order['shipping_address'] . "\n\n";
    
    $pdf .= "ITEMS ORDERED\n";
    $pdf .= str_repeat("-", 80) . "\n";
    foreach ($items as $item) {
        $pdf .= $item['product_name'] . " x" . $item['quantity'] . " = ₱" . number_format($item['subtotal'], 2) . "\n";
    }
    $pdf .= str_repeat("-", 80) . "\n";
    
    $pdf .= "Subtotal: ₱" . number_format($subtotal, 2) . "\n";
    $pdf .= "Shipping: ₱" . number_format($shippingCost, 2) . "\n";
    $pdf .= "Tax (12%): ₱" . number_format($tax, 2) . "\n";
    $pdf .= "TOTAL: ₱" . number_format($total, 2) . "\n\n";
    
    $pdf .= "Thank you for your purchase!\n";
    $pdf .= "This is an electronic receipt.\n";
    
    echo $pdf;
}
?>