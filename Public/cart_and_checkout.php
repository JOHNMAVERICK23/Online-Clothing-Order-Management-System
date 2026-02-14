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
$customer_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : null;
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

// Database connection
$host = 'localhost';
$dbname = 'clothing_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if required tables exist
    $check_tables = $pdo->query("SHOW TABLES LIKE 'orders'");
    if ($check_tables->rowCount() == 0) {
        die("Orders table not found in database. Please run the database setup.");
    }
    
    // Check if orders table has required columns
    $check_columns = $pdo->query("SHOW COLUMNS FROM orders");
    $order_columns = $check_columns->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['order_number', 'customer_name', 'customer_email', 'customer_phone', 'shipping_address', 'total_amount', 'status', 'payment_status'];
    foreach ($required_columns as $col) {
        if (!in_array($col, $order_columns)) {
            die("Missing required column '$col' in orders table. Please check your database structure.");
        }
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Product selection for checkout
    if (isset($_POST['select_for_checkout'])) {
        $selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];
        $_SESSION['checkout_items'] = [];
        
        // Convert selected item IDs to cart items
        foreach ($_SESSION['cart'] as $cart_item) {
            if (in_array($cart_item['product_id'], $selected_items)) {
                $_SESSION['checkout_items'][] = $cart_item;
            }
        }
        
        header('Location: cart_and_checkout.php?checkout=1');
        exit;
    }
    
    if (isset($_POST['update_quantity'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['product_id'] == $product_id) {
                if ($quantity > 0) {
                    $item['quantity'] = $quantity;
                } else {
                    $key = array_search($item, $_SESSION['cart']);
                    if ($key !== false) {
                        unset($_SESSION['cart'][$key]);
                        $_SESSION['cart'] = array_values($_SESSION['cart']);
                    }
                }
                break;
            }
        }
        
        // Update checkout items if they exist
        if (isset($_SESSION['checkout_items'])) {
            foreach ($_SESSION['checkout_items'] as &$item) {
                if ($item['product_id'] == $product_id) {
                    if ($quantity > 0) {
                        $item['quantity'] = $quantity;
                    } else {
                        $key = array_search($item, $_SESSION['checkout_items']);
                        if ($key !== false) {
                            unset($_SESSION['checkout_items'][$key]);
                            $_SESSION['checkout_items'] = array_values($_SESSION['checkout_items']);
                        }
                    }
                    break;
                }
            }
        }
        
        header('Location: cart_and_checkout.php');
        exit;
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
        
        // Remove from checkout items if exists
        if (isset($_SESSION['checkout_items'])) {
            foreach ($_SESSION['checkout_items'] as $key => $item) {
                if ($item['product_id'] == $product_id) {
                    unset($_SESSION['checkout_items'][$key]);
                    $_SESSION['checkout_items'] = array_values($_SESSION['checkout_items']);
                    break;
                }
            }
        }
        
        header('Location: cart_and_checkout.php');
        exit;
    }
    
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        unset($_SESSION['checkout_items']);
        header('Location: cart_and_checkout.php');
        exit;
    }
    
    if (isset($_POST['move_to_wishlist'])) {
        $product_id = intval($_POST['product_id']);
        
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
        
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['product_id'] == $product_id) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                break;
            }
        }
        
        if (!in_array($product_id, $_SESSION['wishlist'])) {
            $_SESSION['wishlist'][] = $product_id;
        }
        
        // Also remove from checkout items
        if (isset($_SESSION['checkout_items'])) {
            foreach ($_SESSION['checkout_items'] as $key => $item) {
                if ($item['product_id'] == $product_id) {
                    unset($_SESSION['checkout_items'][$key]);
                    $_SESSION['checkout_items'] = array_values($_SESSION['checkout_items']);
                    break;
                }
            }
        }
        
        header('Location: cart_and_checkout.php');
        exit;
    }
}

// Initialize checkout items
if (!isset($_SESSION['checkout_items'])) {
    $_SESSION['checkout_items'] = [];
}

// Handle checkout
$checkout_error = '';
$checkout_success = false;
$order_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!$is_logged_in) {
        header('Location: login_register.php?redirect=checkout');
        exit;
    }
    
    if (empty($_SESSION['cart'])) {
        $checkout_error = "Your cart is empty!";
    } else {
        $shipping_name = trim($_POST['shipping_name']);
        $shipping_phone = trim($_POST['shipping_phone']);
        $shipping_address = trim($_POST['shipping_address']);
        $shipping_city = trim($_POST['shipping_city']);
        $shipping_province = trim($_POST['shipping_province']);
        $shipping_zip = trim($_POST['shipping_zip']);
        $payment_method = $_POST['payment_method'];
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($shipping_name) || empty($shipping_phone) || empty($shipping_address) || 
            empty($shipping_city) || empty($shipping_province) || empty($shipping_zip)) {
            $checkout_error = "Please fill in all required shipping information.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Generate order number
                $order_number = 'ORD' . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Use checkout items or all cart items
                $checkout_items = !empty($_SESSION['checkout_items']) ? $_SESSION['checkout_items'] : $_SESSION['cart'];
                
                $subtotal = 0;
                $total_items = 0;
                $items_data = [];
                
                // Get product details and calculate totals
                foreach ($checkout_items as $cart_item) {
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
                    $stmt->execute([$cart_item['product_id']]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($product) {
                        if ($product['quantity'] < $cart_item['quantity']) {
                            throw new Exception("Not enough stock for {$product['product_name']}. Available: {$product['quantity']}, Requested: {$cart_item['quantity']}");
                        }
                        
                        $item_total = $product['price'] * $cart_item['quantity'];
                        $subtotal += $item_total;
                        $total_items += $cart_item['quantity'];
                        
                        $items_data[] = [
                            'product' => $product,
                            'cart_item' => $cart_item,
                            'item_total' => $item_total
                        ];
                    }
                }
                
                if (empty($items_data)) {
                    throw new Exception("No valid items found in cart.");
                }
                
                $shipping_fee = 50.00;
                $tax_rate = 0.12;
                $tax_amount = $subtotal * $tax_rate;
                $total = $subtotal + $shipping_fee + $tax_amount;
                
                // Create full shipping address
                $full_shipping_address = implode(', ', array_filter([
                    $shipping_address,
                    $shipping_city,
                    $shipping_province,
                    $shipping_zip
                ]));
                
                // FIXED: Insert order with ALL required columns for admin
                $order_stmt = $pdo->prepare("INSERT INTO orders 
                    (order_number, customer_id, customer_name, customer_email, customer_phone, 
                     shipping_address, total_amount, status, payment_status, notes, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, NOW())");
                
                $customer_email = $user_email ?: 'guest@example.com';
                
                // Use customer_id if logged in, otherwise NULL
                $order_customer_id = $is_logged_in && $customer_id ? $customer_id : NULL;
                
                $order_stmt->execute([
                    $order_number, 
                    $order_customer_id,
                    $shipping_name, 
                    $customer_email,
                    $shipping_phone, // This goes to customer_phone column (admin expects this)
                    $full_shipping_address,
                    $total,
                    $notes
                ]);
                
                $order_id = $pdo->lastInsertId();
                
                // Insert order items
                $order_item_stmt = $pdo->prepare("INSERT INTO order_items 
                    (order_id, product_id, quantity, price, subtotal) 
                    VALUES (?, ?, ?, ?, ?)");
                
                foreach ($items_data as $item) {
                    $order_item_stmt->execute([
                        $order_id,
                        $item['product']['id'],
                        $item['cart_item']['quantity'],
                        $item['product']['price'],
                        $item['item_total']
                    ]);
                    
                    // Update stock
                    $update_stmt = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                    $update_stmt->execute([$item['cart_item']['quantity'], $item['product']['id']]);
                }
                
                // FIXED: Update customer information in customers table
                if ($customer_id) {
                    try {
                        $update_customer_stmt = $pdo->prepare("UPDATE customers SET shipping_address = ?, contact_number = ? WHERE id = ?");
                        $update_customer_stmt->execute([
                            $full_shipping_address,
                            $shipping_phone,
                            $customer_id
                        ]);
                    } catch (Exception $e) {
                        // Log error but continue
                        error_log("Customer update failed: " . $e->getMessage());
                    }
                }
                
                // FIXED: Create payment record with reference number
                $payment_stmt = $pdo->prepare("INSERT INTO payments 
                    (order_id, customer_id, amount, payment_method, status, reference_number) 
                    VALUES (?, ?, ?, ?, 'pending', ?)");
                    
                $reference_number = '';
                if ($payment_method !== 'cod') {
                    $reference_number = 'PAY' . date('YmdHis') . mt_rand(1000, 9999);
                }
                
                $payment_stmt->execute([
                    $order_id,
                    $customer_id ?: 0,
                    $total,
                    $payment_method,
                    $reference_number
                ]);
                
                $pdo->commit();
                
                // Remove purchased items from cart
                foreach ($checkout_items as $item) {
                    foreach ($_SESSION['cart'] as $key => $cart_item) {
                        if ($cart_item['product_id'] == $item['product_id']) {
                            unset($_SESSION['cart'][$key]);
                            break;
                        }
                    }
                }
                $_SESSION['cart'] = array_values($_SESSION['cart']);
                
                // Clear checkout items
                unset($_SESSION['checkout_items']);
                
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
    if (!empty($product_ids)) {
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        
        $stmt = $pdo->prepare("SELECT p.* FROM products p WHERE p.id IN ($placeholders) AND p.status = 'active'");
        $stmt->execute($product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            foreach ($_SESSION['cart'] as $cart_item) {
                if ($cart_item['product_id'] == $product['id']) {
                    $product['cart_quantity'] = $cart_item['quantity'];
                    $product['item_total'] = $product['price'] * $cart_item['quantity'];
                    
                    // Check if item is selected for checkout
                    $is_selected = false;
                    if (!empty($_SESSION['checkout_items'])) {
                        foreach ($_SESSION['checkout_items'] as $checkout_item) {
                            if ($checkout_item['product_id'] == $product['id']) {
                                $is_selected = true;
                                break;
                            }
                        }
                    } else {
                        // If no checkout items, all items are selected by default
                        $is_selected = true;
                    }
                    
                    $product['selected'] = $is_selected;
                    $cart_products[] = $product;
                    
                    if ($product['selected']) {
                        $subtotal += $product['item_total'];
                        $total_items += $cart_item['quantity'];
                    }
                    break;
                }
            }
        }
    }
}

// Calculate checkout totals
$checkout_products = [];
$checkout_subtotal = 0;
$checkout_items = 0;

if (!empty($_SESSION['checkout_items'])) {
    $checkout_product_ids = array_column($_SESSION['checkout_items'], 'product_id');
    if (!empty($checkout_product_ids)) {
        $placeholders = str_repeat('?,', count($checkout_product_ids) - 1) . '?';
        
        $stmt = $pdo->prepare("SELECT p.* FROM products p WHERE p.id IN ($placeholders) AND p.status = 'active'");
        $stmt->execute($checkout_product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            foreach ($_SESSION['checkout_items'] as $item) {
                if ($item['product_id'] == $product['id']) {
                    $product['cart_quantity'] = $item['quantity'];
                    $product['item_total'] = $product['price'] * $item['quantity'];
                    $checkout_products[] = $product;
                    $checkout_subtotal += $product['item_total'];
                    $checkout_items += $item['quantity'];
                    break;
                }
            }
        }
    }
}

// Use checkout totals if we have selected items
$display_subtotal = !empty($_SESSION['checkout_items']) ? $checkout_subtotal : $subtotal;
$display_items = !empty($_SESSION['checkout_items']) ? $checkout_items : $total_items;

// Calculate final totals
$shipping_fee = $display_subtotal > 0 ? 50.00 : 0;
$tax_rate = 0.12;
$tax_amount = $display_subtotal * $tax_rate;
$total = $display_subtotal + $shipping_fee + $tax_amount;

// Get customer data
$customer_data = [];
if ($is_logged_in && isset($_SESSION['customer_id'])) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, contact_number, shipping_address FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer_data) {
        $customer_data['full_name'] = trim($customer_data['first_name'] . ' ' . $customer_data['last_name']);
    }
}

// Check if we should show checkout form
$show_checkout = isset($_GET['checkout']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']));
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
        /* FIXED CHECKBOX STYLING */
        .item-checkbox {
            position: relative;
            width: 20px;
            height: 20px;
        }

        .item-checkbox input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 100%;
            width: 100%;
            z-index: 2;
            margin: 0;
        }

        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 20px;
            width: 20px;
            background-color: var(--light);
            border: 2px solid var(--border);
            cursor: pointer;
            transition: all 0.3s;
        }

        .item-checkbox:hover input ~ .checkmark {
            border-color: var(--dark);
        }

        .item-checkbox input:checked ~ .checkmark {
            background-color: var(--dark);
            border-color: var(--dark);
        }

        .checkmark:after {
            content: "";
            position: absolute;
            display: none;
        }

        .item-checkbox input:checked ~ .checkmark:after {
            display: block;
        }

        .item-checkbox .checkmark:after {
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        /* SELECT ALL CHECKBOX */
        .select-all-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .select-all-checkbox input[type="checkbox"] {
            display: none;
        }

        .select-all-checkbox .checkmark {
            position: relative;
            width: 18px;
            height: 18px;
            background-color: var(--light);
            border: 2px solid var(--border);
            border-radius: 3px;
            transition: all 0.3s;
        }

        .select-all-checkbox input:checked ~ .checkmark {
            background-color: var(--dark);
            border-color: var(--dark);
        }

        .select-all-checkbox .checkmark:after {
            content: "";
            position: absolute;
            display: none;
            left: 5px;
            top: 2px;
            width: 4px;
            height: 8px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .select-all-checkbox input:checked ~ .checkmark:after {
            display: block;
        }

        /* CART ITEM LAYOUT */
        .cart-item {
            display: grid;
            grid-template-columns: 30px 100px 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }

        @media (max-width: 768px) {
            .cart-item {
                grid-template-columns: 30px 1fr;
                gap: 1rem;
            }
            
            .item-image {
                grid-column: 2;
                justify-self: center;
            }
            
            .item-controls {
                grid-column: 1 / span 2;
            }
        }

        /* REST OF YOUR CSS STYLES (keep as is) */
        .selection-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background: var(--gray-light);
            border-bottom: 1px solid var(--border);
        }

        .select-all {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--dark);
        }

        .selected-count {
            font-size: 0.9rem;
            color: var(--gray);
        }

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

        .checkout-section {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
            margin-bottom: 1.5rem;
        }

        .section-content {
            padding: 1.5rem;
        }

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

        .cart-actions {
            display: flex;
            justify-content: space-between;
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            background: var(--gray-light);
        }

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
        }

        @media (max-width: 576px) {
            .cart-item {
                grid-template-columns: 30px 1fr;
                gap: 1rem;
                padding: 1rem;
            }
            
            .item-image {
                width: 100%;
                height: 120px;
            }
        }
    </style>
</head>
<body>
    <nav class="customer-nav" id="customerNav">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="shop.php" class="nav-logo">
            <img src="images/logo.jpg" alt="Alas Clothing Shop" class="logo-image">
            <span class="brand-name">Alas Clothing Shop</span>
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
            <a href="<?php echo $is_logged_in ? 'account.php' : 'login_register.php'; ?>" class="nav-icon" title="Account">
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

    <div class="main-content">
        <div class="shop-container">
            <div class="page-header">
                <h1><?php echo $show_checkout ? 'Checkout' : 'Shopping Cart'; ?></h1>
                <p class="page-subtitle"><?php echo $show_checkout ? 'Complete your purchase securely' : 'Review your items and proceed to checkout'; ?></p>
            </div>

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
                    <div class="left-column">
                        <?php if (!$show_checkout): ?>
                            <form method="POST" id="cartForm">
                                <div class="cart-section">
                                    <div class="selection-header">
                                        <div class="select-all">
                                            <label class="select-all-checkbox">
                                                <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                                <span class="checkmark"></span>
                                                Select All
                                            </label>
                                        </div>
                                        <div class="selected-count" id="selectedCount">
                                            <?php 
                                            $selected_count = 0;
                                            if (!empty($_SESSION['checkout_items'])) {
                                                $selected_count = count($_SESSION['checkout_items']);
                                            } elseif (!empty($cart_products)) {
                                                // If no checkout items, all are selected by default
                                                $selected_count = count($cart_products);
                                            }
                                            echo $selected_count . ' selected';
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <div class="section-header">
                                        <h2>Your Items (<?php echo count($cart_products); ?>)</h2>
                                        <?php if (!empty($cart_products)): ?>
                                            <button type="submit" name="clear_cart" class="btn btn-danger btn-small" 
                                                    onclick="return confirm('Clear all items from cart?')">
                                                <i class="fas fa-trash"></i> Clear Cart
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <?php foreach ($cart_products as $product): ?>
                                    <div class="cart-item" id="cart-item-<?php echo $product['id']; ?>">
                                        <div class="item-checkbox">
                                            <input type="checkbox" 
                                                   name="selected_items[]" 
                                                   value="<?php echo $product['id']; ?>" 
                                                   id="item-<?php echo $product['id']; ?>"
                                                   onchange="updateSelection()"
                                                   <?php echo $product['selected'] ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                        </div>
                                        
                                        <img src="<?php echo htmlspecialchars($product['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                                             alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                             class="item-image">
                                        
                                        <div class="item-details">
                                            <h3 class="item-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                            <div class="item-category">
                                                <?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?>
                                            </div>
                                            <div class="item-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                            <div class="item-stock">
                                                <?php if ($product['quantity'] > 0): ?>
                                                    <span class="stock-available">
                                                        <i class="fas fa-check-circle"></i>
                                                        <?php echo $product['quantity']; ?> in stock
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
                                                       max="<?php echo $product['quantity']; ?>"
                                                       onchange="updateQuantityInput(<?php echo $product['id']; ?>, this.value)">
                                                <button type="button" class="quantity-btn increase" 
                                                        onclick="updateQuantity(<?php echo $product['id']; ?>, 1)">+</button>
                                            </div>
                                            
                                            <div class="item-total">
                                                ₱<?php echo number_format($product['item_total'], 2); ?>
                                            </div>
                                            
                                            <div class="item-actions">
                                                <button type="submit" name="move_to_wishlist" value="<?php echo $product['id']; ?>" class="btn btn-secondary btn-small">
                                                    <i class="fas fa-heart"></i> Save
                                                </button>
                                                <button type="submit" name="remove_item" value="<?php echo $product['id']; ?>" class="btn btn-danger btn-small" 
                                                        onclick="return confirm('Remove item from cart?')">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="cart-actions">
                                        <a href="shop.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Continue Shopping
                                        </a>
                                        <button type="submit" name="select_for_checkout" class="btn btn-primary">
                                            <i class="fas fa-shopping-bag"></i> Checkout Selected Items
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <form method="POST" id="checkoutForm">
                                <?php if (!$is_logged_in): ?>
                                <div class="guest-notice">
                                    <h3><i class="fas fa-info-circle"></i> Guest Checkout</h3>
                                    <p>You're checking out as a guest. To track your order and save your information for next time, 
                                       <a href="login_register.php?redirect=checkout" style="color: var(--dark); font-weight: 500;">create an account</a>.</p>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($_SESSION['checkout_items'])): ?>
                                <div class="guest-notice" style="background: #e8f5e9; border-color: #a5d6a7; color: #2e7d32;">
                                    <h3><i class="fas fa-check-circle"></i> Checking Out Selected Items</h3>
                                    <p>You are checking out <?php echo count($_SESSION['checkout_items']); ?> selected item(s). 
                                       <a href="cart_and_checkout.php" style="color: var(--dark); font-weight: 500;">Back to cart</a> to modify selection.</p>
                                </div>
                                <?php endif; ?>

                                <div class="checkout-section">
                                    <div class="section-header">
                                        <h2><i class="fas fa-user"></i> Shipping Information</h2>
                                    </div>
                                    <div class="section-content">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label class="form-label">Full Name <span class="required">*</span></label>
                                                <input type="text" name="shipping_name" class="form-control" 
                                                       value="<?php echo htmlspecialchars($customer_data['full_name'] ?? $user_name); ?>" 
                                                       required placeholder="Juan Dela Cruz">
                                            </div>
                                            <div class="form-group">
                                                <label class="form-label">Phone Number <span class="required">*</span></label>
                                                <input type="tel" name="shipping_phone" class="form-control" 
                                                       value="<?php echo htmlspecialchars($customer_data['contact_number'] ?? ''); ?>" 
                                                       required placeholder="09123456789">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Address <span class="required">*</span></label>
                                            <input type="text" name="shipping_address" class="form-control" 
                                                   value="<?php echo htmlspecialchars($customer_data['shipping_address'] ?? ''); ?>" 
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

                                <div class="security-badge">
                                    <i class="fas fa-shield-alt security-icon"></i>
                                    <div class="security-text">
                                        <strong>Secure Checkout:</strong> Your personal and payment information is encrypted and secure.
                                    </div>
                                </div>

                                <div class="cart-actions">
                                    <a href="cart_and_checkout.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Back to Cart
                                    </a>
                                    <button type="submit" name="place_order" class="btn btn-primary">
                                        <i class="fas fa-lock"></i> Place Order
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="order-summary">
                        <div class="summary-header">Order Summary</div>
                        
                        <div class="summary-items">
                            <?php 
                            $display_products = $show_checkout && !empty($_SESSION['checkout_items']) ? $checkout_products : $cart_products;
                            foreach ($display_products as $product): 
                                if (!$show_checkout && !$product['selected']) continue;
                            ?>
                            <div class="summary-item">
                                <img src="<?php echo htmlspecialchars($product['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                     class="summary-image">
                                <div class="summary-details">
                                    <div class="summary-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="summary-quantity">Quantity: <?php echo $product['cart_quantity']; ?></div>
                                </div>
                                <div class="summary-price">₱<?php echo number_format($product['item_total'], 2); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="summary-totals">
                            <div class="total-row">
                                <span class="total-label">Subtotal (<?php echo $display_items; ?> items)</span>
                                <span class="total-value">₱<?php echo number_format($display_subtotal, 2); ?></span>
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
                            
                            <?php if (!$show_checkout && !empty($cart_products)): ?>
                                <button type="submit" form="cartForm" name="select_for_checkout" class="btn btn-primary btn-block checkout-btn">
                                    <i class="fas fa-shopping-bag"></i> Checkout Selected Items
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

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

    <script>
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('input[name="selected_items[]"]');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateSelection();
        }

        function updateSelection() {
            const checkboxes = document.querySelectorAll('input[name="selected_items[]"]:checked');
            document.getElementById('selectedCount').textContent = checkboxes.length + ' selected';
            
            const selectAll = document.getElementById('selectAll');
            const allCheckboxes = document.querySelectorAll('input[name="selected_items[]"]');
            selectAll.checked = checkboxes.length === allCheckboxes.length;
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

        function initPaymentMethods() {
            const paymentRadios = document.querySelectorAll('.payment-radio');
            const paymentDetails = document.getElementById('payment-details');
            const paymentInstructions = document.getElementById('payment-instructions');
            
            const paymentInstructionsMap = {
                'cod': 'Pay with cash when your order arrives. Our delivery rider will collect the payment.',
                'gcash': 'Send payment to GCash number 0917-123-4567. Use your order number as reference.',
                'bank_transfer': 'Transfer payment to BPI Account #1234-5678-90. Send proof of payment to our email.'
            };
            
            paymentRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.checked) {
                        paymentDetails.style.display = 'block';
                        paymentInstructions.textContent = paymentInstructionsMap[this.value] || 'Select a payment method to see instructions.';
                    }
                });
            });
            
            const defaultPayment = document.querySelector('.payment-radio:checked');
            if (defaultPayment && paymentDetails && paymentInstructions) {
                paymentDetails.style.display = 'block';
                paymentInstructions.textContent = paymentInstructionsMap[defaultPayment.value] || 'Select a payment method to see instructions.';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateSelection();
            initPaymentMethods();
            
            <?php if ($checkout_success): ?>
                const modal = document.getElementById('orderSuccessModal');
                if (modal) modal.classList.add('active');
            <?php endif; ?>
            
            const checkoutForm = document.getElementById('checkoutForm');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    const requiredFields = checkoutForm.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.style.borderColor = 'var(--danger)';
                            
                            field.addEventListener('input', function() {
                                this.style.borderColor = 'var(--border)';
                            });
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields marked with *.');
                    }
                });
            }
        });
    </script>
</body>
</html>