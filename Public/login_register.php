<?php
session_start();

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

$message = '';
$message_type = ''; // 'success' or 'error'

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Redirect based on user type
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: ../admin/dashboard.php');
        exit;
    } else {
        header('Location: ../CUSTOMER/shop.php');
        exit;
    }
}

// Track which form was submitted for proper display
$form_submitted = 'none'; // 'login', 'register', 'reset-request'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['register'])) {
        $form_submitted = 'register';
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // Enhanced validation
        $errors = [];
        
        if (empty($name) || empty($email) || empty($password)) {
            $errors[] = 'All fields are required.';
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }
        
        // Strong password requirements: 8-15 characters, uppercase, lowercase, number, special character
        if (strlen($password) < 8 || strlen($password) > 15) {
            $errors[] = 'Password must be 8-15 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }
        
        if (empty($errors)) {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $message = 'Email already registered.';
                $message_type = 'error';
            } else {
                // Hash password and insert
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $user_type = 'customer'; // Default to customer
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, user_type) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$name, $email, $hashedPassword, $user_type])) {
                    // Get the newly created user ID
                    $user_id = $pdo->lastInsertId();
                    
                    // Initialize session variables
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_type'] = $user_type;
                    $_SESSION['logged_in'] = true;
                    
                    // Initialize empty cart and wishlist for new user
                    $_SESSION['cart'] = [];
                    $_SESSION['wishlist'] = [];
                    
                    // Redirect based on user type
                    if ($user_type === 'admin') {
                        header('Location: ../admin/dashboard.php');
                        exit;
                    } else {
                        header('Location: ../CUSTOMER/shop.php');
                        exit;
                    }
                } else {
                    $message = 'Registration failed. Try again.';
                    $message_type = 'error';
                }
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'error';
        }
        
    } elseif (isset($_POST['login'])) {
        $form_submitted = 'login';
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $message = 'All fields are required.';
            $message_type = 'error';
        } else {
            // Find user by name OR email (user can login with either)
            $stmt = $pdo->prepare("SELECT id, name, email, password, user_type, login_attempts, last_attempt FROM users WHERE name = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Check login attempts (lockout after 5 failed attempts in 5 minutes)
                $now = time();
                $lastAttempt = $user['last_attempt'] ? strtotime($user['last_attempt']) : 0;
                
                // If account is locked (5+ attempts) and lockout period hasn't expired
                if ($user['login_attempts'] >= 5 && ($now - $lastAttempt) < 300) { // 5 minutes = 300 seconds
                    $remainingTime = 300 - ($now - $lastAttempt);
                    $minutes = ceil($remainingTime / 60);
                    $message = "Account locked. Too many failed attempts. Try again in $minutes minute(s).";
                    $message_type = 'error';
                } 
                // If lockout period has expired (5 minutes have passed), reset attempts
                elseif ($user['login_attempts'] >= 5 && ($now - $lastAttempt) >= 300) {
                    // Reset attempts since lockout period is over
                    $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, last_attempt = NULL WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Now check password with fresh attempt count
                    if (password_verify($password, $user['password'])) {
                        // Successful login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['logged_in'] = true;
                        
                        // Load user's saved cart from database
                        loadUserCartFromDatabase($pdo, $user['id']);
                        
                        // Load user's saved wishlist from database
                        loadUserWishlistFromDatabase($pdo, $user['id']);
                        
                        // Reset attempts on successful login
                        $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, last_attempt = NULL WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        // Redirect based on user type
                        if ($user['user_type'] === 'admin') {
                            header('Location: ../admin/dashboard.php');
                            exit;
                        } else {
                            header('Location: ../CUSTOMER/shop.php');
                            exit;
                        }
                    } else {
                        // Failed login: increment attempts (this is now attempt #1 after lockout reset)
                        $stmt = $pdo->prepare("UPDATE users SET login_attempts = 1, last_attempt = NOW() WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        $remaining = 5 - 1;
                        $message = "Invalid credentials. Attempts remaining: $remaining";
                        $message_type = 'error';
                    }
                }
                elseif (password_verify($password, $user['password'])) {
                    // Successful login
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['logged_in'] = true;
                    
                    // Load user's saved cart from database
                    loadUserCartFromDatabase($pdo, $user['id']);
                    
                    // Load user's saved wishlist from database
                    loadUserWishlistFromDatabase($pdo, $user['id']);
                    
                    // Reset attempts on successful login
                    $stmt = $pdo->prepare("UPDATE users SET login_attempts = 0, last_attempt = NULL WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Redirect based on user type
                    if ($user['user_type'] === 'admin') {
                        header('Location: ../admin/dashboard.php');
                        exit;
                    } else {
                        header('Location: ../CUSTOMER/shop.php');
                        exit;
                    }
                } else {
                    // Failed login: increment attempts
                    $newAttempts = $user['login_attempts'] + 1;
                    $stmt = $pdo->prepare("UPDATE users SET login_attempts = ?, last_attempt = NOW() WHERE id = ?");
                    $stmt->execute([$newAttempts, $user['id']]);
                    
                    $remaining = 5 - $newAttempts;
                    if ($remaining > 0) {
                        $message = "Invalid credentials. Attempts remaining: $remaining";
                    } else {
                        $message = "Account locked for 5 minutes. Too many failed attempts.";
                    }
                    $message_type = 'error';
                }
            } else {
                $message = 'Invalid username/email or password.';
                $message_type = 'error';
            }
        }
        
    } elseif (isset($_POST['reset-request'])) {
        $form_submitted = 'reset-request';
        $email = trim($_POST['reset-email']);
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Valid email is required.';
            $message_type = 'error';
        } else {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                // Generate token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                
                // Delete old tokens for this email
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmt->execute([$email]);
                
                // Insert new token
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires]);
                
                // For school project: Show token instead of sending email
                $message = 'Password reset link generated:<br><br>Token: <strong>' . $token . '</strong><br>Expires: ' . $expires . '<br><br>Copy this token for password reset.';
                $message_type = 'success';
            } else {
                $message = 'Email not found.';
                $message_type = 'error';
            }
        }
    }
}

// Function to load user's cart from database
function loadUserCartFromDatabase($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM user_carts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cart = [];
    foreach ($cart_items as $item) {
        $cart[] = [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity']
        ];
    }
    
    $_SESSION['cart'] = $cart;
}

// Function to load user's wishlist from database
function loadUserWishlistFromDatabase($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT product_id FROM user_wishlists WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $_SESSION['wishlist'] = $wishlist_items;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Register - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #000000;
            --primary-dark: #111111;
            --secondary: #666666;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #ff3b30;
            --light: #ffffff;
            --dark: #000000;
            --gray: #888888;
            --gray-light: #f5f5f5;
            --border: #e0e0e0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        /* Navigation */
        .nav-logo {
            position: absolute;
            top: 2rem;
            left: 2rem;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo-image {
            height: 40px;
            width: auto;
        }

        .brand-name {
            font-weight: 700;
            letter-spacing: 1px;
            color: var(--dark);
            font-size: 1.2rem;
        }

        /* Auth Container */
        .auth-container {
            background: var(--light);
            border: 1px solid var(--border);
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            position: relative;
            margin: 2rem;
        }

        /* Form Styles */
        .form-container {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .form-container.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-size: 1.8rem;
            font-weight: 300;
            color: var(--dark);
            margin-bottom: 0.5rem;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .form-header p {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 300;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--light);
            border-radius: 0;
            font-size: 0.95rem;
            color: var(--dark);
            transition: border 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--dark);
        }

        .form-control::placeholder {
            color: var(--gray);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: 1px solid var(--border);
            background: var(--dark);
            color: var(--light);
            border-radius: 0;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--light);
            color: var(--dark);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background: var(--dark);
            color: var(--light);
        }

        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
            color: var(--gray);
            font-size: 0.9rem;
        }

        .form-footer a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        /* Password Requirements */
        .password-requirements {
            background: var(--gray-light);
            padding: 1rem;
            margin: 1rem 0;
            border: 1px solid var(--border);
        }

        .password-requirements small {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements li {
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            font-size: 0.75rem;
            color: var(--gray);
        }

        .password-requirements li i {
            margin-right: 0.5rem;
            font-size: 0.7rem;
            width: 12px;
        }

        .password-requirements li.valid {
            color: var(--success);
        }

        .password-requirements li.invalid {
            color: var(--danger);
        }

        .password-strength {
            height: 4px;
            background: var(--gray-light);
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
        }

        .strength-weak { background: var(--danger); width: 33%; }
        .strength-medium { background: var(--warning); width: 66%; }
        .strength-strong { background: var(--success); width: 100%; }

        /* Messages */
        .message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-100%);
            width: 90%;
            max-width: 500px;
            padding: 1rem 1.5rem;
            border: 1px solid var(--border);
            margin-bottom: 15px;
            text-align: center;
            font-size: 0.9rem;
            z-index: 10000;
            animation: slideIn 0.5s ease-out forwards;
        }

        .message.error {
            background: #fdf0f0;
            color: var(--danger);
            border-color: #f8d7da;
        }

        .message.success {
            background: #f0f9f0;
            color: var(--success);
            border-color: #d4edda;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-50%) translateY(-100%); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        @keyframes slideOut {
            from { opacity: 1; transform: translateX(-50%) translateY(0); }
            to { opacity: 0; transform: translateX(-50%) translateY(-100%); }
        }

        /* Reset token styling */
        .reset-token {
            background: var(--gray-light);
            padding: 0.8rem;
            border: 1px solid var(--border);
            margin: 1rem 0;
            font-family: monospace;
            word-break: break-all;
            font-size: 0.8rem;
            color: var(--dark);
        }

        /* Form Tabs */
        .form-tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }

        .form-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--gray);
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-tab.active {
            color: var(--dark);
            border-bottom-color: var(--dark);
        }

        .form-tab:hover:not(.active) {
            color: var(--dark);
            background: var(--gray-light);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-logo {
                top: 1rem;
                left: 1rem;
            }
            
            .auth-container {
                margin: 1rem;
                padding: 1.5rem;
            }
            
            .form-header h1 {
                font-size: 1.5rem;
            }
            
            .form-tabs {
                flex-direction: column;
            }
            
            .form-tab {
                padding: 0.8rem;
                text-align: left;
            }
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 1rem;
            }
            
            .logo-image {
                height: 30px;
            }
            
            .brand-name {
                font-size: 1rem;
            }
        }

        /* Hide elements on mobile/desktop */
        .mobile-only {
            display: none;
        }

        @media (max-width: 768px) {
            .mobile-only {
                display: block;
            }
            
            .desktop-only {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Logo -->
    <a href="shop.php" class="nav-logo">
        <img src="../../resources/images/logo.jpeg" alt="Alas Clothing Shop" class="logo-image">
        <span class="brand-name"></span>
    </a>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>" id="message-box">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="auth-container">
        <!-- Form Tabs -->
        <div class="form-tabs">
            <button class="form-tab <?php echo ($form_submitted === 'login' || $form_submitted === 'none' || ($form_submitted === 'reset-request' && $message_type === 'success')) ? 'active' : ''; ?>" 
                    onclick="showForm('login')">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            <button class="form-tab <?php echo ($form_submitted === 'register' && $message_type === 'error') ? 'active' : ''; ?>" 
                    onclick="showForm('register')">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </div>

        <!-- Register Form -->
        <div class="form-container <?php echo ($form_submitted === 'register' && $message_type === 'error') ? 'active' : ''; ?>" id="registerForm">
            <div class="form-header">
                <h1>Create Account</h1>
                <p>Join our community of fashion lovers</p>
            </div>

            <form action="" method="POST" id="register-form">
                <div class="form-group">
                    <input type="text" name="name" class="form-control" placeholder="Full Name" required 
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                </div>

                <div class="form-group">
                    <input type="email" name="email" class="form-control" placeholder="Email Address" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <div class="form-group">
                    <input type="password" name="password" id="reg-password" class="form-control" 
                           placeholder="Password (8-15 characters)" required>
                    <div class="password-strength">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                </div>

                <div class="password-requirements">
                    <small>Password requirements:</small>
                    <ul>
                        <li id="req-length"><i class="fas fa-times"></i> 8-15 characters</li>
                        <li id="req-uppercase"><i class="fas fa-times"></i> At least one uppercase letter</li>
                        <li id="req-lowercase"><i class="fas fa-times"></i> At least one lowercase letter</li>
                        <li id="req-number"><i class="fas fa-times"></i> At least one number</li>
                        <li id="req-special"><i class="fas fa-times"></i> At least one special character</li>
                    </ul>
                </div>

                <button type="submit" name="register" id="register-btn" class="btn">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>

            <div class="form-footer mobile-only">
                Already have an account? <a onclick="showForm('login')">Login</a>
            </div>
        </div>

        <!-- Login Form -->
        <div class="form-container <?php echo ($form_submitted === 'login' || $form_submitted === 'none' || ($form_submitted === 'reset-request' && $message_type === 'success')) ? 'active' : ''; ?>" id="loginForm">
            <div class="form-header">
                <h1>Welcome Back</h1>
                <p>Sign in to your account</p>
            </div>

            <form action="" method="POST" id="login-form">
                <div class="form-group">
                    <input type="text" name="username" class="form-control" placeholder="Username or Email" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>

                <div class="form-group">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>

                <button type="submit" name="login" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="form-footer">
                <a onclick="showForm('reset')" style="display: block; margin-bottom: 1rem;">
                    <i class="fas fa-key"></i> Forgot Password?
                </a>
                <div class="mobile-only">
                    Don't have an account? <a onclick="showForm('register')">Register</a>
                </div>
            </div>
        </div>

        <!-- Reset Password Form -->
        <div class="form-container <?php echo ($form_submitted === 'reset-request' && $message_type === 'error') ? 'active' : ''; ?>" id="resetForm">
            <div class="form-header">
                <h1>Reset Password</h1>
                <p>Enter your email to receive a reset token</p>
            </div>

            <form action="" method="POST" id="reset-form">
                <div class="form-group">
                    <input type="email" name="reset-email" class="form-control" placeholder="Email Address" required 
                           value="<?php echo isset($_POST['reset-email']) ? htmlspecialchars($_POST['reset-email']) : ''; ?>">
                </div>

                <button type="submit" name="reset-request" class="btn">
                    <i class="fas fa-paper-plane"></i> Get Reset Token
                </button>
            </form>

            <div class="form-footer">
                <a onclick="showForm('login')">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        // Form management
        function showForm(formType) {
            // Hide all forms
            document.querySelectorAll('.form-container').forEach(form => {
                form.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.form-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected form
            if (formType === 'register') {
                document.getElementById('registerForm').classList.add('active');
                document.querySelectorAll('.form-tab')[1].classList.add('active');
            } else if (formType === 'login') {
                document.getElementById('loginForm').classList.add('active');
                document.querySelectorAll('.form-tab')[0].classList.add('active');
            } else if (formType === 'reset') {
                document.getElementById('resetForm').classList.add('active');
                // No tab for reset form
            }
        }

        // Strong password validation
        const passwordInput = document.getElementById('reg-password');
        const registerSubmitBtn = document.getElementById('register-btn');
        const strengthBar = document.getElementById('strengthBar');

        // Requirements elements
        const requirements = {
            length: document.getElementById('req-length'),
            uppercase: document.getElementById('req-uppercase'),
            lowercase: document.getElementById('req-lowercase'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
        };

        function validatePassword() {
            const password = passwordInput.value;
            let strength = 0;
            
            // Check each requirement
            const isLengthValid = password.length >= 8 && password.length <= 15;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^A-Za-z0-9]/.test(password);
            
            // Update requirement UI
            updateRequirement(requirements.length, isLengthValid);
            updateRequirement(requirements.uppercase, hasUppercase);
            updateRequirement(requirements.lowercase, hasLowercase);
            updateRequirement(requirements.number, hasNumber);
            updateRequirement(requirements.special, hasSpecial);
            
            // Calculate strength based on met criteria
            const criteriaMet = [isLengthValid, hasUppercase, hasLowercase, hasNumber, hasSpecial].filter(Boolean).length;
            if (criteriaMet === 5) {
                strength = 3; // strong
            } else if (criteriaMet >= 3) {
                strength = 2; // medium
            } else if (criteriaMet >= 1) {
                strength = 1; // weak
            }
            
            // Update strength bar
            strengthBar.className = 'strength-bar';
            if (strength === 1) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 2) {
                strengthBar.classList.add('strength-medium');
            } else if (strength === 3) {
                strengthBar.classList.add('strength-strong');
            }
            
            // Enable/disable register button
            const isValid = isLengthValid && hasUppercase && hasLowercase && hasNumber && hasSpecial;
            if (registerSubmitBtn) {
                registerSubmitBtn.disabled = !isValid;
                registerSubmitBtn.style.opacity = isValid ? '1' : '0.6';
                registerSubmitBtn.style.cursor = isValid ? 'pointer' : 'not-allowed';
            }
            
            return isValid;
        }

        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i');
            if (isValid) {
                element.classList.add('valid');
                element.classList.remove('invalid');
                icon.className = 'fas fa-check';
            } else {
                element.classList.add('invalid');
                element.classList.remove('valid');
                icon.className = 'fas fa-times';
            }
        }

        // Real-time password validation
        if (passwordInput) {
            passwordInput.addEventListener('input', validatePassword);
        }

        // Form submission validation
        const registerForm = document.getElementById('register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                if (!validatePassword()) {
                    e.preventDefault();
                    alert('Password must meet all requirements:\n• 8-15 characters\n• At least one uppercase letter\n• At least one lowercase letter\n• At least one number\n• At least one special character');
                }
            });
        }

        // Auto-hide messages after 8 seconds
        const messageElement = document.getElementById('message-box');
        if (messageElement) {
            setTimeout(() => {
                messageElement.style.animation = 'slideOut 0.5s ease-out forwards';
                setTimeout(() => {
                    if (messageElement.parentNode) {
                        messageElement.parentNode.removeChild(messageElement);
                    }
                }, 500);
            }, 8000);
        }

        // Clear form inputs on submit
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    setTimeout(() => {
                        if (this === document.getElementById('register-form')) {
                            this.reset();
                            validatePassword();
                        } else {
                            this.reset();
                        }
                    }, 100);
                });
            });
            
            // Initial validation
            if (passwordInput) {
                validatePassword();
            }
            
            // Auto-focus on active form input
            const activeForm = document.querySelector('.form-container.active');
            if (activeForm) {
                const firstInput = activeForm.querySelector('input');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        });

        // Handle enter key to submit form
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const activeForm = document.querySelector('.form-container.active form');
                if (activeForm && !e.target.matches('button')) {
                    e.preventDefault();
                    const submitBtn = activeForm.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.click();
                    }
                }
            }
        });

        // Mobile view adjustments
        function adjustForMobile() {
            if (window.innerWidth <= 768) {
                document.body.style.padding = '80px 0 20px';
            } else {
                document.body.style.padding = '0';
            }
        }

        window.addEventListener('resize', adjustForMobile);
        adjustForMobile();
    </script>
</body>
</html>