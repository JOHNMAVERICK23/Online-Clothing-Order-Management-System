<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    exit(json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]));
}

// Get total products count
$totalResult = $conn->query("SELECT COUNT(*) as count FROM products");
$totalProducts = $totalResult->fetch_assoc()['count'];

// Get active products count
$activeResult = $conn->query("SELECT COUNT(*) as count FROM products WHERE status = 'active'");
$activeProducts = $activeResult->fetch_assoc()['count'];

// Get low stock products (quantity <= 10)
$lowStockResult = $conn->query("SELECT COUNT(*) as count FROM products WHERE quantity <= 10 AND quantity > 0");
$lowStock = $lowStockResult->fetch_assoc()['count'];

// Get total inventory value
$valueResult = $conn->query("SELECT SUM(price * quantity) as total_value FROM products WHERE status = 'active'");
$totalValue = $valueResult->fetch_assoc()['total_value'] ?? 0;

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'totalProducts' => $totalProducts,
    'activeProducts' => $activeProducts,
    'lowStock' => $lowStock,
    'totalValue' => $totalValue
]);
?>