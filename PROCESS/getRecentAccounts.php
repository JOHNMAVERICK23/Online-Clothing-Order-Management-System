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

$query = "SELECT id, first_name, last_name, email, role, status, last_login_at, created_at FROM users ORDER BY created_at DESC LIMIT 5";
$result = $conn->query($query);

$accounts = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
}

echo json_encode([
    'success' => true,
    'accounts' => $accounts
]);
?>
