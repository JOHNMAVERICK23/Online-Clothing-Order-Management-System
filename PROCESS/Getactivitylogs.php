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

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$page = intval($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query with filters
$query = "SELECT al.*, u.first_name, u.last_name, u.email FROM activity_logs al 
          LEFT JOIN users u ON al.user_id = u.id 
          WHERE 1=1";

$params = [];
$types = "";

// Search filter
if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR al.action LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ssss";
}

// Action filter
if (!empty($actionFilter)) {
    $query .= " AND al.action LIKE ?";
    $params[] = "%{$actionFilter}%";
    $types .= "s";
}

// Date filter
if (!empty($dateFilter)) {
    $today = date('Y-m-d');
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $monthStart = date('Y-m-01');
    
    switch ($dateFilter) {
        case 'today':
            $query .= " AND DATE(al.created_at) = ?";
            $params[] = $today;
            $types .= "s";
            break;
        case 'week':
            $query .= " AND DATE(al.created_at) >= ?";
            $params[] = $weekStart;
            $types .= "s";
            break;
        case 'month':
            $query .= " AND DATE(al.created_at) >= ?";
            $params[] = $monthStart;
            $types .= "s";
            break;
    }
}

// Get total count for pagination
$countQuery = str_replace("SELECT al.*, u.first_name, u.last_name, u.email", "SELECT COUNT(*) as total", $query);
$countStmt = $conn->prepare($countQuery);

if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRows = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Add pagination and ordering
$query .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Execute main query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$activities = [];
while ($row = $result->fetch_assoc()) {
    $activities[] = $row;
}

// Calculate statistics
$statsQuery = "SELECT 
    COUNT(*) as total_activities,
    SUM(CASE WHEN DATE(created_at) = CURDATE() AND action LIKE '%Login%' THEN 1 ELSE 0 END) as logins_today,
    SUM(CASE WHEN action LIKE '%Created%' OR action LIKE '%Updated%' THEN 1 ELSE 0 END) as changes_made,
    SUM(CASE WHEN action LIKE '%Failed%' OR action LIKE '%Error%' THEN 1 ELSE 0 END) as failed_actions
    FROM activity_logs";

$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Return response
exit(json_encode([
    'success' => true,
    'activities' => $activities,
    'stats' => [
        'total_activities' => intval($stats['total_activities']),
        'logins_today' => intval($stats['logins_today'] ?? 0),
        'changes_made' => intval($stats['changes_made'] ?? 0),
        'failed_actions' => intval($stats['failed_actions'] ?? 0)
    ],
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_rows' => $totalRows,
        'limit' => $limit
    ]
]));
?>