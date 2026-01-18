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

// Get all products
$query = "SELECT * FROM products ORDER BY created_at DESC";
$result = $conn->query($query);

$products = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'products' => $products
]);
?>
