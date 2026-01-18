<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'customer') {
    header('Location: login_register.php');
    exit;
}

// Database connection
$host = 'localhost';
$dbname = 'clothing_shop';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get user data - FIXED: Include password field
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, name as full_name, email, phone, address, created_at, password FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user stats
$orders_stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE user_id = ?");
$orders_stmt->execute([$user_id]);
$total_orders = $orders_stmt->fetchColumn();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Check if email is already taken by another user
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check_stmt->execute([$email, $user_id]);
    
    if ($check_stmt->fetch()) {
        $error_message = "Email is already taken by another user.";
    } else {
        // Update user profile
        $update_stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        $update_stmt->execute([$full_name, $email, $phone, $address, $user_id]);
        
        // Update session data
        $_SESSION['user_name'] = $full_name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_phone'] = $phone;
        $_SESSION['user_address'] = $address;
        
        $success_message = "Profile updated successfully!";
        
        // Refresh user data
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle password change - FIXED: Now works correctly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password - FIXED: Now $user has password field
    if (!password_verify($current_password, $user['password'])) {
        $error_message = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long.";
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->execute([$hashed_password, $user_id]);
        
        // Update the local user array with new password
        $user['password'] = $hashed_password;
        
        $success_message = "Password changed successfully!";
    }
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Initialize wishlist if not exists
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

$user_name = $_SESSION['user_name'] ?? 'Customer';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login_register.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* ONLY ACCOUNT-SPECIFIC STYLES */
        .account-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            color: var(--dark);
            margin-bottom: 0.5rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 1rem;
            font-weight: 300;
        }

        /* Messages */
        .success-message {
            background: #f0f9f0;
            border: 1px solid #d4edda;
            color: #155724;
            padding: 1rem 1.5rem;
            border-radius: 0;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        .error-message {
            background: #fdf0f0;
            border: 1px solid #f8d7da;
            color: #721c24;
            padding: 1rem 1.5rem;
            border-radius: 0;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }

        /* Account Layout */
        .account-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            background: var(--light);
            border: 1px solid var(--border);
            padding: 0;
        }

        /* Sidebar */
        .account-sidebar {
            background: var(--light);
            border-right: 1px solid var(--border);
            padding: 2rem 0;
            display: flex;
            flex-direction: column;
        }

        .user-profile {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: var(--dark);
            color: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin: 0 auto 1rem;
        }

        .profile-name {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--dark);
            text-align: center;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }

        .profile-email {
            color: var(--gray);
            font-size: 0.9rem;
            text-align: center;
            font-weight: 300;
        }

        .account-stats {
            padding: 0 2rem 2rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;
            border-bottom: 1px solid var(--gray-light);
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 400;
        }

        .stat-value {
            font-weight: 500;
            color: var(--dark);
            font-size: 1rem;
        }

        .account-menu {
            padding: 0 1rem;
            flex-grow: 1;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-weight: 500;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .menu-item:hover,
        .menu-item.active {
            background: #f0f0f0;
            color: var(--dark);
            border-left-color: var(--dark);
        }

        .menu-item i {
            width: 20px;
            text-align: center;
            color: var(--gray);
        }

        .menu-item.active i {
            color: var(--dark);
        }

        /* Logout Button in Sidebar - Made same size as other menu items */
        .logout-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 1rem;
            color: var(--danger);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            font-weight: 500;
            letter-spacing: 0.5px;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            margin-top: 0.5rem;
        }

        .logout-item:hover {
            background: #fff0f0;
            color: var(--danger);
            border-left-color: var(--danger);
        }

        .logout-item i {
            width: 20px;
            text-align: center;
            color: var(--danger);
        }

        /* Main Content */
        .account-content {
            padding: 2rem;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .section-header {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .section-header h2 {
            font-size: 1.5rem;
            color: var(--dark);
            font-weight: 400;
            margin-bottom: 0.5rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .section-header p {
            color: var(--gray);
            font-size: 0.95rem;
            font-weight: 300;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--light);
            border-radius: 0;
            font-size: 1rem;
            color: var(--dark);
            transition: border 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--dark);
        }

        .form-control:disabled {
            background: var(--gray-light);
            color: var(--gray);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .password-strength {
            height: 4px;
            background: var(--gray-light);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
        }

        .password-hint {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.5rem;
            font-weight: 300;
        }

        /* Button Styles */
        .btn {
            padding: 0.8rem 1.5rem;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        .btn-danger {
            background: var(--light);
            color: var(--danger);
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: var(--danger);
            color: var(--light);
        }

        /* Address Display */
        .address-display {
            background: var(--gray-light);
            padding: 1.5rem;
            border: 1px solid var(--border);
            margin-top: 0.5rem;
        }

        .address-display p {
            margin: 0;
            color: var(--dark);
            line-height: 1.6;
        }

        .phone-info {
            margin-top: 0.5rem;
            color: var(--gray);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Danger Zone - Removed since logout is in sidebar now */
        .danger-zone {
            display: none;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .account-container {
                padding: 1rem;
            }
            
            .account-layout {
                grid-template-columns: 1fr;
            }
            
            .account-sidebar {
                border-right: none;
                border-bottom: 1px solid var(--border);
                padding-bottom: 0;
            }
            
            .user-profile,
            .account-stats {
                padding: 1.5rem;
            }
            
            .account-menu {
                padding: 0 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .content-section {
                padding: 0;
            }
            
            .section-header {
                margin-bottom: 1.5rem;
            }
            
            .account-content {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .profile-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation (from main.css) -->
    <nav class="customer-nav" id="customerNav">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="shop.php" class="nav-logo">
            <img src="../../resources/images/logo.jpeg" alt="Alas Clothing Shop" class="logo-image">
            <span class="brand-name"></span>
        </a>
        
        <ul class="nav-menu" id="navMenu">
            <li><a href="index.php">HOME</a></li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="orders.php">MY ORDERS</a></li>
            <li><a href="size_chart.php">SIZE CHART</a></li>
            <li><a href="shipping.php">SHIPPING</a></li>
            <li><a href="announcements.php">ANNOUNCEMENTS</a></li>
        </ul>
        
        <div class="nav-right">
            <a href="account.php" class="nav-icon active" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="wishlist.php" class="nav-icon" title="Wishlist">
                <i class="fas fa-heart"></i>
                <?php if (!empty($_SESSION['wishlist'])): ?>
                    <span class="wishlist-count-badge" id="wishlistCount">
                        <?php echo count($_SESSION['wishlist']); ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="cart_and_checkout.php" class="nav-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge" id="cartCount">
                    <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>
                </span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="account-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>My Account</h1>
                <p class="page-subtitle">Manage your profile and preferences</p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="account-layout">
                <!-- Sidebar -->
                <div class="account-sidebar">
                    <div class="user-profile">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['full_name'] ?? $user_name, 0, 1)); ?>
                        </div>
                        <div class="profile-name"><?php echo htmlspecialchars($user['full_name'] ?? $user_name); ?></div>
                        <div class="profile-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                    </div>

                    <div class="account-stats">
                        <div class="stat-item">
                            <span class="stat-label">Total Orders</span>
                            <span class="stat-value"><?php echo $total_orders; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Member Since</span>
                            <span class="stat-value">
                                <?php echo date('M Y', strtotime($user['created_at'] ?? 'now')); ?>
                            </span>
                        </div>
                    </div>

                    <div class="account-menu">
                        <a href="#" class="menu-item active" onclick="showSection('profile')">
                            <i class="fas fa-user-circle"></i>
                            <span>Profile Information</span>
                        </a>
                        <a href="#" class="menu-item" onclick="showSection('password')">
                            <i class="fas fa-lock"></i>
                            <span>Change Password</span>
                        </a>
                        <a href="#" class="menu-item" onclick="showSection('address')">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Address Book</span>
                        </a>
                        <a href="orders.php" class="menu-item">
                            <i class="fas fa-clipboard-list"></i>
                            <span>My Orders</span>
                        </a>
                        
                        <!-- Logout Button - Same size as other menu items -->
                        <button class="logout-item" onclick="confirmLogout()">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </button>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="account-content">
                    <!-- Profile Information -->
                    <div class="content-section active" id="profileSection">
                        <div class="section-header">
                            <h2>Profile Information</h2>
                            <p>Update your personal information and contact details</p>
                        </div>

                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="full_name">Full Name</label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['full_name'] ?? $user_name); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                           placeholder="09123456789">
                                </div>
                                
                                <div class="form-group">
                                    <label for="account_type">Account Type</label>
                                    <input type="text" id="account_type" class="form-control" 
                                           value="Customer" disabled>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="address">Shipping Address</label>
                                <textarea id="address" name="address" class="form-control" 
                                          rows="4" placeholder="House #, Street, Barangay, City, Province"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="created_at">Member Since</label>
                                <input type="text" id="created_at" class="form-control" 
                                       value="<?php echo date('F d, Y', strtotime($user['created_at'] ?? 'now')); ?>" 
                                       disabled>
                            </div>

                            <button type="submit" name="update_profile" value="1" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="content-section" id="passwordSection">
                        <div class="section-header">
                            <h2>Change Password</h2>
                            <p>Update your password to keep your account secure</p>
                        </div>

                        <form method="POST">
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" required 
                                           onkeyup="checkPasswordStrength(this.value)">
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                    </div>
                                    <div class="password-hint" id="passwordHint">
                                        Password must be at least 6 characters long
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                    <div class="password-hint" id="passwordMatch"></div>
                                </div>
                            </div>

                            <button type="submit" name="change_password" value="1" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>

                    <!-- Address Book -->
                    <div class="content-section" id="addressSection">
                        <div class="section-header">
                            <h2>Address Book</h2>
                            <p>Manage your shipping addresses</p>
                        </div>

                        <div class="form-group">
                            <label>Default Shipping Address</label>
                            <div class="address-display">
                                <?php if (!empty($user['address'])): ?>
                                    <p><?php echo htmlspecialchars($user['address']); ?></p>
                                    <div class="phone-info">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></span>
                                    </div>
                                <?php else: ?>
                                    <p style="color: var(--gray); font-style: italic;">No shipping address saved</p>
                                <?php endif; ?>
                        </div>

                        <p style="color: var(--gray); font-size: 0.9rem; margin-top: 2rem; padding: 1rem; background: var(--gray-light);">
                            <i class="fas fa-info-circle"></i> 
                            To update your shipping address, go to <a href="#" onclick="showSection('profile')" style="color: var(--dark); font-weight: 500;">Profile Information</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer (from main.css) -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>Alas Clothing Shop offers premium quality clothing and accessories for every occasion. We're committed to providing exceptional style and comfort.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-pinterest"></i></a>
                </div>
            </div>
            
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="shop.php"><i class="fas fa-chevron-right"></i> Shop</a></li>
                    <li><a href="size_chart.php"><i class="fas fa-chevron-right"></i> Size Chart</a></li>
                    <li><a href="shipping.php"><i class="fas fa-chevron-right"></i> Shipping & Returns</a></li>
                    <li><a href="announcements.php"><i class="fas fa-chevron-right"></i> Announcements</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Customer Service</h3>
                <ul class="footer-links">
                    <li><a href="orders.php"><i class="fas fa-chevron-right"></i> My Orders</a></li>
                    <li><a href="account.php"><i class="fas fa-chevron-right"></i> My Account</a></li>
                    <li><a href="wishlist.php"><i class="fas fa-chevron-right"></i> Wishlist</a></li>
                    <li><a href="cart_and_checkout.php"><i class="fas fa-chevron-right"></i> Cart</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Contact Info</h3>
                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>123 Fashion Street, City, Country 12345</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <span>+1 (555) 123-4567</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <span>info@alasclothingshop.com</span>
                    </div>
                    <div class="contact-item">
                        <i class="fas fa-clock"></i>
                        <span>Mon-Fri: 9AM-6PM | Sat: 10AM-4PM</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Alas Clothing Shop. All rights reserved.</p>
            <p>Designed with <i class="fas fa-heart" style="color: #ff3b30;"></i> for fashion enthusiasts</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script>
        // Account-specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize navbar using global function from main.js
            if (typeof window.navbar !== 'undefined') {
                window.navbar.init();
                window.navbar.highlightActivePage();
            }
            
            // Update cart and wishlist counts using global functions from main.js
            const cartCount = <?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>;
            const wishlistCount = <?php echo isset($_SESSION['wishlist']) ? count($_SESSION['wishlist']) : 0; ?>;
            
            if (typeof window.cart !== 'undefined') {
                window.cart.updateCount(cartCount);
                window.cart.updateWishlistCount(wishlistCount);
            }
        });

        // Show/Hide Content Sections
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Remove active class from all menu items
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionId + 'Section').classList.add('active');
            
            // Add active class to clicked menu item
            event.target.closest('.menu-item').classList.add('active');
            
            return false;
        }

        // Password Strength Checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrengthBar');
            const passwordHint = document.getElementById('passwordHint');
            
            if (!strengthBar || !passwordHint) return;
            
            let strength = 0;
            let hint = '';
            
            if (password.length >= 6) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.style.background = '#ff3b30';
                hint = 'Weak password. Try adding uppercase letters and numbers.';
            } else if (strength < 75) {
                strengthBar.style.background = '#ffa500';
                hint = 'Good password. Could be stronger.';
            } else {
                strengthBar.style.background = '#28a745';
                hint = 'Strong password!';
            }
            
            passwordHint.textContent = hint;
            
            // Check password match
            const confirmPassword = document.getElementById('confirm_password')?.value;
            const matchHint = document.getElementById('passwordMatch');
            
            if (confirmPassword && matchHint) {
                if (password === confirmPassword) {
                    matchHint.textContent = 'Passwords match ✓';
                    matchHint.style.color = '#28a745';
                } else {
                    matchHint.textContent = 'Passwords do not match ✗';
                    matchHint.style.color = '#dc3545';
                }
            }
        }

        // Check password match on confirm password input
        const confirmPasswordInput = document.getElementById('confirm_password');
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                const password = document.getElementById('new_password').value;
                const matchHint = document.getElementById('passwordMatch');
                
                if (!matchHint) return;
                
                if (password && this.value) {
                    if (password === this.value) {
                        matchHint.textContent = 'Passwords match ✓';
                        matchHint.style.color = '#28a745';
                    } else {
                        matchHint.textContent = 'Passwords do not match ✗';
                        matchHint.style.color = '#dc3545';
                    }
                } else {
                    matchHint.textContent = '';
                }
            });
        }

        // Form validation for password change
        const passwordForms = document.querySelectorAll('form');
        passwordForms.forEach(form => {
            if (form.querySelector('input[name="change_password"]')) {
                form.addEventListener('submit', function(e) {
                    const password = document.getElementById('new_password')?.value;
                    const confirmPassword = document.getElementById('confirm_password')?.value;
                    
                    if (!password || !confirmPassword) return;
                    
                    if (password.length < 6) {
                        e.preventDefault();
                        if (typeof window.utils !== 'undefined' && window.utils.showNotification) {
                            window.utils.showNotification('Password must be at least 6 characters long.', 'error');
                        } else {
                            alert('Password must be at least 6 characters long.');
                        }
                        return false;
                    }
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        if (typeof window.utils !== 'undefined' && window.utils.showNotification) {
                            window.utils.showNotification('Passwords do not match.', 'error');
                        } else {
                            alert('Passwords do not match.');
                        }
                        return false;
                    }
                });
            }
        });

        // Logout confirmation
        function confirmLogout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '?logout=true';
            }
        }
    </script>
</body>
</html>