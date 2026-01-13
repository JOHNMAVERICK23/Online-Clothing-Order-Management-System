<?php
// Shopping cart page - no session required
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Clothing Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../CSS/store.css">
    <style>
        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr 1fr 1fr 1fr 100px;
            gap: 15px;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            background: white;
        }

        .cart-item:hover {
            background: #f9f9f9;
        }

        .cart-item-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #ddd;
        }

        .cart-item-name {
            font-weight: 600;
            color: #1a1a1a;
        }

        .cart-item-price {
            font-weight: 600;
            color: #3498db;
            font-size: 16px;
        }

        .quantity-input {
            width: 80px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }

        .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .remove-btn:hover {
            background: #c0392b;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart-icon {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .order-summary {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            border-top: 2px solid #1a1a1a;
            font-weight: 700;
            font-size: 18px;
            color: #1a1a1a;
            padding-top: 15px;
            margin-top: 10px;
        }

        .cart-icon-container {
            position: relative;
            display: inline-block;
            margin-right: 10px;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }

        .cart-icon-link {
            color: white;
            text-decoration: none;
            font-size: 20px;
        }
    </style>
</head>
<body style="background: #f8f9fa;">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="store.php">
                <i class="bi bi-shop"></i> Alas Ace
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="store.php">Home</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <div class="cart-icon-container">
                        <a href="cart.php" class="cart-icon-link active" title="Shopping Cart">
                            <i class="bi bi-cart3"></i>
                            <span class="cart-badge" id="cartCount">0</span>
                        </a>
                    </div>
                    <a href="../login.html" class="btn btn-light btn-sm">
                        <i class="bi bi-person"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <h1 class="mb-4"><i class="bi bi-cart3"></i> Shopping Cart</h1>

        <div class="row">
            <!-- Cart Items -->
            <div class="col-lg-8">
                <div style="background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <div id="cartContainer">
                        <!-- Cart items will be loaded here -->
                        <div class="empty-cart">
                            <div class="empty-cart-icon">
                                <i class="bi bi-cart-x"></i>
                            </div>
                            <h3>Your cart is empty</h3>
                            <p class="text-muted">Add some products to get started!</p>
                            <a href="store.php" class="btn btn-primary mt-3">Continue Shopping</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="order-summary">
                    <h5 class="mb-3">Order Summary</h5>
                    
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span id="subtotalAmount">₱0.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span>₱100.00</span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (12% VAT):</span>
                        <span id="taxAmount">₱0.00</span>
                    </div>
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span id="totalAmount">₱0.00</span>
                    </div>

                    <button class="btn btn-primary w-100 mt-4" id="checkoutBtn" onclick="proceedToCheckout()">
                        <i class="bi bi-credit-card"></i> Proceed to Checkout
                    </button>

                    <a href="store.php" class="btn btn-outline-secondary w-100 mt-2">
                        <i class="bi bi-arrow-left"></i> Continue Shopping
                    </a>

                    <!-- Promo Code (Optional) -->
                    <hr>
                    <div class="mb-3">
                        <label class="form-label" style="font-size: 13px;">Promo Code (Optional)</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" id="promoCode" placeholder="Enter promo code">
                            <button class="btn btn-outline-secondary btn-sm" type="button">Apply</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; 2024 Alas Ace. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const SHIPPING_COST = 100;
        const TAX_RATE = 0.12;

        function loadCart() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const container = document.getElementById('cartContainer');

            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="empty-cart">
                        <div class="empty-cart-icon">
                            <i class="bi bi-cart-x"></i>
                        </div>
                        <h3>Your cart is empty</h3>
                        <p class="text-muted">Add some products to get started!</p>
                        <a href="store.php" class="btn btn-primary mt-3">Continue Shopping</a>
                    </div>
                `;
                disableCheckout();
                return;
            }

            let html = `
                <div style="padding: 15px; border-bottom: 2px solid #f0f0f0; background: #f9f9f9; font-weight: 600;">
                    <div class="cart-item">
                        <div>Image</div>
                        <div>Product</div>
                        <div>Price</div>
                        <div>Quantity</div>
                        <div>Subtotal</div>
                        <div>Action</div>
                    </div>
                </div>
            `;

            cart.forEach((item, index) => {
                const itemSubtotal = item.price * item.quantity;
                html += `
                    <div class="cart-item">
                        <img src="${item.image}" alt="${item.name}" class="cart-item-image" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22%3E%3Crect fill=%22%23f0f0f0%22 width=%22100%22 height=%22100%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22 font-size=%2214%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                        <div>
                            <div class="cart-item-name">${item.name}</div>
                        </div>
                        <div class="cart-item-price">₱${parseFloat(item.price).toFixed(2)}</div>
                        <div>
                            <input type="number" class="quantity-input" value="${item.quantity}" min="1" max="${item.maxQuantity}" onchange="updateQuantity(${index}, this.value)">
                        </div>
                        <div class="cart-item-price">₱${itemSubtotal.toFixed(2)}</div>
                        <button class="remove-btn" onclick="removeItem(${index})">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                `;
            });

            container.innerHTML = html;
            updateTotals();
            enableCheckout();
        }

        function updateQuantity(index, newQty) {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            newQty = Math.max(1, Math.min(newQty, cart[index].maxQuantity));
            cart[index].quantity = parseInt(newQty);
            localStorage.setItem('cart', JSON.stringify(cart));
            loadCart();
            updateCartCount();
        }

        function removeItem(index) {
            if (confirm('Remove this item from cart?')) {
                const cart = JSON.parse(localStorage.getItem('cart')) || [];
                cart.splice(index, 1);
                localStorage.setItem('cart', JSON.stringify(cart));
                loadCart();
                updateCartCount();
            }
        }

        function updateTotals() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            let subtotal = 0;

            cart.forEach(item => {
                subtotal += item.price * item.quantity;
            });

            const tax = subtotal * TAX_RATE;
            const total = subtotal + SHIPPING_COST + tax;

            document.getElementById('subtotalAmount').textContent = 
                '₱' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('taxAmount').textContent = 
                '₱' + tax.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('totalAmount').textContent = 
                '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cartCount').textContent = totalItems;
        }

        function disableCheckout() {
            document.getElementById('checkoutBtn').disabled = true;
            document.getElementById('checkoutBtn').textContent = 'Cart is Empty';
        }

        function enableCheckout() {
            document.getElementById('checkoutBtn').disabled = false;
            document.getElementById('checkoutBtn').textContent = '💳 Proceed to Checkout';
        }

        function proceedToCheckout() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            if (cart.length === 0) {
                alert('Your cart is empty!');
                return;
            }
            window.location.href = 'checkout.php';
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadCart();
            updateCartCount();
        });

        window.addEventListener('storage', function() {
            loadCart();
            updateCartCount();
        });
    </script>
</body>
</html>