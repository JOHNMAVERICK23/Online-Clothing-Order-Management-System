<?php
session_start();

// Initialize session variables if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// User session variables
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Alas Clothing Shop</title>
    
    <!-- External CSS Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    
    <!-- Your CSS File -->
    <link rel="stylesheet" href="css/main.css">
</head>
<body>
    <!-- ========== NAVBAR ========== -->
    <nav class="customer-nav" id="customerNav">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="index.php" class="nav-logo">
            <img src="../../resources/images/logo.jpeg" alt="Alas Clothing Shop" class="logo-image">
            <span class="brand-name"></span>
        </a>
        
        <!-- Mobile Menu -->
        <ul class="nav-menu" id="navMenu">
            <li><a href="index.php" class="active">HOME</a></li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="orders.php">MY ORDERS</a></li>
            <li><a href="size_chart.php">SIZE CHART</a></li>
            <li><a href="shipping.php">SHIPPING</a></li>
            <li><a href="announcements.php">ANNOUNCEMENTS</a></li>
        </ul>
        
        <div class="nav-right">
            <!-- Icons -->
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
            <a href="cart_and_checkout.php" class="nav-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge" id="cartCount">
                    <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>
                </span>
            </a>
        </div>
    </nav>

    <!-- ========== MAIN CONTENT ========== -->
    <main class="main-content">
        <!-- YOUR CONTENT GOES HERE -->
        <!-- This area is intentionally left empty for page-specific content -->
        
    </main>

    <!-- ========== FOOTER ========== -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>Alas Clothing Shop offers premium quality clothing and accessories for every occasion.</p>
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

    <!-- ========== JAVASCRIPT ========== -->
    <script src="js/main.js"></script>
</body>
</html>