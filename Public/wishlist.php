<?php
session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$customer_id = $_SESSION['customer_id'] ?? null; // Changed from user_id to customer_id

// Initialize session variables if not exists
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

// Database connection
$host = 'localhost';
$dbname = 'clothing_management_system'; // Changed database name
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to save wishlist to database
function saveWishlistToDatabase($pdo, $customer_id, $wishlist) {
    if (!$customer_id) return false;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete existing wishlist items for this customer
        $stmt = $pdo->prepare("DELETE FROM customer_wishlists WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        
        // Insert new wishlist items
        if (!empty($wishlist)) {
            $sql = "INSERT INTO customer_wishlists (customer_id, product_id) VALUES ";
            $values = [];
            $params = [];
            
            foreach ($wishlist as $product_id) {
                $values[] = "(?, ?)";
                $params[] = $customer_id;
                $params[] = $product_id;
            }
            
            $sql .= implode(', ', $values);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error saving wishlist to database: " . $e->getMessage());
        return false;
    }
}

// Helper function to save cart to database
function saveCartToDatabase($pdo, $customer_id, $cart) {
    if (!$customer_id) return false;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete existing cart items for this customer
        $stmt = $pdo->prepare("DELETE FROM customer_carts WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        
        // Insert new cart items
        if (!empty($cart)) {
            $sql = "INSERT INTO customer_carts (customer_id, product_id, quantity) VALUES ";
            $values = [];
            $params = [];
            
            foreach ($cart as $item) {
                if (isset($item['product_id']) && isset($item['quantity'])) {
                    $values[] = "(?, ?, ?)";
                    $params[] = $customer_id;
                    $params[] = $item['product_id'];
                    $params[] = $item['quantity'];
                }
            }
            
            if (!empty($values)) {
                $sql .= implode(', ', $values);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error saving cart to database: " . $e->getMessage());
        return false;
    }
}

// Load wishlist from database if customer is logged in
if ($is_logged_in && $customer_id) {
    try {
        $stmt = $pdo->prepare("SELECT product_id FROM customer_wishlists WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $db_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Merge with session wishlist (session takes precedence for new items)
        $_SESSION['wishlist'] = array_unique(array_merge($db_wishlist, $_SESSION['wishlist']));
    } catch (PDOException $e) {
        error_log("Error loading wishlist from database: " . $e->getMessage());
    }
}

// Load cart from database if customer is logged in
if ($is_logged_in && $customer_id) {
    try {
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM customer_carts WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $db_cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Merge with session cart
        foreach ($db_cart as $cart_item) {
            $found = false;
            foreach ($_SESSION['cart'] as &$session_item) {
                if ($session_item['product_id'] == $cart_item['product_id']) {
                    // Session cart takes precedence for quantity
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // Need to get product details from products table
                $product_stmt = $pdo->prepare("SELECT product_name, price, image_url FROM products WHERE id = ?");
                $product_stmt->execute([$cart_item['product_id']]);
                $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    $_SESSION['cart'][] = [
                        'product_id' => $cart_item['product_id'],
                        'quantity' => $cart_item['quantity'],
                        'product_name' => $product['product_name'],
                        'price' => $product['price'],
                        'image_url' => $product['image_url']
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error loading cart from database: " . $e->getMessage());
    }
}

// Handle removing from wishlist
if (isset($_GET['remove_wishlist'])) {
    $product_id = intval($_GET['remove_wishlist']);
    $key = array_search($product_id, $_SESSION['wishlist']);
    
    if ($key !== false) {
        unset($_SESSION['wishlist'][$key]);
        $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
        $wishlist_message = 'Product removed from wishlist!';
        
        // Save to database if customer is logged in
        if ($is_logged_in && $customer_id) {
            saveWishlistToDatabase($pdo, $customer_id, $_SESSION['wishlist']);
        }
    }
    header('Location: wishlist.php');
    exit;
}

// Handle clear all wishlist
if (isset($_GET['remove_all'])) {
    if (!empty($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
        $wishlist_message = 'All items removed from wishlist!';
        
        // Save to database if customer is logged in
        if ($is_logged_in && $customer_id) {
            saveWishlistToDatabase($pdo, $customer_id, $_SESSION['wishlist']);
        }
    }
    header('Location: wishlist.php');
    exit;
}

// Move to cart from wishlist
if (isset($_GET['move_to_cart'])) {
    $product_id = intval($_GET['move_to_cart']);
    $key = array_search($product_id, $_SESSION['wishlist']);
    
    if ($key !== false) {
        // Get product details first
        $product_stmt = $pdo->prepare("SELECT product_name, price, image_url, quantity FROM products WHERE id = ? AND status = 'active'");
        $product_stmt->execute([$product_id]);
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Remove from wishlist
            unset($_SESSION['wishlist'][$key]);
            $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
            
            // Add to cart
            $found = false;
            foreach ($_SESSION['cart'] as &$item) {
                if ($item['product_id'] == $product_id) {
                    $item['quantity'] += 1;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['cart'][] = [
                    'product_id' => $product_id,
                    'quantity' => 1,
                    'product_name' => $product['product_name'],
                    'price' => $product['price'],
                    'image_url' => $product['image_url']
                ];
            }
            
            $wishlist_message = 'Product moved to cart!';
            
            // Save both wishlist and cart to database if customer is logged in
            if ($is_logged_in && $customer_id) {
                saveWishlistToDatabase($pdo, $customer_id, $_SESSION['wishlist']);
                saveCartToDatabase($pdo, $customer_id, $_SESSION['cart']);
            }
        }
    }
    header('Location: wishlist.php');
    exit;
}

// Get wishlist products
$wishlist_products = [];
if (!empty($_SESSION['wishlist'])) {
    $placeholders = str_repeat('?,', count($_SESSION['wishlist']) - 1) . '?';
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND status = 'active'");
    $stmt->execute($_SESSION['wishlist']);
    $wishlist_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle logout
if (isset($_GET['logout'])) {
    // Save cart to database before logout if customer is logged in
    if ($is_logged_in && $customer_id && !empty($_SESSION['cart'])) {
        saveCartToDatabase($pdo, $customer_id, $_SESSION['cart']);
    }
    
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
    <title>Wishlist - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* WISHLIST PAGE STYLES */
        .wishlist-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 6rem;
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

        .wishlist-stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--gray-light);
            border: 1px solid var(--border);
        }

        .stat-box {
            flex: 1;
            text-align: center;
            padding: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

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

        .empty-wishlist {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-wishlist i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--gray-light);
        }

        .empty-wishlist h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
            font-weight: 400;
        }

        .empty-wishlist p {
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .wishlist-items {
            display: grid;
            gap: 1.5rem;
        }

        .wishlist-item {
            display: grid;
            grid-template-columns: 120px 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem;
            border: 1px solid var(--border);
            background: var(--light);
            transition: all 0.3s;
        }

        .wishlist-item:hover {
            border-color: var(--dark);
        }

        .item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            background: var(--gray-light);
        }

        .item-details {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .item-category {
            color: var(--gray);
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .item-title {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .item-price {
            font-size: 1.3rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
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

        .item-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            justify-content: center;
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

        .clear-all {
            text-align: right;
            margin-bottom: 1.5rem;
        }

        .continue-shopping {
            text-align: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 768px) {
            .wishlist-container {
                padding: 1rem;
                padding-top: 5rem;
            }
            
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .wishlist-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .wishlist-item {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .item-image {
                width: 100%;
                height: 200px;
                margin: 0 auto;
            }
            
            .item-actions {
                flex-direction: row;
                justify-content: center;
                flex-wrap: wrap;
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
            <a href="<?php echo ($is_logged_in) ? 'account.php' : 'login_register.php'; ?>" class="nav-icon" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="wishlist.php" class="nav-icon active" title="Wishlist">
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
    <div class="main-content">
        <div class="wishlist-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>My Wishlist</h1>
                <p class="page-subtitle">Save your favorite items for later</p>
            </div>

            <?php if (isset($wishlist_message)): ?>
                <div class="message success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($wishlist_message); ?>
                </div>
            <?php endif; ?>

            <!-- Login reminder if not logged in -->
            <?php if (!$is_logged_in && !empty($_SESSION['wishlist'])): ?>
                <div class="message error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    You are not logged in. Your wishlist will be saved only in this browser session. 
                    <a href="login_register.php" style="margin-left: 10px; color: var(--dark); text-decoration: underline;">Login to save permanently</a>
                </div>
            <?php endif; ?>

            <div class="wishlist-stats">
                <div class="stat-box">
                    <div class="stat-number"><?php echo count($_SESSION['wishlist']); ?></div>
                    <div class="stat-label">Items in Wishlist</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?php echo count($_SESSION['cart']); ?></div>
                    <div class="stat-label">Items in Cart</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number">0</div>
                    <div class="stat-label">Items Purchased</div>
                </div>
            </div>

            <?php if (empty($wishlist_products)): ?>
                <div class="empty-wishlist">
                    <i class="fas fa-heart"></i>
                    <h3>Your wishlist is empty</h3>
                    <p>Add items you love to your wishlist. Review them anytime and easily move them to the shopping cart.</p>
                    <a href="shop.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Start Shopping
                    </a>
                </div>
            <?php else: ?>
                <div class="clear-all">
                    <a href="?remove_all=1" class="btn btn-danger" onclick="return confirm('Remove all items from wishlist?')">
                        <i class="fas fa-trash"></i> Clear All
                    </a>
                </div>

                <div class="wishlist-items">
                    <?php foreach ($wishlist_products as $product): ?>
                    <div class="wishlist-item" id="wishlist-item-<?php echo $product['id']; ?>">
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                             class="item-image">
                        
                        <div class="item-details">
                            <div class="item-category">
                                <?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?>
                            </div>
                            <h3 class="item-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
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
                        
                        <div class="item-actions">
                            <a href="?move_to_cart=<?php echo $product['id']; ?>" class="btn btn-primary btn-small"
                               onclick="return confirm('Move this item to cart?')">
                                <i class="fas fa-cart-plus"></i> Move to Cart
                            </a>
                            <!-- View Details button - FIXED to go to view_info.php -->
                            <a href="view_info.php?id=<?php echo $product['id']; ?>" class="btn btn-secondary btn-small">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <a href="?remove_wishlist=<?php echo $product['id']; ?>" class="btn btn-danger btn-small" 
                               onclick="return confirm('Remove from wishlist?')">
                                <i class="fas fa-trash"></i> Remove
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="continue-shopping">
                    <a href="shop.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
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

    <script>
        // Check if customer is logged in
        const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        const customerId = <?php echo $customer_id ? "'" . $customer_id . "'" : 'null'; ?>;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize mobile menu if needed
            if (typeof initMobileMenu === 'function') {
                initMobileMenu();
            }
        });

        // Handle clear all confirmation
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('remove_all')) {
            if (confirm('Are you sure you want to remove all items from your wishlist?')) {
                window.location.href = '?remove_all=1';
            }
        }
        
        // Mobile menu functionality (if not in main.js)
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
    </script>
</body>
</html>