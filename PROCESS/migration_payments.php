<?php
// Run this file once to create the necessary tables
// Access it via: http://yoursite.com/PROCESS/migration_payments.php

require_once 'db_config.php';

$migrations = [
    // Create payments table
    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL UNIQUE,
        xendit_invoice_id VARCHAR(100),
        external_id VARCHAR(255),
        amount DECIMAL(10, 2) NOT NULL,
        paid_amount DECIMAL(10, 2),
        payment_method VARCHAR(50),
        status ENUM('pending', 'paid', 'failed', 'expired', 'cancelled') DEFAULT 'pending',
        xendit_response LONGTEXT,
        paid_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        INDEX idx_xendit_invoice (xendit_invoice_id),
        INDEX idx_external_id (external_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    // Add payment method column to orders if it doesn't exist
    "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT NULL",
    
    // Create payment logs table for tracking
    "CREATE TABLE IF NOT EXISTS payment_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        event VARCHAR(100),
        details TEXT,
        response TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        INDEX idx_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$success = 0;
$failed = 0;

echo "<h2>Database Migration</h2>";
echo "<ul>";

foreach ($migrations as $sql) {
    if ($conn->query($sql)) {
        echo "<li style='color: green;'>✓ " . substr($sql, 0, 50) . "...</li>";
        $success++;
    } else {
        echo "<li style='color: orange;'>⚠ " . substr($sql, 0, 50) . "... (" . $conn->error . ")</li>";
        $failed++;
    }
}

echo "</ul>";
echo "<p><strong>Success:</strong> $success | <strong>Failed/Skipped:</strong> $failed</p>";
echo "<p><a href='../Admin/orders.php'>Go to Orders</a></p>";
?>