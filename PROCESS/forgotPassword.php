<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

if (!$email || !$new_password) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

// Hanapin ang user
$stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    // Vague ang message para sa security
    echo json_encode(['success' => true, 'message' => 'Request submitted! Please wait for admin approval.']);
    exit;
}

// Check kung may pending na request na
$stmt = $conn->prepare("SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'pending'");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    echo json_encode(['success' => false, 'message' => 'You already have a pending request. Please wait for admin approval.']);
    exit;
}

// I-hash ang bagong password
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

// I-save ang request
$stmt = $conn->prepare("INSERT INTO password_reset_requests (user_id, new_password) VALUES (?, ?)");
$stmt->bind_param("is", $user['id'], $hashed);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Request submitted! Please wait for admin approval.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit request. Please try again.']);
}

$stmt->close();
?>