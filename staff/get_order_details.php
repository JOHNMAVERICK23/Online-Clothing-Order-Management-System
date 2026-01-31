<?php
session_start();
require_once '../PROCESS/db_config.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    exit('Unauthorized');
}

$orderId = $_GET['order_id'] ?? 0;

if (empty($orderId)) {
    exit('Invalid order ID');
}

// Get order details
$orderStmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
$orderStmt->bind_param("i", $orderId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();

if (!$order) {
    exit('Order not found');
}

// Get order items
$itemsStmt = $conn->prepare("
    SELECT oi.*, p.product_name, p.category, p.price as product_price
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$itemsStmt->bind_param("i", $orderId);
$itemsStmt->execute();
$items = $itemsStmt->get_result();

?>
<style>
    .order-info {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .info-item {
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .info-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }

    .info-value {
        color: #666;
        font-size: 14px;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }

    .items-table th {
        background: #f5f5f5;
        padding: 12px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid #ddd;
        font-size: 13px;
    }

    .items-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        font-size: 13px;
    }

    .summary-section {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 4px;
        margin-top: 20px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }

    .summary-row:last-child {
        border-bottom: none;
        font-weight: 700;
        font-size: 16px;
        padding-top: 15px;
        border-top: 2px solid #ddd;
    }

    .close-btn {
        background: #1a1a1a;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        margin-top: 20px;
        width: 100%;
    }

    .close-btn:hover {
        background: #333;
    }

    .badge-status {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .badge-pending {
        background: #fff3cd;
        color: #856404;
    }

    .badge-delivered {
        background: #d1e7dd;
        color: #0f5132;
    }

    .badge-processing {
        background: #cfe2ff;
        color: #084298;
    }

    .badge-paid {
        background: #d1e7dd;
        color: #0f5132;
    }

    .badge-pending-payment {
        background: #fff3cd;
        color: #856404;
    }
</style>

<div class="order-info">
    <div>
        <div class="info-item">
            <div class="info-label">Order Number</div>
            <div class="info-value" style="font-weight: 600; font-size: 15px;">
                <?php echo htmlspecialchars($order['order_number']); ?>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label">Customer Name</div>
            <div class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Email</div>
            <div class="info-value"><?php echo htmlspecialchars($order['customer_email']); ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Phone</div>
            <div class="info-value"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
        </div>
    </div>

    <div>
        <div class="info-item">
            <div class="info-label">Order Status</div>
            <div class="info-value">
                <span class="badge-status badge-<?php echo $order['status']; ?>">
                    <?php echo strtoupper($order['status']); ?>
                </span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label">Payment Status</div>
            <div class="info-value">
                <span class="badge-status badge-<?php echo $order['payment_status']; ?>">
                    <?php echo strtoupper($order['payment_status']); ?>
                </span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label">Order Date</div>
            <div class="info-value"><?php echo date('M d, Y H:i A', strtotime($order['created_at'])); ?></div>
        </div>
        <div class="info-item">
            <div class="info-label">Shipping Address</div>
            <div class="info-value"><?php echo htmlspecialchars($order['shipping_address']); ?></div>
        </div>
    </div>
</div>

<div style="margin-top: 30px; border-top: 2px solid #eee; padding-top: 20px;">
    <h5 style="margin-bottom: 15px; font-weight: 600;">Items Ordered</h5>
    
    <table class="items-table">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['product_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                    <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                    <td style="text-align: right;">₱<?php echo number_format($item['price'], 2); ?></td>
                    <td style="text-align: right;">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<div class="summary-section">
    <div class="summary-row">
        <span>Subtotal:</span>
        <span>₱<?php echo number_format($order['total_amount'] - ($order['total_amount'] * 0.12), 2); ?></span>
    </div>
    <div class="summary-row">
        <span>Tax (12% VAT):</span>
        <span>₱<?php echo number_format($order['total_amount'] * 0.12, 2); ?></span>
    </div>
    <div class="summary-row">
        <span>Shipping:</span>
        <span>₱100.00</span>
    </div>
    <div class="summary-row">
        <span>Total Amount:</span>
        <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
    </div>
</div>

<button class="close-btn" onclick="closeModal()">Close</button>