<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : ''; // 'approve' or 'reject'
$admin_id = $_SESSION['user_id'];

if (!$request_id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Kunin ang request
$stmt = $conn->prepare("SELECT * FROM password_reset_requests WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$request) {
    echo json_encode(['success' => false, 'message' => 'Request not found or already reviewed']);
    exit;
}

if ($action === 'approve') {
    // I-update ang password ng user
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $request['new_password'], $request['user_id']);
    $stmt->execute();
    $stmt->close();
}

// I-update ang status ng request
$status = $action === 'approve' ? 'approved' : 'rejected';
$stmt = $conn->prepare("UPDATE password_reset_requests SET status = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?");
$stmt->bind_param("sii", $status, $admin_id, $request_id);
$stmt->execute();
$stmt->close();

$message = $action === 'approve' ? 'Password reset approved!' : 'Request rejected.';
echo json_encode(['success' => true, 'message' => $message]);
?>