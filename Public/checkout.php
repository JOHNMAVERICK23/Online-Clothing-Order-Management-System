<?php
session_start();

// Check if user is logged in for checkout
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login_register.php?redirect=checkout');
    exit;
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header('Location: cart_and_checkout.php');
    exit;
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Database connection
$host = 'localhost';
$dbname = 'clothing_shop';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, name as full_name, email, phone, address FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get cart products
$cart_products = [];
$subtotal = 0;
$total_items = 0;

if (!empty($_SESSION['cart'])) {
    $product_ids = array_column($_SESSION['cart'], 'product_id');
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.id IN ($placeholders) AND p.is_active = TRUE");
    $stmt->execute($product_ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combine with cart quantities
    foreach ($products as $product) {
        foreach ($_SESSION['cart'] as $cart_item) {
            if ($cart_item['product_id'] == $product['id']) {
                $product['cart_quantity'] = $cart_item['quantity'];
                $product['item_total'] = $product['price'] * $cart_item['quantity'];
                $cart_products[] = $product;
                $subtotal += $product['item_total'];
                $total_items += $cart_item['quantity'];
                break;
            }
        }
    }
}

// Calculate totals
$shipping_fee = 50.00; // Fixed shipping fee
$tax_rate = 0.12; // 12% tax
$tax_amount = $subtotal * $tax_rate;
$total = $subtotal + $shipping_fee + $tax_amount;

// Handle checkout form submission
$checkout_success = false;
$checkout_error = '';
$order_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    try {
        $pdo->beginTransaction();
        
        // Get form data
        $shipping_name = trim($_POST['shipping_name']);
        $shipping_phone = trim($_POST['shipping_phone']);
        $shipping_address = trim($_POST['shipping_address']);
        $shipping_city = trim($_POST['shipping_city']);
        $shipping_province = trim($_POST['shipping_province']);
        $shipping_zip = trim($_POST['shipping_zip']);
        $payment_method = $_POST['payment_method'];
        $notes = trim($_POST['notes'] ?? '');
        
        // Validate required fields
        if (empty($shipping_name) || empty($shipping_phone) || empty($shipping_address) || 
            empty($shipping_city) || empty($shipping_province) || empty($shipping_zip)) {
            throw new Exception('Please fill in all required shipping information.');
        }
        
        // Generate order number
        $order_number = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert order
        $order_stmt = $pdo->prepare("INSERT INTO orders 
            (user_id, order_number, shipping_name, shipping_phone, shipping_address, 
             shipping_city, shipping_province, shipping_zip, payment_method, notes,
             subtotal, shipping_fee, tax_amount, total_amount, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        $order_stmt->execute([
            $user_id, $order_number, $shipping_name, $shipping_phone, $shipping_address,
            $shipping_city, $shipping_province, $shipping_zip, $payment_method, $notes,
            $subtotal, $shipping_fee, $tax_amount, $total
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Insert order items
        $order_item_stmt = $pdo->prepare("INSERT INTO order_items 
            (order_id, product_id, product_name, quantity, unit_price, total_price) 
            VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($cart_products as $product) {
            $order_item_stmt->execute([
                $order_id,
                $product['id'],
                $product['name'],
                $product['cart_quantity'],
                $product['price'],
                $product['item_total']
            ]);
            
            // Update product stock
            $update_stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
            $update_stmt->execute([$product['cart_quantity'], $product['id']]);
        }
        
        // Clear cart after successful order
        $_SESSION['cart'] = [];
        
        // Update user shipping info if changed
        if ($shipping_address !== $user['address'] || $shipping_phone !== $user['phone']) {
            $update_user_stmt = $pdo->prepare("UPDATE users SET address = ?, phone = ? WHERE id = ?");
            $update_user_stmt->execute([$shipping_address . ', ' . $shipping_city . ', ' . $shipping_province . ' ' . $shipping_zip, 
                                       $shipping_phone, $user_id]);
        }
        
        $pdo->commit();
        $checkout_success = true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $checkout_error = 'Checkout failed: ' . $e->getMessage();
    }
}

$user_name = $_SESSION['user_name'] ?? 'Customer';

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
    <title>Checkout - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #000000;
            --primary-dark: #111111;
            --secondary: #666666;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #ff3b30;
            --light: #ffffff;
            --dark: #000000;
            --gray: #888888;
            --gray-light: #f5f5f5;
            --border: #e0e0e0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light);
            color: var(--dark);
            min-height: 100vh;
        }

        /* Navigation (Same as shop.php) */
        .customer-nav {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--dark);
            text-decoration: none;
        }

        .logo-image {
            height: 40px;
            width: auto;
        }

        .brand-name {
            font-weight: 700;
            letter-spacing: 1px;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 25px;
        }

        .nav-menu a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: #f0f0f0;
            color: var(--dark);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-icon {
            color: var(--dark);
            font-size: 1.1rem;
            text-decoration: none;
            position: relative;
            transition: color 0.3s;
        }

        .nav-icon:hover {
            color: var(--secondary);
        }

        .cart-count-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .mobile-menu-btn {
            display: none;
            font-size: 1.5rem;
            background: none;
            border: none;
            color: var(--dark);
            cursor: pointer;
            padding: 5px;
        }

        @media (max-width: 992px) {
            .customer-nav {
                padding: 1rem;
            }
            
            .mobile-menu-btn {
                display: block;
                order: 1;
                margin-right: 10px;
            }
            
            .nav-logo {
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                order: 2;
            }
            
            .nav-right {
                order: 3;
                margin-left: auto;
            }
            
            .nav-left .nav-menu {
                display: none;
            }
            
            .nav-menu {
                display: none;
                position: fixed;
                top: 70px;
                left: 0;
                width: 100%;
                background: white;
                flex-direction: column;
                padding: 0;
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
                z-index: 1000;
                border-top: 1px solid var(--border);
                max-height: calc(100vh - 70px);
                overflow-y: auto;
            }
            
            .nav-menu.active {
                display: flex;
            }
            
            .nav-menu a {
                padding: 1rem 1.5rem;
                border-bottom: 1px solid var(--border);
                justify-content: flex-start;
                border-radius: 0;
            }
        }

        /* Checkout Content */
        .checkout-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
            text-align: center;
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

        /* Checkout Steps */
        .checkout-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            padding: 0 2rem;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light);
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 0.5rem;
            transition: all 0.3s;
        }

        .step.active .step-number {
            background: var(--dark);
            border-color: var(--dark);
            color: var(--light);
        }

        .step.completed .step-number {
            background: var(--success);
            border-color: var(--success);
            color: var(--light);
        }

        .step-label {
            font-size: 0.9rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .step.active .step-label {
            color: var(--dark);
            font-weight: 500;
        }

        .steps-line {
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: var(--border);
            z-index: 1;
        }

        /* Checkout Layout */
        .checkout-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
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
            grid-column: 1 / -1;
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

        /* Checkout Sections */
        .checkout-section {
            background: var(--light);
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--dark);
        }

        .section-content {
            padding: 1.5rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-label .required {
            color: var(--danger);
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--light);
            border-radius: 0;
            font-size: 1rem;
            color: var(--dark);
            transition: border 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--dark);
        }

        .form-control::placeholder {
            color: var(--gray);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        /* Radio Buttons */
        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .payment-option {
            position: relative;
        }

        .payment-radio {
            position: absolute;
            opacity: 0;
        }

        .payment-label {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--light);
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-radio:checked + .payment-label {
            border-color: var(--dark);
            background: var(--gray-light);
        }

        .payment-icon {
            font-size: 1.5rem;
            color: var(--dark);
        }

        .payment-info h4 {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }

        .payment-info p {
            font-size: 0.85rem;
            color: var(--gray);
        }

        /* Order Summary */
        .order-summary {
            background: var(--light);
            border: 1px solid var(--border);
            position: sticky;
            top: 100px;
        }

        .summary-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--dark);
        }

        .summary-items {
            max-height: 300px;
            overflow-y: auto;
            border-bottom: 1px solid var(--border);
        }

        .summary-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            background: var(--gray-light);
        }

        .summary-details {
            flex: 1;
        }

        .summary-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .summary-quantity {
            font-size: 0.85rem;
            color: var(--gray);
        }

        .summary-price {
            font-weight: 500;
            color: var(--dark);
        }

        .summary-totals {
            padding: 1.5rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .total-label {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .total-value {
            font-weight: 500;
            color: var(--dark);
        }

        .total-grand {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        /* Buttons */
        .btn {
            padding: 1rem 1.5rem;
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
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background: var(--dark);
            color: var(--light);
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        /* Checkout Actions */
        .checkout-actions {
            display: flex;
            justify-content: space-between;
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--gray-light);
        }

        /* Order Success Modal */
        .order-success-modal {
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

        .order-success-modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--light);
            width: 100%;
            max-width: 600px;
            border-radius: 0;
            animation: modalSlideIn 0.3s ease;
            border: 1px solid var(--border);
        }

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            text-align: center;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            color: var(--dark);
            font-weight: 500;
            letter-spacing: 1px;
        }

        .modal-body {
            padding: 2rem;
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success);
            color: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
        }

        .order-details {
            background: var(--gray-light);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border: 1px solid var(--border);
        }

        .order-number {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .order-total {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin: 1rem 0;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 1rem;
            justify-content: center;
        }

        /* Security Badge */
        .security-badge {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 1rem;
            background: var(--gray-light);
            border: 1px solid var(--border);
            margin-top: 1.5rem;
        }

        .security-icon {
            color: var(--success);
            font-size: 1.5rem;
        }

        .security-text {
            font-size: 0.85rem;
            color: var(--gray);
        }

        /* Mobile Responsive */
        @media (max-width: 992px) {
            .checkout-container {
                padding: 1rem;
            }
            
            .checkout-layout {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
                margin-top: 2rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .checkout-steps {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .step {
                padding: 0 1rem;
            }
            
            .steps-line {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .payment-options {
                grid-template-columns: 1fr;
            }
            
            .checkout-actions {
                flex-direction: column;
                gap: 1rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .modal-footer {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    
    <!-- Navigation -->
    <nav class="customer-nav">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="shop.php" class="nav-logo">
            <img src="images/logo.jpg" alt="Alas Clothing Shop" class="logo-image">
            <span class="brand-name">Alas Clothing Shop</span>
        </a>
        
        <!-- Mobile Menu -->
        <ul class="nav-menu" id="navMenu">
            <li><a href="../../index.php">HOME</a></li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="size_chart.php">SIZE CHART</a></li>
            <li><a href="shipping.php">SHIPPING</a></li>
            <li><a href="announcements.php">ANNOUNCEMENTS</a></li>
        </ul>

        <div class="nav-right">
            <!-- Icons -->
            <a href="account.php" class="nav-icon active" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="wishlist.php" class="nav-icon" title="Wishlist">
                <i class="fas fa-heart"></i>
            </a>
            <a href="cart_and_checkout.php" class="nav-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge">0</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="checkout-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Checkout</h1>
            <p class="page-subtitle">Complete your purchase securely</p>
        </div>

        <!-- Checkout Steps -->
        <div class="checkout-steps">
            <div class="steps-line"></div>
            <div class="step active">
                <div class="step-number">1</div>
                <div class="step-label">Information</div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-label">Shipping</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-label">Payment</div>
            </div>
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-label">Confirmation</div>
            </div>
        </div>

        <?php if ($checkout_error): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($checkout_error); ?>
            </div>
        <?php endif; ?>

        <div class="checkout-layout">
            <!-- Checkout Form -->
            <div class="checkout-form">
                <form method="POST" id="checkoutForm">
                    <!-- Shipping Information -->
                    <div class="checkout-section">
                        <div class="section-header">
                            <i class="fas fa-user"></i>
                            <h2>Shipping Information</h2>
                        </div>
                        <div class="section-content">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Full Name <span class="required">*</span></label>
                                    <input type="text" name="shipping_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" 
                                           required placeholder="Juan Dela Cruz">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone Number <span class="required">*</span></label>
                                    <input type="tel" name="shipping_phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                           required placeholder="09123456789">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Address <span class="required">*</span></label>
                                <input type="text" name="shipping_address" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" 
                                       required placeholder="House #, Street, Barangay">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">City <span class="required">*</span></label>
                                    <input type="text" name="shipping_city" class="form-control" 
                                           value="Cebu City" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Province <span class="required">*</span></label>
                                    <select name="shipping_province" class="form-control" required>
                                        <option value="">Select Province</option>
                                        <option value="Cebu" selected>Cebu</option>
                                        <option value="Bohol">Bohol</option>
                                        <option value="Negros Oriental">Negros Oriental</option>
                                        <option value="Leyte">Leyte</option>
                                        <option value="Metro Manila">Metro Manila</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">ZIP Code <span class="required">*</span></label>
                                <input type="text" name="shipping_zip" class="form-control" 
                                       value="6000" required placeholder="6000">
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="checkout-section">
                        <div class="section-header">
                            <i class="fas fa-credit-card"></i>
                            <h2>Payment Method</h2>
                        </div>
                        <div class="section-content">
                            <div class="payment-options">
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="cod" id="cod" class="payment-radio" checked required>
                                    <label for="cod" class="payment-label">
                                        <i class="fas fa-money-bill-wave payment-icon"></i>
                                        <div class="payment-info">
                                            <h4>Cash on Delivery</h4>
                                            <p>Pay when you receive your order</p>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="gcash" id="gcash" class="payment-radio" required>
                                    <label for="gcash" class="payment-label">
                                        <i class="fas fa-mobile-alt payment-icon"></i>
                                        <div class="payment-info">
                                            <h4>GCash</h4>
                                            <p>Pay via GCash mobile app</p>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="bank_transfer" id="bank" class="payment-radio" required>
                                    <label for="bank" class="payment-label">
                                        <i class="fas fa-university payment-icon"></i>
                                        <div class="payment-info">
                                            <h4>Bank Transfer</h4>
                                            <p>BPI, BDO, or Metrobank</p>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="payment-option">
                                    <input type="radio" name="payment_method" value="credit_card" id="credit" class="payment-radio" required>
                                    <label for="credit" class="payment-label">
                                        <i class="fas fa-credit-card payment-icon"></i>
                                        <div class="payment-info">
                                            <h4>Credit/Debit Card</h4>
                                            <p>Visa, Mastercard, or JCB</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div id="payment-details" style="margin-top: 1.5rem; display: none;">
                                <div class="form-group">
                                    <label class="form-label">Payment Details</label>
                                    <div style="padding: 1rem; background: var(--gray-light); border: 1px solid var(--border);">
                                        <p id="payment-instructions">Select a payment method to see instructions.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Notes -->
                    <div class="checkout-section">
                        <div class="section-header">
                            <i class="fas fa-sticky-note"></i>
                            <h2>Order Notes (Optional)</h2>
                        </div>
                        <div class="section-content">
                            <div class="form-group">
                                <textarea name="notes" class="form-control" 
                                          placeholder="Special instructions for your order..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Security Badge -->
                    <div class="security-badge">
                        <i class="fas fa-shield-alt security-icon"></i>
                        <div class="security-text">
                            <strong>Secure Checkout:</strong> Your personal and payment information is encrypted and secure.
                        </div>
                    </div>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <div class="summary-header">Order Summary</div>
                
                <div class="summary-items">
                    <?php foreach ($cart_products as $product): ?>
                    <div class="summary-item">
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="summary-image">
                        <div class="summary-details">
                            <div class="summary-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="summary-quantity">Quantity: <?php echo $product['cart_quantity']; ?></div>
                        </div>
                        <div class="summary-price">₱<?php echo number_format($product['item_total'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary-totals">
                    <div class="total-row">
                        <span class="total-label">Subtotal (<?php echo $total_items; ?> items)</span>
                        <span class="total-value">₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span class="total-label">Shipping Fee</span>
                        <span class="total-value">₱<?php echo number_format($shipping_fee, 2); ?></span>
                    </div>
                    
                    <div class="total-row">
                        <span class="total-label">Tax (12% VAT)</span>
                        <span class="total-value">₱<?php echo number_format($tax_amount, 2); ?></span>
                    </div>
                    
                    <div class="total-row total-grand">
                        <span class="total-label">Total</span>
                        <span class="total-value">₱<?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <button type="submit" form="checkoutForm" name="place_order" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">
                        <i class="fas fa-lock"></i> Place Order
                    </button>
                    
                    <a href="cart_and_checkout.php" class="btn btn-secondary btn-block" style="margin-top: 0.5rem;">
                        <i class="fas fa-arrow-left"></i> Back to Cart
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Checkout Actions (for mobile) -->
        <div class="checkout-actions">
            <a href="cart_and_checkout.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Cart
            </a>
            <button type="submit" form="checkoutForm" name="place_order" class="btn btn-primary">
                <i class="fas fa-lock"></i> Place Order
            </button>
        </div>
    </div>

    <!-- Order Success Modal -->
    <div class="order-success-modal <?php echo $checkout_success ? 'active' : ''; ?>" id="orderSuccessModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Order Confirmed!</h2>
            </div>
            <div class="modal-body">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3 style="margin-bottom: 1rem;">Thank you for your order!</h3>
                <p>Your order has been placed successfully and is being processed.</p>
                
                <div class="order-details">
                    <div class="order-number">Order #: <?php echo $order_number ?? 'N/A'; ?></div>
                    <div style="color: var(--gray); font-size: 0.9rem; margin-bottom: 0.5rem;">
                        <?php echo date('F d, Y h:i A'); ?>
                    </div>
                    <div class="order-total">Total: ₱<?php echo number_format($total, 2); ?></div>
                    <div style="color: var(--gray); font-size: 0.9rem;">
                        Payment Method: Cash on Delivery
                    </div>
                </div>
                
                <p style="margin: 1.5rem 0; color: var(--gray); font-size: 0.9rem;">
                    You will receive an order confirmation email shortly. 
                    You can also track your order from your account page.
                </p>
            </div>
            <div class="modal-footer">
                <a href="account.php?tab=orders" class="btn btn-primary">
                    <i class="fas fa-clipboard-list"></i> View Orders
                </a>
                <a href="shop.php" class="btn btn-secondary">
                    <i class="fas fa-shopping-bag"></i> Continue Shopping
                </a>
            </div>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navMenu = document.getElementById('navMenu');
        
        mobileMenuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!navMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                navMenu.classList.remove('active');
            }
        });

        // Payment method selection
        const paymentRadios = document.querySelectorAll('.payment-radio');
        const paymentDetails = document.getElementById('payment-details');
        const paymentInstructions = document.getElementById('payment-instructions');
        
        const paymentInstructionsMap = {
            'cod': 'Pay with cash when your order arrives. Our delivery rider will collect the payment.',
            'gcash': 'Send payment to GCash number 0917-123-4567. Use your order number as reference.',
            'bank_transfer': 'Transfer payment to BPI Account #1234-5678-90. Send proof of payment to our email.',
            'credit_card': 'You will be redirected to our secure payment gateway to enter your card details.'
        };
        
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    paymentDetails.style.display = 'block';
                    paymentInstructions.textContent = paymentInstructionsMap[this.value] || 'Select a payment method to see instructions.';
                }
            });
        });
        
        // Set default payment instructions
        const defaultPayment = document.querySelector('.payment-radio:checked');
        if (defaultPayment) {
            paymentDetails.style.display = 'block';
            paymentInstructions.textContent = paymentInstructionsMap[defaultPayment.value] || 'Select a payment method to see instructions.';
        }

        // Form validation
        const checkoutForm = document.getElementById('checkoutForm');
        checkoutForm.addEventListener('submit', function(e) {
            // Basic validation
            const requiredFields = checkoutForm.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'var(--danger)';
                    
                    // Remove error styling on input
                    field.addEventListener('input', function() {
                        this.style.borderColor = 'var(--border)';
                    });
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *.');
            } else {
                // Show loading state
                const submitBtn = checkoutForm.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                submitBtn.disabled = true;
            }
        });

        // Auto-format phone number
        const phoneInput = document.querySelector('input[name="shipping_phone"]');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                value = value.substring(0, 11);
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 7) {
                    value = value.substring(0, 4) + '-' + value.substring(4);
                } else {
                    value = value.substring(0, 4) + '-' + value.substring(4, 7) + '-' + value.substring(7);
                }
            }
            e.target.value = value;
        });

        // Update cart count on page load
        document.addEventListener('DOMContentLoaded', function() {
            const cartCount = <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>;
            const cartBadge = document.querySelector('.cart-count-badge');
            
            if (cartBadge) {
                cartBadge.textContent = cartCount;
            }
            
            // If order was successful, show modal
            <?php if ($checkout_success): ?>
                const modal = document.getElementById('orderSuccessModal');
                if (modal) {
                    modal.classList.add('active');
                    
                    // Prevent closing modal by clicking outside
                    modal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            e.stopPropagation();
                        }
                    });
                }
            <?php endif; ?>
        });

        // Province selection auto-fill for Cebu
        const provinceSelect = document.querySelector('select[name="shipping_province"]');
        const cityInput = document.querySelector('input[name="shipping_city"]');
        const zipInput = document.querySelector('input[name="shipping_zip"]');
        
        provinceSelect.addEventListener('change', function() {
            if (this.value === 'Cebu') {
                cityInput.value = 'Cebu City';
                zipInput.value = '6000';
            } else if (this.value === 'Metro Manila') {
                cityInput.value = 'Manila';
                zipInput.value = '1000';
            } else {
                cityInput.value = '';
                zipInput.value = '';
            }
        });
    </script>
</body>
</html>