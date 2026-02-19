<?php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$lockout_duration = 30; // seconds
$max_attempts = 3;

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit;
}

// --- CREATE TABLE IF NOT EXISTS ---
$conn->query("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// --- CLEAN UP old attempts na lampas na sa 30 seconds ---
$conn->query("
    DELETE FROM login_attempts 
    WHERE attempted_at < NOW() - INTERVAL {$lockout_duration} SECOND
");

// --- CHECK kung naka-lockout na ---
$checkStmt = $conn->prepare("
    SELECT COUNT(*) as attempt_count 
    FROM login_attempts 
    WHERE email = ? 
    AND attempted_at >= NOW() - INTERVAL {$lockout_duration} SECOND
");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$attemptData = $checkStmt->get_result()->fetch_assoc();
$attemptCount = $attemptData['attempt_count'];

if ($attemptCount >= $max_attempts) {
    // Kunin kung gaano na katagal mula sa pinakabagong attempt
    $timeStmt = $conn->prepare("
        SELECT TIMESTAMPDIFF(SECOND, attempted_at, NOW()) as seconds_ago
        FROM login_attempts 
        WHERE email = ?
        ORDER BY attempted_at DESC 
        LIMIT 1
    ");
    $timeStmt->bind_param("s", $email);
    $timeStmt->execute();
    $timeData = $timeStmt->get_result()->fetch_assoc();
    $secondsAgo = $timeData['seconds_ago'] ?? 0;
    $remaining = $lockout_duration - $secondsAgo;
    if ($remaining < 0) $remaining = 0;

    echo json_encode([
        'success' => false,
        'message' => "Too many failed attempts. Please wait {$remaining} second(s) before trying again.",
        'locked' => true,
        'remaining' => $remaining
    ]);
    exit;
}

// --- FETCH USER ---
$stmt = $conn->prepare("
    SELECT id, first_name, last_name, email, password, role 
    FROM users WHERE email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0 || !password_verify($password, $result->fetch_assoc()['password'])) {
    // I-re-fetch dahil na-consume na ng fetch_assoc
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    // Log failed attempt
    $logStmt = $conn->prepare("
        INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)
    ");
    $logStmt->bind_param("ss", $email, $ip_address);
    $logStmt->execute();

    $remainingAttempts = $max_attempts - ($attemptCount + 1);

    if ($remainingAttempts <= 0) {
        echo json_encode([
            'success' => false,
            'message' => "Too many failed attempts. Please wait {$lockout_duration} second(s).",
            'locked' => true,
            'remaining' => $lockout_duration
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => "Invalid email or password. {$remainingAttempts} attempt(s) remaining.",
            'locked' => false,
            'remaining_attempts' => $remainingAttempts
        ]);
    }
    exit;
}

// --- SUCCESS: I-fetch ulit ang user nang maayos ---
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Clear failed attempts ng user na ito
$clearStmt = $conn->prepare("DELETE FROM login_attempts WHERE email = ?");
$clearStmt->bind_param("s", $email);
$clearStmt->execute();

// Update status at last login
$updateStmt = $conn->prepare("
    UPDATE users SET status = 'active', last_login_at = NOW() WHERE id = ?
");
$updateStmt->bind_param("i", $user['id']);
$updateStmt->execute();

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];

// Log successful login
$action = 'User Login';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$logStmt = $conn->prepare("
    INSERT INTO activity_logs (user_id, action, ip_address, user_agent) 
    VALUES (?, ?, ?, ?)
");
$logStmt->bind_param("isss", $user['id'], $action, $ip_address, $user_agent);
$logStmt->execute();

$redirectUrl = $user['role'] === 'admin' ? 'Admin/dashboard.php' : 'staff/orders.php';

echo json_encode([
    'success' => true,
    'message' => 'Login successful!',
    'redirect' => $redirectUrl
]);
exit;
?>