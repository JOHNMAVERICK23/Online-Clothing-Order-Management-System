<?php
session_start();
require_once 'db_config.php';

// Log activity if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // Update user status to inactive and set last logout time
    $updateStmt = $conn->prepare("UPDATE users SET status = 'inactive', last_logout_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $userId);
    $updateStmt->execute();
    
    // Log activity with IP and user agent
    $action = 'User Logout';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $logStmt->bind_param("isss", $userId, $action, $ip_address, $user_agent);
    $logStmt->execute();
    
    // Store logout message in session for login page
    $_SESSION['logout_message'] = 'Logged out successfully';
}

// Destroy session
session_unset();
session_destroy();

// Start new session for logout message
session_start();
$_SESSION['logout_message'] = 'Logged out successfully';

// Redirect to login
header('Location: ../login.html');
exit;
?>