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
$productName = trim($_POST['product_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$category = trim($_POST['category'] ?? '');
$price = $_POST['price'] ?? 0;
$quantity = $_POST['quantity'] ?? 0;
$status = $_POST['status'] ?? 'active';
$size = trim($_POST['size'] ?? '');
$color = trim($_POST['color'] ?? '');
$imageUrl = trim($_POST['image_url'] ?? '');

// Validation
if (empty($productId)) {
    exit(json_encode([
        'success' => false,
        'message' => 'Product ID is required'
    ]));
}

if (empty($productName) || empty($category) || empty($price) || empty($quantity)) {
    exit(json_encode([
        'success' => false,
        'message' => 'All required fields must be filled'
    ]));
}

if (!is_numeric($price) || $price <= 0) {
    exit(json_encode([
        'success' => false,
        'message' => 'Price must be a positive number'
    ]));
}

if (!is_numeric($quantity) || $quantity < 0) {
    exit(json_encode([
        'success' => false,
        'message' => 'Quantity must be 0 or greater'
    ]));
}

if (!in_array($status, ['active', 'inactive'])) {
    exit(json_encode([
        'success' => false,
        'message' => 'Invalid status'
    ]));
}

// Check if product exists
$checkStmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
$checkStmt->bind_param("i", $productId);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows === 0) {
    exit(json_encode([
        'success' => false,
        'message' => 'Product not found'
    ]));
}

// Prepare and execute update query
$stmt = $conn->prepare("
    UPDATE products 
    SET product_name = ?, description = ?, category = ?, price = ?, quantity = ?, 
        status = ?, size = ?, color = ?, image_url = ?, updated_at = NOW()
    WHERE id = ?
");
$stmt->bind_param("sssdissssi", 
    $productName, 
    $description, 
    $category, 
    $price, 
    $quantity, 
    $status, 
    $size, 
    $color, 
    $imageUrl, 
    $productId
);

if ($stmt->execute()) {
    // Log activity
    $action = 'Updated product: ' . $productName;
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logStmt->bind_param("is", $_SESSION['user_id'], $action);
    $logStmt->execute();

    exit(json_encode([
        'success' => true,
        'message' => 'Product updated successfully'
    ]));
} else {
    exit(json_encode([
        'success' => false,
        'message' => 'Error updating product: ' . $conn->error
    ]));
}
?>
