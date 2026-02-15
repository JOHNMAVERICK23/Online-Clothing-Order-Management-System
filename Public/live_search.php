<?php
// live_search.php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'clothing_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$search = $_GET['search'] ?? '';

if (strlen($search) < 2) {
    echo json_encode([]);
    exit;
}

$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.is_active = TRUE 
          AND (p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)
          ORDER BY p.name ASC
          LIMIT 10";

$search_term = "%$search%";
$stmt = $pdo->prepare($query);
$stmt->execute([$search_term, $search_term, $search_term]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($products);
?>