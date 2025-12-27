<?php
session_start();
require_once 'db_config.php';

// Log activity
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $action = 'User Logout';
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
    $logStmt->bind_param("is", $userId, $action);
    $logStmt->execute();
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login
header('Location: ../index.html?success=Logged out successfully');
exit;
?>