<?php
session_start();
require_once '../PROCESS/db_config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.html');
    exit;
}

// Handle stock movements
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'stock_in') {
        $productId = $_POST['product_id'];
        $quantity = intval($_POST['quantity']);
        $supplierInfo = trim($_POST['supplier_info'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($quantity > 0) {
            // Get current stock
            $stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            
            if ($product) {
                $newQuantity = $product['quantity'] + $quantity;
                
                // Update product stock
                $updateStmt = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
                $updateStmt->bind_param("ii", $newQuantity, $productId);
                $updateStmt->execute();
                
                // Log the activity
                $logAction = "Stock In: Product ID $productId, Qty +$quantity, Supplier: $supplierInfo";
                $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
                $logStmt->bind_param("iss", $_SESSION['user_id'], $logAction, $notes);
                $logStmt->execute();
                
                $_SESSION['success'] = "Stock added successfully! New quantity: $newQuantity";
            }
        }
    } 
    elseif ($action === 'stock_out') {
        $productId = $_POST['product_id'];
        $quantity = intval($_POST['quantity']);
        $reason = trim($_POST['reason'] ?? 'Sale');
        $notes = trim($_POST['notes'] ?? '');
        
        if ($quantity > 0) {
            // Get current stock
            $stmt = $conn->prepare("SELECT quantity FROM products WHERE id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            
            if ($product && $product['quantity'] >= $quantity) {
                $newQuantity = $product['quantity'] - $quantity;
                
                // Update product stock
                $updateStmt = $conn->prepare("UPDATE products SET quantity = ? WHERE id = ?");
                $updateStmt->bind_param("ii", $newQuantity, $productId);
                $updateStmt->execute();
                
                // Log the activity
                $logAction = "Stock Out: Product ID $productId, Qty -$quantity, Reason: $reason";
                $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
                $logStmt->bind_param("iss", $_SESSION['user_id'], $logAction, $notes);
                $logStmt->execute();
                
                $_SESSION['success'] = "Stock removed successfully! New quantity: $newQuantity";
            } else {
                $_SESSION['error'] = "Insufficient stock available!";
            }
        }
    }
}

// Get all products with stock info
$searchTerm = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock_status'] ?? '';

$query = "SELECT * FROM products WHERE 1=1";

if ($searchTerm) {
    $searchTerm = $conn->real_escape_string($searchTerm);
    $query .= " AND product_name LIKE '%$searchTerm%'";
}

if ($categoryFilter) {
    $categoryFilter = $conn->real_escape_string($categoryFilter);
    $query .= " AND category = '$categoryFilter'";
}

if ($stockFilter === 'low') {
    $query .= " AND quantity BETWEEN 1 AND 10";
} elseif ($stockFilter === 'out') {
    $query .= " AND quantity = 0";
}

$query .= " ORDER BY quantity ASC";

$result = $conn->query($query);
$products = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Get inventory statistics
$statsQuery = "SELECT 
    COUNT(*) as total_products,
    SUM(quantity) as total_stock,
    SUM(price * quantity) as inventory_value,
    COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock,
    COUNT(CASE WHEN quantity BETWEEN 1 AND 10 THEN 1 END) as low_stock
    FROM products";

$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Get categories for filter
$categoryQuery = "SELECT DISTINCT category FROM products ORDER BY category";
$categoryResult = $conn->query($categoryQuery);
$categories = [];
while ($cat = $categoryResult->fetch_assoc()) {
    $categories[] = $cat['category'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Clothing Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <link rel="stylesheet" href="../CSS/inventory.css">
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
            <li><a href="inventory.php" class="active"><i class="bi bi-boxes"></i> Inventory</a></li>
            <li><a href="../PROCESS/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-title"><i class="bi bi-boxes"></i> Inventory Management</div>
            <div class="user-info">
                <span class="user-role">ADMIN</span>
                <a href="../PROCESS/logout.php" class="btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-box">
                    <div class="stat-icon"><i class="bi bi-box"></i></div>
                    <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                    <div class="stat-label">Total Products</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box">
                    <div class="stat-icon"><i class="bi bi-stack"></i></div>
                    <div class="stat-number"><?php echo number_format($stats['total_stock']); ?></div>
                    <div class="stat-label">Total Stock Units</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box">
                    <div class="stat-icon"><i class="bi bi-currency-dollar"></i></div>
                    <div class="stat-number">₱<?php echo number_format($stats['inventory_value'], 0); ?></div>
                    <div class="stat-label">Inventory Value</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-box">
                    <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="stat-number" style="color: #e74c3c;"><?php echo $stats['low_stock'] + $stats['out_of_stock']; ?></div>
                    <div class="stat-label">Low/Out Stock</div>
                </div>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Filter & Search</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search product..." value="<?php echo $searchTerm; ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php echo $categoryFilter === $cat ? 'selected' : ''; ?>>
                                    <?php echo $cat; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="stock_status">
                            <option value="">All Stock Levels</option>
                            <option value="low" <?php echo $stockFilter === 'low' ? 'selected' : ''; ?>>Low Stock (1-10)</option>
                            <option value="out" <?php echo $stockFilter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products Inventory -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Product Inventory</h5>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#bulkStockModal">
                    <i class="bi bi-plus-circle"></i> Bulk Stock Update
                </button>
            </div>
            <div class="card-body">
                <div class="product-row" style="background: #f9f9f9; font-weight: 600; border-bottom: 2px solid #ddd; padding-top: 20px; padding-bottom: 20px;">
                    <div>Product Name</div>
                    <div>Category</div>
                    <div>Current Stock</div>
                    <div>Price</div>
                    <div>Stock Value</div>
                    <div>Actions</div>
                </div>

                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h5 class="mt-3">No products found</h5>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                        $stockStatus = 'stock-in';
                        if ($product['quantity'] == 0) {
                            $stockStatus = 'stock-out';
                        } elseif ($product['quantity'] <= 10) {
                            $stockStatus = 'stock-low';
                        }
                        $stockValue = $product['price'] * $product['quantity'];
                        ?>
                        <div class="product-row">
                            <div>
                                <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                <div class="product-category">ID: #<?php echo $product['id']; ?></div>
                            </div>
                            <div><?php echo $product['category']; ?></div>
                            <div>
                                <span class="stock-badge <?php echo $stockStatus; ?>">
                                    <?php echo $product['quantity']; ?> units
                                </span>
                            </div>
                            <div>₱<?php echo number_format($product['price'], 2); ?></div>
                            <div>₱<?php echo number_format($stockValue, 2); ?></div>
                            <div>
                                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#stockInModal<?php echo $product['id']; ?>" title="Stock In">
                                    <i class="bi bi-plus-square"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#stockOutModal<?php echo $product['id']; ?>" title="Stock Out">
                                    <i class="bi bi-dash-square"></i>
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="viewHistory(<?php echo $product['id']; ?>)" title="History">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Stock In Modal -->
                        <div class="modal fade" id="stockInModal<?php echo $product['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Stock In - <?php echo htmlspecialchars($product['product_name']); ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="stock_in">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Current Stock</label>
                                                <input type="text" class="form-control" value="<?php echo $product['quantity']; ?> units" disabled>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Quantity to Add *</label>
                                                <input type="number" class="form-control" name="quantity" min="1" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Supplier Information</label>
                                                <input type="text" class="form-control" name="supplier_info" placeholder="Supplier name, contact, etc.">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control" name="notes" rows="2" placeholder="Additional notes..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-success">Confirm Stock In</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Out Modal -->
                        <div class="modal fade" id="stockOutModal<?php echo $product['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title">Stock Out - <?php echo htmlspecialchars($product['product_name']); ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="stock_out">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Current Stock</label>
                                                <input type="text" class="form-control" value="<?php echo $product['quantity']; ?> units" disabled>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Quantity to Remove *</label>
                                                <input type="number" class="form-control" name="quantity" min="1" max="<?php echo $product['quantity']; ?>" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Reason</label>
                                                <select class="form-select" name="reason">
                                                    <option value="Sale">Sale</option>
                                                    <option value="Damage">Damage/Defect</option>
                                                    <option value="Loss">Loss/Theft</option>
                                                    <option value="Return">Return to Supplier</option>
                                                    <option value="Other">Other</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Notes</label>
                                                <textarea class="form-control" name="notes" rows="2" placeholder="Additional notes..."></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Confirm Stock Out</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewHistory(productId) {
            window.location.href = 'stock_history.php?product_id=' + productId;
        }
    </script>
</body>
</html>
