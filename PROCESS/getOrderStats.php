<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    exit(json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]));
}

// Kunin ang counts ng orders ayon sa status
$query = "SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_orders,
            SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
            SUM(total_amount) as total_sales
          FROM orders";

$result = $conn->query($query);
$stats = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'total_orders' => (int)$stats['total_orders'],
    'pending_orders' => (int)$stats['pending_orders'],
    'processing_orders' => (int)$stats['processing_orders'],
    'shipped_orders' => (int)$stats['shipped_orders'],
    'delivered_orders' => (int)$stats['delivered_orders'],
    'cancelled_orders' => (int)$stats['cancelled_orders'],
    'total_sales' => (float)$stats['total_sales']
]);
?>