<?php
session_start();
require_once '../PROCESS/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.html');
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $orderId = $_POST['order_id'];
        $newStatus = $_POST['new_status'];
        $courierChoice = $_POST['courier_choice'] ?? '';
        $waybill = $_POST['waybill'] ?? '';
        
        $stmt = $conn->prepare("
            UPDATE orders 
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("si", $newStatus, $orderId);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Order status updated successfully';
        }
    }
}

// Get all orders with filtering
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM orders WHERE 1=1";
if ($status) {
    $query .= " AND status = '" . $conn->real_escape_string($status) . "'";
}
if ($search) {
    $query .= " AND (order_number LIKE '%" . $conn->real_escape_string($search) . "%' 
                      OR customer_name LIKE '%" . $conn->real_escape_string($search) . "%'
                      OR customer_email LIKE '%" . $conn->real_escape_string($search) . "%')";
}
$query .= " ORDER BY created_at DESC";

$result = $conn->query($query);
$orders = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}

// Calculate stats
$stats = [
    'total' => count($orders),
    'pending' => 0,
    'shipped' => 0,
    'delivered' => 0
];

foreach ($orders as $order) {
    if ($order['status'] === 'pending') $stats['pending']++;
    if ($order['status'] === 'shipped') $stats['shipped']++;
    if ($order['status'] === 'delivered') $stats['delivered']++;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Clothing Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/orders.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">Admin</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.html"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="accounts.html"><i class="bi bi-people"></i> Manage Accounts</a></li>
            <li><a href="activityLog.html"><i class="bi bi-clock-history"></i> Activity Log</a></li>
            <li><a href="products.html"><i class="bi bi-box-seam"></i> Products Management</a></li>
            <li><a href="orders.php" class="active"><i class="bi bi-receipt"></i> Orders Management</a></li>
            <li><a href="inventory.php"><i class="bi bi-boxes"></i>Inventory</a></li>
            <li><a href="cms.php"><i class="bi bi-file-earmark-text"></i> Content Management</a></li>
            <li><a href="../PROCESS/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title"><i class="bi bi-receipt"></i> Order Management</div>
            <div class="user-info">
                <span class="user-role"><?php echo strtoupper($_SESSION['user_role']); ?></span>
                <a href="../PROCESS/logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters & Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search by order #, name, or email...">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" onclick="applyFilters()">Filter</button>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-box primary">
                    <div class="stat-icon"><i class="bi bi-bag-check"></i></div>
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box warning">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box info">
                    <div class="stat-icon"><i class="bi bi-truck"></i></div>
                    <div class="stat-number"><?php echo $stats['shipped']; ?></div>
                    <div class="stat-label">Shipped</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box success">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['delivered']; ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <div id="ordersContainer">
            <?php if (empty($orders)): ?>
                <div class="card text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h3 class="mt-3">No orders found</h3>
                    <p class="text-muted">Start selling to see orders here</p>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-number"><?php echo $order['order_number']; ?></div>
                                <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></small>
                            </div>
                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                <?php echo strtoupper($order['status']); ?>
                            </span>
                        </div>

                        <div class="order-info">
                            <div class="info-item">
                                <div class="info-label">Customer Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><a href="mailto:<?php echo $order['customer_email']; ?>"><?php echo $order['customer_email']; ?></a></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Total Amount</div>
                                <div class="info-value">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                            </div>
                            
                            <!-- PAYMENT STATUS - FIXED VERSION -->
                            <div class="info-item">
                                <div class="info-label">Payment Status</div>
                                <div class="info-value">
                                    <?php
                                    // Check if payment record exists
                                    $paymentStmt = $conn->prepare("SELECT status, payment_method FROM payments WHERE order_id = ?");
                                    $paymentStmt->bind_param("i", $order['id']);
                                    $paymentStmt->execute();
                                    $paymentResult = $paymentStmt->get_result();
                                    
                                    if ($paymentResult->num_rows > 0) {
                                        $payment = $paymentResult->fetch_assoc();
                                        $paymentStatus = $payment['status'];
                                        
                                        $badgeClass = match($paymentStatus) {
                                            'paid' => 'bg-success',
                                            'pending' => 'bg-warning text-dark',
                                            'failed' => 'bg-danger',
                                            'expired' => 'bg-secondary',
                                            default => 'bg-info'
                                        };
                                        
                                        echo '<span class="badge ' . $badgeClass . '">' . strtoupper($paymentStatus) . '</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary">NOT INITIATED</span>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <!-- PAYMENT METHOD -->
                            <div class="info-item">
                                <div class="info-label">Payment Method</div>
                                <div class="info-value">
                                    <?php
                                    $paymentStmt = $conn->prepare("SELECT payment_method FROM payments WHERE order_id = ?");
                                    $paymentStmt->bind_param("i", $order['id']);
                                    $paymentStmt->execute();
                                    $paymentResult = $paymentStmt->get_result();
                                    
                                    if ($paymentResult->num_rows > 0) {
                                        $payment = $paymentResult->fetch_assoc();
                                        $method = match($payment['payment_method']) {
                                            'credit_card' => '💳 Credit/Debit Card',
                                            'bank_transfer' => '🏦 Bank Transfer',
                                            'ewallet' => '📱 E-Wallet',
                                            'retail' => '🏪 Over-the-Counter',
                                            default => $payment['payment_method']
                                        };
                                        echo '<span class="badge bg-info">' . $method . '</span>';
                                    } else {
                                        echo '<span class="text-muted">Not set</span>';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="info-label">Shipping Address</div>
                                <div class="info-value" style="font-size: 12px;"><?php echo htmlspecialchars($order['shipping_address']); ?></div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="order-items">
                            <strong class="mb-2 d-block">Order Items:</strong>
                            <?php
                            $itemsStmt = $conn->prepare("SELECT oi.*, p.product_name FROM order_items oi 
                                                        LEFT JOIN products p ON oi.product_id = p.id 
                                                        WHERE oi.order_id = ?");
                            $itemsStmt->bind_param("i", $order['id']);
                            $itemsStmt->execute();
                            $itemsResult = $itemsStmt->get_result();
                            
                            while ($item = $itemsResult->fetch_assoc()):
                            ?>
                                <div class="item-row">
                                    <span><?php echo htmlspecialchars($item['product_name']); ?> x<?php echo $item['quantity']; ?></span>
                                    <span>₱<?php echo number_format($item['subtotal'], 2); ?></span>
                                </div>
                            <?php endwhile; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
                            <?php
                            // Check payment status
                            $paymentStmt = $conn->prepare("SELECT status FROM payments WHERE order_id = ?");
                            $paymentStmt->bind_param("i", $order['id']);
                            $paymentStmt->execute();
                            $paymentResult = $paymentStmt->get_result();
                            
                            $paymentStatus = null;
                            if ($paymentResult->num_rows > 0) {
                                $payment = $paymentResult->fetch_assoc();
                                $paymentStatus = $payment['status'];
                            }
                            ?>
                            
                            <!-- Payment Button -->
                            <?php if ($paymentStatus !== 'paid'): ?>
                                <button class="btn btn-outline-success btn-sm" 
                                        onclick="openPaymentModal(<?php echo $order['id']; ?>, '<?php echo addslashes($order['order_number']); ?>', '<?php echo addslashes($order['customer_name']); ?>', <?php echo $order['total_amount']; ?>)">
                                    <i class="bi bi-credit-card"></i> Process Payment
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success btn-sm" disabled>
                                    <i class="bi bi-check-circle"></i> Payment Received
                                </button>
                            <?php endif; ?>
                            
                            <!-- Update Status Button -->
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $order['id']; ?>">
                                <i class="bi bi-pencil"></i> Update Status
                            </button>
                            
                            <!-- E-Receipt Button -->
                            <button class="btn btn-outline-success btn-sm" onclick="generateReceipt(<?php echo $order['id']; ?>)">
                                <i class="bi bi-file-pdf"></i> E-Receipt
                            </button>
                            
                            <!-- Print Button -->
                            <button class="btn btn-outline-info btn-sm" onclick="printOrder(<?php echo $order['id']; ?>)">
                                <i class="bi bi-printer"></i> Print
                            </button>
                        </div>
                    </div>

                    <!-- Update Status Modal -->
                    <div class="modal fade" id="updateStatusModal<?php echo $order['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Update Order Status</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">New Status</label>
                                            <select class="form-select" name="new_status" required>
                                                <option value="">Select Status</option>
                                                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Courier Choice</label>
                                            <input type="text" class="form-control" name="courier_choice" placeholder="e.g., JNT, LBC, Grab">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Waybill Number</label>
                                            <input type="text" class="form-control" name="waybill" placeholder="Tracking number">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Update Status</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-credit-card"></i> Process Payment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <form id="paymentForm">
                    <div class="modal-body">
                        <input type="hidden" id="paymentOrderId" value="">
                        
                        <!-- Order Summary -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title mb-2">Order Details</h6>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span>Order #:</span>
                                    <strong id="paymentOrderNo"></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                    <span>Customer:</span>
                                    <strong id="paymentCustomer"></strong>
                                </div>
                                <div style="display: flex; justify-content: space-between; border-top: 1px solid #ddd; padding-top: 8px;">
                                    <span style="font-weight: 600;">Total Amount:</span>
                                    <strong id="paymentAmount" style="color: #27ae60; font-size: 1.1rem;"></strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Methods -->
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                                <div class="form-check" style="padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; cursor: pointer;">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="method_credit_card" value="credit_card" checked>
                                    <label class="form-check-label" for="method_credit_card">
                                        <i class="bi bi-credit-card"></i> Credit/Debit Card
                                    </label>
                                </div>
                                
                                <div class="form-check" style="padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; cursor: pointer;">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="method_bank_transfer" value="bank_transfer">
                                    <label class="form-check-label" for="method_bank_transfer">
                                        <i class="bi bi-bank"></i> Bank Transfer
                                    </label>
                                </div>
                                
                                <div class="form-check" style="padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; cursor: pointer;">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="method_ewallet" value="ewallet">
                                    <label class="form-check-label" for="method_ewallet">
                                        <i class="bi bi-wallet"></i> E-Wallet
                                    </label>
                                </div>
                                
                                <div class="form-check" style="padding: 12px 15px; border: 2px solid #e0e0e0; border-radius: 6px; cursor: pointer;">
                                    <input class="form-check-input" type="radio" name="payment_method" 
                                           id="method_retail" value="retail">
                                    <label class="form-check-label" for="method_retail">
                                        <i class="bi bi-shop"></i> Over-the-Counter
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="paymentMessage" class="alert" style="display: none;"></div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btnProcessPayment">
                            <i class="bi bi-lock"></i> Process Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functions
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            window.location.href = `orders.php?search=${search}&status=${status}`;
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            window.location.href = 'orders.php';
        }

        function generateReceipt(orderId) {
            window.open(`receipt.php?order_id=${orderId}`, '_blank');
        }

        function printOrder(orderId) {
            window.print();
        }

        // Payment Modal Functions
        function openPaymentModal(orderId, orderNumber, customerName, amount) {
            document.getElementById('paymentOrderId').value = orderId;
            document.getElementById('paymentOrderNo').textContent = orderNumber;
            document.getElementById('paymentCustomer').textContent = customerName;
            document.getElementById('paymentAmount').textContent = '₱' + parseFloat(amount).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            document.getElementById('paymentForm').reset();
            document.getElementById('paymentMessage').style.display = 'none';
            document.getElementById('method_credit_card').checked = true;
            
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        }

        // Handle payment form submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const orderId = document.getElementById('paymentOrderId').value;
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            const btnSubmit = document.getElementById('btnProcessPayment');
            
            // Disable button
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';
            
            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('payment_method', paymentMethod);
            
            fetch('../PROCESS/processPayment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const msgDiv = document.getElementById('paymentMessage');
                    msgDiv.className = 'alert alert-success';
                    msgDiv.innerHTML = '<i class="bi bi-check-circle"></i> ' + data.message;
                    msgDiv.style.display = 'block';
                    
                    // Redirect to Xendit after 2 seconds
                    setTimeout(() => {
                        window.location.href = data.payment_url;
                    }, 2000);
                } else {
                    // Show error message
                    const msgDiv = document.getElementById('paymentMessage');
                    msgDiv.className = 'alert alert-danger';
                    msgDiv.innerHTML = '<i class="bi bi-exclamation-circle"></i> ' + data.message;
                    msgDiv.style.display = 'block';
                    
                    // Re-enable button
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = '<i class="bi bi-lock"></i> Process Payment';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="bi bi-lock"></i> Process Payment';
            });
        });
    </script>
</body>
</html>