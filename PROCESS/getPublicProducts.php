<?php
// Updated path reference for root-level structure
require_once __DIR__ . '/db_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $query = "SELECT * FROM products WHERE status = 'active' ORDER BY created_at DESC";
    $result = $conn->query($query);
    
    // DEBUG: Count results
    $count = $result->num_rows;
    
    $products = [];
    if ($count > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    
    // DEBUG: Log to error log
    error_log("getPublicProducts.php: Found $count active products");
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => $count
    ]);

} catch (Exception $e) {
    error_log("getPublicProducts.php Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error loading products: ' . $e->getMessage(),
        'products' => []
    ]);
}

$conn->close();
?>