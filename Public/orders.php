<?php
session_start();

// Initialize cart and wishlist if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Check if user is logged in as customer
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'guest';
$customer_id = $_SESSION['customer_id'] ?? null;

// Debug: Check session variables
error_log("Session debug - logged_in: " . ($_SESSION['logged_in'] ?? 'not set'));
error_log("Session debug - user_type: " . ($_SESSION['user_type'] ?? 'not set'));
error_log("Session debug - customer_id: " . ($_SESSION['customer_id'] ?? 'not set'));

// Redirect to login if not logged in
if (!$is_logged_in) {
    header('Location: login_register.php?redirect=orders');
    exit;
}

// Check if user_type is customer
if ($user_type !== 'customer') {
    // If user is admin/staff, they shouldn't access customer orders
    header('Location: ../admin/index.php'); // Redirect to admin dashboard
    exit;
}

// If customer_id is not set but user is logged in, we need to get it from database
if (!$customer_id && $is_logged_in && isset($_SESSION['user_email'])) {
    // Database connection
    $host = 'localhost';
    $dbname = 'clothing_management_system';
    $username = 'root';
    $password = '';
    
    try {
        $pdo_temp = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get customer_id from database using email
        $stmt = $pdo_temp->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$_SESSION['user_email']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer) {
            $customer_id = $customer['id'];
            $_SESSION['customer_id'] = $customer_id; // Store in session for future use
        } else {
            // Customer not found in database
            session_destroy();
            header('Location: login_register.php?error=account_not_found');
            exit;
        }
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// If still no customer_id, redirect to login
if (!$customer_id) {
    session_destroy();
    header('Location: login_register.php?error=session_expired');
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'clothing_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Initialize variables
$orders = [];
$status_counts = [];
$error_message = '';
$success_message = '';

// Get order status counts
$status_query = "SELECT 
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
    SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    COUNT(*) as total
    FROM orders WHERE customer_id = ?";
$status_stmt = $pdo->prepare($status_query);
$status_stmt->execute([$customer_id]);
$status_counts = $status_stmt->fetch(PDO::FETCH_ASSOC);

// Handle filter
$status_filter = $_GET['status'] ?? 'all';

// Build query based on filter
if ($status_filter !== 'all') {
    $query = "SELECT o.*, 
                     COUNT(oi.id) as item_count,
                     SUM(oi.subtotal) as items_total
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              WHERE o.customer_id = ? AND o.status = ?
              GROUP BY o.id
              ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$customer_id, $status_filter]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Query for all statuses
    $query = "SELECT o.*, 
                     COUNT(oi.id) as item_count,
                     SUM(oi.subtotal) as items_total
              FROM orders o
              LEFT JOIN order_items oi ON o.id = oi.order_id
              WHERE o.customer_id = ?
              GROUP BY o.id
              ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = intval($_POST['order_id']);
    
    // Check if order can be cancelled (only pending orders)
    $check_stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND customer_id = ?");
    $check_stmt->execute([$order_id, $customer_id]);
    $order = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order && $order['status'] === 'pending') {
        // UPDATE order status to 'cancelled' - NOT DELETE
        $update_stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        if ($update_stmt->execute([$order_id])) {
            // Restore product stock
            $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $items_stmt->execute([$order_id]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $restore_stmt = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");
                $restore_stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            $success_message = "Order has been cancelled successfully.";
            
            // Refresh orders based on current filter
            if ($status_filter !== 'all') {
                $stmt->execute([$customer_id, $status_filter]);
            } else {
                $stmt->execute([$customer_id]);
            }
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Refresh status counts
            $status_stmt->execute([$customer_id]);
            $status_counts = $status_stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error_message = "Failed to cancel order. Please try again.";
        }
    } else {
        $error_message = "Order cannot be cancelled. Only pending orders can be cancelled.";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login_register.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ONLY ORDERS-SPECIFIC STYLES */
        .orders-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 100px;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            color: var(--dark);
            margin-bottom: 0.5rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 1rem;
            font-weight: 300;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            border-color: var(--dark);
            transform: translateY(-2px);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 300;
            color: var(--dark);
            margin-bottom: 0.5rem;
            letter-spacing: 1px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Orders Filter */
        .orders-filter {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .filter-tab {
            padding: 0.8rem 1.5rem;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .filter-tab:hover {
            border-color: var(--dark);
        }

        .filter-tab.active {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        /* Orders List */
        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .order-card {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
            overflow: hidden;
            transition: all 0.3s;
        }

        .order-card:hover {
            border-color: var(--dark);
            transform: translateY(-2px);
        }

        .order-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--gray-light);
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .order-number {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            letter-spacing: 0.5px;
        }

        .order-date {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 300;
        }

        .order-status {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            border-color: var(--warning);
            color: var(--warning);
        }

        .status-processing {
            border-color: var(--secondary);
            color: var(--secondary);
        }

        .status-shipped {
            border-color: var(--dark);
            color: var(--dark);
        }

        .status-delivered {
            border-color: var(--success);
            color: var(--success);
        }

        .status-cancelled {
            border-color: var(--danger);
            color: var(--danger);
        }

        /* Order Items */
        .order-items {
            padding: 1.5rem;
        }

        .items-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .order-item {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }

        .order-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            background: var(--gray-light);
            display: block;
        }

        .item-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .item-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark);
        }

        .item-meta {
            color: var(--gray);
            font-size: 0.85rem;
        }

        .item-total {
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark);
            text-align: right;
        }

        /* Order Summary */
        .order-summary {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--gray-light);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .summary-label {
            color: var(--gray);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-value {
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark);
        }

        .order-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--dark);
        }

        /* Order Actions */
        .order-actions {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            text-align: center;
        }

        .btn-primary {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        .btn-primary:hover {
            background: var(--primary-light);
            border-color: var(--primary-light);
        }

        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border-color: var(--dark);
        }

        .btn-secondary:hover {
            background: var(--dark);
            color: var(--light);
        }

        .btn-danger {
            background: var(--light);
            color: var(--danger);
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: var(--light);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--gray-light);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
            font-weight: 300;
        }

        .empty-state p {
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 300;
        }

        /* Messages */
        .message {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border-radius: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .success-message {
            background: #f0f9f0;
            border: 1px solid #d4edda;
            color: #155724;
        }

        .error-message {
            background: #fdf0f0;
            border: 1px solid #f8d7da;
            color: #721c24;
        }

        /* Modal Styles */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--light);
            width: 100%;
            max-width: 800px;
            border-radius: 0;
            animation: modalSlideIn 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border);
        }

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            color: var(--dark);
            font-weight: 500;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark);
        }

        .modal-body {
            padding: 2rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .order-item {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .item-image {
                margin: 0 auto;
            }
            
            .order-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .orders-container {
                padding: 1rem;
                padding-top: 80px;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-tabs {
                flex-direction: column;
            }
            
            .filter-tab {
                text-align: center;
            }
            
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="customer-nav" id="customerNav">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="shop.php" class="nav-logo">
            <img src="../images/logo.jpg" alt="Alas Clothing Shop" class="logo-image">
            <span class="brand-name">Alas Clothing Shop</span>
        </a>
        
        <ul class="nav-menu" id="navMenu">
            <li><a href="../index.php">HOME</a></li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="orders.php" class="active">MY ORDERS</a></li>
            <li><a href="size_chart.php">SIZE CHART</a></li>
            <li><a href="shipping.php">SHIPPING</a></li>
            <li><a href="announcements.php">ANNOUNCEMENTS</a></li>
        </ul>
        
        <div class="nav-right">
            <a href="account.php" class="nav-icon" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="wishlist.php" class="nav-icon" title="Wishlist">
                <i class="fas fa-heart"></i>
                <?php if (!empty($_SESSION['wishlist'])): ?>
                    <span class="wishlist-count-badge" id="wishlistCount">
                        <?php echo count($_SESSION['wishlist']); ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="cart_and_checkout.php" class="nav-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge" id="cartCount">
                    <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>
                </span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="orders-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>My Orders</h1>
            <p class="page-subtitle">Track and manage your purchases</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['processing'] ?? 0; ?></div>
                <div class="stat-label">Processing</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['shipped'] ?? 0; ?></div>
                <div class="stat-label">Shipped</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['delivered'] ?? 0; ?></div>
                <div class="stat-label">Delivered</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['cancelled'] ?? 0; ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-number"><?php echo $status_counts['total'] ?? 0; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
        </div>

        <!-- Orders Filter -->
        <div class="orders-filter">
            <div class="filter-title">Filter by Status</div>
            <div class="filter-tabs">
                <a href="?status=all" class="filter-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    All Orders
                </a>
                <a href="?status=pending" class="filter-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    Pending
                </a>
                <a href="?status=processing" class="filter-tab <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">
                    Processing
                </a>
                <a href="?status=shipped" class="filter-tab <?php echo $status_filter === 'shipped' ? 'active' : ''; ?>">
                    Shipped
                </a>
                <a href="?status=delivered" class="filter-tab <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>">
                    Delivered
                </a>
                <a href="?status=cancelled" class="filter-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                    Cancelled
                </a>
            </div>
        </div>

        <!-- Orders List -->
        <div class="orders-list">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>No orders found</h3>
                    <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
                    <a href="shop.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <!-- Order Header -->
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                            <div class="order-date">Placed on <?php echo date('F d, Y', strtotime($order['created_at'])); ?></div>
                        </div>
                        <div class="order-status status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="order-items">
                        <div class="items-list">
                            <?php
                            // Get order items
                            $items_stmt = $pdo->prepare("SELECT oi.*, p.product_name, p.image_url FROM order_items oi 
                                                        JOIN products p ON oi.product_id = p.id 
                                                        WHERE oi.order_id = ?");
                            $items_stmt->execute([$order['id']]);
                            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($items as $item):
                            ?>
                            <div class="order-item">
                                <img src="<?php echo htmlspecialchars($item['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                     class="item-image">
                                <div class="item-details">
                                    <div class="item-title"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div class="item-meta">
                                        Quantity: <?php echo $item['quantity']; ?> × ₱<?php echo number_format($item['price'], 2); ?>
                                    </div>
                                </div>
                                <div class="item-total">₱<?php echo number_format($item['subtotal'], 2); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <div class="summary-grid">
                            <div class="summary-item">
                                <div class="summary-label">Items</div>
                                <div class="summary-value"><?php echo $order['item_count']; ?> items</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Subtotal</div>
                                <div class="summary-value">₱<?php echo number_format($order['items_total'] ?? 0, 2); ?></div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Shipping</div>
                                <div class="summary-value">₱50.00</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-label">Total</div>
                                <div class="summary-value">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                            </div>
                        </div>
                        
                        <div class="order-total">
                            <span>Grand Total:</span>
                            <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Order Actions -->
                    <div class="order-actions">
                        <?php if ($order['status'] === 'pending'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" name="cancel_order" value="1" class="btn btn-danger" 
                                    onclick="return confirm('Are you sure you want to cancel this order?')">
                                <i class="fas fa-times"></i> Cancel Order
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] === 'delivered'): ?>
                        <button class="btn btn-secondary reorder-btn" data-order-id="<?php echo $order['id']; ?>">
                            <i class="fas fa-redo"></i> Reorder
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-primary view-details-btn" data-order-id="<?php echo $order['id']; ?>">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                        
                        <?php if (isset($order['tracking_number']) && $order['tracking_number']): ?>
                        <button class="btn btn-secondary track-btn" data-tracking="<?php echo $order['tracking_number']; ?>">
                            <i class="fas fa-shipping-fast"></i> Track
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>Alas Clothing Shop offers premium quality clothing and accessories for every occasion. We're committed to providing exceptional style and comfort.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-pinterest"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="../index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="shop.php"><i class="fas fa-chevron-right"></i> Shop</a></li>
                    <li><a href="size_chart.php"><i class="fas fa-chevron-right"></i> Size Chart</a></li>
                    <li><a href="shipping.php"><i class="fas fa-chevron-right"></i> Shipping & Returns</a></li>
                    <li><a href="announcements.php"><i class="fas fa-chevron-right"></i> Announcements</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Customer Service</h3>
                <ul class="footer-links">
                    <li><a href="orders.php"><i class="fas fa-chevron-right"></i> My Orders</a></li>
                    <li><a href="account.php"><i class="fas fa-chevron-right"></i> My Account</a></li>
                    <li><a href="wishlist.php"><i class="fas fa-chevron-right"></i> Wishlist</a></li>
                    <li><a href="cart_and_checkout.php"><i class="fas fa-chevron-right"></i> Cart</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Contact Info</h3>
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>123 Fashion Street, City, Country 12345</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>+1 (555) 123-4567</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>info@alasclothingshop.com</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-clock"></i>
                        <span>Mon-Fri: 9AM-6PM | Sat: 10AM-4PM</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Alas Clothing Shop. All rights reserved.</p>
            <p>Designed with <i class="fas fa-heart" style="color: #ff3b30;"></i> for fashion enthusiasts</p>
        </div>
    </footer>

    <script>
        // Orders-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize navbar
            initMobileMenu();
            
            // Initialize buttons
            initializeViewDetailsButtons();
            initializeReorderButtons();
            initializeTrackButtons();
        });

        // Mobile Menu Functionality (same as in shop.php)
        function initMobileMenu() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const navMenu = document.getElementById('navMenu');
            
            if (mobileMenuBtn && navMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                    mobileMenuBtn.classList.toggle('active');
                    
                    // Change icon
                    const icon = mobileMenuBtn.querySelector('i');
                    if (navMenu.classList.contains('active')) {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    } else {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!navMenu.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                        navMenu.classList.remove('active');
                        mobileMenuBtn.classList.remove('active');
                        const icon = mobileMenuBtn.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    }
                });
                
                // Close menu when clicking on a link
                navMenu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', function() {
                        navMenu.classList.remove('active');
                        mobileMenuBtn.classList.remove('active');
                        const icon = mobileMenuBtn.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    });
                });
            }
        }

        // View Order Details
        async function viewOrderDetails(orderId) {
            try {
                // You would typically make an API call here
                // For now, we'll show a simplified modal
                showOrderModal(orderId);
                
            } catch (error) {
                console.error('Error loading order details:', error);
                alert('Unable to load order details. Please try again.');
            }
        }

        // Show Order Modal
        function showOrderModal(orderId) {
            // Create modal
            const modal = document.createElement('div');
            modal.className = 'modal active';
            
            // Get the order from the page (simplified - in real app, fetch from API)
            const orderCard = document.querySelector(`.order-card:has(.view-details-btn[data-order-id="${orderId}"])`);
            if (!orderCard) return;
            
            const orderNumber = orderCard.querySelector('.order-number').textContent;
            const orderDate = orderCard.querySelector('.order-date').textContent;
            const orderStatus = orderCard.querySelector('.order-status').textContent;
            const orderTotal = orderCard.querySelector('.order-total span:last-child').textContent;
            
            modal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>${orderNumber}</h2>
                        <button class="close-btn" onclick="this.closest('.modal').classList.remove('active')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div style="margin-bottom: 2rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="color: var(--dark); margin-bottom: 0.5rem;">Order Status</h3>
                                    <span class="order-status">${orderStatus}</span>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: var(--gray); font-size: 0.9rem;">Order Date</div>
                                    <div style="font-weight: 600;">${orderDate.replace('Placed on ', '')}</div>
                                </div>
                            </div>
                        </div>
                        
                        <h3 style="color: var(--dark); margin-bottom: 1rem;">Order Items</h3>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--gray-light);">
                                        <th style="padding: 1rem; text-align: left; border: 1px solid var(--border);">Product</th>
                                        <th style="padding: 1rem; text-align: center; border: 1px solid var(--border);">Quantity</th>
                                        <th style="padding: 1rem; text-align: right; border: 1px solid var(--border);">Price</th>
                                        <th style="padding: 1rem; text-align: right; border: 1px solid var(--border);">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${Array.from(orderCard.querySelectorAll('.order-item')).map(item => {
                                        const title = item.querySelector('.item-title').textContent;
                                        const meta = item.querySelector('.item-meta').textContent;
                                        const total = item.querySelector('.item-total').textContent;
                                        const [quantity, price] = meta.split(' × ');
                                        const image = item.querySelector('.item-image').getAttribute('src');
                                        
                                        return `
                                            <tr style="border-bottom: 1px solid var(--border);">
                                                <td style="padding: 1rem; border: 1px solid var(--border);">
                                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                                        <img src="${image}" 
                                                             alt="${title}" 
                                                             style="width: 50px; height: 50px; object-fit: cover; background: var(--gray-light);">
                                                        <div>
                                                            <div style="font-weight: 600;">${title}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td style="padding: 1rem; text-align: center; border: 1px solid var(--border);">${quantity.replace('Quantity: ', '')}</td>
                                                <td style="padding: 1rem; text-align: right; border: 1px solid var(--border);">${price}</td>
                                                <td style="padding: 1rem; text-align: right; border: 1px solid var(--border); font-weight: 600;">${total}</td>
                                            </tr>
                                        `;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top: 2rem; text-align: right; font-size: 1.2rem; font-weight: 600;">
                            Total: ${orderTotal}
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
            
            // Close modal with escape key
            document.addEventListener('keydown', function closeModal(e) {
                if (e.key === 'Escape') {
                    modal.classList.remove('active');
                    document.removeEventListener('keydown', closeModal);
                }
            });
        }

        // Reorder items
        function reorderItems(orderId) {
            if (confirm('Would you like to add all items from this order to your cart?')) {
                // Show loading
                const btn = document.querySelector(`.reorder-btn[data-order-id="${orderId}"]`);
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                btn.disabled = true;
                
                // Simulate API call
                setTimeout(() => {
                    alert('All items have been added to your cart!');
                    
                    btn.innerHTML = '<i class="fas fa-check"></i> Added';
                    btn.classList.remove('btn-secondary');
                    btn.classList.add('btn-success');
                    btn.disabled = true;
                    
                    // Update cart count
                    const cartCountEl = document.getElementById('cartCount');
                    if (cartCountEl) {
                        let currentCount = parseInt(cartCountEl.textContent) || 0;
                        currentCount += 3; // Simulate adding 3 items
                        cartCountEl.textContent = currentCount;
                    }
                }, 1000);
            }
        }

        // Track order
        function trackOrder(trackingNumber) {
            alert(`Tracking Number: ${trackingNumber}\n\nYou can track your order using the courier's website.`);
        }

        // Initialize buttons
        function initializeViewDetailsButtons() {
            document.querySelectorAll('.view-details-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    viewOrderDetails(orderId);
                });
            });
        }

        function initializeReorderButtons() {
            document.querySelectorAll('.reorder-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    reorderItems(orderId);
                });
            });
        }

        function initializeTrackButtons() {
            document.querySelectorAll('.track-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const trackingNumber = this.getAttribute('data-tracking');
                    trackOrder(trackingNumber);
                });
            });
        }
    </script>
</body>
</html>