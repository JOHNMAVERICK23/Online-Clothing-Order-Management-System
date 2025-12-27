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
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';

// Validate inputs
if (empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
    exit(json_encode([
        'success' => false,
        'message' => 'All fields are required'
    ]));
}

// Validate role
if (!in_array($role, ['admin', 'staff'])) {
    exit(json_encode([
        'success' => false,
        'message' => 'Invalid role'
    ]));
}

// NEW ACCOUNT
if (empty($id)) {
    if (empty($password)) {
        exit(json_encode([
            'success' => false,
            'message' => 'Password is required for new accounts'
        ]));
    }

    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        exit(json_encode([
            'success' => false,
            'message' => 'Email already exists'
        ]));
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // New accounts start as inactive (until they login)
    $status = 'inactive';
    
    $stmt = $conn->prepare("
        INSERT INTO users (first_name, last_name, email, password, role, status)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssss", $firstName, $lastName, $email, $hashedPassword, $role, $status);
    
    if ($stmt->execute()) {
        // Log activity
        $action = 'Created new account: ' . $email;
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
        $logStmt->bind_param("is", $_SESSION['user_id'], $action);
        $logStmt->execute();

        exit(json_encode([
            'success' => true,
            'message' => 'Account created successfully'
        ]));
    } else {
        exit(json_encode([
            'success' => false,
            'message' => 'Error creating account: ' . $conn->error
        ]));
    }
}

// UPDATE ACCOUNT
else {
    // Get current account
    $checkStmt = $conn->prepare("SELECT id, password FROM users WHERE id = ?");
    $checkStmt->bind_param("i", $id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        exit(json_encode([
            'success' => false,
            'message' => 'Account not found'
        ]));
    }

    $currentAccount = $result->fetch_assoc();

    // Check if email changed and if new email already exists
    $emailCheckStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $emailCheckStmt->bind_param("si", $email, $id);
    $emailCheckStmt->execute();
    if ($emailCheckStmt->get_result()->num_rows > 0) {
        exit(json_encode([
            'success' => false,
            'message' => 'Email already exists'
        ]));
    }

    // Use current password if new password not provided
    $finalPassword = !empty($password) ? password_hash($password, PASSWORD_BCRYPT) : $currentAccount['password'];

    $stmt = $conn->prepare("
        UPDATE users 
        SET first_name = ?, last_name = ?, email = ?, password = ?, role = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssssi", $firstName, $lastName, $email, $finalPassword, $role, $id);

    if ($stmt->execute()) {
        // Log activity
        $action = 'Updated account: ' . $email;
        $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action) VALUES (?, ?)");
        $logStmt->bind_param("is", $_SESSION['user_id'], $action);
        $logStmt->execute();

        exit(json_encode([
            'success' => true,
            'message' => 'Account updated successfully'
        ]));
    } else {
        exit(json_encode([
            'success' => false,
            'message' => 'Error updating account: ' . $conn->error
        ]));
    }
}
?>