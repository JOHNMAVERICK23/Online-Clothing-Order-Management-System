<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'clothing_management_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

$conn->set_charset("utf8");

// Create tables if they don't exist
$createTablesQuery = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'staff') NOT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
";

$conn->multi_query($createTablesQuery);

// Clear the results
while ($conn->next_result()) {
    if (!$conn->more_results()) break;
}

// Check if default admin exists
$checkAdmin = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if ($checkAdmin->num_rows === 0) {
    // Create default admin account
    $adminEmail = 'admin@clothing.local';
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $conn->query("
        INSERT INTO users (first_name, last_name, email, password, role, status)
        VALUES ('System', 'Admin', '$adminEmail', '$adminPassword', 'admin', 'active')
    ");
}
?>