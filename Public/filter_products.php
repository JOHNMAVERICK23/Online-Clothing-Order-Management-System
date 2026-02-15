<?php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'clothing_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
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

// Get total count without filters for comparison
$total_stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE is_active = TRUE");
$total = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'products' => $products,
    'total' => $total,
    'count' => count($products)
]);
?>