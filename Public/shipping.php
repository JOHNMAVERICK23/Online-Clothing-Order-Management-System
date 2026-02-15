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

// Get shipping content
$stmt = $pdo->query("SELECT * FROM content_management WHERE page = 'shipping' AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
$shipping_content = $stmt->fetch(PDO::FETCH_ASSOC);

// Get shipping methods
$stmt = $pdo->query("SELECT * FROM shipping_methods WHERE is_active = 1 ORDER BY display_order");
$shipping_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping & Returns - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .shipping-container {
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

        /* Content Sections */
        .content-section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 0.5rem;
        }

        .section-content {
            background: var(--light);
            border: 1px solid var(--border);
            padding: 2rem;
            border-radius: 0;
            line-height: 1.8;
        }

        /* Shipping Methods */
        .shipping-methods {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .method-card {
            background: var(--light);
            border: 1px solid var(--border);
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
        }

        .method-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
        }

        .method-icon {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        .method-title {
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .method-price {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .method-desc {
            color: var(--gray);
            line-height: 1.6;
        }

        /* Timeline */
        .timeline {
            position: relative;
            max-width: 800px;
            margin: 3rem auto;
        }

        .timeline:before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--accent);
            transform: translateX(-50%);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 3rem;
            width: 50%;
            padding: 0 2rem;
        }

        .timeline-item:nth-child(odd) {
            left: 0;
            text-align: right;
        }

        .timeline-item:nth-child(even) {
            left: 50%;
        }

        .timeline-dot {
            position: absolute;
            width: 20px;
            height: 20px;
            background: var(--accent);
            border-radius: 50%;
            top: 10px;
        }

        .timeline-item:nth-child(odd) .timeline-dot {
            right: -10px;
        }

        .timeline-item:nth-child(even) .timeline-dot {
            left: -10px;
        }

        .timeline-content {
            background: var(--light);
            border: 1px solid var(--border);
            padding: 1.5rem;
            border-radius: 0;
        }

        .timeline-title {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        /* FAQ */
        .faq-section {
            margin-top: 3rem;
        }

        .faq-item {
            margin-bottom: 1rem;
            border: 1px solid var(--border);
        }

        .faq-question {
            padding: 1.5rem;
            background: var(--light);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
            color: var(--dark);
        }

        .faq-question:hover {
            background: var(--gray-light);
        }

        .faq-answer {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: var(--gray-light);
        }

        .faq-answer.show {
            padding: 1.5rem;
            max-height: 500px;
        }

        .faq-toggle {
            font-size: 1.2rem;
            transition: transform 0.3s;
        }

        .faq-toggle.rotate {
            transform: rotate(45deg);
        }

        /* Return Policy */
        .policy-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 2rem;
        }

        .policy-card {
            background: var(--light);
            border: 1px solid var(--border);
            padding: 2rem;
            text-align: center;
        }

        .policy-icon {
            font-size: 2.5rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        .policy-title {
            font-size: 1.2rem;
            color: var(--dark);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        /* Contact Info */
        .contact-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 2rem;
        }

        .contact-card {
            text-align: center;
            padding: 2rem;
            background: var(--light);
            border: 1px solid var(--border);
        }

        .contact-icon {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .shipping-methods {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .policy-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .contact-info {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .shipping-container {
                padding: 1rem;
                padding-top: 5rem;
            }
            
            .shipping-methods {
                grid-template-columns: 1fr;
            }
            
            .timeline:before {
                left: 20px;
            }
            
            .timeline-item {
                width: 100%;
                left: 0 !important;
                text-align: left !important;
                padding-left: 50px;
                padding-right: 1rem;
            }
            
            .timeline-item:nth-child(odd) .timeline-dot {
                left: 10px;
                right: auto;
            }
            
            .policy-grid {
                grid-template-columns: 1fr;
            }
            
            .contact-info {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .section-title {
                font-size: 1.5rem;
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
            <li><a href="size_chart.php">SIZE CHART</a></li>
            <li><a href="shipping.php" class="active">SHIPPING</a></li>
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
    <div class="shipping-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Shipping & Returns</h1>
            <p class="page-subtitle">Everything you need to know about delivery and returns</p>
        </div>

        <!-- Shipping Content -->
        <?php if ($shipping_content): ?>
        <div class="content-section">
            <h2 class="section-title">Shipping Information</h2>
            <div class="section-content">
                <?php echo nl2br(htmlspecialchars($shipping_content['content'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Shipping Methods -->
        <div class="content-section">
            <h2 class="section-title">Shipping Methods</h2>
            <div class="shipping-methods">
                <?php foreach ($shipping_methods as $method): ?>
                <div class="method-card">
                    <div class="method-icon">
                        <i class="<?php echo htmlspecialchars($method['icon']); ?>"></i>
                    </div>
                    <h3 class="method-title"><?php echo htmlspecialchars($method['name']); ?></h3>
                    <div class="method-price">₱<?php echo number_format($method['cost'], 2); ?></div>
                    <p class="method-desc"><?php echo htmlspecialchars($method['description']); ?></p>
                    <p><strong>Delivery Time:</strong> <?php echo htmlspecialchars($method['delivery_time']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Shipping Timeline -->
        <div class="content-section">
            <h2 class="section-title">Order Processing Timeline</h2>
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <h3 class="timeline-title">Order Placed</h3>
                        <p>We receive your order and begin processing</p>
                        <small>Within 1 hour of order placement</small>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <h3 class="timeline-title">Order Processing</h3>
                        <p>We prepare your items for shipping</p>
                        <small>1-2 business days</small>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <h3 class="timeline-title">Order Shipped</h3>
                        <p>Your order is handed to the courier</p>
                        <small>You'll receive tracking information</small>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <h3 class="timeline-title">Out for Delivery</h3>
                        <p>Your order is on its way to you</p>
                        <small>Estimated delivery time applies</small>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <h3 class="timeline-title">Delivered</h3>
                        <p>Your order has been delivered</p>
                        <small>Please check your items upon delivery</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Return Policy -->
        <div class="content-section">
            <h2 class="section-title">Return Policy</h2>
            <div class="section-content">
                <p>We want you to be completely satisfied with your purchase. If you're not happy with your order, we accept returns within 30 days of delivery.</p>
                
                <div class="policy-grid">
                    <div class="policy-card">
                        <div class="policy-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="policy-title">30-Day Return Window</h3>
                        <p>Items can be returned within 30 days of delivery date</p>
                    </div>
                    <div class="policy-card">
                        <div class="policy-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <h3 class="policy-title">Original Condition</h3>
                        <p>Items must be unworn, unwashed, and in original packaging</p>
                    </div>
                    <div class="policy-card">
                        <div class="policy-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h3 class="policy-title">Tags Attached</h3>
                        <p>All original tags and labels must be attached</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="content-section faq-section">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>How long does shipping take?</span>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Shipping times vary based on your location and chosen shipping method. Standard shipping typically takes 3-5 business days, while express shipping takes 1-2 business days.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Do you ship internationally?</span>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, we ship to most countries worldwide. International shipping times vary from 7-21 business days depending on the destination.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Can I track my order?</span>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, once your order ships, you'll receive a tracking number via email that you can use to track your package.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>What is your return process?</span>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>To return an item, please contact our customer service to initiate the return process. Once approved, you'll receive return instructions and a return label.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFaq(this)">
                        <span>Are return shipping costs covered?</span>
                        <span class="faq-toggle">+</span>
                    </div>
                    <div class="faq-answer">
                        <p>We offer free returns for defective or incorrect items. For size exchanges or change of mind returns, return shipping costs are the customer's responsibility.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="content-section">
            <h2 class="section-title">Need Help?</h2>
            <div class="section-content">
                <p>If you have any questions about shipping or returns, our customer service team is here to help.</p>
                
                <div class="contact-info">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3>Call Us</h3>
                        <p>+1 (555) 123-4567</p>
                        <small>Mon-Fri: 9AM-6PM EST</small>
                    </div>
                    
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3>Email Us</h3>
                        <p>shipping@alasclothingshop.com</p>
                        <small>Response within 24 hours</small>
                    </div>
                    
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <h3>Live Chat</h3>
                        <p>Available during business hours</p>
                        <small>Click the chat icon below</small>
                    </div>
                </div>
            </div>
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
    <script>
        // FAQ Toggle Function
        function toggleFaq(element) {
            const answer = element.nextElementSibling;
            const toggle = element.querySelector('.faq-toggle');
            
            answer.classList.toggle('show');
            toggle.classList.toggle('rotate');
            
            // Close other FAQs
            const allFaqs = document.querySelectorAll('.faq-item');
            allFaqs.forEach(faq => {
                if (faq !== element.parentElement) {
                    const otherAnswer = faq.querySelector('.faq-answer');
                    const otherToggle = faq.querySelector('.faq-toggle');
                    otherAnswer.classList.remove('show');
                    otherToggle.classList.remove('rotate');
                }
            });
        }
    </script>
</body>
</html>