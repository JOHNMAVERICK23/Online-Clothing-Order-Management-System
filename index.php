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

// Database connection for products AND CMS content
try {
    $host = 'localhost';
    $dbname = 'clothing_management_system';
    $username = 'root';
    $password = '';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ===== CMS CONTENT =====
    // Get homepage CMS sections
    $cmsStmt = $pdo->prepare("SELECT * FROM cms_sections WHERE page = 'homepage' AND status = 'active' ORDER BY sort_order ASC");
    $cmsStmt->execute();
    $cmsSections = $cmsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize CMS sections by section_type for easy access
    $cmsData = [];
    foreach ($cmsSections as $section) {
        $cmsData[$section['section_type']] = $section;
    }
    
    // Get CMS features (for features section)
    $featuresStmt = $pdo->prepare("SELECT * FROM cms_features WHERE status = 'active' ORDER BY sort_order ASC LIMIT 4");
    $featuresStmt->execute();
    $cmsFeatures = $featuresStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== PRODUCTS =====
    // Get BEST SELLING products - products with highest sold quantity from DELIVERED orders
    $bestSellingStmt = $pdo->prepare("
        SELECT 
            p.*,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COUNT(DISTINCT o.id) as order_count
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
        WHERE p.status = 'active'
        GROUP BY p.id
        ORDER BY total_sold DESC, p.created_at DESC
        LIMIT 8
    ");
    $bestSellingStmt->execute();
    $best_selling_products = $bestSellingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If we don't have 8 best-selling products, fill with most recent products
    if (count($best_selling_products) < 8) {
        $needed = 8 - count($best_selling_products);
        $recentStmt = $pdo->prepare("
            SELECT p.*, 0 as total_sold, 0 as order_count
            FROM products p
            WHERE p.status = 'active' 
            AND p.id NOT IN (SELECT id FROM (SELECT id FROM products ORDER BY created_at DESC LIMIT 8) as temp)
            ORDER BY p.created_at DESC 
            LIMIT ?
        ");
        $recentStmt->bindValue(1, $needed, PDO::PARAM_INT);
        $recentStmt->execute();
        $recent_products = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $best_selling_products = array_merge($best_selling_products, $recent_products);
    }
    
    // Get new arrivals - MOST RECENT 6 products
    $newArrivalsStmt = $pdo->prepare("
        SELECT p.*, 0 as total_sold, 0 as order_count
        FROM products p 
        WHERE p.status = 'active' 
        ORDER BY p.created_at DESC 
        LIMIT 6
    ");
    $newArrivalsStmt->execute();
    $new_arrivals = $newArrivalsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique categories for quick links from products table
    $categoriesStmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' AND status = 'active' ORDER BY category LIMIT 4");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get product count for stats
    $countStmt = $pdo->query("SELECT COUNT(*) as total_products FROM products WHERE status = 'active'");
    $product_count = $countStmt->fetch(PDO::FETCH_ASSOC)['total_products'];
    
    // Get total delivered orders count
    $deliveredStmt = $pdo->query("SELECT COUNT(*) as delivered_orders FROM orders WHERE status = 'delivered'");
    $delivered_count = $deliveredStmt->fetch(PDO::FETCH_ASSOC)['delivered_orders'];
    
} catch (PDOException $e) {
    // Don't die, just log error and set empty arrays
    error_log("Database connection failed: " . $e->getMessage());
    $cmsData = [];
    $cmsFeatures = [];
    $best_selling_products = [];
    $new_arrivals = [];
    $categories = [];
    $product_count = 0;
    $delivered_count = 0;
}

// Default CMS content if database is empty
$defaultCms = [
    'hero' => [
        'title' => 'Elevate Your Style<br>With <span>Timeless</span> Fashion',
        'subtitle' => 'PREMIUM CLOTHING COLLECTION',
        'description' => 'Discover exquisite craftsmanship and premium fabrics in every piece. From casual essentials to statement pieces, we bring you fashion that lasts beyond seasons.',
        'button1_text' => 'Shop Collection',
        'button1_link' => './Public/shop.php',
        'button2_text' => 'New Arrivals',
        'button2_link' => '#new-arrivals'
    ],
    'features' => [
        'title' => 'Experience <span>Premium</span> Quality',
        'subtitle' => 'Why Choose Us'
    ],
    'categories' => [
        'title' => 'Browse Our <span>Collections</span>',
        'subtitle' => 'Shop by Category'
    ],
    'featured' => [
        'title' => 'Best <span>Sellers</span>',
        'subtitle' => 'Customer Favorites'
    ],
    'new_arrivals' => [
        'title' => 'New <span>Arrivals</span>',
        'subtitle' => 'Just Arrived'
    ],
    'newsletter' => [
        'title' => 'Stay Updated',
        'description' => 'Subscribe to our newsletter for exclusive offers, new arrivals, and style tips.'
    ]
];

// Helper function to get CMS content
function getCmsContent($section, $field, $default = '') {
    global $cmsData, $defaultCms;
    
    if (isset($cmsData[$section][$field]) && !empty($cmsData[$section][$field])) {
        return $cmsData[$section][$field];
    } elseif (isset($defaultCms[$section][$field])) {
        return $defaultCms[$section][$field];
    }
    
    return $default;
}
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    
    <!-- Your CSS File -->
    <link rel="stylesheet" href="./Public/css/main.css">
    <style>
        /* ===== HOME PAGE SPECIFIC STYLES ===== */
        :root {
            --primary: #000000;
            --secondary: #8B7355;
            --accent: #C19A6B;
            --light: #FFFFFF;
            --dark: #1A1A1A;
            --gray: #666666;
            --gray-light: #F5F5F5;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --border: #E5E5E5;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 30px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 15px 50px rgba(0, 0, 0, 0.1);
        }

        .home-hero {
            position: relative;
            height: 85vh;
            min-height: 600px;
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            overflow: hidden;
            display: flex;
            align-items: center;
            padding: 0 2rem;
            margin-top: 80px;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            color: var(--light);
            animation: fadeInUp 1s ease-out;
        }

        .hero-subtitle {
            display: block;
            font-size: 1rem;
            letter-spacing: 3px;
            color: var(--accent);
            margin-bottom: 1rem;
            font-weight: 300;
            opacity: 0;
            animation: fadeIn 0.8s ease-out 0.3s forwards;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 300;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            letter-spacing: 2px;
            opacity: 0;
            animation: fadeInUp 0.8s ease-out 0.5s forwards;
        }

        .hero-title span {
            font-weight: 600;
            color: var(--accent);
        }

        .hero-description {
            font-size: 1.1rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 2rem;
            opacity: 0;
            animation: fadeIn 0.8s ease-out 0.7s forwards;
        }

        .hero-stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            opacity: 0;
            animation: fadeIn 0.8s ease-out 0.8s forwards;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 2rem;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            opacity: 0;
            animation: fadeIn 0.8s ease-out 0.9s forwards;
        }

        .btn-hero {
            padding: 1rem 2.5rem;
            border: 2px solid var(--accent);
            background: var(--accent);
            color: var(--light);
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-hero-outline {
            background: transparent;
            color: var(--accent);
        }

        .btn-hero:hover {
            background: var(--secondary);
            border-color: var(--secondary);
            color: var(--light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .hero-background {
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            background: linear-gradient(90deg, transparent 0%, rgba(0,0,0,0.7) 100%);
            z-index: 1;
        }

        .hero-slider {
            position: absolute;
            top: 0;
            right: 0;
            width: 60%;
            height: 100%;
            z-index: 0;
        }

        .swiper-slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Features Section */
        .features-section {
            padding: 5rem 2rem;
            background: var(--gray-light);
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-subtitle {
            display: block;
            font-size: 0.9rem;
            color: var(--accent);
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .section-heading {
            font-size: 2.5rem;
            font-weight: 300;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .section-heading span {
            font-weight: 600;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: var(--light);
            padding: 2.5rem 2rem;
            text-align: center;
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
            border-color: var(--accent);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--light);
            font-size: 1.8rem;
            transition: var(--transition);
        }

        .feature-card:hover .feature-icon {
            background: var(--dark);
            transform: scale(1.1) rotate(5deg);
        }

        .feature-card h3 {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 1rem;
            color: var(--dark);
        }

        .feature-card p {
            color: var(--gray);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        /* Categories Section */
        .categories-section {
            padding: 5rem 2rem;
            background: var(--light);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .category-card {
            position: relative;
            height: 300px;
            overflow: hidden;
            cursor: pointer;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .category-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .category-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .category-card:hover .category-image {
            transform: scale(1.1);
        }

        .category-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%);
            display: flex;
            align-items: flex-end;
            padding: 2rem;
            color: var(--light);
        }

        .category-content {
            width: 100%;
        }

        .category-title {
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .category-count {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 1rem;
        }

        .category-cta {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--light);
            text-decoration: none;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
            transition: var(--transition);
        }

        .category-cta:hover {
            color: var(--accent);
            gap: 1rem;
        }

        /* New Arrivals Section */
        .new-arrivals-section {
            padding: 5rem 2rem;
            background: var(--gray-light);
        }

        .arrivals-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .product-card {
            background: var(--light);
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .product-card:hover {
            border-color: var(--dark);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--dark);
            color: var(--light);
            padding: 0.3rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            z-index: 1;
        }

        .badge-new {
            background: var(--accent);
        }
        
        .badge-bestseller {
            background: #e74c3c;
        }

        .product-image-container {
            position: relative;
            overflow: hidden;
            height: 300px;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-content {
            padding: 1.5rem;
        }

        .product-category {
            color: var(--gray);
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark);
            line-height: 1.4;
        }

        .product-description {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 1rem;
            letter-spacing: 1px;
        }
        
        .product-stats {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--gray);
            font-size: 0.85rem;
        }
        
        .product-stats span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .product-stats i {
            color: var(--accent);
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-product {
            flex: 1;
            padding: 0.8rem;
            text-align: center;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-product:hover {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        .btn-product.primary {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        .btn-product.primary:hover {
            background: var(--accent);
            border-color: var(--accent);
        }

        .view-all {
            text-align: center;
            margin-top: 3rem;
        }

        /* Featured Products Carousel */
        .featured-section {
            padding: 5rem 2rem;
            background: var(--light);
        }

        .products-slider {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
        }

        .swiper-button-next,
        .swiper-button-prev {
            width: 50px;
            height: 50px;
            background: var(--light);
            border: 1px solid var(--border);
            color: var(--dark);
            transition: var(--transition);
        }

        .swiper-button-next:hover,
        .swiper-button-prev:hover {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        .swiper-button-next:after,
        .swiper-button-prev:after {
            font-size: 1rem;
        }

        /* Newsletter */
        .newsletter-section {
            padding: 4rem 2rem;
            background: var(--accent);
            color: var(--light);
            text-align: center;
        }

        .newsletter-content {
            max-width: 600px;
            margin: 0 auto;
        }

        .newsletter-title {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 1rem;
        }

        .newsletter-form {
            display: flex;
            gap: 1rem;
            max-width: 500px;
            margin: 2rem auto 0;
        }

        .newsletter-input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
            font-size: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .newsletter-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .newsletter-input:focus {
            outline: none;
            border-color: var(--light);
        }

        .btn-newsletter {
            padding: 1rem 2rem;
            background: var(--dark);
            color: var(--light);
            border: none;
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-newsletter:hover {
            background: var(--light);
            color: var(--dark);
        }

        /* Debug styles */
        .debug-info {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            margin: 1rem;
            border-radius: 5px;
            font-family: monospace;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .hero-title { font-size: 3rem; }
            .hero-slider { width: 50%; }
            .categories-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 992px) {
            .home-hero { height: 70vh; }
            .hero-title { font-size: 2.5rem; }
            .hero-slider { width: 45%; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .arrivals-grid { grid-template-columns: repeat(2, 1fr); }
            .new-arrivals-section .product-image-container { height: 250px; }
        }

        @media (max-width: 768px) {
            .home-hero {
                height: 60vh;
                text-align: center;
                justify-content: center;
                padding: 2rem;
            }
            .hero-slider { display: none; }
            .hero-background { display: none; }
            .hero-title { font-size: 2rem; }
            .hero-stats { flex-direction: column; gap: 1rem; }
            .hero-buttons { flex-direction: column; }
            .features-grid { grid-template-columns: 1fr; }
            .categories-grid { grid-template-columns: 1fr; }
            .arrivals-grid { grid-template-columns: 1fr; }
            .newsletter-form { flex-direction: column; }
            .section-heading { font-size: 2rem; }
            .product-actions { flex-direction: column; }
        }

        @media (max-width: 576px) {
            .home-hero { height: 50vh; }
            .hero-title { font-size: 1.8rem; }
            .newsletter-title { font-size: 2rem; }
            .features-section,
            .categories-section,
            .new-arrivals-section,
            .featured-section {
                padding: 3rem 1rem;
            }
            .category-card { height: 250px; }
        }
    </style>
</head>
<body>
    <!-- ========== NAVBAR ========== -->
    <nav class="customer-nav" id="customerNav">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="index.php" class="nav-logo">
            <img src="images/logo.jpg"  class="logo-image">
            <span class="brand-name">Alas Clothing Shop</span>
        </a>
        
        <!-- Mobile Menu -->
        <ul class="nav-menu" id="navMenu">
            <li><a href="index.php" class="active">HOME</a></li>
            <li><a href="./Public/shop.php">SHOP</a></li>
            <li><a href="./Public/orders.php">MY ORDERS</a></li>
            <li><a href="./Public/size_chart.php">SIZE CHART</a></li>
            <li><a href="./Public/shipping.php">SHIPPING</a></li>
            <li><a href="./Public/announcements.php">ANNOUNCEMENTS</a></li>
        </ul>
        
        <div class="nav-right">
            <!-- Icons -->
            <a href="<?php echo (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) ? './Public/account.php' : './Public/login_register.php'; ?>" class="nav-icon" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="./Public/wishlist.php" class="nav-icon" title="Wishlist">
                <i class="fas fa-heart"></i>
                <?php if (!empty($_SESSION['wishlist'])): ?>
                    <span class="wishlist-count-badge" id="wishlistCount">
                        <?php echo count($_SESSION['wishlist']); ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="./Public/cart_and_checkout.php" class="nav-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge" id="cartCount">
                    <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>
                </span>
            </a>
        </div>
    </nav>

    <!-- Debug Information (Remove this in production) -->
    <?php if (empty($best_selling_products) || empty($new_arrivals)): ?>
    <div class="debug-info">
        <strong>DEBUG INFO:</strong><br>
        CMS Sections: <?php echo count($cmsSections); ?><br>
        CMS Features: <?php echo count($cmsFeatures); ?><br>
        Best Selling Products: <?php echo count($best_selling_products); ?><br>
        New Arrivals: <?php echo count($new_arrivals); ?><br>
        Delivered Orders: <?php echo $delivered_count; ?>
    </div>
    <?php endif; ?>

    <!-- ========== HERO SECTION ========== -->
    <section class="home-hero">
        <div class="hero-content">
            <span class="hero-subtitle"><?php echo getCmsContent('hero', 'subtitle'); ?></span>
            <h1 class="hero-title">
                <?php echo getCmsContent('hero', 'title'); ?>
            </h1>
            <p class="hero-description">
                <?php echo getCmsContent('hero', 'description'); ?>
            </p>
            
            <div class="hero-stats">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($product_count); ?>+</span>
                    <span class="stat-label">Products</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($delivered_count); ?>+</span>
                    <span class="stat-label">Orders Delivered</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">100%</span>
                    <span class="stat-label">Quality</span>
                </div>
            </div>
            
            <div class="hero-buttons">
                <a href="<?php echo getCmsContent('hero', 'button1_link', './Public/shop.php'); ?>" class="btn-hero">
                    <i class="fas fa-shopping-bag"></i>
                    <?php echo getCmsContent('hero', 'button1_text'); ?>
                </a>
                <a href="<?php echo getCmsContent('hero', 'button2_link', './Public/new_arrivals.php'); ?>" class="btn-hero btn-hero-outline">
                    <i class="fas fa-star"></i>
                    <?php echo getCmsContent('hero', 'button2_text'); ?>
                </a>
            </div>
        </div>
        
        <div class="hero-slider">
            <div class="swiper">
                <div class="swiper-wrapper">
                    <?php 
                    $hero_images = json_decode(getCmsContent('hero', 'images', '[]'), true);
                    if (empty($hero_images)) {
                        $hero_images = [
                            'https://images.unsplash.com/photo-1445205170230-053b83016050?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80',
                            'https://images.unsplash.com/photo-1523381210434-271e8be1f52b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80',
                            'https://images.unsplash.com/photo-1558769132-cb1c458e4222?ixlib=rb-1.2.1&auto=format&fit=crop&w=1600&q=80'
                        ];
                    }
                    
                    foreach ($hero_images as $image): 
                        if (is_array($image)) {
                            $image_url = $image['url'] ?? $image;
                        } else {
                            $image_url = $image;
                        }
                    ?>
                    <div class="swiper-slide">
                        <img src="<?php echo htmlspecialchars($image_url); ?>" alt="Hero Image">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="hero-background"></div>
    </section>

    <!-- ========== FEATURES SECTION ========== -->
    <section class="features-section">
        <div class="section-title">
            <span class="section-subtitle"><?php echo getCmsContent('features', 'subtitle'); ?></span>
            <h2 class="section-heading"><?php echo getCmsContent('features', 'title'); ?></h2>
        </div>
        <div class="features-grid">
            <?php if (!empty($cmsFeatures)): ?>
                <?php foreach ($cmsFeatures as $feature): ?>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="<?php echo htmlspecialchars($feature['icon']); ?>"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($feature['title']); ?></h3>
                    <p><?php echo htmlspecialchars($feature['description']); ?></p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default features if none in database -->
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3>Premium Quality</h3>
                    <p>Handcrafted with the finest materials and attention to detail.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3>Free Shipping</h3>
                    <p>Free shipping on all orders over ₱1,000. Fast delivery nationwide.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-undo"></i>
                    </div>
                    <h3>Easy Returns</h3>
                    <p>30-day return policy. No questions asked.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h3>24/7 Support</h3>
                    <p>Dedicated customer support team always ready to help.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
                        
     <!-- ========== BEST SELLING PRODUCTS CAROUSEL ========== -->
    <?php if (!empty($best_selling_products)): ?>
    <section class="featured-section">
        <div class="section-title">
            <span class="section-subtitle"><?php echo getCmsContent('featured', 'subtitle'); ?></span>
            <h2 class="section-heading"><?php echo getCmsContent('featured', 'title'); ?></h2>
        </div>
        
        <div class="products-slider">
            <div class="swiper featured-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($best_selling_products as $product): ?>
                    <div class="swiper-slide">
                        <div class="product-card">
                            <?php if ($product['total_sold'] > 0): ?>
                            <div class="product-badge badge-bestseller">
                                <i class="fas fa-fire"></i> BEST SELLER
                            </div>
                            <?php endif; ?>
                            
                            <div class="product-image-container">
                                <img src="<?php echo htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                     class="product-image">
                            </div>
                            <div class="product-content">
                                <div class="product-category">
                                    <?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?>
                                </div>
                                <h3 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                
                                <?php if ($product['total_sold'] > 0): ?>
                                <div class="product-stats">
                                    <span>
                                        <i class="fas fa-shopping-bag"></i>
                                        <?php echo number_format($product['total_sold']); ?> sold
                                    </span>
                                    <?php if ($product['order_count'] > 0): ?>
                                    <span>
                                        <i class="fas fa-check-circle"></i>
                                        <?php echo $product['order_count']; ?> orders
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                <div class="product-actions">
                                    <a href="./Public/shop.php?search=<?php echo urlencode($product['product_name']); ?>" class="btn-product">
                                        View Details
                                    </a>
                                    <button class="btn-product primary" onclick="addToCartFromHome(<?php echo $product['id']; ?>)">
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Navigation buttons -->
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>
        
        <div class="view-all">
            <a href="./Public/shop.php" class="btn-hero btn-hero-outline" style="background: var(--dark); color: var(--light);">
                <i class="fas fa-store"></i>
                View All Products
            </a>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- ========== CATEGORIES SECTION ========== -->
    <?php if (!empty($categories)): ?>
    <section class="categories-section">
        <div class="section-title">
            <span class="section-subtitle"><?php echo getCmsContent('categories', 'subtitle'); ?></span>
            <h2 class="section-heading"><?php echo getCmsContent('categories', 'title'); ?></h2>
        </div>
        <div class="categories-grid">
            <?php 
            $category_images = [
                'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1515372039744-b8f02a3ae446?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1491553895911-0055eca6402d?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'
            ];
            
            $category_index = 0;
            foreach ($categories as $category): 
                if ($category_index >= 4) break; // Show only 4 categories
            ?>
            <div class="category-card">
                <img src="<?php echo $category_images[$category_index]; ?>" 
                     alt="<?php echo htmlspecialchars($category); ?>" 
                     class="category-image">
                <div class="category-overlay">
                    <div class="category-content">
                        <h3 class="category-title"><?php echo htmlspecialchars($category); ?></h3>
                        <a href="./Public/shop.php?category=<?php echo urlencode($category); ?>" class="category-cta">
                            Shop Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php 
                $category_index++;
                endforeach; 
            ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ========== NEW ARRIVALS SECTION ========== -->
    <section class="new-arrivals-section" id="new-arrivals">
        <div class="section-title">
            <span class="section-subtitle"><?php echo getCmsContent('new_arrivals', 'subtitle'); ?></span>
            <h2 class="section-heading"><?php echo getCmsContent('new_arrivals', 'title'); ?></h2>
        </div>
        
        <?php if (!empty($new_arrivals)): ?>
        <div class="arrivals-grid">
            <?php foreach ($new_arrivals as $product): ?>
            <div class="product-card">
                <span class="product-badge badge-new">NEW</span>
                <div class="product-image-container">
                    <img src="<?php echo htmlspecialchars($product['image_url'] ?: 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80'); ?>" 
                         alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                         class="product-image">
                </div>
                <div class="product-content">
                    <div class="product-category">
                        <?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?>
                    </div>
                    <h3 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                    <p class="product-description">
                        <?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 100)); ?>
                        <?php if (strlen($product['description'] ?? '') > 100): ?>...<?php endif; ?>
                    </p>
                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                    <div class="product-actions">
                        <a href="./Public/shop.php?search=<?php echo urlencode($product['product_name']); ?>" class="btn-product">
                            View Details
                        </a>
                        <button class="btn-product primary" onclick="addToCartFromHome(<?php echo $product['id']; ?>)">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 3rem; color: var(--gray);">
            <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem;"></i>
            <h3>No New Arrivals Yet</h3>
            <p>Check back soon for new products!</p>
        </div>
        <?php endif; ?>
        
        <div class="view-all">
            <a href="./Public/shop.php" class="btn-hero btn-hero-outline" style="background: var(--dark); color: var(--light);">
                <i class="fas fa-store"></i>
                View All Products
            </a>
        </div>
    </section>

    <!-- ========== NEWSLETTER ========== -->
    <section class="newsletter-section">
        <div class="newsletter-content">
            <h2 class="newsletter-title"><?php echo getCmsContent('newsletter', 'title'); ?></h2>
            <p><?php echo getCmsContent('newsletter', 'description'); ?></p>
            <form class="newsletter-form" id="newsletterForm">
                <input type="email" class="newsletter-input" placeholder="Enter your email address" required>
                <button type="submit" class="btn-newsletter">Subscribe</button>
            </form>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
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
                    <li><a href="./Public/shop.php"><i class="fas fa-chevron-right"></i> Shop</a></li>
                    <li><a href="./Public/size_chart.php"><i class="fas fa-chevron-right"></i> Size Chart</a></li>
                    <li><a href="./Public/shipping.php"><i class="fas fa-chevron-right"></i> Shipping & Returns</a></li>
                    <li><a href="./Public/announcements.php"><i class="fas fa-chevron-right"></i> Announcements</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Customer Service</h3>
                <ul class="footer-links">
                    <li><a href="./Public/orders.php"><i class="fas fa-chevron-right"></i> My Orders</a></li>
                    <li><a href="./Public/account.php"><i class="fas fa-chevron-right"></i> My Account</a></li>
                    <li><a href="./Public/wishlist.php"><i class="fas fa-chevron-right"></i> Wishlist</a></li>
                    <li><a href="./Public/cart_and_checkout.php"><i class="fas fa-chevron-right"></i> Cart</a></li>
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
    <script src="./Public/js/main.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Hero Slider
            const heroSwiper = new Swiper('.hero-slider .swiper', {
                loop: true,
                autoplay: {
                    delay: 5000,
                    disableOnInteraction: false,
                },
                speed: 1000,
                effect: 'fade',
                fadeEffect: {
                    crossFade: true
                }
            });

            // Best Selling Products Slider
            const featuredSwiper = new Swiper('.featured-swiper', {
                slidesPerView: 1,
                spaceBetween: 20,
                loop: true,
                autoplay: {
                    delay: 3000,
                    disableOnInteraction: false,
                },
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    640: {
                        slidesPerView: 2,
                    },
                    768: {
                        slidesPerView: 3,
                    },
                    1024: {
                        slidesPerView: 4,
                    },
                }
            });

            // Newsletter Form
            const newsletterForm = document.getElementById('newsletterForm');
            if (newsletterForm) {
                newsletterForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const email = this.querySelector('input[type="email"]').value;
                    
                    // Simple validation
                    if (email && email.includes('@')) {
                        // Here you would typically send this to your server
                        alert('Thank you for subscribing to our newsletter!');
                        this.reset();
                    } else {
                        alert('Please enter a valid email address.');
                    }
                });
            }

            // Intersection Observer for animations
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animated');
                        if (entry.target.classList.contains('feature-card')) {
                            entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                        }
                    }
                });
            }, observerOptions);

            // Observe all cards for animations
            document.querySelectorAll('.feature-card, .category-card, .product-card').forEach(el => {
                observer.observe(el);
            });

            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Add to Cart function for home page
            window.addToCartFromHome = async function(productId) {
                try {
                    const formData = new FormData();
                    formData.append('add_to_cart', 'true');
                    formData.append('product_id', productId);
                    formData.append('quantity', 1);
                    
                    const response = await fetch('./Public/shop.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // Update cart count
                        const cartCountEl = document.getElementById('cartCount');
                        if (cartCountEl) {
                            cartCountEl.textContent = data.cart_count;
                        }
                        
                        // Show success message
                        alert('Product added to cart successfully!');
                    } else {
                        alert('Failed to add product to cart. Please try again.');
                    }
                } catch (error) {
                    console.error('Error adding to cart:', error);
                    alert('Unable to add product to cart. Please try again.');
                }
            }
        });
    </script>
</body>
</html>