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

// Prepare and execute insert query
$stmt = $conn->prepare("
    INSERT INTO products (product_name, description, category, price, quantity, status, size, color, image_url, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
    $_SESSION['user_id']
);

if ($stmt->execute()) {
    // Log activity
    $action = 'Created new product: ' . $productName;
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logStmt->bind_param("is", $_SESSION['user_id'], $action);
    $logStmt->execute();

    exit(json_encode([
        'success' => true,
        'message' => 'Product created successfully'
    ]));
} else {
    exit(json_encode([
        'success' => false,
        'message' => 'Error creating product: ' . $conn->error
    ]));
}
?>
