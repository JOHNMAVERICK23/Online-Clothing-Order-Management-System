<?php
session_start();

// Initialize session variables if not exists
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['wishlist'])) $_SESSION['wishlist'] = [];

// User session variables
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_type = $_SESSION['user_type'] ?? 'guest';
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$customer_id = $_SESSION['customer_id'] ?? null;

// Database connection
$host = 'localhost';
$dbname = 'clothing_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Handle add to cart (AJAX or regular)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']) ?: 1;
    
    // Validate product exists and has stock
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Product not found!'
            ]);
            exit;
        }
    }
    
    // Check stock
    if ($product['quantity'] < $quantity) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Not enough stock available!'
            ]);
            exit;
        }
    }
    
    // Add to cart
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
    
    // Save to database if logged in
    if ($is_logged_in && $customer_id) {
        saveCartToDatabase($pdo, $customer_id, $_SESSION['cart']);
    }
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'cart_count' => count($_SESSION['cart']),
            'message' => 'Product added to cart!'
        ]);
        exit;
    } else {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle add to wishlist (AJAX or regular)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    if (!$is_logged_in) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'requires_login' => true,
                'message' => 'Please login to add items to wishlist!'
            ]);
            exit;
        } else {
            $_SESSION['redirect_after_login'] = $_SERVER['PHP_SELF'];
            header('Location: login_register.php');
            exit;
        }
    }
    
    $product_id = intval($_POST['product_id']);
    
    // Validate product exists
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Product not found!'
            ]);
            exit;
        }
    }
    
    if (!in_array($product_id, $_SESSION['wishlist'])) {
        $_SESSION['wishlist'][] = $product_id;
        $wishlist_added = true;
        $wishlist_message = 'Added to wishlist!';
        
        if ($customer_id) {
            saveWishlistToDatabase($pdo, $customer_id, $_SESSION['wishlist']);
        }
    } else {
        $wishlist_added = false;
        $wishlist_message = 'Already in wishlist!';
    }
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $wishlist_added,
            'wishlist_count' => count($_SESSION['wishlist']),
            'message' => $wishlist_message
        ]);
        exit;
    }
}

// Handle remove from wishlist (AJAX or regular)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_wishlist'])) {
    if (!$is_logged_in) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'requires_login' => true,
                'message' => 'Please login to manage wishlist!'
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
        $wishlist_message = 'Removed from wishlist!';
        
        if ($customer_id) {
            saveWishlistToDatabase($pdo, $customer_id, $_SESSION['wishlist']);
        }
    } else {
        $wishlist_removed = false;
        $wishlist_message = 'Not found in wishlist!';
    }
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $wishlist_removed,
            'wishlist_count' => count($_SESSION['wishlist']),
            'message' => $wishlist_message
        ]);
        exit;
    }
}

// Get filter parameters (only if not AJAX request)
if (!$is_ajax) {
    $category_filter = $_GET['category'] ?? 'all';
    $size_filter = $_GET['size'] ?? 'all';
    $color_filter = $_GET['color'] ?? 'all';
    $price_min = $_GET['price_min'] ?? '';
    $price_max = $_GET['price_max'] ?? '';
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'newest';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $items_per_page = 25;

    // Build base query for counting and fetching
    $query_base = "SELECT p.* FROM products p WHERE p.status = 'active'";

    $params = [];
    $query_conditions = [];

    if ($category_filter !== 'all') {
        $query_conditions[] = "p.category = ?";
        $params[] = $category_filter;
    }

    if ($size_filter !== 'all') {
        $query_conditions[] = "p.size = ?";
        $params[] = $size_filter;
    }

    if ($color_filter !== 'all') {
        $query_conditions[] = "p.color = ?";
        $params[] = $color_filter;
    }

    if ($price_min !== '') {
        $query_conditions[] = "p.price >= ?";
        $params[] = floatval($price_min);
    }

    if ($price_max !== '') {
        $query_conditions[] = "p.price <= ?";
        $params[] = floatval($price_max);
    }

    if ($search !== '') {
        $query_conditions[] = "(p.product_name LIKE ? OR p.description LIKE ? OR p.category LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Add conditions to query
    if (!empty($query_conditions)) {
        $query_base .= " AND " . implode(" AND ", $query_conditions);
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM ($query_base) AS count_table";
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_products = $stmt->fetchColumn();

    // Calculate pagination
    $total_pages = ceil($total_products / $items_per_page);
    $offset = ($page - 1) * $items_per_page;

    // Add sorting
    switch ($sort) {
        case 'price_low': $order_by = "ORDER BY p.price ASC"; break;
        case 'price_high': $order_by = "ORDER BY p.price DESC"; break;
        case 'name': $order_by = "ORDER BY p.product_name ASC"; break;
        default: $order_by = "ORDER BY p.created_at DESC"; break;
    }

    // Fetch products with pagination
    $query = "$query_base $order_by LIMIT ? OFFSET ?";
    $params_with_limit = array_merge($params, [$items_per_page, $offset]);

    $stmt = $pdo->prepare($query);
    $param_index = 1;
    foreach ($params_with_limit as $value) {
        if (is_int($value)) {
            $stmt->bindValue($param_index, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($param_index, $value);
        }
        $param_index++;
    }
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for filter
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get sizes for filter
    $stmt = $pdo->query("SELECT DISTINCT size FROM products WHERE size IS NOT NULL AND size != '' ORDER BY size");
    $sizes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get colors for filter
    $stmt = $pdo->query("SELECT DISTINCT color FROM products WHERE color IS NOT NULL AND color != '' ORDER BY color");
    $colors = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Helper function to save cart to database
function saveCartToDatabase($pdo, $customer_id, $cart) {
    if (!$customer_id) return false;
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Clear existing cart
        $stmt = $pdo->prepare("DELETE FROM customer_carts WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        
        // Insert new cart items
        if (!empty($cart)) {
            $sql = "INSERT INTO customer_carts (customer_id, product_id, quantity) VALUES ";
            $values = [];
            $params = [];
            
            foreach ($cart as $item) {
                $values[] = "(?, ?, ?)";
                $params[] = $customer_id;
                $params[] = $item['product_id'];
                $params[] = $item['quantity'];
            }
            
            $sql .= implode(', ', $values);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error saving cart to database: " . $e->getMessage());
        return false;
    }
}

// Helper function to save wishlist to database
function saveWishlistToDatabase($pdo, $customer_id, $wishlist) {
    if (!$customer_id) return false;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM customer_wishlists WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        
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

// Handle logout (only if not AJAX)
if (isset($_GET['logout']) && !$is_ajax) {
    if ($is_logged_in && $customer_id && !empty($_SESSION['cart'])) {
        saveCartToDatabase($pdo, $customer_id, $_SESSION['cart']);
    }
    
    session_destroy();
    header('Location: login_register.php');
    exit;
}

// If this is an AJAX request, we should have already exited above
// Only continue with HTML if it's not an AJAX request
if ($is_ajax) {
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - Alas Clothing Shop</title>
    
    <!-- External CSS Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    
    <!-- Your CSS File -->
    <link rel="stylesheet" href="css/main.css">
    
    <style>
        /* ===== SHOP PAGE SPECIFIC STYLES ===== */
        .main-content {
            padding-top: 6rem; /* Height of navbar */
            min-height: calc(100vh - 300px); /* Adjust based on footer height */
        }

        .shop-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 1rem;
            font-weight: 400;
        }

        /* Shop Controls */
        .shop-controls {
            background: var(--light);
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 3rem;
            border: 1px solid var(--border);
        }

        /* Search Bar */
        .search-container {
            position: relative;
            margin-bottom: 2rem;
        }

        .search-box {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--light);
            color: var(--dark);
            transition: all 0.3s;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 1rem;
        }

        .clear-search {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 1rem;
            padding: 0.5rem;
            display: none;
        }

        .clear-search:hover {
            color: var(--danger);
        }

        /* Filter Actions */
        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .filter-btn {
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-apply {
            background: var(--dark);
            color: var(--light);
            border: 1px solid var(--dark);
        }

        .btn-apply:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-reset {
            background: var(--light);
            color: var(--dark);
            border: 1px solid var(--border);
        }

        .btn-reset:hover {
            background: var(--gray-light);
            border-color: var(--dark);
        }

        /* Filters */
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            position: relative;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.85rem;
        }

        .filter-select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            background: var(--light);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--dark);
            cursor: pointer;
            transition: all 0.3s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' class='bi bi-chevron-down' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .price-range {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .price-input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            background: var(--light);
            border-radius: 8px;
            text-align: center;
            color: var(--dark);
            transition: border 0.3s;
            font-size: 0.95rem;
        }

        .price-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Loading Animation */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid var(--border);
            border-top: 4px solid var(--dark);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1.5rem;
        }

        .loading-text {
            color: var(--dark);
            font-size: 1.1rem;
            font-weight: 500;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Sort & Results */
        .sort-results {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .results-count {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 400;
        }

        /* Products Grid - 5x5 on desktop */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        /* Product Card */
        .product-card {
            background: var(--light);
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            border-color: var(--dark);
        }

        .product-image-container {
            position: relative;
            width: 100%;
            height: 280px;
            overflow: hidden;
            background: var(--gray-light);
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--light);
            color: var(--dark);
            padding: 0.4rem 1rem;
            border: 1px solid var(--dark);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border-radius: 4px;
            z-index: 1;
        }

        /* Wishlist Button */
        .wishlist-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 40px;
            height: 40px;
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
            font-size: 1.1rem;
        }

        .wishlist-btn:hover {
            background: var(--light);
            border-color: var(--danger);
            color: var(--danger);
            transform: scale(1.1);
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
            opacity: 0.6;
            cursor: not-allowed;
        }

        .wishlist-btn.disabled:hover {
            background: var(--light);
            border-color: var(--border);
            color: var(--dark);
            transform: none;
        }

        /* Product Content */
        .product-content {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .product-category {
            color: var(--gray);
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .product-title {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark);
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.8rem;
        }

        /* Product Size and Color */
        .product-extra {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
        }

        .product-size, .product-color {
            background: var(--gray-light);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            color: var(--gray);
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }

        .product-stock {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            color: var(--gray);
        }

        .stock-available { color: var(--success); }
        .stock-low { color: var(--warning); }
        .stock-out { color: var(--gray); }

        /* Product Actions - Single Row */
        .product-actions {
            margin-top: auto;
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .btn-cart {
            flex: 1;
            background: var(--dark);
            color: var(--light);
            border: 1px solid var(--dark);
            padding: 0.8rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .btn-cart:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-cart:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-view {
            width: 40px;
            height: 40px;
            background: var(--light);
            color: var(--dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 1rem;
        }

        .btn-view:hover {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 4rem;
            flex-wrap: wrap;
        }

        .page-btn {
            min-width: 40px;
            height: 40px;
            padding: 0 0.75rem;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
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

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .page-dots {
            color: var(--gray);
            padding: 0 0.5rem;
        }

        /* No Results */
        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--border);
        }

        .no-results h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .no-results p {
            color: var(--gray);
            margin-bottom: 0.25rem;
        }

        /* Notifications */
        .notification {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--light);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            z-index: 1000;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s;
            max-width: 400px;
        }

        .notification.show {
            transform: translateX(0);
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
            flex-shrink: 0;
        }

        .notification-content {
            flex: 1;
        }

        .notification-content h4 {
            color: var(--dark);
            margin-bottom: 0.2rem;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .notification-content p {
            color: var(--gray);
            font-size: 0.85rem;
            font-weight: 400;
        }

        .notification-close {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 1rem;
            padding: 0.25rem;
            transition: color 0.3s;
        }

        .notification-close:hover {
            color: var(--dark);
        }

        /* Login Modal */
        .login-modal {
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

        .login-modal.active {
            display: flex;
        }

        .login-content {
            background: var(--light);
            width: 100%;
            max-width: 400px;
            border-radius: 8px;
            animation: modalSlideIn 0.3s ease;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .login-header {
            padding: 2rem 2rem 1.5rem;
            text-align: center;
            background: var(--gray-light);
        }

        .login-header i {
            font-size: 3rem;
            color: var(--danger);
            margin-bottom: 1rem;
        }

        .login-header h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .login-header p {
            color: var(--gray);
        }

        .login-body {
            padding: 2rem;
        }

        .login-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .login-buttons .btn {
            flex: 1;
            padding: 1rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            font-size: 0.95rem;
            border: none;
        }

        .btn-primary {
            background: var(--dark);
            color: var(--light);
            border: 1px solid var(--dark);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--gray-light);
            border-color: var(--dark);
        }

        /* Animations */
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design - FIXED FOR MOBILE */
        @media (max-width: 1400px) {
            .products-grid {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .filters-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .products-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .filters-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 992px) {
            .shop-container {
                padding: 1.5rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr); /* 2 products per row on tablets */
                gap: 1.25rem;
            }
            
            .filters-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.25rem;
            }
            
            .product-image-container {
                height: 240px;
            }
            
            .notification {
                left: 1rem;
                right: 1rem;
                max-width: none;
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .filters-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .filter-actions button {
                width: 100%;
            }
            
            .sort-results {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .product-title {
                font-size: 0.95rem;
            }
            
            .product-price {
                font-size: 1.1rem;
            }
            
            .page-btn {
                min-width: 36px;
                height: 36px;
                font-size: 0.9rem;
                padding: 0 0.5rem;
            }
            
            .shop-controls {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            /* Mobile: 2 products per row - FIXED */
            .products-grid {
                grid-template-columns: repeat(2, 1fr); /* Changed from 1fr to 2fr */
                gap: 1rem;
                max-width: none; /* Remove max-width restriction */
                margin-left: 0;
                margin-right: 0;
            }
            
            .product-card {
                margin-bottom: 0;
            }
            
            .product-image-container {
                height: 180px; /* Smaller height for mobile */
            }
            
            .product-content {
                padding: 1rem;
            }
            
            .product-title {
                font-size: 0.9rem;
                min-height: 2.5rem;
            }
            
            .product-price {
                font-size: 1rem;
            }
            
            .product-actions {
                flex-direction: row; /* Keep buttons in row on mobile */
                gap: 0.5rem;
            }
            
            .btn-cart {
                padding: 0.7rem;
                font-size: 0.85rem;
            }
            
            .btn-view {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            
            .login-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .shop-container {
                padding: 1rem;
            }
            
            .page-header h1 {
                font-size: 1.75rem;
            }
            
            .product-image-container {
                height: 160px;
            }
            
            .products-grid {
                gap: 0.75rem;
            }
            
            .product-content {
                padding: 0.75rem;
            }
            
            .product-extra {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 360px) {
            /* For very small screens, show 1 product per row */
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .product-image-container {
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading products...</div>
    </div>

    <!-- ========== NAVBAR ========== -->
    <nav class="customer-nav" id="customerNav">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="../index.php" class="nav-logo">
            <img src="images/logo.jpg" class="logo-image">
            <span class="brand-name">Alas Clothing Shop</span>
        </a>
        
        <!-- Mobile Menu -->
        <ul class="nav-menu" id="navMenu">
            <li><a href="../index.php">HOME</a></li>
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

    <!-- ========== MAIN CONTENT ========== -->
    <div class="main-content">
        <div class="shop-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Discover Our Collection</h1>
                <p class="page-subtitle">Premium clothing and accessories for every occasion</p>
            </div>

            <!-- Search & Filters -->
            <div class="shop-controls">
                <!-- Search -->
                <div class="search-container">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" 
                           id="searchInput" 
                           class="search-box" 
                           placeholder="Search products by name, description, or category..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           onkeyup="handleSearch(event)">
                    <button class="clear-search" id="clearSearch" title="Clear search" onclick="clearSearch()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Filter Actions -->
                <div class="filter-actions">
                    <button type="button" class="filter-btn btn-reset" onclick="resetFilters()">
                        <i class="fas fa-redo"></i>
                        Reset Filters
                    </button>
                    <button type="button" class="filter-btn btn-apply" onclick="applyFilters()">
                        <i class="fas fa-filter"></i>
                        Apply Filters
                    </button>
                </div>

                <!-- Filters -->
                <form method="GET" id="filterForm">
                    <input type="hidden" name="search" id="hiddenSearch" value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="page" value="1">
                    
                    <div class="filters-grid">
                        <!-- Category Filter -->
                        <div class="filter-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" class="filter-select">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" 
                                        <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Size Filter -->
                        <div class="filter-group">
                            <label for="size">Size</label>
                            <select id="size" name="size" class="filter-select">
                                <option value="all">All Sizes</option>
                                <?php foreach ($sizes as $size): ?>
                                    <option value="<?php echo htmlspecialchars($size); ?>" 
                                        <?php echo $size_filter === $size ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($size); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Color Filter -->
                        <div class="filter-group">
                            <label for="color">Color</label>
                            <select id="color" name="color" class="filter-select">
                                <option value="all">All Colors</option>
                                <?php foreach ($colors as $color): ?>
                                    <option value="<?php echo htmlspecialchars($color); ?>" 
                                        <?php echo $color_filter === $color ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($color); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Price Range -->
                        <div class="filter-group">
                            <label for="price_range">Price Range</label>
                            <div class="price-range">
                                <input type="number" 
                                       name="price_min" 
                                       class="price-input" 
                                       placeholder="Min" 
                                       value="<?php echo htmlspecialchars($price_min); ?>"
                                       min="0">
                                <span>to</span>
                                <input type="number" 
                                       name="price_max" 
                                       class="price-input" 
                                       placeholder="Max" 
                                       value="<?php echo htmlspecialchars($price_max); ?>"
                                       min="0">
                            </div>
                        </div>

                        <!-- Sort -->
                        <div class="filter-group">
                            <label for="sort">Sort By</label>
                            <select id="sort" name="sort" class="filter-select">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                            </select>
                        </div>
                    </div>

                    <!-- Results & Sort Info -->
                    <div class="sort-results">
                        <div class="results-count" id="resultsCount">
                            Showing <?php echo ($page - 1) * $items_per_page + 1; ?>-<?php echo min($page * $items_per_page, $total_products); ?> of <?php echo $total_products; ?> products
                        </div>
                    </div>
                </form>
            </div>

            <!-- Products Grid -->
            <div class="products-grid" id="productsGrid">
                <?php if (empty($products)): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h3>No products found</h3>
                        <p>Try adjusting your search or filter criteria</p>
                        <button class="filter-btn btn-reset" onclick="resetFilters()" style="margin-top: 1rem;">
                            <i class="fas fa-redo"></i>
                            Reset All Filters
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): 
                        $in_wishlist = $is_logged_in && in_array($product['id'], $_SESSION['wishlist']);
                    ?>
                    <div class="product-card">
                        <?php if ($product['quantity'] < 10 && $product['quantity'] > 0): ?>
                            <span class="product-badge">Low Stock</span>
                        <?php elseif ($product['quantity'] == 0): ?>
                            <span class="product-badge" style="border-color: var(--gray); color: var(--gray);">Out of Stock</span>
                        <?php endif; ?>
                        
                        <!-- Wishlist Button -->
                        <button class="wishlist-btn <?php echo $in_wishlist ? 'active' : ''; ?> <?php echo !$is_logged_in ? 'disabled' : ''; ?>" 
                                data-product-id="<?php echo $product['id']; ?>"
                                onclick="toggleWishlist(<?php echo $product['id']; ?>)"
                                title="<?php echo !$is_logged_in ? 'Login to add to wishlist' : ($in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'); ?>">
                            <i class="fas fa-heart"></i>
                        </button>
                        
                        <div class="product-image-container">
                            <img src="<?php echo htmlspecialchars($product['image_url'] ?: '../../resources/images/product-placeholder.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
                                 class="product-image">
                        </div>
                        
                        <div class="product-content">
                            <div class="product-category">
                                <?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?>
                            </div>
                            
                            <h3 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                            
                            <!-- Display size and color if available -->
                            <?php if (!empty($product['size']) || !empty($product['color'])): ?>
                            <div class="product-extra">
                                <?php if (!empty($product['size'])): ?>
                                    <span class="product-size">Size: <?php echo htmlspecialchars($product['size']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($product['color'])): ?>
                                    <span class="product-color">Color: <?php echo htmlspecialchars($product['color']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            
                            <div class="product-stock">
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
                            
                            <div class="product-actions">
                                <button class="btn-cart" 
                                        onclick="addToCart(<?php echo $product['id']; ?>)" 
                                        <?php echo $product['quantity'] == 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-cart-plus"></i>
                                    Add to Cart
                                </button>
                                <a href="view_info.php?id=<?php echo $product['id']; ?>" class="btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination" id="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn" title="First">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn" title="Previous">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled">
                        <i class="fas fa-angle-double-left"></i>
                    </span>
                    <span class="page-btn disabled">
                        <i class="fas fa-angle-left"></i>
                    </span>
                <?php endif; ?>

                <?php
                // Show page numbers
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo '<span class="page-dots">...</span>';
                }
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <span class="page-dots">...</span>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn" title="Next">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn" title="Last">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php else: ?>
                    <span class="page-btn disabled">
                        <i class="fas fa-angle-right"></i>
                    </span>
                    <span class="page-btn disabled">
                        <i class="fas fa-angle-double-right"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications -->
    <div class="notification" id="cartNotification">
        <div class="notification-icon">
            <i class="fas fa-check"></i>
        </div>
        <div class="notification-content">
            <h4>Added to Cart!</h4>
            <p id="notificationMessage">Product has been added to your cart</p>
        </div>
        <button class="notification-close" onclick="hideNotification('cartNotification')">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="notification" id="wishlistNotification">
        <div class="notification-icon">
            <i class="fas fa-heart"></i>
        </div>
        <div class="notification-content">
            <h4 id="wishlistNotificationTitle">Added to Wishlist!</h4>
            <p id="wishlistNotificationMessage">Product has been added to your wishlist</p>
        </div>
        <button class="notification-close" onclick="hideNotification('wishlistNotification')">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Login Modal -->
    <div class="login-modal" id="loginModal">
        <div class="login-content">
            <div class="login-header">
                <i class="fas fa-lock"></i>
                <h3>Login Required</h3>
                <p>Please login to continue</p>
            </div>
            <div class="login-body">
                <p>You need to be logged in to add items to your wishlist.</p>
                <div class="login-buttons">
                    <button class="btn btn-primary" onclick="redirectToLogin()">
                        <i class="fas fa-sign-in-alt"></i> Login Now
                    </button>
                    <button class="btn btn-secondary" onclick="closeLoginModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

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
                    <li><a href="../index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
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
            <p>Designed with <i class="fas fa-heart" style="color: var(--danger);"></i> for fashion enthusiasts</p>
        </div>
    </footer>

    <!-- ========== JAVASCRIPT ========== -->
    <script>
        // User session state
        const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        const customerId = <?php echo $customer_id ? "'" . $customer_id . "'" : 'null'; ?>;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize mobile menu
            initMobileMenu();
            
            // Initialize scroll effect for navbar
            initScrollEffect();
            
            // Setup clear search button
            const searchInput = document.getElementById('searchInput');
            const clearSearchBtn = document.getElementById('clearSearch');
            
            if (searchInput.value) {
                clearSearchBtn.style.display = 'block';
            }
        });

        // Loading overlay functions
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.add('active');
                // Prevent scrolling
                document.body.style.overflow = 'hidden';
            }
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.classList.remove('active');
                // Restore scrolling
                document.body.style.overflow = '';
            }
        }

        // Mobile Menu Functionality
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

        // Scroll effect for navbar (from main.js in index.php)
        function initScrollEffect() {
            let lastScrollTop = 0;
            const navbar = document.getElementById('customerNav');
            
            window.addEventListener('scroll', function() {
                let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                
                if (scrollTop > lastScrollTop && scrollTop > 100) {
                    // Scrolling down
                    navbar.classList.add('nav-hidden');
                } else {
                    // Scrolling up
                    navbar.classList.remove('nav-hidden');
                }
                lastScrollTop = scrollTop;
            });
        }

        // Search functionality
        function handleSearch(event) {
            const searchInput = document.getElementById('searchInput');
            const clearSearchBtn = document.getElementById('clearSearch');
            
            if (event.key === 'Enter') {
                applySearch();
            } else {
                clearSearchBtn.style.display = searchInput.value ? 'block' : 'none';
            }
        }

        function applySearch() {
            const searchInput = document.getElementById('searchInput');
            const hiddenSearch = document.getElementById('hiddenSearch');
            
            hiddenSearch.value = searchInput.value;
            applyFilters();
        }

        function clearSearch() {
            const searchInput = document.getElementById('searchInput');
            const clearSearchBtn = document.getElementById('clearSearch');
            
            searchInput.value = '';
            clearSearchBtn.style.display = 'none';
            applySearch();
        }

        // Filter functionality with loading animation
        function applyFilters() {
            showLoading();
            
            const filterForm = document.getElementById('filterForm');
            const searchInput = document.getElementById('searchInput');
            const hiddenSearch = document.getElementById('hiddenSearch');
            
            // Sync search input with hidden input
            hiddenSearch.value = searchInput.value;
            
            // Set page to 1 when applying filters
            filterForm.querySelector('[name="page"]').value = 1;
            
            // Submit form after a small delay to show loading animation
            setTimeout(() => {
                filterForm.submit();
            }, 300);
        }

        function resetFilters() {
            showLoading();
            
            // Reset all filter inputs
            document.getElementById('category').value = 'all';
            document.getElementById('size').value = 'all';
            document.getElementById('color').value = 'all';
            document.getElementById('sort').value = 'newest';
            
            const priceInputs = document.querySelectorAll('.price-input');
            priceInputs.forEach(input => input.value = '');
            
            const searchInput = document.getElementById('searchInput');
            searchInput.value = '';
            
            const clearSearchBtn = document.getElementById('clearSearch');
            clearSearchBtn.style.display = 'none';
            
            // Reset hidden search
            document.getElementById('hiddenSearch').value = '';
            
            // Submit form after a small delay
            setTimeout(() => {
                const filterForm = document.getElementById('filterForm');
                filterForm.querySelector('[name="page"]').value = 1;
                filterForm.submit();
            }, 300);
        }

        // Add to Cart
        async function addToCart(productId) {
            try {
                const formData = new FormData();
                formData.append('add_to_cart', 'true');
                formData.append('product_id', productId);
                formData.append('quantity', 1);
                
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
                    const cartCountEl = document.getElementById('cartCount');
                    if (cartCountEl) {
                        cartCountEl.textContent = data.cart_count;
                    }
                    
                    // Show notification
                    showNotification('cartNotification', data.message || 'Product added to cart!');
                } else {
                    // Show error message
                    alert(data.message || 'Failed to add product to cart. Please try again.');
                }
            } catch (error) {
                console.error('Error adding to cart:', error);
                alert('Unable to add to cart. Please try again.');
            }
        }

        // Toggle Wishlist
        async function toggleWishlist(productId) {
            if (!isLoggedIn) {
                showLoginModal();
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
                    // Update button state
                    wishlistBtn.classList.toggle('active');
                    
                    // Update wishlist count
                    const wishlistCountEl = document.getElementById('wishlistCount');
                    if (wishlistCountEl) {
                        wishlistCountEl.textContent = data.wishlist_count;
                    } else if (data.wishlist_count > 0) {
                        // Create badge if it doesn't exist
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
                    const title = isInWishlist ? 'Removed from Wishlist' : 'Added to Wishlist';
                    const message = data.message;
                    showWishlistNotification(title, message);
                    
                } else {
                    if (data.requires_login) {
                        showLoginModal();
                    } else {
                        alert(data.message || 'Failed to update wishlist.');
                    }
                }
            } catch (error) {
                console.error('Error updating wishlist:', error);
                alert('Unable to update wishlist.');
            }
        }

        // Notification functions
        function showNotification(notificationId, message) {
            const notification = document.getElementById(notificationId);
            if (notification) {
                const messageEl = notification.querySelector('p');
                if (messageEl) {
                    messageEl.textContent = message;
                }
                notification.classList.add('show');
                
                // Auto-hide after 3 seconds
                setTimeout(() => {
                    hideNotification(notificationId);
                }, 3000);
            }
        }

        function showWishlistNotification(title, message) {
            const notification = document.getElementById('wishlistNotification');
            if (notification) {
                const titleEl = notification.querySelector('h4');
                const messageEl = notification.querySelector('p');
                
                if (titleEl) titleEl.textContent = title;
                if (messageEl) messageEl.textContent = message;
                
                notification.classList.add('show');
                
                // Auto-hide after 3 seconds
                setTimeout(() => {
                    hideNotification('wishlistNotification');
                }, 3000);
            }
        }

        function hideNotification(notificationId) {
            const notification = document.getElementById(notificationId);
            if (notification) {
                notification.classList.remove('show');
            }
        }

        // Login modal functions
        function showLoginModal() {
            document.getElementById('loginModal').classList.add('active');
        }

        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('active');
        }

        function redirectToLogin() {
            sessionStorage.setItem('redirectAfterLogin', window.location.href);
            window.location.href = 'login_register.php';
        }

        // Close notification when clicking close button
        document.addEventListener('click', function(event) {
            if (event.target.closest('.notification-close')) {
                const notification = event.target.closest('.notification');
                if (notification) {
                    notification.classList.remove('show');
                }
            }
        });

        // Close login modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('loginModal');
            if (event.target === modal) {
                closeLoginModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeLoginModal();
            }
        });

        // Show loading when clicking on pagination links
        document.addEventListener('click', function(event) {
            if (event.target.closest('.page-btn') && !event.target.closest('.page-btn.disabled')) {
                showLoading();
            }
            
            if (event.target.closest('.page-btn a')) {
                showLoading();
            }
        });
    </script>
</body>
</html>