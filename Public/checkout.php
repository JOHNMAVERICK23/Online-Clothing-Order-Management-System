<?php
session_start();
require_once '../PROCESS/db_config.php';

// Redirect to login if not in session
if (!isset($_SESSION['user_id']) && !isset($_GET['guest'])) {
    // For public customers - allow guest checkout
    if (!isset($_GET['guest'])) {
        // Check if coming from store
        $isGuest = true;
    }
}

$cartItems = [];
$subtotal = 0;
$shippingCost = 100;
$tax = 0;

// Get cart from session or POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'place_order') {
        // Validate form data
        $customerName = trim($_POST['customer_name'] ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $shippingAddress = trim($_POST['shipping_address'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'cod';
        $cartData = json_decode($_POST['cart_data'] ?? '[]', true);
        
        if (empty($customerName) || empty($customerEmail) || empty($customerPhone) || 
            empty($shippingAddress) || empty($cartData)) {
            $_SESSION['error'] = 'All fields are required';
        } else {
            // Calculate totals
            $subtotal = 0;
            foreach ($cartData as $item) {
                $subtotal += ($item['price'] * $item['quantity']);
            }
            $tax = $subtotal * 0.12; // 12% VAT
            $total = $subtotal + $shippingCost + $tax;
            
            // Create order
            $orderNumber = 'ORD-' . date('YmdHis') . '-' . rand(1000, 9999);
            $status = 'pending';
            $paymentStatus = ($paymentMethod === 'cod') ? 'pending' : 'pending';
            
            $stmt = $conn->prepare("
                INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, 
                                  shipping_address, total_amount, status, payment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("sssssdss", $orderNumber, $customerName, $customerEmail, 
                            $customerPhone, $shippingAddress, $total, $status, $paymentStatus);
            
            if ($stmt->execute()) {
                $orderId = $conn->insert_id;
                
                // Insert order items
                foreach ($cartData as $item) {
                    $itemSubtotal = $item['price'] * $item['quantity'];
                    $itemStmt = $conn->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, price, subtotal)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $itemStmt->bind_param("iiidd", $orderId, $item['id'], $item['quantity'], 
                                        $item['price'], $itemSubtotal);
                    $itemStmt->execute();
                }
                
                // Redirect to payment or confirmation
                if ($paymentMethod === 'cod') {
                    header("Location: confirmation.php?order_id=$orderId&order_number=$orderNumber");
                } else {
                    header("Location: payment.php?order_id=$orderId&payment_method=$paymentMethod");
                }
                exit;
            } else {
                $_SESSION['error'] = 'Error creating order: ' . $conn->error;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Clothing Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; }
        .checkout-container { max-width: 1000px; margin: 40px auto; }
        .order-summary { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-section { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .order-item { border-bottom: 1px solid #eee; padding: 10px 0; }
        .order-item:last-child { border-bottom: none; }
        .price-row { display: flex; justify-content: space-between; padding: 8px 0; }
        .price-row.total { border-top: 2px solid #1a1a1a; font-weight: bold; font-size: 18px; padding-top: 12px; }
        .payment-option { border: 2px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 10px; cursor: pointer; }
        .payment-option input[type="radio"]:checked + .payment-label { color: #1a1a1a; font-weight: 600; }
        .payment-option.active { border-color: #1a1a1a; background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="checkout-container">
        <h1 class="mb-4"><i class="bi bi-bag-check"></i> Checkout</h1>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Shipping Information -->
                <div class="form-section">
                    <h3 class="mb-4">Shipping Information</h3>
                    <form id="checkoutForm" method="POST">
                        <input type="hidden" name="action" value="place_order">
                        <input type="hidden" id="cartDataInput" name="cart_data" value="">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="customer_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="customer_email" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" name="customer_phone" placeholder="+63" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Shipping Address *</label>
                            <textarea class="form-control" name="shipping_address" rows="3" required></textarea>
                        </div>

                        <!-- Payment Method -->
                        <div class="form-section">
                            <h3 class="mb-4">Payment Method</h3>
                            
                            <div class="payment-option active" onclick="selectPayment(this, 'cod')">
                                <input type="radio" name="payment_method" value="cod" id="cod" checked>
                                <label for="cod" class="payment-label mb-0">
                                    <i class="bi bi-cash-coin"></i> Cash on Delivery (COD)
                                </label>
                                <small class="d-block mt-2 text-muted">Pay when your order arrives</small>
                            </div>
                            
                            <div class="payment-option" onclick="selectPayment(this, 'gcash')">
                                <input type="radio" name="payment_method" value="gcash" id="gcash">
                                <label for="gcash" class="payment-label mb-0">
                                    <i class="bi bi-wallet2"></i> GCash
                                </label>
                                <small class="d-block mt-2 text-muted">Pay via GCash (Ref Number required)</small>
                            </div>
                            
                            <div class="payment-option" onclick="selectPayment(this, 'paymaya')">
                                <input type="radio" name="payment_method" value="paymaya" id="paymaya">
                                <label for="paymaya" class="payment-label mb-0">
                                    <i class="bi bi-credit-card"></i> PayMaya
                                </label>
                                <small class="d-block mt-2 text-muted">Pay via PayMaya (Ref Number required)</small>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="bi bi-check-circle"></i> Place Order
                            </button>
                            <a href="cart.php" class="btn btn-outline-secondary btn-lg w-100 mt-2">
                                <i class="bi bi-arrow-left"></i> Back to Cart
                            </a>
                            <a href="../index.php" class="btn btn-outline-secondary btn-lg w-100 mt-2">
                                <i class="bi bi-shop"></i> Continue Shopping
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="order-summary position-sticky" style="top: 20px;">
                    <h4 class="mb-4">Order Summary</h4>
                    
                    <div id="orderItems">
                        <!-- Order items will be loaded here -->
                    </div>
                    
                    <div class="price-row">
                        <span>Subtotal:</span>
                        <span id="subtotalAmount">₱0.00</span>
                    </div>
                    <div class="price-row">
                        <span>Shipping:</span>
                        <span>₱100.00</span>
                    </div>
                    <div class="price-row">
                        <span>Tax (12% VAT):</span>
                        <span id="taxAmount">₱0.00</span>
                    </div>
                    <div class="price-row total">
                        <span>Total:</span>
                        <span id="totalAmount">₱0.00</span>
                    </div>

                    <button class="btn btn-outline-secondary w-100 mt-3" onclick="editCart()">
                        <i class="bi bi-pencil"></i> Edit Cart
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const SHIPPING_COST = 100;
        const TAX_RATE = 0.12;

        function loadCart() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            if (cart.length === 0) {
                document.getElementById('orderItems').innerHTML = '<p class="text-danger">Your cart is empty!</p>';
                return;
            }

            let html = '';
            let subtotal = 0;

            cart.forEach(item => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                html += `
                    <div class="order-item">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <strong>${item.name}</strong>
                            <span>₱${parseFloat(item.price).toFixed(2)}</span>
                        </div>
                        <small class="text-muted">Qty: ${item.quantity}</small>
                    </div>
                `;
            });

            document.getElementById('orderItems').innerHTML = html;
            document.getElementById('cartDataInput').value = JSON.stringify(cart);

            // Calculate totals
            const tax = subtotal * TAX_RATE;
            const total = subtotal + SHIPPING_COST + tax;

            document.getElementById('subtotalAmount').textContent = '₱' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('taxAmount').textContent = '₱' + tax.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('totalAmount').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
        }

        function selectPayment(element, method) {
            document.querySelectorAll('.payment-option').forEach(e => e.classList.remove('active'));
            element.classList.add('active');
            document.getElementById(method).checked = true;
        }

        function editCart() {
            window.location.href = 'cart.html';
        }

        document.addEventListener('DOMContentLoaded', loadCart);
    </script>
</body>
</html>