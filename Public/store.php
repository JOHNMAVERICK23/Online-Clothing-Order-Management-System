<?php
session_start();

// Initialize session variables
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

// Database connection
require_once '../PROCESS/db_config.php';

// Get all active products
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.is_active = TRUE 
          ORDER BY p.created_at DESC";

$result = $conn->query($query);
$products = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Get categories for filter
$categories = [];
$catQuery = "SELECT DISTINCT c.* FROM categories c 
             INNER JOIN products p ON c.id = p.category_id 
             WHERE p.is_active = TRUE 
             ORDER BY c.name";
$catResult = $conn->query($catQuery);

if ($catResult->num_rows > 0) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

$user_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_name = $_SESSION['user_name'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .shop-container { max-width: 1400px; margin: 0 auto; padding: 2rem; padding-top: 7rem; }
        .page-header { text-align: center; margin-bottom: 3rem; }
        .page-header h1 { font-size: 2.5rem; font-weight: 300; letter-spacing: 2px; text-transform: uppercase; }
        .filters { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; }
        .filter-sidebar { background: var(--light); border: 1px solid var(--border); padding: 1.5rem; }
        .filter-group { margin-bottom: 2rem; }
        .filter-group h3 { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-option { margin-bottom: 0.8rem; }
        .filter-option input { margin-right: 0.5rem; cursor: pointer; }
        .filter-option label { cursor: pointer; font-size: 0.95rem; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem; }
        .product-card { background: var(--light); border: 1px solid var(--border); transition: all 0.3s; }
        .product-card:hover { border-color: var(--dark); transform: translateY(-5px); }
        .product-image { width: 100%; height: 250px; object-fit: cover; background: var(--gray-light); }
        .product-info { padding: 1.5rem; }
        .product-category { color: var(--gray); font-size: 0.8rem; text-transform: uppercase; margin-bottom: 0.5rem; }
        .product-name { font-size: 1.05rem; font-weight: 500; margin-bottom: 0.5rem; color: var(--dark); }
        .product-price { font-size: 1.3rem; font-weight: 600; margin-bottom: 1rem; color: var(--dark); }
        .product-stock { font-size: 0.85rem; margin-bottom: 1rem; }
        .stock-badge { padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; }
        .stock-available { background: #d4edda; color: #155724; }
        .stock-low { background: #fff3cd; color: #856404; }
        .stock-out { background: #f8d7da; color: #721c24; }
        .product-actions { display: flex; gap: 0.5rem; }
        .btn { padding: 0.8rem 1.2rem; border: 1px solid var(--border); background: var(--light); color: var(--dark); border-radius: 0; font-weight: 500; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; }
        .btn:hover { border-color: var(--dark); }
        .btn-primary { background: var(--dark); color: var(--light); border-color: var(--dark); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-small { padding: 0.5rem 0.8rem; font-size: 0.8rem; }
        .empty-products { grid-column: 1 / -1; text-align: center; padding: 3rem; }
        @media (max-width: 992px) {
            .filters { grid-template-columns: 1fr; }
            .filter-sidebar { order: 2; }
            .products-grid { order: 1; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); }
        }
        @media (max-width: 768px) {
            .shop-container { padding: 1rem; }
            .page-header h1 { font-size: 1.8rem; }
            .filters { display: none; }
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
            <img src="../../resources/images/logo.jpeg" alt="Alas Clothing Shop" class="logo-image">
            <span class="brand-name">ALAS</span>
        </a>
        
        <ul class="nav-menu" id="navMenu">
            <li><a href="index.php">HOME</a></li>
            <li><a href="shop.php" class="active">SHOP</a></li>
            <li><a href="orders.php">MY ORDERS</a></li>
            <li><a href="size_chart.php">SIZE CHART</a></li>
            <li><a href="shipping.php">SHIPPING</a></li>
        </ul>
        
        <div class="nav-right">
            <a href="<?php echo $user_logged_in ? 'account.php' : 'login_register.php'; ?>" class="nav-icon" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="wishlist.php" class="nav-icon" title="Wishlist">
                <i class="fas fa-heart"></i>
                <?php if (!empty($_SESSION['wishlist'])): ?>
                    <span class="wishlist-count-badge" id="wishlistCount"><?php echo count($_SESSION['wishlist']); ?></span>
                <?php endif; ?>
            </a>
            <a href="cart_and_checkout.php" class="nav-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge" id="cartCount"><?php echo count($_SESSION['cart']); ?></span>
            </a>
        </div>
    </nav>

    <div class="main-content">
        <div class="shop-container">
            <div class="page-header">
                <h1>Shop Our Collection</h1>
                <p class="page-subtitle">Browse our latest fashion items</p>
            </div>

            <div class="filters">
                <div class="filter-sidebar">
                    <div class="filter-group">
                        <h3>Categories</h3>
                        <?php foreach ($categories as $cat): ?>
                        <div class="filter-option">
                            <input type="checkbox" id="cat-<?php echo $cat['id']; ?>" 
                                   value="<?php echo $cat['id']; ?>" class="category-filter">
                            <label for="cat-<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="filter-group">
                        <h3>Price Range</h3>
                        <div class="filter-option">
                            <label>
                                Min: ₱<input type="number" id="priceMin" value="0" style="width: 80px; padding: 0.3rem;">
                            </label>
                        </div>
                        <div class="filter-option">
                            <label>
                                Max: ₱<input type="number" id="priceMax" value="10000" style="width: 80px; padding: 0.3rem;">
                            </label>
                        </div>
                        <button class="btn btn-primary" onclick="applyFilters()" style="width: 100%; margin-top: 1rem;">
                            Apply Filters
                        </button>
                    </div>
                </div>

                <div>
                    <div class="products-grid" id="productsGrid">
                        <?php if (empty($products)): ?>
                        <div class="empty-products">
                            <i class="fas fa-inbox" style="font-size: 3rem; color: var(--gray-light); margin-bottom: 1rem;"></i>
                            <h3>No products available</h3>
                            <p style="color: var(--gray);">Check back later for new items!</p>
                        </div>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                            <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                                <img src="<?php echo htmlspecialchars($product['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="product-image">
                                <div class="product-info">
                                    <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                                    <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="product-stock">
                                        <?php if ($product['stock_quantity'] > 10): ?>
                                            <span class="stock-badge stock-available"><i class="fas fa-check-circle"></i> In Stock</span>
                                        <?php elseif ($product['stock_quantity'] > 0): ?>
                                            <span class="stock-badge stock-low"><i class="fas fa-exclamation-circle"></i> Low Stock</span>
                                        <?php else: ?>
                                            <span class="stock-badge stock-out"><i class="fas fa-times-circle"></i> Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-actions">
                                        <button class="btn btn-primary btn-small add-to-cart-btn" 
                                                data-product-id="<?php echo $product['id']; ?>" 
                                                onclick="addToCart(<?php echo $product['id']; ?>)" 
                                                <?php echo $product['stock_quantity'] <= 0 ? 'disabled' : ''; ?>>
                                            <i class="fas fa-cart-plus"></i> Add
                                        </button>
                                        <button class="btn btn-small add-to-wishlist-btn" 
                                                data-product-id="<?php echo $product['id']; ?>"
                                                onclick="addToWishlist(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-heart"></i> Save
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>Alas Clothing Shop offers premium quality clothing for every occasion.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="shop.php"><i class="fas fa-chevron-right"></i> Shop</a></li>
                    <li><a href="orders.php"><i class="fas fa-chevron-right"></i> My Orders</a></li>
                    <li><a href="account.php"><i class="fas fa-chevron-right"></i> Account</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Alas Clothing Shop. All rights reserved.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script>
        function addToCart(productId) {
            const quantity = 1;
            // Update session/local storage
            const carts = JSON.parse(localStorage.getItem('cart') || '[]');
            const exists = carts.find(c => c.product_id == productId);
            
            if (exists) {
                exists.quantity += quantity;
            } else {
                carts.push({ product_id: productId, quantity: quantity });
            }
            
            localStorage.setItem('cart', JSON.stringify(carts));
            
            // Update cart badge
            document.getElementById('cartCount').textContent = carts.length;
            
            alert('Product added to cart!');
        }

        function addToWishlist(productId) {
            const wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');
            
            if (!wishlist.includes(productId)) {
                wishlist.push(productId);
                localStorage.setItem('wishlist', JSON.stringify(wishlist));
                
                if (document.getElementById('wishlistCount')) {
                    document.getElementById('wishlistCount').textContent = wishlist.length;
                }
                
                alert('Added to wishlist!');
            } else {
                alert('Already in your wishlist!');
            }
        }

        function applyFilters() {
            // Get selected categories
            const categories = Array.from(document.querySelectorAll('.category-filter:checked'))
                .map(cb => cb.value);
            
            const minPrice = parseFloat(document.getElementById('priceMin').value) || 0;
            const maxPrice = parseFloat(document.getElementById('priceMax').value) || 10000;
            
            console.log('Filtering by:', { categories, minPrice, maxPrice });
            // Add AJAX call here to filter products
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const cartCount = JSON.parse(localStorage.getItem('cart') || '[]').length;
            const wishlistCount = JSON.parse(localStorage.getItem('wishlist') || '[]').length;
            
            document.getElementById('cartCount').textContent = cartCount;
            if (wishlistCount > 0 && document.getElementById('wishlistCount')) {
                document.getElementById('wishlistCount').textContent = wishlistCount;
            }
        });
    </script>
</body>
</html>