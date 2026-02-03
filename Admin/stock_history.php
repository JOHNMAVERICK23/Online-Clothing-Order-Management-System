<?php
session_start();
require_once '../PROCESS/db_config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.html');
    exit;
}

$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$filterAction = $_GET['action'] ?? '';
$filterDate = $_GET['date'] ?? '';

// Get product details
$productQuery = "SELECT * FROM products WHERE id = ?";
$productStmt = $conn->prepare($productQuery);
$productStmt->bind_param("i", $productId);
$productStmt->execute();
$productResult = $productStmt->get_result();
$product = $productResult->fetch_assoc();

if (!$product) {
    header('Location: inventory.php');
    exit;
}

// Get stock movement history
$query = "SELECT al.* FROM activity_logs al 
          WHERE al.action LIKE '%Product ID $productId%' 
          AND al.action LIKE '%Stock%'";

if ($filterAction) {
    $filterAction = $conn->real_escape_string($filterAction);
    if ($filterAction === 'stock_in') {
        $query .= " AND al.action LIKE '%Stock In%'";
    } elseif ($filterAction === 'stock_out') {
        $query .= " AND al.action LIKE '%Stock Out%'";
    }
}

if ($filterDate) {
    $filterDate = $conn->real_escape_string($filterDate);
    $query .= " AND DATE(al.created_at) = '$filterDate'";
}

$query .= " ORDER BY al.created_at DESC";

$result = $conn->query($query);
$movements = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $movements[] = $row;
    }
}

// Calculate statistics for this product
$statsQuery = "SELECT 
    COUNT(CASE WHEN action LIKE '%Stock In%' THEN 1 END) as total_stock_in,
    COUNT(CASE WHEN action LIKE '%Stock Out%' THEN 1 END) as total_stock_out,
    SUM(CASE WHEN action LIKE '%Stock In%' THEN CAST(REGEXP_SUBSTR(action, 'Qty \\+([0-9]+)') AS UNSIGNED) ELSE 0 END) as total_qty_in,
    SUM(CASE WHEN action LIKE '%Stock Out%' THEN CAST(REGEXP_SUBSTR(action, 'Qty -([0-9]+)') AS UNSIGNED) ELSE 0 END) as total_qty_out
    FROM activity_logs 
    WHERE action LIKE '%Product ID $productId%'";

$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock History - <?php echo htmlspecialchars($product['product_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/stock_history.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">Admin</div>
        <ul class="sidebar-menu sidebar-scroll">
            <li><a href="dashboard.html"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="accounts.html"><i class="bi bi-people"></i> Manage Accounts</a></li>
            <li><a href="activityLog.html"><i class="bi bi-clock-history"></i> Activity Log</a></li>
            <li><a href="products.html"><i class="bi bi-box-seam"></i> Products Management</a></li>
            <li><a href="orders.php"><i class="bi bi-receipt"></i> Orders Management</a></li>
            <li><a href="inventory.php"><i class="bi bi-boxes"></i> Inventory</a></li>
            <li><a href="../PROCESS/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title"><i class="bi bi-clock-history"></i> Stock History</div>
            <div class="user-info">
                <span class="user-role">ADMIN</span>
                <a href="../PROCESS/logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>

        <div class="history-container">
            <a href="inventory.php" class="back-button">
                <i class="bi bi-chevron-left"></i> Back to Inventory
            </a>

            <!-- Product Header -->
            <div class="product-header">
                <div>
                    <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                    <p>Product ID: #<?php echo $product['id']; ?> | Category: <?php echo $product['category']; ?></p>
                </div>
                <div class="header-stats">
                    <div class="header-stat-item">
                        <div class="header-stat-number"><?php echo $product['quantity']; ?></div>
                        <div class="header-stat-label">Current Stock</div>
                    </div>
                    <div class="header-stat-item">
                        <div class="header-stat-number">₱<?php echo number_format($product['price'], 2); ?></div>
                        <div class="header-stat-label">Unit Price</div>
                    </div>
                </div>
            </div>

            <!-- Statistics Row -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-box-small">
                        <div class="stat-icon-small"><i class="bi bi-arrow-down-circle"></i></div>
                        <div class="stat-number-small"><?php echo $stats['total_stock_in'] ?? 0; ?></div>
                        <div class="stat-label-small">Stock In Count</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box-small">
                        <div class="stat-icon-small"><i class="bi bi-bag-plus"></i></div>
                        <div class="stat-number-small"><?php echo $stats['total_qty_in'] ?? 0; ?></div>
                        <div class="stat-label-small">Total Units In</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box-small">
                        <div class="stat-icon-small"><i class="bi bi-arrow-up-circle"></i></div>
                        <div class="stat-number-small"><?php echo $stats['total_stock_out'] ?? 0; ?></div>
                        <div class="stat-label-small">Stock Out Count</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box-small">
                        <div class="stat-icon-small"><i class="bi bi-bag-dash"></i></div>
                        <div class="stat-number-small"><?php echo $stats['total_qty_out'] ?? 0; ?></div>
                        <div class="stat-label-small">Total Units Out</div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                    
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-funnel"></i> Filter by Type</label>
                        <select class="form-select" name="action">
                            <option value="">All Movements</option>
                            <option value="stock_in" <?php echo $filterAction === 'stock_in' ? 'selected' : ''; ?>>Stock In</option>
                            <option value="stock_out" <?php echo $filterAction === 'stock_out' ? 'selected' : ''; ?>>Stock Out</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-calendar"></i> Filter by Date</label>
                        <input type="date" class="form-control" name="date" value="<?php echo $filterDate; ?>">
                    </div>

                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-search"></i> Apply Filter
                        </button>
                        <a href="?product_id=<?php echo $productId; ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- History Timeline -->
            <div class="history-timeline">
                <?php if (empty($movements)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inbox"></i>
                        <h4>No stock movements found</h4>
                        <p class="text-muted">Start tracking stock movements for this product</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($movements as $movement): ?>
                        <?php
                        // Parse the action to extract details
                        $isStockIn = strpos($movement['action'], 'Stock In') !== false;
                        $isStockOut = strpos($movement['action'], 'Stock Out') !== false;
                        
                        // Extract quantity
                        preg_match('/Qty ([+-]\d+)/', $movement['action'], $qtyMatch);
                        $quantity = $qtyMatch[1] ?? 'N/A';
                        
                        // Extract reason/supplier
                        if ($isStockIn) {
                            preg_match('/Supplier: (.+)$/', $movement['action'], $reasonMatch);
                            $reason = $reasonMatch[1] ?? 'N/A';
                        } else {
                            preg_match('/Reason: (.+)$/', $movement['action'], $reasonMatch);
                            $reason = $reasonMatch[1] ?? 'N/A';
                        }
                        
                        $timestamp = new DateTime($movement['created_at']);
                        $formattedDate = $timestamp->format('M d, Y h:i A');
                        ?>
                        <div class="history-item">
                            <div class="history-icon <?php echo $isStockIn ? 'stock-in' : 'stock-out'; ?>">
                                <i class="bi <?php echo $isStockIn ? 'bi-plus-lg' : 'bi-dash-lg'; ?>"></i>
                            </div>
                            <div class="history-content">
                                <div class="history-action">
                                    <?php echo $isStockIn ? '<i class="bi bi-arrow-down"></i> Stock In' : '<i class="bi bi-arrow-up"></i> Stock Out'; ?>
                                </div>
                                <div class="history-details">
                                    <strong>Quantity:</strong> <?php echo abs(intval($quantity)); ?> units | 
                                    <strong><?php echo $isStockIn ? 'Supplier' : 'Reason'; ?>:</strong> <?php echo htmlspecialchars($reason); ?>
                                </div>
                                <div class="history-timestamp">
                                    <i class="bi bi-calendar-event"></i> <?php echo $formattedDate; ?> 
                                    <span style="margin-left: 15px;">
                                        <i class="bi bi-person"></i> Admin
                                    </span>
                                </div>
                                <?php if (!empty($movement['details'])): ?>
                                    <div class="notes-section">
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($movement['details']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth transitions on page load
        document.addEventListener('DOMContentLoaded', function() {
            const items = document.querySelectorAll('.history-item');
            items.forEach((item, index) => {
                item.style.animationDelay = (index * 0.1) + 's';
            });
        });
    </script>
</body>
</html>