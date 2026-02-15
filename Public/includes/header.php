<?php
session_start();

// Initialize session variables if not exists
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

// User session variables
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_type = $_SESSION['user_type'] ?? 'guest';
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$user_id = $_SESSION['user_id'] ?? null;

// Get active page from parameter
$active_page = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Alas Clothing Shop'; ?></title>
    
    <!-- External Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    
    <!-- Your CSS Files -->
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="header.css">
    <link rel="stylesheet" href="footer.css">
    
    <!-- Page specific CSS -->
    <?php if (isset($page_css)): ?>
    <style><?php echo $page_css; ?></style>
    <?php endif; ?>
</head>
<body>
    
    <!-- Navigation -->
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
            <li><a href="index.php" class="<?php echo $active_page === 'home' ? 'active' : ''; ?>">HOME</a></li>
            <li><a href="shop.php" class="<?php echo $active_page === 'shop' ? 'active' : ''; ?>">SHOP</a></li>
            <li><a href="orders.php" class="<?php echo $active_page === 'orders' ? 'active' : ''; ?>">MY ORDERS</a></li>
            <li><a href="size_chart.php" class="<?php echo $active_page === 'size_chart' ? 'active' : ''; ?>">SIZE CHART</a></li>
            <li><a href="shipping.php" class="<?php echo $active_page === 'shipping' ? 'active' : ''; ?>">SHIPPING</a></li>
            <li><a href="announcements.php" class="<?php echo $active_page === 'announcements' ? 'active' : ''; ?>">ANNOUNCEMENTS</a></li>
        </ul>
        
        <div class="nav-right">
            <!-- Icons -->
            <a href="<?php echo $is_logged_in ? 'account.php' : 'login_register.php'; ?>" class="nav-icon" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="wishlist.php" class="nav-icon" title="Wishlist">
                <i class="fas fa-heart"></i>
                <?php if ($is_logged_in && !empty($_SESSION['wishlist'])): ?>
                    <span class="wishlist-count-badge" id="wishlistCount">
                        <?php echo count($_SESSION['wishlist']); ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="cart_and_checkout.php" class="nav-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge" id="cartCount">
                    <?php echo count($_SESSION['cart']); ?>
                </span>
            </a>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <div class="main-content">