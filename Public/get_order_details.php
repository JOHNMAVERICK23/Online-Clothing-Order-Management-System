<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'customer') {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

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
    $order_id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];
    
    // Get order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("SELECT oi.*, p.name, p.image_url FROM order_items oi 
                          JOIN products p ON oi.product_id = p.id 
                          WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate order total from items
    $order_total = array_sum(array_column($items, 'total_price'));
    
    $response = [
        'order_number' => $order['order_number'],
        'status' => $order['status'],
        'created_at' => $order['created_at'],
        'shipping_address' => $order['shipping_address'],
        'contact_number' => $order['contact_number'],
        'payment_method' => $order['payment_method'],
        'total_amount' => $order['total_amount'],
        'order_total' => $order_total,
        'items' => $items
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'No order ID provided']);
}
?>