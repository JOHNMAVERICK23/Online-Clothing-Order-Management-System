<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validation
if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

if ($password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    // Check if email exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Insert customer - NOTE: Using 'customer' role (stored as text in your enum)
    // Since your ENUM only has 'admin' and 'staff', we need to handle customers differently
    // Option 1: Store as 'staff' and identify by another method
    // Option 2: Add 'customer' to enum - RECOMMENDED
    
    // For now, we'll insert as 'staff' but you should update your enum to include 'customer'
    // ALTER TABLE users MODIFY role ENUM('admin', 'staff', 'customer') NOT NULL;
    
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password, role, status) 
        VALUES (?, ?, ?, ?, 'customer', 'active')
    ");
    
    if (!$stmt) {
        // If customer role doesn't exist in enum, try with 'staff'
        $stmt = $conn->prepare("
            INSERT INTO users (first_name, last_name, email, password, role, status) 
            VALUES (?, ?, ?, ?, 'staff', 'active')
        ");
    }
    
    $stmt->bind_param("ssss", $first_name, $last_name, $email, $hashed_password);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Set session
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        $_SESSION['user_role'] = 'customer';
        $_SESSION['logged_in'] = true;
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful!',
            'redirect' => 'Public/shop.php'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    error_log('Registration Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

$conn->close();
?>