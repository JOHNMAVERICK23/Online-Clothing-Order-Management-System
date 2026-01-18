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

$id = $_POST['id'] ?? '';

if (empty($id)) {
    exit(json_encode([
        'success' => false,
        'message' => 'Account ID is required'
    ]));
}

// Prevent deleting your own account
if ($id == $_SESSION['user_id']) {
    exit(json_encode([
        'success' => false,
        'message' => 'You cannot delete your own account'
    ]));
}

// Get account email before deletion for logging
$emailStmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$emailStmt->bind_param("i", $id);
$emailStmt->execute();
$result = $emailStmt->get_result();

if ($result->num_rows === 0) {
    exit(json_encode([
        'success' => false,
        'message' => 'Account not found'
    ]));
}

$account = $result->fetch_assoc();
$accountEmail = $account['email'];

// Delete the account
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Log activity
    $action = 'Deleted account: ' . $accountEmail;
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logStmt->bind_param("is", $_SESSION['user_id'], $action);
    $logStmt->execute();

    exit(json_encode([
        'success' => true,
        'message' => 'Account deleted successfully'
    ]));
} else {
    exit(json_encode([
        'success' => false,
        'message' => 'Error deleting account: ' . $conn->error
    ]));
}
?>
