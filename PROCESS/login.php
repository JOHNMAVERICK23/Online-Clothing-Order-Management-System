<?php
session_start();
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header('Location: ../index.html?error=Invalid credentials');
    exit;
}

// Fetch user from database
$stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, role, status FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ../index.html?error=Invalid email or password');
    exit;
}

$user = $result->fetch_assoc();

// Check if password is correct
if (!password_verify($password, $user['password'])) {
    header('Location: ../index.html?error=Invalid email or password');
    exit;
}

// Check if user is active
if ($user['status'] !== 'active') {
    header('Location: ../index.html?error=Your account is inactive');
    exit;
}

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];

// Log activity
$action = 'User Login';
$logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
$logStmt->bind_param("is", $user['id'], $action);
$logStmt->execute();

// Redirect based on role
if ($user['role'] === 'admin') {
    header('Location: ../Admin/dashboard.html');
} else if ($user['role'] === 'staff') {
    header('Location: ../staff/dashboard.html');
}
exit;
?>