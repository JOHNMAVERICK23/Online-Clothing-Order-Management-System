<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate inputs
if (empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required'
    ]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address'
    ]);
    exit;
}

try {
    // Check database connection
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Prepare statement to fetch user - matches your table structure
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, role, status FROM users WHERE email = ?");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    $stmt->bind_param("s", $email);
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        $stmt->close();
        exit;
    }

    // Fetch user data
    $user = $result->fetch_assoc();
    $stmt->close();

    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }

    // Update last login
    $update_stmt = $conn->prepare("UPDATE users SET last_login_at = NOW(), status = 'active' WHERE id = ?");
    
    if ($update_stmt) {
        $update_stmt->bind_param("i", $user['id']);
        $update_stmt->execute();
        $update_stmt->close();
    }

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in'] = true;

    // Determine redirect based on role
    $redirect = '';
    if ($user['role'] === 'admin') {
        $redirect = 'Admin/dashboard.html';
    } elseif ($user['role'] === 'staff') {
        $redirect = 'staff/dashboard.html';
    } else {
        // For any other role, treat as customer
        $redirect = 'Public/shop.php';
    }

    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'redirect' => $redirect,
        'user_role' => $user['role'],
        'user_name' => $user['first_name']
    ]);
    exit;

} catch (Exception $e) {
    // Log error for debugging
    error_log('Login Error: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during login. Please try again.'
    ]);
    exit;
}

$conn->close();
?>