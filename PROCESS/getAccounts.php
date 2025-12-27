<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    exit(json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]));
}

$query = "SELECT id, first_name, last_name, email, role, status, last_login_at, created_at FROM users ORDER BY created_at DESC";
$result = $conn->query($query);

$accounts = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'accounts' => $accounts
]);
?>