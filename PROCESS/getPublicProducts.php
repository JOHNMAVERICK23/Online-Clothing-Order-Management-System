<?php
require_once 'db_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow public access

try {
    // Get only active products
    $query = "SELECT * FROM products WHERE status = 'active' ORDER BY created_at DESC";
    $result = $conn->query($query);

    $products = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }

    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading products: ' . $e->getMessage(),
        'products' => []
    ]);
}

$conn->close();
?>