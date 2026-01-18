<?php
// test_login.php
session_start();
require_once 'PROCESS/db_config.php';

echo "Testing Login API<br>";
echo "Database Connection: ";

if ($conn) {
    echo "✓ Connected<br>";
} else {
    echo "✗ Failed: " . mysqli_connect_error() . "<br>";
    exit;
}

echo "Database: " . (mysqli_select_db($conn, 'clothing_shop') ? '✓ Selected' : '✗ Failed') . "<br>";

// Test user lookup
$email = 'admin@clothing.local';
$stmt = $conn->prepare("SELECT id, email, password, user_role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

echo "User found: " . ($result->num_rows > 0 ? '✓ Yes' : '✗ No') . "<br>";

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "User ID: " . $user['id'] . "<br>";
    echo "Email: " . $user['email'] . "<br>";
    echo "Role: " . $user['user_role'] . "<br>";
    echo "Password Hash: " . substr($user['password'], 0, 20) . "...<br>";
    
    // Test password
    $password = 'admin123';
    echo "Password verification: ";
    echo password_verify($password, $user['password']) ? '✓ Correct' : '✗ Wrong' . "<br>";
}

$stmt->close();
$conn->close();
?>