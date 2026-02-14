<?php
session_start();

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: shop.php');
    exit;
}

$product_id = intval($_GET['id']);

// Initialize session variables if not exists
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

// User session variables
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_type = $_SESSION['user_type'] ?? 'guest';
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;

// Database connection
try {
    $host = 'localhost';
    $dbname = 'clothing_management_system'; // Changed from clothing_shop
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get product details
$query = "SELECT p.* 
          FROM products p 
          WHERE p.id = ? AND p.status = 'active'"; // Changed from is_active to status

$stmt = $pdo->prepare($query);
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if product exists
if (!$product) {
    header('Location: shop.php');
    exit;
}

// Check if product is in wishlist (using session)
$in_wishlist = in_array($product_id, $_SESSION['wishlist']);

// Handle add to cart from this page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = intval($_POST['quantity']) ?: 1;
    
    // Check if product is in stock
    if ($product['quantity'] <= 0) {
        $_SESSION['error'] = 'Product is out of stock!';
        header('Location: view_info.php?id=' . $product_id);
        exit;
    }
    
    // Check if quantity exceeds available stock
    if ($quantity > $product['quantity']) {
        $_SESSION['error'] = 'Quantity exceeds available stock!';
        header('Location: view_info.php?id=' . $product_id);
        exit;
    }
    
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $product_id) {
            $item['quantity'] += $quantity;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $_SESSION['cart'][] = [
            'product_id' => $product_id, 
            'quantity' => $quantity,
            'product_name' => $product['product_name'],
            'price' => $product['price'],
            'image_url' => $product['image_url']
        ];
    }
    
    // Save cart to database if user is logged in
    if ($is_logged_in && $user_id) {
        try {
            // Delete existing cart item for this user and product
            $stmt = $pdo->prepare("DELETE FROM customer_carts WHERE customer_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            
            // Insert new cart item
            $stmt = $pdo->prepare("INSERT INTO customer_carts (customer_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $product_id, $quantity]);
            
        } catch (PDOException $e) {
            error_log("Error saving cart to database: " . $e->getMessage());
        }
    }
    
    $_SESSION['success'] = 'Product added to cart successfully!';
    header('Location: view_info.php?id=' . $product_id);
    exit;
}

// Handle add to wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    // Check if user is logged in
    if (!$is_logged_in) {
        $_SESSION['redirect_after_login'] = 'view_info.php?id=' . $product_id;
        header('Location: login_register.php');
        exit;
    }
    
    if (!in_array($product_id, $_SESSION['wishlist'])) {
        $_SESSION['wishlist'][] = $product_id;
        
        // Save wishlist to database
        if ($user_id) {
            try {
                $stmt = $pdo->prepare("INSERT INTO customer_wishlists (customer_id, product_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $product_id]);
            } catch (PDOException $e) {
                // Handle duplicate entry (product already in wishlist)
                if ($e->getCode() == 23000) { // Duplicate entry error code
                    // Product already exists in wishlist, just update session
                } else {
                    error_log("Error saving wishlist: " . $e->getMessage());
                }
            }
        }
        
        $_SESSION['success'] = 'Product added to wishlist!';
        header('Location: view_info.php?id=' . $product_id);
        exit;
    } else {
        $_SESSION['info'] = 'Product is already in your wishlist!';
        header('Location: view_info.php?id=' . $product_id);
        exit;
    }
}

// Handle remove from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_wishlist'])) {
    // Check if user is logged in
    if (!$is_logged_in) {
        $_SESSION['redirect_after_login'] = 'view_info.php?id=' . $product_id;
        header('Location: login_register.php');
        exit;
    }
    
    $key = array_search($product_id, $_SESSION['wishlist']);
    if ($key !== false) {
        unset($_SESSION['wishlist'][$key]);
        $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
        
        // Remove from database
        if ($user_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM customer_wishlists WHERE customer_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
            } catch (PDOException $e) {
                error_log("Error removing from wishlist: " . $e->getMessage());
            }
        }
        
        $_SESSION['info'] = 'Product removed from wishlist!';
        header('Location: view_info.php?id=' . $product_id);
        exit;
    }
}

// Handle buy now - redirects to cart page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_now'])) {
    $quantity = intval($_POST['quantity']) ?: 1;
    
    // Check if product is in stock
    if ($product['quantity'] <= 0) {
        $_SESSION['error'] = 'Product is out of stock!';
        header('Location: view_info.php?id=' . $product_id);
        exit;
    }
    
    // Check if quantity exceeds available stock
    if ($quantity > $product['quantity']) {
        $_SESSION['error'] = 'Quantity exceeds available stock!';
        header('Location: view_info.php?id=' . $product_id);
        exit;
    }
    
    // Clear cart and add only this product for immediate checkout
    $_SESSION['cart'] = [[
        'product_id' => $product_id, 
        'quantity' => $quantity,
        'product_name' => $product['product_name'],
        'price' => $product['price'],
        'image_url' => $product['image_url']
    ]];
    
    // Save cart to database if user is logged in
    if ($is_logged_in && $user_id) {
        try {
            // Clear user's cart
            $stmt = $pdo->prepare("DELETE FROM customer_carts WHERE customer_id = ?");
            $stmt->execute([$user_id]);
            
            // Add the single product
            $stmt = $pdo->prepare("INSERT INTO customer_carts (customer_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $product_id, $quantity]);
            
        } catch (PDOException $e) {
            error_log("Error saving cart for buy now: " . $e->getMessage());
        }
    }
    
    header('Location: cart_and_checkout.php?checkout=immediate');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - Alas Clothing Shop</title>
    
    <!-- External Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    
    <!-- Your CSS Files -->
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ===== PRODUCT DETAILS PAGE STYLES ===== */
        .product-details-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 6rem;
            flex: 1;
        }

        .breadcrumb {
            margin-bottom: 2rem;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--dark);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .product-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .product-image-section {
            position: relative;
        }

        .product-main-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            background: var(--gray-light);
            border: 1px solid var(--border);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--light);
            color: var(--dark);
            padding: 0.5rem 1.5rem;
            border: 1px solid var(--dark);
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            z-index: 1;
        }

        .wishlist-btn-detail {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--light);
            border: 1px solid var(--border);
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1;
            font-size: 1.2rem;
        }

        .wishlist-btn-detail:hover {
            background: var(--light);
            border-color: var(--danger);
            color: var(--danger);
        }

        .wishlist-btn-detail.active {
            background: var(--danger);
            border-color: var(--danger);
            color: var(--light);
        }

        .wishlist-btn-detail.active:hover {
            background: var(--light);
            color: var(--danger);
        }

        .product-info-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .product-category {
            color: var(--gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .product-title {
            font-size: 2.5rem;
            font-weight: 300;
            color: var(--dark);
            line-height: 1.2;
            letter-spacing: 0.5px;
        }

        .product-price {
            font-size: 2rem;
            font-weight: 500;
            color: var(--dark);
            letter-spacing: 1px;
        }

        .product-stock-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            border: 1px solid var(--border);
            margin: 1rem 0;
            font-weight: 500;
        }

        .stock-available { 
            color: var(--success);
            border-color: var(--success);
        }

        .stock-low { 
            color: var(--warning);
            border-color: var(--warning);
        }

        .stock-out { 
            color: var(--gray);
            border-color: var(--gray);
        }

        .product-description {
            color: var(--gray);
            line-height: 1.6;
            margin: 1.5rem 0;
            font-weight: 300;
        }

        .product-specs {
            margin: 1.5rem 0;
        }

        .specs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .specs-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
        }

        .specs-table td:first-child {
            font-weight: 500;
            color: var(--dark);
            width: 150px;
        }

        .quantity-section {
            margin: 1.5rem 0;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .quantity-btn:hover {
            border-color: var(--dark);
        }

        .quantity-input {
            width: 80px;
            text-align: center;
            padding: 0.75rem;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            font-size: 1rem;
        }

        .quantity-input:focus {
            outline: none;
            border-color: var(--dark);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin: 2rem 0;
        }

        .btn {
            padding: 1rem 2rem;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 1rem;
            letter-spacing: 0.5px;
            flex: 1;
            justify-content: center;
        }

        .btn-primary {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        .btn-primary:hover {
            background: #333;
            border-color: #333;
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

        .btn-success {
            background: var(--success);
            color: var(--light);
            border-color: var(--success);
        }

        .btn-success:hover {
            background: #218838;
            border-color: #218838;
        }

        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-disabled:hover {
            background: var(--light);
            color: var(--dark);
            border-color: var(--border);
        }

        .message {
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
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

        .info-message {
            background: #f0f8ff;
            border: 1px solid #cce5ff;
            color: #004085;
        }

        .warning-message {
            background: #fff8e6;
            border: 1px solid #ffeeba;
            color: #856404;
        }

        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .related-products {
            margin-top: 4rem;
            border-top: 1px solid var(--border);
            padding-top: 2rem;
        }

        .related-title {
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
        }

        .related-product {
            border: 1px solid var(--border);
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .related-product:hover {
            border-color: var(--dark);
            transform: translateY(-4px);
        }

        .related-product img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .related-product-content {
            padding: 1rem;
        }

        .related-product-title {
            font-size: 1rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .related-product-price {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--dark);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .product-details-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .product-main-image {
                height: 400px;
            }
            
            .product-title {
                font-size: 2rem;
            }
            
            .related-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .product-details-container {
                padding: 1rem;
                padding-top: 5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .product-title {
                font-size: 1.5rem;
            }
            
            .product-price {
                font-size: 1.5rem;
            }
            
            .related-grid {
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
        
        <a href="index.php" class="nav-logo">
            <img src="images/logo.jpeg" class="logo-image">
            <span class="brand-name">Alas Clothing Shop</span>
        </a>
        
        <!-- Mobile Menu -->
        <ul class="nav-menu" id="navMenu">
            <li><a href="index.php">HOME</a></li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="orders.php">MY ORDERS</a></li>
            <li><a href="size_chart.php">SIZE CHART</a></li>
            <li><a href="shipping.php">SHIPPING</a></li>
            <li><a href="announcements.php">ANNOUNCEMENTS</a></li>
        </ul>
        
        <div class="nav-right">
            <!-- Icons -->
            <a href="<?php echo $is_logged_in ? 'account.php' : 'login_register.php'; ?>" class="nav-icon" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="wishlist.php" class="nav-icon" title="Wishlist">
                <i class="fas fa-heart"></i>
                <?php if ($is_logged_in && !empty($_SESSION['wishlist'])): ?>
                    <span class="wishlist-count-badge">
                        <?php echo count($_SESSION['wishlist']); ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="cart_and_checkout.php" class="nav-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge">
                    <?php echo count($_SESSION['cart']); ?>
                </span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="product-details-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="index.php">Home</a> &gt; 
            <a href="shop.php">Shop</a> &gt; 
            <span><?php echo htmlspecialchars($product['product_name']); ?></span>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['info'])): ?>
            <div class="message info-message">
                <i class="fas fa-info-circle"></i>
                <?php echo htmlspecialchars($_SESSION['info']); unset($_SESSION['info']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="product-details-grid">
            <!-- Product Images -->
            <div class="product-image-section">
                <?php if ($product['quantity'] < 10 && $product['quantity'] > 0): ?>
                    <span class="product-badge">Low Stock</span>
                <?php elseif ($product['quantity'] == 0): ?>
                    <span class="product-badge" style="border-color: var(--gray); color: var(--gray);">Out of Stock</span>
                <?php endif; ?>
                
                <!-- Wishlist Button -->
                <form method="POST" class="wishlist-form">
                    <?php if ($in_wishlist): ?>
                        <input type="hidden" name="remove_from_wishlist" value="1">
                        <button type="submit" class="wishlist-btn-detail active" title="Remove from Wishlist">
                            <i class="fas fa-heart"></i>
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="add_to_wishlist" value="1">
                        <button type="submit" class="wishlist-btn-detail" title="Add to Wishlist">
                            <i class="far fa-heart"></i>
                        </button>
                    <?php endif; ?>
                </form>
                
                <img src="<?php echo htmlspecialchars($product['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                     alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                     class="product-main-image" id="mainImage">
            </div>

            <!-- Product Info -->
            <div class="product-info-section">
                <div class="product-category">
                    <?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?>
                </div>
                
                <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                
                <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                
                <div class="product-stock-info <?php echo $product['quantity'] > 10 ? 'stock-available' : ($product['quantity'] > 0 ? 'stock-low' : 'stock-out'); ?>">
                    <i class="fas <?php echo $product['quantity'] > 0 ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                    <?php if ($product['quantity'] > 0): ?>
                        <?php echo $product['quantity']; ?> items in stock
                    <?php else: ?>
                        Out of stock
                    <?php endif; ?>
                </div>
                
                <p class="product-description">
                    <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?>
                </p>
                
                <div class="product-specs">
                    <table class="specs-table">
                        <tr>
                            <td>Category:</td>
                            <td><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></td>
                        </tr>
                        <tr>
                            <td>SKU:</td>
                            <td>#<?php echo str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?></td>
                        </tr>
                        <?php if (!empty($product['size'])): ?>
                        <tr>
                            <td>Size:</td>
                            <td><?php echo htmlspecialchars($product['size']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($product['color'])): ?>
                        <tr>
                            <td>Color:</td>
                            <td><?php echo htmlspecialchars($product['color']); ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Status:</td>
                            <td><?php echo ucfirst($product['status']); ?></td>
                        </tr>
                    </table>
                </div>
                
                <form method="POST" class="product-actions-form">
                    <div class="quantity-section">
                        <label for="quantity">Quantity:</label>
                        <div class="quantity-controls">
                            <button type="button" class="quantity-btn" onclick="decreaseQuantity()">-</button>
                            <input type="number" id="quantity" name="quantity" class="quantity-input" value="1" min="1" 
                                   max="<?php echo $product['quantity']; ?>" 
                                   <?php echo $product['quantity'] == 0 ? 'disabled' : ''; ?>>
                            <button type="button" class="quantity-btn" onclick="increaseQuantity()">+</button>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <?php if ($product['quantity'] > 0): ?>
                            <!-- Buy Now Button -->
                            <button type="submit" name="buy_now" class="btn btn-success">
                                <i class="fas fa-bolt"></i> BUY NOW
                            </button>
                            
                            <!-- Add to Cart Button -->
                            <button type="submit" name="add_to_cart" class="btn btn-primary">
                                <i class="fas fa-cart-plus"></i> ADD TO CART
                            </button>
                            
                            <!-- Continue Shopping -->
                            <a href="shop.php" class="btn btn-secondary">
                                <i class="fas fa-shopping-bag"></i> CONTINUE SHOPPING
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-disabled" disabled>
                                <i class="fas fa-times-circle"></i> OUT OF STOCK
                            </button>
                            <a href="shop.php" class="btn btn-secondary">
                                <i class="fas fa-shopping-bag"></i> CONTINUE SHOPPING
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Related Products -->
        <?php
        // Get related products (same category, excluding current product)
        if (!empty($product['category'])) {
            $related_query = "SELECT p.* 
                              FROM products p 
                              WHERE p.status = 'active' 
                              AND p.id != ? 
                              AND p.category = ? 
                              AND p.quantity > 0
                              ORDER BY RAND() 
                              LIMIT 4";
            
            $related_stmt = $pdo->prepare($related_query);
            $related_stmt->execute([$product_id, $product['category']]);
            $related_products = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($related_products)):
        ?>
        <div class="related-products">
            <h2 class="related-title">You May Also Like</h2>
            <div class="related-grid">
                <?php foreach ($related_products as $related): ?>
                <a href="view_info.php?id=<?php echo $related['id']; ?>" class="related-product">
                    <img src="<?php echo htmlspecialchars($related['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($related['product_name']); ?>">
                    <div class="related-product-content">
                        <h3 class="related-product-title"><?php echo htmlspecialchars($related['product_name']); ?></h3>
                        <div class="related-product-price">₱<?php echo number_format($related['price'], 2); ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php 
            endif;
        }
        ?>
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

    <!-- External JavaScript -->
    <script src="js/main.js"></script>
    
    <!-- Product Details JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize quantity controls
            const quantityInput = document.getElementById('quantity');
            const maxQuantity = <?php echo $product['quantity']; ?>;
            
            // Check if product is in stock
            if (maxQuantity <= 0) {
                // Disable all forms if product is out of stock
                document.querySelectorAll('form').forEach(form => {
                    if (form.querySelector('button[type="submit"]')) {
                        form.querySelector('button[type="submit"]').disabled = true;
                    }
                });
            }
        });

        // Quantity Controls
        function increaseQuantity() {
            const input = document.getElementById('quantity');
            const max = <?php echo $product['quantity']; ?>;
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
            }
        }

        function decreaseQuantity() {
            const input = document.getElementById('quantity');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }
    </script>
</body>
</html>