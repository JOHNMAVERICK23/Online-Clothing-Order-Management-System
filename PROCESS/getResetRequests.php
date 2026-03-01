<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$query = "SELECT r.id, r.status, r.requested_at, r.reviewed_at,
                 u.first_name, u.last_name, u.email
          FROM password_reset_requests r
          JOIN users u ON r.user_id = u.id
          ORDER BY 
            CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END,
            r.requested_at DESC";

$result = $conn->query($query);
$requests = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}

echo json_encode(['success' => true, 'requests' => $requests]);
?>
