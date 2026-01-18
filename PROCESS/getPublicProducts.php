<?php
// PROCESS/getPublicProducts.php
// API endpoint for fetching products for the public store

session_start();
header('Content-Type: application/json');

require_once 'db_config.php';

try {
    // Get parameters
    $search = trim($_GET['search'] ?? '');
    $category_id = intval($_GET['category_id'] ?? 0);
    $min_price = floatval($_GET['min_price'] ?? 0);
    $max_price = floatval($_GET['max_price'] ?? 999999);
    $sort = $_GET['sort'] ?? 'newest'; // newest, price_low, price_high, popular
    $limit = intval($_GET['limit'] ?? 20);
    $offset = intval($_GET['offset'] ?? 0);

    // Build query
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.is_active = TRUE";

    $params = [];

    // Add search filter
    if (!empty($search)) {
        $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Add category filter
    if ($category_id > 0) {
        $query .= " AND p.category_id = ?";
        $params[] = $category_id;
    }

    // Add price filter
    $query .= " AND p.price BETWEEN ? AND ?";
    $params[] = $min_price;
    $params[] = $max_price;

    // Add sorting
    switch ($sort) {
        case 'price_low':
            $query .= " ORDER BY p.price ASC";
            break;
        case 'price_high':
            $query .= " ORDER BY p.price DESC";
            break;
        case 'popular':
            $query .= " ORDER BY p.stock_quantity DESC";
            break;
        default: // newest
            $query .= " ORDER BY p.created_at DESC";
    }

    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // Prepare and execute
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters dynamically
    if (!empty($params)) {
        $types = str_repeat('s', count($params) - 2) . 'ii'; // Last two are integers (limit, offset)
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => floatval($row['price']),
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'brand' => $row['brand'],
            'stock_quantity' => intval($row['stock_quantity']),
            'image_url' => $row['image_url'],
            'created_at' => $row['created_at']
        ];
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM products p 
                   WHERE p.is_active = TRUE";
    
    if (!empty($search)) {
        $count_query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    }
    if ($category_id > 0) {
        $count_query .= " AND p.category_id = ?";
    }
    $count_query .= " AND p.price BETWEEN ? AND ?";

    $count_stmt = $conn->prepare($count_query);
    
    if (!empty($params)) {
        // Rebuild params for count query (without limit and offset)
        $count_params = [];
        if (!empty($search)) {
            $count_params[] = "%$search%";
            $count_params[] = "%$search%";
        }
        if ($category_id > 0) {
            $count_params[] = $category_id;
        }
        $count_params[] = $min_price;
        $count_params[] = $max_price;
        
        $types = str_repeat('s', max(0, count($count_params) - 2)) . 'dd';
        if (!empty($count_params)) {
            $count_stmt->bind_param($types, ...$count_params);
        }
    }

    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total = $count_result->fetch_assoc()['total'];

    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => $total,
        'count' => count($products),
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => ($offset + count($products)) < $total
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching products: ' . $e->getMessage()
    ]);
}

$conn->close();
?>