<?php
session_start();

// Initialize session variables if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['wishlist'])) {
    $_SESSION['wishlist'] = [];
}

$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$user_name = $_SESSION['user_name'] ?? 'Guest';

// Database connection
$host = 'localhost';
$dbname = 'clothing_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get size chart data
$stmt = $pdo->query("SELECT * FROM size_charts WHERE is_active = 1 ORDER BY category, size_order");
$size_charts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category
$grouped_charts = [];
foreach ($size_charts as $chart) {
    $category = $chart['category'];
    if (!isset($grouped_charts[$category])) {
        $grouped_charts[$category] = [];
    }
    $grouped_charts[$category][] = $chart;
}

// Get size guide content
$stmt = $pdo->query("SELECT * FROM content_management WHERE page = 'size_chart' AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
$size_guide_content = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Size Chart - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .size-chart-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 6rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
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

        /* Size Guide Content */
        .size-guide-content {
            background: var(--light);
            border: 1px solid var(--border);
            padding: 2rem;
            margin-bottom: 3rem;
            border-radius: 0;
        }

        .guide-title {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .guide-text {
            line-height: 1.8;
            color: var(--dark);
            margin-bottom: 1.5rem;
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .tip-card {
            background: var(--gray-light);
            padding: 1.5rem;
            border: 1px solid var(--border);
            text-align: center;
        }

        .tip-icon {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        .tip-title {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        /* Size Charts */
        .size-charts {
            display: flex;
            flex-direction: column;
            gap: 3rem;
        }

        .chart-category {
            margin-bottom: 2rem;
        }

        .category-title {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 0.5rem;
        }

        .chart-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
        }

        .chart-table th {
            background: var(--dark);
            color: var(--light);
            padding: 1rem;
            text-align: center;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid var(--border);
        }

        .chart-table td {
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--border);
            color: var(--dark);
        }

        .chart-table tr:nth-child(even) {
            background: var(--gray-light);
        }

        .chart-table tr:hover {
            background: rgba(0,0,0,0.05);
        }

        .unit {
            color: var(--gray);
            font-size: 0.9rem;
            font-style: italic;
        }

        /* Measurement Guide */
        .measurement-guide {
            background: var(--light);
            border: 1px solid var(--border);
            padding: 2rem;
            margin-top: 3rem;
        }

        .measurement-title {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .measurement-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .measurement-image {
            width: 100%;
            border: 1px solid var(--border);
        }

        .measurement-list {
            list-style: none;
        }

        .measurement-list li {
            margin-bottom: 1rem;
            padding-left: 1.5rem;
            position: relative;
        }

        .measurement-list li:before {
            content: "•";
            color: var(--accent);
            position: absolute;
            left: 0;
            font-size: 1.5rem;
        }

        /* Comparison Chart */
        .comparison-chart {
            background: var(--light);
            border: 1px solid var(--border);
            padding: 2rem;
            margin-top: 3rem;
        }

        .comparison-table {
            width: 100%;
            border-collapse: collapse;
        }

        .comparison-table th {
            background: var(--accent);
            color: var(--light);
            padding: 1rem;
            text-align: center;
        }

        .comparison-table td {
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--border);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .tips-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .measurement-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 768px) {
            .size-chart-container {
                padding: 1rem;
                padding-top: 5rem;
            }
            
            .tips-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-table th,
            .chart-table td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .comparison-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="customer-nav" id="customerNav">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        
        <a href="../index.php" class="nav-logo">
            <img src="images/logo.jpg" class="logo-image">
            <span class="brand-name">Alas Clothing Shop</span>
        </a>
        
        <ul class="nav-menu" id="navMenu">
            <li><a href="../index.php">HOME</a></li>
            <li><a href="shop.php">SHOP</a></li>
            <li><a href="orders.php">MY ORDERS</a></li>
            <li><a href="size_chart.php" class="active">SIZE CHART</a></li>
            <li><a href="shipping.php">SHIPPING</a></li>
            <li><a href="announcements.php">ANNOUNCEMENTS</a></li>
        </ul>
        
        <div class="nav-right">
            <a href="<?php echo $is_logged_in ? 'account.php' : 'login_register.php'; ?>" class="nav-icon" title="Account">
                <i class="fas fa-user"></i>
            </a>
            <a href="wishlist.php" class="nav-icon" title="Wishlist">
                <i class="fas fa-heart"></i>
                <?php if ($is_logged_in && !empty($_SESSION['wishlist'])): ?>
                    <span class="wishlist-count-badge" id="wishlistCount">
                        <?php echo count($_SESSION['wishlist']); ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="cart_and_checkout.php" class="nav-icon" title="Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count-badge" id="cartCount">
                    <?php echo count($_SESSION['cart']); ?>
                </span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="size-chart-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Size Chart Guide</h1>
            <p class="page-subtitle">Find your perfect fit with our detailed size guide</p>
        </div>

        <!-- Size Guide Content -->
        <?php if ($size_guide_content): ?>
        <div class="size-guide-content">
            <h2 class="guide-title"><?php echo htmlspecialchars($size_guide_content['title']); ?></h2>
            <div class="guide-text">
                <?php echo nl2br(htmlspecialchars($size_guide_content['content'])); ?>
            </div>
            
            <div class="tips-grid">
                <div class="tip-card">
                    <div class="tip-icon">
                        <i class="fas fa-ruler"></i>
                    </div>
                    <h3 class="tip-title">Measure Yourself</h3>
                    <p>Use a soft measuring tape for accurate measurements</p>
                </div>
                <div class="tip-card">
                    <div class="tip-icon">
                        <i class="fas fa-tshirt"></i>
                    </div>
                    <h3 class="tip-title">Check Garment Fit</h3>
                    <p>Consider the style and fit of the garment you're buying</p>
                </div>
                <div class="tip-card">
                    <div class="tip-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h3 class="tip-title">Easy Returns</h3>
                    <p>30-day return policy if the size doesn't fit perfectly</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Size Charts -->
        <div class="size-charts">
            <?php foreach ($grouped_charts as $category => $charts): ?>
            <div class="chart-category">
                <h2 class="category-title"><?php echo htmlspecialchars($category); ?> Size Chart</h2>
                
                <table class="chart-table">
                    <thead>
                        <tr>
                            <?php 
                            // Get all unique measurement types for this category
                            $measurements = [];
                            foreach ($charts as $chart) {
                                $chart_data = json_decode($chart['measurements'], true);
                                if (is_array($chart_data)) {
                                    foreach ($chart_data as $key => $value) {
                                        if (!in_array($key, $measurements)) {
                                            $measurements[] = $key;
                                        }
                                    }
                                }
                            }
                            
                            // Create header
                            echo '<th>Size</th>';
                            foreach ($measurements as $measurement) {
                                echo '<th>' . htmlspecialchars(ucwords(str_replace('_', ' ', $measurement))) . '</th>';
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($charts as $chart): 
                            $measurements = json_decode($chart['measurements'], true);
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($chart['size_label']); ?></strong></td>
                            <?php foreach ($measurements as $key => $value): ?>
                            <td>
                                <?php echo htmlspecialchars($value); ?>
                                <?php if ($chart['unit']): ?>
                                <span class="unit"><?php echo htmlspecialchars($chart['unit']); ?></span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Measurement Guide -->
        <div class="measurement-guide">
            <h2 class="measurement-title">How to Measure</h2>
            <div class="measurement-grid">
                <div>
                    <img src="https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" 
                         alt="Measurement Guide" class="measurement-image">
                </div>
                <div>
                    <ul class="measurement-list">
                        <li><strong>Chest:</strong> Measure around the fullest part of your chest, keeping the tape level</li>
                        <li><strong>Waist:</strong> Measure around your natural waistline</li>
                        <li><strong>Hips:</strong> Measure around the fullest part of your hips</li>
                        <li><strong>Inseam:</strong> Measure from your crotch to the bottom of your ankle</li>
                        <li><strong>Shoulder:</strong> Measure from the edge of one shoulder to the other</li>
                        <li><strong>Arm Length:</strong> Measure from shoulder to wrist</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Size Comparison Chart -->
        <div class="comparison-chart">
            <h2 class="measurement-title">International Size Conversion</h2>
            <table class="comparison-table">
                <thead>
                    <tr>
                        <th>US</th>
                        <th>UK</th>
                        <th>EU</th>
                        <th>AU</th>
                        <th>JP</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>XS</td>
                        <td>UK 6</td>
                        <td>EU 34</td>
                        <td>AU 8</td>
                        <td>JP 5</td>
                    </tr>
                    <tr>
                        <td>S</td>
                        <td>UK 8</td>
                        <td>EU 36</td>
                        <td>AU 10</td>
                        <td>JP 7</td>
                    </tr>
                    <tr>
                        <td>M</td>
                        <td>UK 10</td>
                        <td>EU 38</td>
                        <td>AU 12</td>
                        <td>JP 9</td>
                    </tr>
                    <tr>
                        <td>L</td>
                        <td>UK 12</td>
                        <td>EU 40</td>
                        <td>AU 14</td>
                        <td>JP 11</td>
                    </tr>
                    <tr>
                        <td>XL</td>
                        <td>UK 14</td>
                        <td>EU 42</td>
                        <td>AU 16</td>
                        <td>JP 13</td>
                    </tr>
                    <tr>
                        <td>XXL</td>
                        <td>UK 16</td>
                        <td>EU 44</td>
                        <td>AU 18</td>
                        <td>JP 15</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Us</h3>
                <p>Alas Clothing Shop offers premium quality clothing and accessories for every occasion.</p>
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
                    <li><a href="../index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
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
                </div>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Alas Clothing Shop. All rights reserved.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>