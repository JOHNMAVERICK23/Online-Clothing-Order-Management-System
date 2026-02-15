<?php
session_start();

// Check if user is logged in (optional, depending on your requirements)
// If you want to restrict access to logged-in users only, uncomment:
/*
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}
*/

// Database connection
$host = 'localhost';
$dbname = 'clothing_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if ($id <= 0) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid product ID']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.id = ? AND p.is_active = TRUE");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        header('Content-Type: application/json');
        echo json_encode($product);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Product not found or inactive']);
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'No product ID provided']);
}
?>