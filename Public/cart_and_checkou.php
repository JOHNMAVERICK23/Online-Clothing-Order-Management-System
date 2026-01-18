<?php
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Initialize wishlist if not exists
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Determine if user is logged in
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'guest';

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

// Handle cart actions (for both guests and logged in users)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $product_id) {
                if ($quantity > 0) {
                    $item['quantity'] = $quantity;
                } else {
                    // Remove item if quantity is 0
                    $key = array_search($item, $_SESSION['cart']);
                    if ($key !== false) {
                        unset($_SESSION['cart'][$key]);
                        $_SESSION['cart'] = array_values($_SESSION['cart']);
                    }
                }
                break;
            }
        }
    }
    
    if (isset($_POST['remove_item'])) {
        $product_id = intval($_POST['product_id']);
        
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product_id) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                break;
            }
        }
    }
    
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_POST['move_to_wishlist'])) {
        $product_id = intval($_POST['product_id']);
        
        // Initialize wishlist if not exists
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
        
        // Remove from cart
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product_id) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                break;
            }
        }
        
        // Add to wishlist if not already there
        if (!in_array($product_id, $_SESSION['wishlist'])) {
            $_SESSION['wishlist'][] = $product_id;
        }
    }
}

// Handle checkout (requires login)
$checkout_error = '';
$checkout_success = false;
$order_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!$is_logged_in) {
        // Redirect to login with checkout redirect
        header('Location: ../LANDING PAGE/login_register.php?redirect=checkout');
        exit;
    }
    
    if (empty($_SESSION['cart'])) {
        $checkout_error = "Your cart is empty!";
    } else {
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
            $checkout_error = "Please fill in all required shipping information.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Generate order number
                $order_number = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Get cart totals
                $subtotal = 0;
                $total_items = 0;
                
                // Get cart products with details
                if (!empty($_SESSION['cart'])) {
                    $product_ids = array_column($_SESSION['cart'], 'product_id');
                    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
                    $stmt = $pdo->prepare("SELECT p.* FROM products p WHERE p.id IN ($placeholders) AND p.is_active = TRUE");
                    $stmt->execute($product_ids);
                    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Calculate totals
                    foreach ($products as $product) {
                        foreach ($_SESSION['cart'] as $cart_item) {
                            if ($cart_item['product_id'] == $product['id']) {
                                $subtotal += $product['price'] * $cart_item['quantity'];
                                $total_items += $cart_item['quantity'];
                                break;
                            }
                        }
                    }
                }
                
                $shipping_fee = 50.00;
                $tax_rate = 0.12;
                $tax_amount = $subtotal * $tax_rate;
                $total = $subtotal + $shipping_fee + $tax_amount;
                
                // Insert order
                $order_stmt = $pdo->prepare("INSERT INTO orders 
                    (user_id, order_number, shipping_name, shipping_phone, shipping_address, 
                     shipping_city, shipping_province, shipping_zip, payment_method, notes,
                     subtotal, shipping_fee, tax_amount, total_amount, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                
                $order_stmt->execute([
                    $_SESSION['user_id'], $order_number, $shipping_name, $shipping_phone, $shipping_address,
                    $shipping_city, $shipping_province, $shipping_zip, $payment_method, $notes,
                    $subtotal, $shipping_fee, $tax_amount, $total
                ]);
                
                $order_id = $pdo->lastInsertId();
                
                // Insert order items
                $order_item_stmt = $pdo->prepare("INSERT INTO order_items 
                    (order_id, product_id, product_name, quantity, unit_price, total_price) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                
                foreach ($products as $product) {
                    foreach ($_SESSION['cart'] as $cart_item) {
                        if ($cart_item['product_id'] == $product['id']) {
                            $order_item_stmt->execute([
                                $order_id,
                                $product['id'],
                                $product['name'],
                                $cart_item['quantity'],
                                $product['price'],
                                $product['price'] * $cart_item['quantity']
                            ]);
                            
                            // Update product stock
                            $update_stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                            $update_stmt->execute([$cart_item['quantity'], $product['id']]);
                            break;
                        }
                    }
                }
                
                // Update user shipping info
                $update_user_stmt = $pdo->prepare("UPDATE users SET address = ?, phone = ? WHERE id = ?");
                $full_address = $shipping_address . ', ' . $shipping_city . ', ' . $shipping_province . ' ' . $shipping_zip;
                $update_user_stmt->execute([$full_address, $shipping_phone, $_SESSION['user_id']]);
                
                $pdo->commit();
                
                // Clear cart and set success
                $_SESSION['cart'] = [];
                $checkout_success = true;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $checkout_error = "Failed to place order: " . $e->getMessage();
            }
        }
    }
}

// Get cart products for display
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
$shipping_fee = $subtotal > 0 ? 50.00 : 0;
$tax_rate = 0.12;
$tax_amount = $subtotal * $tax_rate;
$total = $subtotal + $shipping_fee + $tax_amount;

// Get user data for checkout form (if logged in)
$user_data = [];
if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT name, email, phone, address FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login_register.php');
    exit;
}

// Check if we should show checkout form (from query parameter or after adding to cart)
$show_checkout = isset($_GET['checkout']) || isset($_POST['place_order']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart & Checkout - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ONLY CART & CHECKOUT SPECIFIC STYLES */
        .shop-container {
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

        /* Layout */
        .content-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 992px) {
            .content-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Cart Section */
        .cart-section {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            font-size: 1.2rem;
            color: var(--dark);
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-cart i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--gray-light);
        }

        .empty-cart h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
            font-weight: 400;
        }

        .empty-cart p {
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Cart Items */
        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            background: var(--gray-light);
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

        .item-category {
            color: var(--gray);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .item-price {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--dark);
        }

        .item-stock {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stock-available {
            color: var(--success);
        }

        .stock-out {
            color: var(--gray);
        }

        .item-controls {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .quantity-btn:hover {
            border-color: var(--dark);
        }

        .quantity-input {
            width: 50px;
            text-align: center;
            padding: 0.5rem;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            font-size: 0.9rem;
        }

        .item-total {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--dark);
        }

        .item-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Checkout Form Section */
        .checkout-section {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
            margin-bottom: 1.5rem;
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

        /* Payment Options */
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
            border-color: var(--dark);
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

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        .checkout-btn {
            width: 100%;
            padding: 1rem;
            margin-top: 1rem;
        }

        /* Cart Actions */
        .cart-actions {
            display: flex;
            justify-content: space-between;
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--gray-light);
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
            animation: slideIn 0.3s ease;
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

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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

        /* Guest Checkout Notice */
        .guest-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-radius: 0;
        }

        .guest-notice h3 {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .shop-container {
                padding: 1rem;
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
            
            .cart-actions {
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
            
            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .item-image {
                width: 100%;
                height: 200px;
                margin: 0 auto;
            }
            
            .item-controls {
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation (from main.css) -->
    <nav class="customer-nav" id="customerNav">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="shop.php" class="nav-logo">
            <img src="../../resources/images/logo.jpeg" alt="Alas Clothing Shop" class="logo-image">
            <span class="brand-name"></span>
        </a>
        
        <ul class="nav-menu" id="navMenu">
            <li><a href="index.php">HOME</a></li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="orders.php">MY ORDERS</a></li>
            <li><a href="size_chart.php">SIZE CHART</a></li>
            <li><a href="shipping.php">SHIPPING</a></li>
            <li><a href="announcements.php">ANNOUNCEMENTS</a></li>
        </ul>
        
        <div class="nav-right">
            <a href="<?php echo (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) ? 'account.php' : 'login_register.php'; ?>" class="nav-icon" title="Account">
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
            <a href="cart_and_checkout.php" class="nav-icon active" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge" id="cartCount">
                    <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>
                </span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="shop-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1><?php echo $show_checkout ? 'Checkout' : 'Shopping Cart'; ?></h1>
                <p class="page-subtitle"><?php echo $show_checkout ? 'Complete your purchase securely' : 'Review your items and proceed to checkout'; ?></p>
            </div>

            <!-- Checkout Steps (only show if cart not empty) -->
            <?php if (!empty($cart_products)): ?>
            <div class="checkout-steps">
                <div class="steps-line"></div>
                <div class="step <?php echo !$show_checkout ? 'active' : 'completed'; ?>">
                    <div class="step-number">1</div>
                    <div class="step-label">Cart</div>
                </div>
                <div class="step <?php echo $show_checkout ? 'active' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-label">Checkout</div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($checkout_error): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($checkout_error); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart_products) && !$checkout_success): ?>
                <!-- Empty Cart -->
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Your cart is empty</h3>
                    <p>Add some products to your cart and they will appear here.</p>
                    <a href="shop.php" class="btn btn-primary" style="margin-top: 2rem;">
                        <i class="fas fa-store"></i> Continue Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="content-layout">
                    <!-- Left Column: Cart or Checkout Form -->
                    <div class="left-column">
                        <?php if (!$show_checkout): ?>
                            <!-- Cart Items -->
                            <div class="cart-section">
                                <div class="section-header">
                                    <h2>Your Items (<?php echo $total_items; ?>)</h2>
                                    <?php if (!empty($cart_products)): ?>
                                        <form method="POST" style="display: inline;">
                                            <button type="submit" name="clear_cart" class="btn btn-danger btn-small" 
                                                    onclick="return confirm('Clear all items from cart?')">
                                                <i class="fas fa-trash"></i> Clear Cart
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <?php foreach ($cart_products as $product): ?>
                                <div class="cart-item" id="cart-item-<?php echo $product['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($product['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                         class="item-image">
                                    
                                    <div class="item-details">
                                        <h3 class="item-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <div class="item-category">
                                            <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                        </div>
                                        <div class="item-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                        <div class="item-stock">
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <span class="stock-available">
                                                    <i class="fas fa-check-circle"></i>
                                                    <?php echo $product['stock_quantity']; ?> in stock
                                                </span>
                                            <?php else: ?>
                                                <span class="stock-out">
                                                    <i class="fas fa-times-circle"></i>
                                                    Out of stock
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="item-controls">
                                        <div class="quantity-controls">
                                            <button type="button" class="quantity-btn decrease" 
                                                    onclick="updateQuantity(<?php echo $product['id']; ?>, -1)">-</button>
                                            <input type="number" 
                                                   class="quantity-input" 
                                                   value="<?php echo $product['cart_quantity']; ?>" 
                                                   min="1" 
                                                   max="<?php echo $product['stock_quantity']; ?>"
                                                   onchange="updateQuantityInput(<?php echo $product['id']; ?>, this.value)">
                                            <button type="button" class="quantity-btn increase" 
                                                    onclick="updateQuantity(<?php echo $product['id']; ?>, 1)">+</button>
                                            <input type="hidden" name="update_quantity" value="1">
                                        </div>
                                        
                                        <div class="item-total">
                                            ₱<?php echo number_format($product['item_total'], 2); ?>
                                        </div>
                                        
                                        <div class="item-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" name="move_to_wishlist" class="btn btn-secondary btn-small">
                                                    <i class="fas fa-heart"></i> Save
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" name="remove_item" class="btn btn-danger btn-small" 
                                                        onclick="return confirm('Remove item from cart?')">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="cart-actions">
                                    <a href="shop.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Continue Shopping
                                    </a>
                                    <a href="?checkout=1" class="btn btn-primary">
                                        <i class="fas fa-lock"></i> Proceed to Checkout
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Checkout Form -->
                            <form method="POST" id="checkoutForm">
                                <!-- Guest Checkout Notice -->
                                <?php if (!$is_logged_in): ?>
                                <div class="guest-notice">
                                    <h3><i class="fas fa-info-circle"></i> Guest Checkout</h3>
                                    <p>You're checking out as a guest. To track your order and save your information for next time, 
                                       <a href="login_register.php?redirect=checkout" style="color: var(--dark); font-weight: 500;">create an account</a>.</p>
                                </div>
                                <?php endif; ?>

                                <!-- Shipping Information -->
                                <div class="checkout-section">
                                    <div class="section-header">
                                        <h2><i class="fas fa-user"></i> Shipping Information</h2>
                                    </div>
                                    <div class="section-content">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label class="form-label">Full Name <span class="required">*</span></label>
                                                <input type="text" name="shipping_name" class="form-control" 
                                                       value="<?php echo htmlspecialchars($user_data['name'] ?? $user_name); ?>" 
                                                       required placeholder="Juan Dela Cruz">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Phone Number <span class="required">*</span></label>
                                                <input type="tel" name="shipping_phone" class="form-control" 
                                                       value="<?php echo htmlspecialchars($user_data['phone'] ?? ''); ?>" 
                                                       required placeholder="09123456789">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Address <span class="required">*</span></label>
                                            <input type="text" name="shipping_address" class="form-control" 
                                                   value="<?php echo htmlspecialchars($user_data['address'] ?? ''); ?>" 
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
                                        <h2><i class="fas fa-credit-card"></i> Payment Method</h2>
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
                                        <h2><i class="fas fa-sticky-note"></i> Order Notes (Optional)</h2>
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

                                <!-- Checkout Actions -->
                                <div class="cart-actions">
                                    <a href="?" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Cart
                                    </a>
                                    <button type="submit" name="place_order" class="btn btn-primary">
                                        <i class="fas fa-lock"></i> Place Order
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Order Summary -->
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
                            
                            <?php if (!$show_checkout): ?>
                                <a href="?checkout=1" class="btn btn-primary btn-block checkout-btn">
                                    <i class="fas fa-lock"></i> Proceed to Checkout
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
                    <div class="order-number">Order #: <?php echo $order_number; ?></div>
                    <div style="color: var(--gray); font-size: 0.9rem; margin-bottom: 0.5rem;">
                        <?php echo date('F d, Y h:i A'); ?>
                    </div>
                    <div class="order-total">Total: ₱<?php echo number_format($total, 2); ?></div>
                    <div style="color: var(--gray); font-size: 0.9rem;">
                        Payment Method: Cash on Delivery
                    </div>
                </div>
                
                <p style="margin: 1.5rem 0; color: var(--gray); font-size: 0.9rem;">
                    <?php if ($is_logged_in): ?>
                        You will receive an order confirmation email shortly. 
                        You can also track your order from your account page.
                    <?php else: ?>
                        Please save your order number for tracking. 
                        <a href="login_register.php" style="color: var(--dark); font-weight: 500;">Create an account</a> to track your order easily.
                    <?php endif; ?>
                </p>
            </div>
            <div class="modal-footer">
                <?php if ($is_logged_in): ?>
                    <a href="account.php?tab=orders" class="btn btn-primary">
                        <i class="fas fa-clipboard-list"></i> View Orders
                    </a>
                <?php endif; ?>
                <a href="shop.php" class="btn btn-secondary">
                    <i class="fas fa-shopping-bag"></i> Continue Shopping
                </a>
            </div>
        </div>
    </div>

    <!-- Footer (from main.css) -->
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
                    <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
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

    <script src="js/main.js"></script>
    <script>
        // Cart and Checkout specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize navbar using global function from main.js
            if (typeof window.navbar !== 'undefined') {
                window.navbar.init();
                window.navbar.highlightActivePage();
            }
            
            // Update cart count using global functions from main.js
            const cartCount = <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>;
            const wishlistCount = <?php echo isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0; ?>;
            
            if (typeof window.cart !== 'undefined') {
                window.cart.updateCount(cartCount);
                window.cart.updateWishlistCount(wishlistCount);
            }
            
            // Initialize payment method selection
            initPaymentMethods();
            
            // Initialize quantity controls
            initQuantityControls();
            
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

        // Payment method selection
        function initPaymentMethods() {
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
            if (defaultPayment && paymentDetails && paymentInstructions) {
                paymentDetails.style.display = 'block';
                paymentInstructions.textContent = paymentInstructionsMap[defaultPayment.value] || 'Select a payment method to see instructions.';
            }
        }

        // Quantity Controls
        function initQuantityControls() {
            // Add event listeners to quantity buttons
            document.querySelectorAll('.quantity-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.getAttribute('onclick').match(/\d+/)[0];
                    const change = this.classList.contains('increase') ? 1 : -1;
                    updateQuantity(productId, change);
                });
            });

            // Add event listeners to quantity inputs
            document.querySelectorAll('.quantity-input').forEach(input => {
                input.addEventListener('change', function() {
                    const productId = this.getAttribute('onchange').match(/\d+/)[0];
                    updateQuantityInput(productId, this.value);
                });
            });
        }

        function updateQuantity(productId, change) {
            const input = document.querySelector(`#cart-item-${productId} .quantity-input`);
            if (!input) return;
            
            const currentValue = parseInt(input.value);
            const max = parseInt(input.max);
            const newValue = currentValue + change;
            
            if (newValue >= 1 && newValue <= max) {
                input.value = newValue;
                submitQuantityUpdate(productId, newValue);
            }
        }

        function updateQuantityInput(productId, value) {
            const input = document.querySelector(`#cart-item-${productId} .quantity-input`);
            if (!input) return;
            
            const max = parseInt(input.max);
            if (value >= 1 && value <= max) {
                submitQuantityUpdate(productId, parseInt(value));
            } else {
                alert(`Quantity must be between 1 and ${max}`);
                input.value = input.dataset.oldValue || 1;
            }
        }

        function submitQuantityUpdate(productId, quantity) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.innerHTML = `
                <input type="hidden" name="product_id" value="${productId}">
                <input type="hidden" name="quantity" value="${quantity}">
                <input type="hidden" name="update_quantity" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Form validation
        const checkoutForm = document.getElementById('checkoutForm');
        if (checkoutForm) {
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
                    // Use global notification function if available
                    if (typeof window.utils !== 'undefined' && window.utils.showNotification) {
                        window.utils.showNotification('Please fill in all required fields marked with *.', 'error');
                    } else {
                        alert('Please fill in all required fields marked with *.');
                    }
                } else {
                    // Show loading state
                    const submitBtn = checkoutForm.querySelector('button[type="submit"]');
                    const originalHtml = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    submitBtn.disabled = true;
                    
                    // Re-enable button if form submission fails
                    setTimeout(() => {
                        submitBtn.innerHTML = originalHtml;
                        submitBtn.disabled = false;
                    }, 5000);
                }
            });
        }

        // Auto-format phone number
        const phoneInput = document.querySelector('input[name="shipping_phone"]');
        if (phoneInput) {
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
        }

        // Province selection auto-fill for Cebu
        const provinceSelect = document.querySelector('select[name="shipping_province"]');
        const cityInput = document.querySelector('input[name="shipping_city"]');
        const zipInput = document.querySelector('input[name="shipping_zip"]');
        
        if (provinceSelect && cityInput && zipInput) {
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
        }
    </script>
</body>
</html>