<?php
session_start();
require_once '../PROCESS/db_config.php';

// Check if user is logged in and is staff
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../login.html');
    exit;
}

// Handle export requests
if (isset($_GET['export'])) {
    $exportType = $_GET['export'];
    
    if ($exportType === 'excel') {
        // Redirect to external export file
        $exportUrl = 'export_excel.php?date_from=' . urlencode($dateFrom) . 
                     '&date_to=' . urlencode($dateTo) . 
                     '&status=' . urlencode($statusFilter) . 
                     '&search=' . urlencode($searchTerm);
        header('Location: ' . $exportUrl);
        exit;
    }
}

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$statusFilter = $_GET['status'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Build query for orders
$query = "SELECT o.*, COUNT(oi.id) as item_count, SUM(oi.quantity) as total_quantity
          FROM orders o
          LEFT JOIN order_items oi ON o.id = oi.order_id
          WHERE DATE(o.created_at) BETWEEN ? AND ?";

$params = [$dateFrom, $dateTo];
$types = "ss";

if (!empty($statusFilter)) {
    $query .= " AND o.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($searchTerm)) {
    $query .= " AND (o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)";
    $searchWildcard = "%{$searchTerm}%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types .= "sss";
}

$query .= " GROUP BY o.id ORDER BY o.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
$totalRevenue = 0;
$totalOrders = 0;
$totalItems = 0;

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
    $totalRevenue += $row['total_amount'];
    $totalOrders++;
    $totalItems += $row['total_quantity'];
}
// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total_orders,
    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
    COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
    COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_orders,
    SUM(total_amount) as total_revenue
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?";

$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("ss", $dateFrom, $dateTo);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report - Clothing Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/staff_report.css">
</head>
<body>
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-logo">Staff Dashboard</div>
        <ul class="sidebar-menu">
            <li><a href="orders.php"><i class="bi bi-receipt"></i> Order Management</a></li>
            <li><a href="products.html"><i class="bi bi-box-seam"></i> Product Management</a></li>
            <li><a href="staff_report.php" class="active"><i class="bi bi-file-earmark-bar-graph"></i> Sales Report</a></li>
            <li><a href="../PROCESS/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="topbar-title"><i class="bi bi-file-earmark-bar-graph"></i> Sales Report</div>
            <div class="user-info">
                <span class="user-role">STAFF</span>
                <a href="../PROCESS/logout.php" class="btn-logout">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>

        <!-- STATISTICS -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-box primary">
                    <div class="stat-icon"><i class="bi bi-bag-check"></i></div>
                    <div class="stat-number"><?php echo $stats['total_orders'] ?? 0; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box success">
                    <div class="stat-icon"><i class="bi bi-check-circle"></i></div>
                    <div class="stat-number"><?php echo $stats['delivered_orders'] ?? 0; ?></div>
                    <div class="stat-label">Delivered</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box warning">
                    <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                    <div class="stat-number"><?php echo $stats['pending_orders'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box">
                    <div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
                    <div class="stat-number">₱<?php echo number_format($stats['total_revenue'] ?? 0, 0); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- FILTER SECTION -->
        <div class="filter-section">
            <h5 class="mb-3">Filter Report</h5>
            <form method="GET" action="staff_report.php">
                <div class="filter-row">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Order Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Order # or Customer Name" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="color: transparent;">Action</label>
                        <button type="submit" class="btn-primary w-100">
                            <i class="bi bi-search"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- EXPORT BUTTONS -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Export Report</div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="staff_report.php?date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchTerm); ?>&export=excel" class="btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Export to Excel
                    </a>
                    <a href="javascript:printReport()" class="btn-outline-primary">
                        <i class="bi bi-printer"></i> Print Report
                    </a>
                </div>
            </div>
        </div>

        <!-- ORDERS TABLE -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Order Details</div>
                <div style="color: var(--gray); font-size: 14px;">
                    Total: <?php echo $totalOrders; ?> orders | 
                    Items: <?php echo $totalItems; ?> | 
                    Revenue: ₱<?php echo number_format($totalRevenue, 2); ?>
                </div>
            </div>

            <?php if (empty($orders)): ?>
                <div style="padding: 40px; text-align: center; color: var(--gray);">
                    <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; color: #ddd;"></i>
                    <h5>No orders found</h5>
                    <p>Try adjusting your filters</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Customer Name</th>
                                <th>Email</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="background: #f0f0f0; color: #333;">
                                            <?php echo $order['item_count']; ?> items
                                        </span>
                                    </td>
                                    <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $order['status']; ?>">
                                            <?php echo strtoupper($order['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: <?php echo $order['payment_status'] === 'paid' ? '#d1e7dd' : '#fff3cd'; ?>; color: <?php echo $order['payment_status'] === 'paid' ? '#0f5132' : '#856404'; ?>;">
                                            <?php echo strtoupper($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <button class="btn-outline-primary" style="padding: 4px 8px; font-size: 12px;" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ORDER DETAILS MODAL -->
    <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto; padding: 20px;">
        <div style="background: white; max-width: 800px; margin: 50px auto; border-radius: 4px; padding: 30px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 15px;">
                <h3 style="margin: 0;">Order Details</h3>
                <button onclick="closeModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--gray);">×</button>
            </div>
            <div id="modalContent"></div>
        </div>
    </div>

    <script>
        function viewOrderDetails(orderId) {
            // Load order details via AJAX
            fetch('get_order_details.php?order_id=' + orderId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalContent').innerHTML = html;
                    document.getElementById('orderModal').style.display = 'block';
                })
                .catch(error => {
                    alert('Error loading order details');
                    console.error(error);
                });
        }

        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        function printReport() {
            window.print();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>