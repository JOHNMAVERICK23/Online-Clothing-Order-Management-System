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

// Get filter parameters
$category_filter = $_GET['category'] ?? 'all';
$brand_filter = $_GET['brand'] ?? 'all';
$price_min = $_GET['price_min'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.is_active = TRUE";

$params = [];

if ($category_filter !== 'all') {
    $query .= " AND c.name = ?";
    $params[] = $category_filter;
}

if ($brand_filter !== 'all') {
    $query .= " AND p.brand = ?";
    $params[] = $brand_filter;
}

if ($price_min !== '') {
    $query .= " AND p.price >= ?";
    $params[] = floatval($price_min);
}

if ($price_max !== '') {
    $query .= " AND p.price <= ?";
    $params[] = floatval($price_max);
}

if ($search !== '') {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Add sorting
switch ($sort) {
    case 'price_low': $query .= " ORDER BY p.price ASC"; break;
    case 'price_high': $query .= " ORDER BY p.price DESC"; break;
    case 'name': $query .= " ORDER BY p.name ASC"; break;
    default: $query .= " ORDER BY p.created_at DESC"; break;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->query("SELECT DISTINCT name FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get brands for filter
$stmt = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand");
$brands = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Helper function to save cart to database
function saveCartToDatabase($pdo, $user_id, $cart) {
    if (!$user_id) return false;
    
    // Delete existing cart items for this user
    $stmt = $pdo->prepare("DELETE FROM user_carts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Insert new cart items
    if (!empty($cart)) {
        $sql = "INSERT INTO user_carts (user_id, product_id, quantity) VALUES ";
        $values = [];
        $params = [];
        
        foreach ($cart as $item) {
            $values[] = "(?, ?, ?)";
            $params[] = $user_id;
            $params[] = $item['product_id'];
            $params[] = $item['quantity'];
        }
        
        $sql .= implode(', ', $values);
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    return true;
}

// Helper function to save wishlist to database
function saveWishlistToDatabase($pdo, $user_id, $wishlist) {
    if (!$user_id) return false;
    
    // Delete existing wishlist items for this user
    $stmt = $pdo->prepare("DELETE FROM user_wishlists WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Insert new wishlist items
    if (!empty($wishlist)) {
        $sql = "INSERT INTO user_wishlists (user_id, product_id) VALUES ";
        $values = [];
        $params = [];
        
        foreach ($wishlist as $product_id) {
            $values[] = "(?, ?)";
            $params[] = $user_id;
            $params[] = $product_id;
        }
        
        $sql .= implode(', ', $values);
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    return true;
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']) ?: 1;
    
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $product_id) {
            $item['quantity'] += $quantity;
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        $_SESSION['cart'][] = ['product_id' => $product_id, 'quantity' => $quantity];
    }
    
    // Save cart to database if user is logged in
    if ($is_logged_in && $user_id) {
        saveCartToDatabase($pdo, $user_id, $_SESSION['cart']);
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'cart_count' => count($_SESSION['cart']),
            'message' => 'Product added to cart successfully!'
        ]);
        exit;
    } else {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle add to wishlist - NOW REQUIRES LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    // Check if user is logged in
    if (!$is_logged_in) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'requires_login' => true,
                'message' => 'Please login to add items to your wishlist!'
            ]);
            exit;
        } else {
            // Redirect to login page for non-AJAX requests
            $_SESSION['redirect_after_login'] = $_SERVER['PHP_SELF'];
            header('Location: login_register.php');
            exit;
        }
    }
    
    $product_id = intval($_POST['product_id']);
    
    // Check if product already in wishlist
    if (!in_array($product_id, $_SESSION['wishlist'])) {
        $_SESSION['wishlist'][] = $product_id;
        $wishlist_added = true;
        $wishlist_message = 'Product added to wishlist!';
        
        // Save wishlist to database
        if ($user_id) {
            saveWishlistToDatabase($pdo, $user_id, $_SESSION['wishlist']);
        }
    } else {
        $wishlist_added = false;
        $wishlist_message = 'Product already in wishlist!';
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $wishlist_added,
            'wishlist_count' => count($_SESSION['wishlist']),
            'message' => $wishlist_message
        ]);
        exit;
    }
}

// Handle remove from wishlist - NOW REQUIRES LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_wishlist'])) {
    // Check if user is logged in
    if (!$is_logged_in) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'requires_login' => true,
                'message' => 'Please login to manage your wishlist!'
            ]);
            exit;
        } else {
            $_SESSION['redirect_after_login'] = $_SERVER['PHP_SELF'];
            header('Location: login_register.php');
            exit;
        }
    }
    
    $product_id = intval($_POST['product_id']);
    
    $key = array_search($product_id, $_SESSION['wishlist']);
    if ($key !== false) {
        unset($_SESSION['wishlist'][$key]);
        $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
        $wishlist_removed = true;
        $wishlist_message = 'Product removed from wishlist!';
        
        // Save updated wishlist to database
        if ($user_id) {
            saveWishlistToDatabase($pdo, $user_id, $_SESSION['wishlist']);
        }
    } else {
        $wishlist_removed = false;
        $wishlist_message = 'Product not found in wishlist!';
    }
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $wishlist_removed,
            'wishlist_count' => count($_SESSION['wishlist']),
            'message' => $wishlist_message
        ]);
        exit;
    }
}

// Handle logout - Save cart before destroying session
if (isset($_GET['logout'])) {
    // Save cart to database if user is logged in
    if ($is_logged_in && $user_id && !empty($_SESSION['cart'])) {
        saveCartToDatabase($pdo, $user_id, $_SESSION['cart']);
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
    <title>Shop - Alas Clothing Shop</title>
    
    <!-- External Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    
    <!-- Your CSS Files -->
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ===== SHOP PAGE SPECIFIC STYLES ===== */
        .shop-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 6rem;
            flex: 1;
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

        .shop-controls {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        /* Search Box */
        .search-box {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .search-box input {
            flex: 1;
            padding: 1rem;
            padding-right: 3rem;
            border: 1px solid var(--border);
            background: var(--light);
            border-radius: 0;
            font-size: 1rem;
            color: var(--dark);
            transition: border 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--dark);
        }

        .search-box .search-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            pointer-events: none;
        }

        .clear-search {
            position: absolute;
            right: 2.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 1.2rem;
            display: none;
        }

        .clear-search:hover {
            color: var(--dark);
        }

        /* Filters */
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            margin-bottom: 0;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            background: var(--light);
            border-radius: 0;
            font-size: 0.9rem;
            color: var(--dark);
            cursor: pointer;
            transition: border 0.3s;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--dark);
        }

        .price-range {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .price-input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            background: var(--light);
            border-radius: 0;
            text-align: center;
            color: var(--dark);
            transition: border 0.3s;
        }

        .price-input:focus {
            outline: none;
            border-color: var(--dark);
        }

        .sort-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .results-count {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 300;
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .product-card {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }

        .product-card:hover {
            border-color: var(--dark);
            transform: translateY(-4px);
        }

        .product-image {
            width: 100%;
            height: 320px;
            object-fit: cover;
            background: var(--gray-light);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--light);
            color: var(--dark);
            padding: 0.3rem 1rem;
            border: 1px solid var(--dark);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
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
            letter-spacing: 0.5px;
        }

        .product-description {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.5;
            font-weight: 300;
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

        .product-stock {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            color: var(--gray);
        }

        .stock-available { color: var(--success); }
        .stock-low { color: var(--warning); }
        .stock-out { color: var(--gray); }

        /* Wishlist Button */
        .wishlist-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--light);
            border: 1px solid var(--border);
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 2;
        }

        .wishlist-btn:hover {
            background: var(--light);
            border-color: var(--danger);
            color: var(--danger);
        }

        .wishlist-btn.active {
            background: var(--danger);
            border-color: var(--danger);
            color: var(--light);
        }

        .wishlist-btn.active:hover {
            background: var(--light);
            color: var(--danger);
        }

        .wishlist-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .wishlist-btn.disabled:hover {
            background: var(--light);
            border-color: var(--border);
            color: var(--dark);
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
        }

        .btn-primary {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
            flex: 1;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
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

        /* Quantity Controls */
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .quantity-btn {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .quantity-btn:hover {
            border-color: var(--dark);
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 0.5rem;
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

        .product-actions {
            display: flex;
            gap: 0.5rem;
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

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .page-btn:hover {
            border-color: var(--dark);
            background: var(--dark);
            color: var(--light);
        }

        .page-btn.active {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        /* No Results */
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem;
            color: var(--gray);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--light);
            width: 100%;
            max-width: 900px;
            border-radius: 0;
            animation: modalSlideIn 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border);
        }

        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            color: var(--dark);
            font-weight: 500;
            letter-spacing: 1px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--dark);
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: var(--secondary);
        }

        .modal-body {
            padding: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .modal-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            background: var(--gray-light);
        }

        .modal-details {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .modal-price {
            font-size: 2rem;
            font-weight: 500;
            color: var(--dark);
            letter-spacing: 1px;
        }

        .modal-stock {
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border: 1px solid var(--border);
            display: inline-block;
            width: fit-content;
            font-weight: 500;
        }

        .stock-in {
            background: var(--light);
            color: var(--dark);
            border-color: var(--success);
        }

        .stock-low {
            background: var(--light);
            color: var(--dark);
            border-color: var(--warning);
        }

        .modal-description {
            color: var(--gray);
            line-height: 1.6;
            margin: 1rem 0;
            font-weight: 300;
        }

        .specs-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        .specs-table td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
        }

        .specs-table td:first-child {
            font-weight: 500;
            color: var(--dark);
            width: 120px;
        }

        /* Notifications */
        .cart-notification,
        .wishlist-notification,
        .login-notification {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--light);
            padding: 1rem 1.5rem;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
            z-index: 1000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
            max-width: 350px;
        }

        .wishlist-notification {
            right: auto;
            left: 2rem;
        }

        .login-notification {
            background: var(--warning);
            color: var(--dark);
            border-color: var(--warning);
        }

        .cart-notification.show,
        .wishlist-notification.show,
        .login-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            background: var(--dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--light);
            font-size: 1.2rem;
        }

        .wishlist-notification .notification-icon {
            background: var(--secondary);
        }

        .login-notification .notification-icon {
            background: var(--warning);
            color: var(--dark);
        }

        .notification-content {
            flex: 1;
        }

        .notification-content h4 {
            color: var(--dark);
            margin-bottom: 0.2rem;
            font-weight: 500;
        }

        .login-notification .notification-content h4 {
            color: var(--dark);
        }

        .notification-content p {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 300;
        }

        .login-notification .notification-content p {
            color: var(--dark);
        }

        /* Login Prompt Modal */
        .login-prompt-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-prompt-modal.active {
            display: flex;
        }

        .login-prompt-content {
            background: var(--light);
            width: 100%;
            max-width: 500px;
            border-radius: 0;
            animation: modalSlideIn 0.3s ease;
            border: 1px solid var(--border);
            text-align: center;
        }

        .login-prompt-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
        }

        .login-prompt-header i {
            font-size: 3rem;
            color: var(--warning);
            margin-bottom: 1rem;
        }

        .login-prompt-header h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .login-prompt-header p {
            color: var(--gray);
        }

        .login-prompt-body {
            padding: 2rem;
        }

        .login-prompt-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .products-grid { grid-template-columns: repeat(3, 1fr); }
            .filters-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 992px) {
            .shop-container { padding: 1rem; padding-top: 5rem; }
            .products-grid { grid-template-columns: repeat(2, 1fr); }
            .filters-grid { grid-template-columns: 1fr; }
            .cart-notification,
            .wishlist-notification,
            .login-notification { left: 1rem; right: 1rem; max-width: none; }
        }

        @media (max-width: 768px) {
            .search-box { flex-direction: column; }
            .sort-controls { flex-direction: column; gap: 1rem; align-items: flex-start; }
            .product-actions { flex-direction: column; }
            .modal-body { grid-template-columns: 1fr; padding: 1.5rem; }
            .cart-notification,
            .wishlist-notification,
            .login-notification { bottom: 1rem; }
            .login-prompt-buttons { flex-direction: column; }
        }

        @media (max-width: 576px) {
            .page-header h1 { font-size: 1.8rem; }
            .product-image { height: 280px; }
            .products-grid { grid-template-columns: 1fr; }
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
            <span class="brand-name"></span>
        </a>
        
        <!-- Mobile Menu -->
        <ul class="nav-menu" id="navMenu">
            <li><a href="index.php">HOME</a></li>
            <li><a href="shop.php" class="active">SHOP</a></li>
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

    <!-- Main Content -->
    <div class="shop-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Discover Our Collection</h1>
            <p class="page-subtitle">Premium clothing and accessories for every occasion</p>
        </div>

        <?php if (isset($wishlist_message)): ?>
            <div class="message success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($wishlist_message); ?>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="shop-controls">
            <!-- Live Search Box -->
            <div class="search-box">
                <input type="text" 
                       id="liveSearch" 
                       placeholder="Search products by name, description, or brand..."
                       value="<?php echo htmlspecialchars($search); ?>">
                <i class="fas fa-search search-icon"></i>
                <button class="clear-search" id="clearSearch" title="Clear search">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="GET" class="filters-grid">
                <input type="hidden" name="search" id="filterSearch" value="<?php echo htmlspecialchars($search); ?>">
                
                <div class="filter-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="filter-select" onchange="this.form.submit()">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" 
                                <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="brand">Brand</label>
                    <select id="brand" name="brand" class="filter-select" onchange="this.form.submit()">
                        <option value="all">All Brands</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo htmlspecialchars($brand); ?>" 
                                <?php echo $brand_filter === $brand ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="price_range">Price Range</label>
                    <div class="price-range">
                        <input type="number" name="price_min" class="price-input" placeholder="Min" 
                               value="<?php echo htmlspecialchars($price_min); ?>" onchange="this.form.submit()">
                        <span>to</span>
                        <input type="number" name="price_max" class="price-input" placeholder="Max" 
                               value="<?php echo htmlspecialchars($price_max); ?>" onchange="this.form.submit()">
                    </div>
                </div>

                <div class="filter-group">
                    <label for="sort">Sort By</label>
                    <select id="sort" name="sort" class="filter-select" onchange="this.form.submit()">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                    </select>
                </div>
            </form>

            <div class="sort-controls">
                <div class="results-count" id="resultsCount">
                    Showing <?php echo count($products); ?> of <?php echo count($products); ?> products
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="products-grid" id="productsGrid">
            <?php if (empty($products)): ?>
                <div class="no-results">
                    <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3>No products found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): 
                    $in_wishlist = $is_logged_in && in_array($product['id'], $_SESSION['wishlist']);
                ?>
                <div class="product-card" data-product-id="<?php echo $product['id']; ?>"
                     data-search-text="<?php echo htmlspecialchars(strtolower($product['name'] . ' ' . ($product['description'] ?? '') . ' ' . ($product['brand'] ?? '') . ' ' . ($product['category_name'] ?? ''))); ?>">
                    <?php if ($product['stock_quantity'] < 10 && $product['stock_quantity'] > 0): ?>
                        <span class="product-badge">Low Stock</span>
                    <?php elseif ($product['stock_quantity'] == 0): ?>
                        <span class="product-badge" style="border-color: var(--gray); color: var(--gray);">Out of Stock</span>
                    <?php endif; ?>
                    
                    <!-- Wishlist Button -->
                    <button class="wishlist-btn <?php echo $in_wishlist ? 'active' : ''; ?> <?php echo !$is_logged_in ? 'disabled' : ''; ?>" 
                            data-product-id="<?php echo $product['id']; ?>"
                            onclick="toggleWishlist(<?php echo $product['id']; ?>)"
                            title="<?php echo !$is_logged_in ? 'Login to add to wishlist' : ($in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'); ?>">
                        <i class="fas fa-heart"></i>
                    </button>
                    
                    <img src="<?php echo htmlspecialchars($product['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="product-image">
                    
                    <div class="product-content">
                        <div class="product-category">
                            <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                        </div>
                        
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        
                        <p class="product-description">
                            <?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 100)); ?>
                            <?php if (strlen($product['description'] ?? '') > 100): ?>...<?php endif; ?>
                        </p>
                        
                        <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                        
                        <div class="product-stock">
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
                        
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="decreaseQuantity(<?php echo $product['id']; ?>)">-</button>
                            <input type="number" id="quantity_<?php echo $product['id']; ?>" 
                                   class="quantity-input" value="1" min="1" 
                                   max="<?php echo $product['stock_quantity']; ?>">
                            <button class="quantity-btn" onclick="increaseQuantity(<?php echo $product['id']; ?>)">+</button>
                        </div>
                        
                        <div class="product-actions">
                            <button class="btn btn-primary" onclick="addToCart(<?php echo $product['id']; ?>)">
                                <i class="fas fa-cart-plus"></i> Add to Cart
                            </button>
                            <button class="btn btn-secondary" onclick="quickView(<?php echo $product['id']; ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <button class="page-btn active">1</button>
            <button class="page-btn">2</button>
            <button class="page-btn">3</button>
            <button class="page-btn">4</button>
            <button class="page-btn">5</button>
        </div>
    </div>

    <!-- Quick View Modal -->
    <div class="modal" id="quickViewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalProductName">Product Name</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Dynamic content will be inserted here -->
            </div>
        </div>
    </div>

    <!-- Login Prompt Modal -->
    <div class="login-prompt-modal" id="loginPromptModal">
        <div class="login-prompt-content">
            <div class="login-prompt-header">
                <i class="fas fa-lock"></i>
                <h3>Login Required</h3>
                <p>Please login to add items to your wishlist</p>
            </div>
            <div class="login-prompt-body">
                <p>You need to be logged in to save items to your wishlist.</p>
                <div class="login-prompt-buttons">
                    <button class="btn btn-primary" onclick="redirectToLogin()">
                        <i class="fas fa-sign-in-alt"></i> Login Now
                    </button>
                    <button class="btn btn-secondary" onclick="closeLoginPrompt()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Notification -->
    <div class="cart-notification" id="cartNotification">
        <div class="notification-icon">
            <i class="fas fa-check"></i>
        </div>
        <div class="notification-content">
            <h4>Added to Cart!</h4>
            <p id="notificationMessage">Product has been added to your cart</p>
        </div>
        <button class="btn btn-primary" onclick="window.location.href='cart_and_checkout.php'">
            View Cart
        </button>
    </div>

    <!-- Wishlist Notification -->
    <div class="wishlist-notification" id="wishlistNotification">
        <div class="notification-icon">
            <i class="fas fa-heart"></i>
        </div>
        <div class="notification-content">
            <h4 id="wishlistNotificationTitle">Added to Wishlist!</h4>
            <p id="wishlistNotificationMessage">Product has been added to your wishlist</p>
        </div>
        <button class="btn btn-primary" onclick="window.location.href='wishlist.php'">
            View Wishlist
        </button>
    </div>

    <!-- Login Notification -->
    <div class="login-notification" id="loginNotification">
        <div class="notification-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="notification-content">
            <h4>Login Required</h4>
            <p id="loginNotificationMessage">Please login to add items to wishlist</p>
        </div>
        <button class="btn btn-primary" onclick="redirectToLogin()">
            Login
        </button>
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
    
    <!-- Shop Page JavaScript -->
    <script>
        // Shop page specific functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize shop features
            initShop();
            
            // Highlight SHOP as active page
            highlightShopPage();
            
            // Initialize shop-specific UI
            initShopUI();
        });

        function initShop() {
            // Store all product HTML initially
            window.allProductsHTML = document.getElementById('productsGrid').innerHTML;
            window.productCards = Array.from(document.querySelectorAll('.product-card'));
            
            // Initialize clear button visibility
            const liveSearch = document.getElementById('liveSearch');
            const clearSearchBtn = document.getElementById('clearSearch');
            if (liveSearch.value) {
                clearSearchBtn.style.display = 'block';
            }
            
            // Update results count
            updateResultsCount();
        }

        function highlightShopPage() {
            const navLinks = document.querySelectorAll('.nav-menu a');
            navLinks.forEach(link => {
                if (link.getAttribute('href') === 'shop.php') {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }

        function initShopUI() {
            // Add hover effects to product cards
            const cards = document.querySelectorAll('.product-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.borderColor = 'var(--dark)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.borderColor = 'var(--border)';
                });
            });
        }

        // Check if user is logged in (from PHP session)
        const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        const userId = <?php echo $user_id ? "'" . $user_id . "'" : 'null'; ?>;

        // Quantity Controls
        window.increaseQuantity = function(productId) {
            const input = document.getElementById(`quantity_${productId}`);
            if (!input) return;
            const max = parseInt(input.max);
            if (input.value < max) {
                input.value = parseInt(input.value) + 1;
            }
        }

        window.decreaseQuantity = function(productId) {
            const input = document.getElementById(`quantity_${productId}`);
            if (!input) return;
            if (input.value > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }

        // Add to Cart Function - Now saves to database if logged in
        window.addToCart = async function(productId) {
            const input = document.getElementById(`quantity_${productId}`);
            if (!input) return;
            
            const quantity = input.value;
            
            try {
                const formData = new FormData();
                formData.append('add_to_cart', 'true');
                formData.append('product_id', productId);
                formData.append('quantity', quantity);
                
                const response = await fetch('shop.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update cart count
                    document.getElementById('cartCount').textContent = data.cart_count;
                    
                    // Show notification
                    const notification = document.getElementById('cartNotification');
                    const message = document.getElementById('notificationMessage');
                    message.textContent = data.message || 'Product added to cart successfully!';
                    notification.classList.add('show');
                    
                    // Hide notification after 3 seconds
                    setTimeout(() => {
                        notification.classList.remove('show');
                    }, 3000);
                } else {
                    alert('Failed to add product to cart. Please try again.');
                }
            } catch (error) {
                console.error('Error adding to cart:', error);
                // Fallback to traditional form submission
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="add_to_cart" value="true">
                    <input type="hidden" name="product_id" value="${productId}">
                    <input type="hidden" name="quantity" value="${quantity}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Toggle Wishlist Function - Saves to database when logged in
        window.toggleWishlist = async function(productId) {
            // Check if user is logged in
            if (!isLoggedIn) {
                showLoginPrompt('wishlist');
                return;
            }
            
            const wishlistBtn = document.querySelector(`.wishlist-btn[data-product-id="${productId}"]`);
            const isInWishlist = wishlistBtn.classList.contains('active');
            
            try {
                const formData = new FormData();
                if (isInWishlist) {
                    formData.append('remove_from_wishlist', 'true');
                } else {
                    formData.append('add_to_wishlist', 'true');
                }
                formData.append('product_id', productId);
                
                const response = await fetch('shop.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update wishlist button
                    wishlistBtn.classList.toggle('active');
                    
                    // Update wishlist count
                    const wishlistCountEl = document.getElementById('wishlistCount');
                    if (wishlistCountEl) {
                        wishlistCountEl.textContent = data.wishlist_count;
                    } else if (data.wishlist_count > 0) {
                        // Create wishlist count badge if it doesn't exist
                        const wishlistLink = document.querySelector('.nav-icon[href="wishlist.php"]');
                        if (wishlistLink) {
                            const badge = document.createElement('span');
                            badge.className = 'wishlist-count-badge';
                            badge.id = 'wishlistCount';
                            badge.textContent = data.wishlist_count;
                            wishlistLink.appendChild(badge);
                        }
                    }
                    
                    // Show notification
                    const notification = document.getElementById('wishlistNotification');
                    const title = document.getElementById('wishlistNotificationTitle');
                    const message = document.getElementById('wishlistNotificationMessage');
                    
                    if (isInWishlist) {
                        title.textContent = 'Removed from Wishlist';
                        message.textContent = 'Product has been removed from your wishlist';
                    } else {
                        title.textContent = 'Added to Wishlist';
                        message.textContent = 'Product has been added to your wishlist';
                    }
                    
                    notification.classList.add('show');
                    
                    // Hide notification after 3 seconds
                    setTimeout(() => {
                        notification.classList.remove('show');
                    }, 3000);
                    
                    // Update button title
                    wishlistBtn.title = isInWishlist ? 'Add to Wishlist' : 'Remove from Wishlist';
                    
                } else {
                    if (data.requires_login) {
                        showLoginPrompt('wishlist');
                    } else {
                        alert(data.message || 'Failed to update wishlist. Please try again.');
                    }
                }
            } catch (error) {
                console.error('Error updating wishlist:', error);
                alert('Unable to update wishlist. Please try again.');
            }
        }

        // Login Prompt Functions
        function showLoginPrompt(action = 'wishlist') {
            const modal = document.getElementById('loginPromptModal');
            const notification = document.getElementById('loginNotification');
            const message = document.getElementById('loginNotificationMessage');
            
            // Show modal
            modal.classList.add('active');
            
            // Also show notification
            message.textContent = `Please login to ${action === 'wishlist' ? 'add items to wishlist' : 'perform this action'}`;
            notification.classList.add('show');
            
            // Hide notification after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
            }, 5000);
        }

        function closeLoginPrompt() {
            document.getElementById('loginPromptModal').classList.remove('active');
        }

        function redirectToLogin() {
            // Store current URL to redirect back after login
            sessionStorage.setItem('redirectAfterLogin', window.location.href);
            window.location.href = 'login_register.php';
        }

        // Quick View Function
        window.quickView = async function(productId) {
            try {
                const response = await fetch(`get_product.php?id=${productId}`);
                const product = await response.json();
                
                if (product.error) {
                    alert(product.error);
                    return;
                }
                
                // Populate modal
                document.getElementById('modalProductName').textContent = product.name;
                
                const modalBody = document.getElementById('modalBody');
                modalBody.innerHTML = `
                    <div>
                        <img src="${product.image_url || '../../resources/images/product-placeholder.jpg'}" 
                             alt="${product.name}" 
                             class="modal-image">
                    </div>
                    <div class="modal-details">
                        <div class="modal-price">₱${parseFloat(product.price).toFixed(2)}</div>
                        
                        <span class="modal-stock ${product.stock_quantity > 10 ? 'stock-in' : 'stock-low'}">
                            <i class="fas ${product.stock_quantity > 0 ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                            ${product.stock_quantity > 0 ? `${product.stock_quantity} in stock` : 'Out of stock'}
                        </span>
                        
                        <p class="modal-description">${product.description || 'No description available.'}</p>
                        
                        <table class="specs-table">
                            <tr>
                                <td>Brand:</td>
                                <td>${product.brand || 'Not specified'}</td>
                            </tr>
                            <tr>
                                <td>Category:</td>
                                <td>${product.category_name || 'Uncategorized'}</td>
                            </tr>
                            <tr>
                                <td>SKU:</td>
                                <td>#${product.id.toString().padStart(6, '0')}</td>
                            </tr>
                        </table>
                        
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="modalDecreaseQuantity()">-</button>
                            <input type="number" id="modalQuantity" class="quantity-input" value="1" min="1" max="${product.stock_quantity}">
                            <button class="quantity-btn" onclick="modalIncreaseQuantity()">+</button>
                        </div>
                        
                        <div class="product-actions" style="margin-top: 1rem;">
                            <button class="btn btn-primary" onclick="addToCartFromModal(${product.id})">
                                <i class="fas fa-cart-plus"></i> Add to Cart
                            </button>
                            <button class="btn btn-secondary" onclick="closeModal()">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>
                    </div>
                `;
                
                // Show modal
                document.getElementById('quickViewModal').classList.add('active');
            } catch (error) {
                console.error('Error loading product:', error);
                alert('Unable to load product details. Please try again.');
            }
        }

        // Modal quantity controls
        function modalIncreaseQuantity() {
            const input = document.getElementById('modalQuantity');
            const max = parseInt(input.max);
            if (input.value < max) {
                input.value = parseInt(input.value) + 1;
            }
        }

        function modalDecreaseQuantity() {
            const input = document.getElementById('modalQuantity');
            if (input.value > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }

        // Add to cart from modal
        function addToCartFromModal(productId) {
            const quantity = document.getElementById('modalQuantity').value;
            addToCart(productId);
            closeModal();
        }

        // Close modal
        function closeModal() {
            document.getElementById('quickViewModal').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('quickViewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close login prompt when clicking outside
        document.getElementById('loginPromptModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLoginPrompt();
            }
        });

        // Live Search Functionality
        const liveSearch = document.getElementById('liveSearch');
        const clearSearchBtn = document.getElementById('clearSearch');
        const productsGrid = document.getElementById('productsGrid');
        const resultsCount = document.getElementById('resultsCount');
        let searchTimeout;

        // Show/hide clear button
        liveSearch.addEventListener('input', function() {
            clearSearchBtn.style.display = this.value ? 'block' : 'none';
        });

        // Clear search input
        clearSearchBtn.addEventListener('click', function() {
            liveSearch.value = '';
            this.style.display = 'none';
            performLiveSearch();
        });

        // Live search with debouncing
        liveSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performLiveSearch();
            }, 300);
        });

        // Simple search function
        function matchesSearch(searchTerm, productText) {
            if (!searchTerm) return true;
            
            const searchLower = searchTerm.toLowerCase();
            const productLower = productText.toLowerCase();
            
            let productIndex = 0;
            for (let i = 0; i < searchLower.length; i++) {
                const char = searchLower[i];
                const foundIndex = productLower.indexOf(char, productIndex);
                if (foundIndex === -1) return false;
                productIndex = foundIndex + 1;
            }
            return true;
        }

        function performLiveSearch() {
            const searchTerm = liveSearch.value.trim();
            
            if (searchTerm === '') {
                productsGrid.innerHTML = window.allProductsHTML;
                updateResultsCount(window.productCards.length);
                return;
            }
            
            let visibleCount = 0;
            let filteredHTML = '';
            
            window.productCards.forEach(card => {
                const searchText = card.dataset.searchText || '';
                
                if (matchesSearch(searchTerm, searchText)) {
                    filteredHTML += card.outerHTML;
                    visibleCount++;
                }
            });

            if (visibleCount === 0) {
                productsGrid.innerHTML = `
                    <div class="no-results">
                        <i class="fas fa-search" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                        <h3>No products found</h3>
                        <p>No products match "${searchTerm}"</p>
                        <p>Try different search terms or clear your search</p>
                    </div>
                `;
            } else {
                productsGrid.innerHTML = filteredHTML;
                // Reattach wishlist button event listeners
                reattachWishlistListeners();
            }
            
            updateResultsCount(visibleCount);
        }

        function reattachWishlistListeners() {
            document.querySelectorAll('.wishlist-btn').forEach(btn => {
                const productId = btn.getAttribute('data-product-id');
                btn.onclick = () => toggleWishlist(productId);
            });
        }

        function updateResultsCount(visibleCount = null) {
            if (visibleCount !== null) {
                resultsCount.textContent = `Showing ${visibleCount} of ${window.productCards.length} products`;
            } else {
                resultsCount.textContent = `Showing ${window.productCards.length} of ${window.productCards.length} products`;
            }
        }

        // Filter form auto-submit for price range
        const priceInputs = document.querySelectorAll('.price-input');
        let priceTimeout;
        
        priceInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(priceTimeout);
                priceTimeout = setTimeout(() => {
                    this.form.submit();
                }, 1000);
            });
        });
    </script>
</body>
</html>