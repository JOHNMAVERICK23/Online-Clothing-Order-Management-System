<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'clothing_shop');
define('UPLOAD_DIR', '../uploads/products/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

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

// ================================================
// CREATE CUSTOMERS TABLE
// ================================================
$conn->query("
    CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        phone VARCHAR(20),
        address TEXT,
        city VARCHAR(100),
        province VARCHAR(100),
        zip_code VARCHAR(10),
        country VARCHAR(100) DEFAULT 'Philippines',
        profile_image VARCHAR(500),
        gender ENUM('male', 'female', 'other'),
        birth_date DATE,
        newsletter BOOLEAN DEFAULT 1,
        total_orders INT DEFAULT 0,
        total_spent DECIMAL(10,2) DEFAULT 0,
        loyalty_points INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id)
    )
");

// Create categories table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Create products table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        category_id INT,
        brand VARCHAR(100),
        quantity INT DEFAULT 0,
        size VARCHAR(50),
        color VARCHAR(50),
        image_url VARCHAR(500),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_category (category_id),
        INDEX idx_active (status)
    )
");

// Create activity_logs table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user (user_id),
        INDEX idx_action (action)
    )
");

// Create orders table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_number VARCHAR(50) UNIQUE NOT NULL,
        customer_name VARCHAR(200) NOT NULL,
        customer_email VARCHAR(100) NOT NULL,
        customer_phone VARCHAR(20),
        shipping_address TEXT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
        staff_id INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    )
");

// Create order_items table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )
");

// Create user_carts table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS user_carts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_product (user_id, product_id)
    )
");

// Create user_wishlists table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS user_wishlists (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_wishlist (user_id, product_id)
    )
");

// Create password_resets table if it doesn't exist
$conn->query("
    CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at TIMESTAMP NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

?>