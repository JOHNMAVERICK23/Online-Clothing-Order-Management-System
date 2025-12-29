<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    exit(json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]));
}

$productId = $_POST['id'] ?? '';

if (empty($productId)) {
    exit(json_encode([
        'success' => false,
        'message' => 'Product ID is required'
    ]));
}

// Get product name before deletion for logging
$checkStmt = $conn->prepare("SELECT product_name FROM products WHERE id = ?");
$checkStmt->bind_param("i", $productId);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    exit(json_encode([
        'success' => false,
        'message' => 'Product not found'
    ]));
}

$product = $result->fetch_assoc();
$productName = $product['product_name'];

// Delete the product
$stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
$stmt->bind_param("i", $productId);

if ($stmt->execute()) {
    // Log activity
    $action = 'Deleted product: ' . $productName;
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logStmt->bind_param("is", $_SESSION['user_id'], $action);
    $logStmt->execute();

    exit(json_encode([
        'success' => true,
        'message' => 'Product deleted successfully'
    ]));
} else {
    exit(json_encode([
        'success' => false,
        'message' => 'Error deleting product: ' . $conn->error
    ]));
}
?>