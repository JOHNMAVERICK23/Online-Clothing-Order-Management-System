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

// Get total users
$totalResult = $conn->query("SELECT COUNT(*) as count FROM users");
$totalUsers = $totalResult->fetch_assoc()['count'];

// Get admin count
$adminResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$adminCount = $adminResult->fetch_assoc()['count'];

// Get staff count
$staffResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'staff'");
$staffCount = $staffResult->fetch_assoc()['count'];

// Get active users count
$activeResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$activeCount = $activeResult->fetch_assoc()['count'];

echo json_encode([
    'success' => true,
    'totalUsers' => $totalUsers,
    'adminCount' => $adminCount,
    'staffCount' => $staffCount,
    'activeCount' => $activeCount
]);
?>