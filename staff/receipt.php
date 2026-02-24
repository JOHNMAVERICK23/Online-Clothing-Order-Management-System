<?php
session_start();
require_once '../PROCESS/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

$orderId = intval($_GET['order_id'] ?? 0);

if (!$orderId) {
    die('Invalid order ID');
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->bind_param("i", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die('Order not found');
}

// Get order items
$itemsStmt = $conn->prepare("
    SELECT oi.*, p.product_name, p.category, p.image_url 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();
$orderItems = [];
while ($item = $itemsResult->fetch_assoc()) {
    $orderItems[] = $item;
}

$statusColors = [
    'pending'    => '#f39c12',
    'processing' => '#3498db',
    'shipped'    => '#8e44ad',
    'delivered'  => '#27ae60',
    'cancelled'  => '#e74c3c',
];
$statusColor = $statusColors[$order['status']] ?? '#333';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Receipt - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f0f0;
            min-height: 100vh;
            padding: 30px 20px;
        }

        /* ── TOP BUTTONS (hidden on print) ── */
        .top-actions {
            max-width: 720px;
            margin: 0 auto 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.85; }
        .btn-print  { background: #1a1a1a; color: white; }
        .btn-back   { background: #6c757d; color: white; }
        .btn-close  { background: #e74c3c; color: white; }

        /* ── RECEIPT CARD ── */
        .receipt {
            max-width: 720px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            overflow: hidden;
        }

        /* Header */
        .receipt-header {
            background: #1a1a1a;
            color: white;
            padding: 36px 40px 28px;
            text-align: center;
            position: relative;
        }
        .receipt-header .brand { font-size: 28px; font-weight: 800; letter-spacing: 1px; }
        .receipt-header .tagline { font-size: 13px; opacity: .7; margin-top: 4px; }
        .receipt-header .receipt-label {
            display: inline-block;
            margin-top: 18px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 20px;
            padding: 4px 18px;
            font-size: 12px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        /* Status ribbon */
        .status-ribbon {
            text-align: center;
            padding: 12px;
            font-weight: 700;
            font-size: 13px;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: white;
            background: <?php echo $statusColor; ?>;
        }

        /* Body */
        .receipt-body { padding: 32px 40px; }

        /* Order meta */
        .order-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            padding-bottom: 24px;
            border-bottom: 1px dashed #ddd;
            margin-bottom: 24px;
        }
        .meta-item .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #999;
            margin-bottom: 4px;
        }
        .meta-item .value {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a1a;
        }

        /* Section title */
        .section-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            font-weight: 700;
            margin-bottom: 14px;
        }

        /* Items table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table thead tr {
            background: #f8f9fa;
            border-bottom: 2px solid #eee;
        }
        .items-table th {
            padding: 10px 12px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #666;
        }
        .items-table th:last-child,
        .items-table td:last-child { text-align: right; }

        .items-table tbody tr { border-bottom: 1px solid #f0f0f0; }
        .items-table tbody tr:last-child { border-bottom: none; }
        .items-table td { padding: 12px; font-size: 14px; color: #333; }
        .items-table .product-name { font-weight: 600; color: #1a1a1a; }
        .items-table .product-cat  { font-size: 12px; color: #999; margin-top: 2px; }

        /* Totals */
        .totals {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 14px;
            color: #555;
        }
        .total-row.grand {
            border-top: 2px solid #ddd;
            margin-top: 8px;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 800;
            color: #1a1a1a;
        }

        /* Customer & shipping */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding-top: 24px;
            border-top: 1px dashed #ddd;
            margin-bottom: 24px;
        }
        .info-block h4 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #999;
            margin-bottom: 8px;
        }
        .info-block p { font-size: 14px; color: #333; line-height: 1.6; }

        /* Payment badge */
        .payment-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .payment-paid   { background: #d4edda; color: #155724; }
        .payment-pending{ background: #fff3cd; color: #856404; }
        .payment-failed { background: #f8d7da; color: #721c24; }

        /* Footer */
        .receipt-footer {
            background: #f8f9fa;
            border-top: 1px solid #eee;
            padding: 20px 40px;
            text-align: center;
        }
        .receipt-footer p { font-size: 12px; color: #999; line-height: 1.7; }
        .receipt-footer .thank-you { font-size: 16px; font-weight: 700; color: #1a1a1a; margin-bottom: 6px; }

        /* Watermark for cancelled */
        <?php if ($order['status'] === 'cancelled'): ?>
        .receipt::after {
            content: 'CANCELLED';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 100px;
            font-weight: 900;
            color: rgba(231, 76, 60, 0.08);
            pointer-events: none;
            white-space: nowrap;
            z-index: 0;
        }
        <?php endif; ?>

        /* ── PRINT STYLES ── */
        @media print {
            body { background: white; padding: 0; }
            .top-actions { display: none !important; }
            .receipt { box-shadow: none; border-radius: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<!-- Action Buttons -->
<div class="top-actions no-print">
    <button class="btn btn-print" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Receipt
    </button>
    <button class="btn btn-close" onclick="window.close()">
        <i class="bi bi-x-lg"></i> Close
    </button>
</div>

<!-- Receipt -->
<div class="receipt">

    <!-- Header -->
    <div class="receipt-header">
        <div class="brand">Clothing Management</div>
        <div class="tagline">Official Electronic Receipt</div>
        <div class="receipt-label">E-Receipt</div>
    </div>

    <!-- Status Ribbon -->
    <div class="status-ribbon">
        <?php echo strtoupper($order['status']); ?>
    </div>

    <!-- Body -->
    <div class="receipt-body">

        <!-- Order Meta -->
        <div class="order-meta">
            <div class="meta-item">
                <div class="label">Order Number</div>
                <div class="value"><?php echo htmlspecialchars($order['order_number']); ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Date Ordered</div>
                <div class="value"><?php echo date('F d, Y · h:i A', strtotime($order['created_at'])); ?></div>
            </div>
            <div class="meta-item">
                <div class="label">Payment Status</div>
                <div class="value">
                    <?php
                    $pc = $order['payment_status'] === 'paid' ? 'payment-paid' : ($order['payment_status'] === 'failed' ? 'payment-failed' : 'payment-pending');
                    $pi = $order['payment_status'] === 'paid' ? 'bi-check-circle-fill' : ($order['payment_status'] === 'failed' ? 'bi-x-circle-fill' : 'bi-clock-fill');
                    ?>
                    <span class="payment-badge <?php echo $pc; ?>">
                        <i class="bi <?php echo $pi; ?>"></i>
                        <?php echo strtoupper($order['payment_status']); ?>
                    </span>
                </div>
            </div>
            <div class="meta-item">
                <div class="label">Last Updated</div>
                <div class="value"><?php echo date('F d, Y', strtotime($order['updated_at'])); ?></div>
            </div>
        </div>

        <!-- Items -->
        <div class="section-title">Order Items</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderItems as $i => $item): ?>
                <tr>
                    <td style="color:#999"><?php echo $i + 1; ?></td>
                    <td>
                        <div class="product-name"><?php echo htmlspecialchars($item['product_name'] ?? 'Product Unavailable'); ?></div>
                        <?php if (!empty($item['category'])): ?>
                        <div class="product-cat"><?php echo htmlspecialchars($item['category']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>₱<?php echo number_format($item['price'], 2); ?></td>
                    <td><strong>₱<?php echo number_format($item['subtotal'], 2); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="total-row">
                <span>Subtotal</span>
                <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
            <div class="total-row">
                <span>Shipping Fee</span>
                <span>FREE</span>
            </div>
            <div class="total-row grand">
                <span>TOTAL</span>
                <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>

        <!-- Customer & Shipping Info -->
        <div class="info-grid">
            <div class="info-block">
                <h4><i class="bi bi-person"></i> Customer</h4>
                <p>
                    <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                    <?php echo htmlspecialchars($order['customer_email']); ?><br>
                    <?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?>
                </p>
            </div>
            <div class="info-block">
                <h4><i class="bi bi-geo-alt"></i> Shipping Address</h4>
                <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
            </div>
        </div>

        <?php if (!empty($order['notes'])): ?>
        <div style="background:#fff3cd;border-radius:6px;padding:14px 18px;margin-bottom:16px;">
            <div class="section-title" style="margin-bottom:6px;">Notes</div>
            <p style="font-size:14px;color:#555;"><?php echo htmlspecialchars($order['notes']); ?></p>
        </div>
        <?php endif; ?>

    </div><!-- /receipt-body -->

    <!-- Footer -->
    <div class="receipt-footer">
        <p class="thank-you">Thank you for your purchase!</p>
        <p>
            This is an official electronic receipt.<br>
            Keep this for your records. For concerns, contact us at <strong>support@clothingmgmt.local</strong><br>
            Generated on <?php echo date('F d, Y \a\t h:i A'); ?>
        </p>
    </div>

</div><!-- /receipt -->

</body>
</html>