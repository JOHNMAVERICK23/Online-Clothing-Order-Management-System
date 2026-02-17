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
$dbname = 'clothing_management_system'; // Changed from 'clothing_shop'
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get announcements
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE is_active = 1 ORDER BY CASE WHEN is_pinned = 1 THEN 0 ELSE 1 END, created_at DESC");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured announcements (pinned)
$pinned_announcements = array_filter($announcements, function($announcement) {
    return $announcement['is_pinned'] == 1;
});

// Get regular announcements (not pinned)
$regular_announcements = array_filter($announcements, function($announcement) {
    return $announcement['is_pinned'] == 0;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Alas Clothing Shop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .announcements-container {
            max-width: 1200px;
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

        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 0.8rem 1.5rem;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .filter-tab:hover {
            border-color: var(--dark);
        }

        .filter-tab.active {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        /* Featured Announcements */
        .featured-section {
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

        .featured-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .featured-card {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
            overflow: hidden;
            transition: all 0.3s;
            position: relative;
        }

        .featured-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
        }

        .pin-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--accent);
            color: var(--light);
            padding: 0.3rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 1;
        }

        .featured-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--gray-light);
        }

        .featured-content {
            padding: 1.5rem;
        }

        .announcement-category {
            display: inline-block;
            background: var(--gray-light);
            color: var(--gray);
            padding: 0.3rem 0.8rem;
            border-radius: 0;
            font-size: 0.8rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .announcement-title {
            font-size: 1.3rem;
            color: var(--dark);
            margin-bottom: 1rem;
            font-weight: 500;
            line-height: 1.4;
        }

        .announcement-excerpt {
            color: var(--gray);
            line-height: 1.6;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .announcement-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--gray);
            font-size: 0.85rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        /* Regular Announcements */
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .announcement-card {
            background: var(--light);
            border: 1px solid var(--border);
            border-radius: 0;
            overflow: hidden;
            transition: all 0.3s;
        }

        .announcement-card:hover {
            border-color: var(--dark);
        }

        .announcement-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .announcement-header-content {
            flex: 1;
        }

        .announcement-header h3 {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .announcement-date {
            color: var(--gray);
            font-size: 0.85rem;
        }

        .announcement-toggle {
            font-size: 1.2rem;
            color: var(--dark);
            transition: transform 0.3s;
        }

        .announcement-toggle.rotate {
            transform: rotate(45deg);
        }

        .announcement-body {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .announcement-body.show {
            padding: 1.5rem;
            max-height: 1000px;
        }

        .announcement-content {
            line-height: 1.8;
            color: var(--dark);
        }

        .announcement-content img {
            max-width: 100%;
            height: auto;
            margin: 1rem 0;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border);
            background: var(--light);
            color: var(--dark);
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }

        .page-btn:hover {
            border-color: var(--dark);
            background: var(--dark);
            color: var(--light);
        }

        .page-btn.active {
            background: var(--dark);
            color: var(--light);
            border-color: var(--dark);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: var(--gray-light);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
            font-weight: 300;
        }

        /* Category Badges */
        .category-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            background: var(--gray-light);
            color: var(--gray);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .category-badge.sale {
            background: #fff0f0;
            color: #d9534f;
        }

        .category-badge.new {
            background: #f0f9ff;
            color: #31708f;
        }

        .category-badge.event {
            background: #f0fff4;
            color: #5cb85c;
        }

        .category-badge.update {
            background: #fff8f0;
            color: #f0ad4e;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .featured-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .announcements-container {
                padding: 1rem;
                padding-top: 5rem;
            }
            
            .filter-tabs {
                flex-direction: column;
            }
            
            .filter-tab {
                text-align: center;
            }
            
            .announcement-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }

        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.8rem;
            }
            
            .featured-image {
                height: 150px;
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
            <li><a href="shipping.php">SHIPPING</a></li>
            <li><a href="announcements.php" class="active">ANNOUNCEMENTS</a></li>
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
    <div class="announcements-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Latest Announcements</h1>
            <p class="page-subtitle">Stay updated with our latest news, sales, and updates</p>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-tab active" onclick="filterAnnouncements('all')">All Updates</button>
            <button class="filter-tab" onclick="filterAnnouncements('sale')">Sales & Promotions</button>
            <button class="filter-tab" onclick="filterAnnouncements('new')">New Arrivals</button>
            <button class="filter-tab" onclick="filterAnnouncements('event')">Events</button>
            <button class="filter-tab" onclick="filterAnnouncements('update')">Store Updates</button>
        </div>

        <!-- Featured Announcements -->
        <?php if (!empty($pinned_announcements)): ?>
        <div class="featured-section">
            <h2 class="section-title">Featured Announcements</h2>
            <div class="featured-grid">
                <?php foreach ($pinned_announcements as $announcement): ?>
                <div class="featured-card" data-category="<?php echo htmlspecialchars($announcement['category']); ?>">
                    <span class="pin-badge">
                        <i class="fas fa-thumbtack"></i> Pinned
                    </span>
                    
                    <?php if ($announcement['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($announcement['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($announcement['title']); ?>" 
                         class="featured-image">
                    <?php endif; ?>
                    
                    <div class="featured-content">
                        <span class="announcement-category category-badge <?php echo htmlspecialchars($announcement['category']); ?>">
                            <?php echo htmlspecialchars(ucfirst($announcement['category'])); ?>
                        </span>
                        
                        <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                        
                        <div class="announcement-excerpt">
                            <?php echo htmlspecialchars(substr($announcement['content'], 0, 150)); ?>...
                        </div>
                        
                        <div class="announcement-meta">
                            <span><i class="far fa-calendar"></i> <?php echo date('F d, Y', strtotime($announcement['created_at'])); ?></span>
                            <button class="read-more-btn" onclick="viewAnnouncement(<?php echo $announcement['id']; ?>)">
                                Read More <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Regular Announcements -->
        <div class="regular-section">
            <h2 class="section-title">Recent Updates</h2>
            
            <?php if (empty($regular_announcements)): ?>
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <h3>No announcements yet</h3>
                <p>Check back later for updates!</p>
            </div>
            <?php else: ?>
            <div class="announcements-list">
                <?php foreach ($regular_announcements as $announcement): ?>
                <div class="announcement-card" data-category="<?php echo htmlspecialchars($announcement['category']); ?>">
                    <div class="announcement-header" onclick="toggleAnnouncement(this)">
                        <div class="announcement-header-content">
                            <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <div class="announcement-date">
                                <i class="far fa-calendar"></i> <?php echo date('F d, Y', strtotime($announcement['created_at'])); ?>
                                <span class="category-badge <?php echo htmlspecialchars($announcement['category']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($announcement['category'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="announcement-toggle">+</div>
                    </div>
                    
                    <div class="announcement-body">
                        <div class="announcement-content">
                            <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                            
                            <?php if ($announcement['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($announcement['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($announcement['title']); ?>"
                                 style="max-width: 100%; height: auto; margin: 1rem 0;">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="pagination">
            <button class="page-btn active">1</button>
            <button class="page-btn">2</button>
            <button class="page-btn">3</button>
            <button class="page-btn">4</button>
            <button class="page-btn">5</button>
        </div>
    </div>

    <!-- Announcement Modal -->
    <div class="modal" id="announcementModal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 id="modalTitle"></h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
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
        // Filter announcements by category
        function filterAnnouncements(category) {
            const tabs = document.querySelectorAll('.filter-tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            const featuredCards = document.querySelectorAll('.featured-card');
            const announcementCards = document.querySelectorAll('.announcement-card');
            
            if (category === 'all') {
                featuredCards.forEach(card => card.style.display = 'block');
                announcementCards.forEach(card => card.style.display = 'flex');
            } else {
                featuredCards.forEach(card => {
                    card.style.display = card.dataset.category === category ? 'block' : 'none';
                });
                announcementCards.forEach(card => {
                    card.style.display = card.dataset.category === category ? 'flex' : 'none';
                });
            }
        }

        // Toggle announcement expansion
        function toggleAnnouncement(element) {
            const body = element.nextElementSibling;
            const toggle = element.querySelector('.announcement-toggle');
            
            body.classList.toggle('show');
            toggle.classList.toggle('rotate');
            
            // Close other announcements
            const allAnnouncements = document.querySelectorAll('.announcement-card');
            allAnnouncements.forEach(card => {
                if (card !== element.parentElement) {
                    const otherBody = card.querySelector('.announcement-body');
                    const otherToggle = card.querySelector('.announcement-toggle');
                    otherBody.classList.remove('show');
                    otherToggle.classList.remove('rotate');
                }
            });
        }

        // View full announcement
        function viewAnnouncement(id) {
            // In a real app, you would fetch the announcement details via AJAX
            // For now, we'll show the first announcement's content
            const modal = document.getElementById('announcementModal');
            const title = document.getElementById('modalTitle');
            const content = document.getElementById('modalContent');
            
            // Find the announcement in the page
            const announcementCard = document.querySelector(`.featured-card:has(.read-more-btn[onclick*="${id}"])`);
            if (announcementCard) {
                const announcementTitle = announcementCard.querySelector('.announcement-title').textContent;
                const announcementExcerpt = announcementCard.querySelector('.announcement-excerpt').textContent;
                
                title.textContent = announcementTitle;
                content.innerHTML = `
                    <p>${announcementExcerpt}</p>
                    <p>This is the full content of the announcement. In a real application, 
                    this would be fetched from the database based on the announcement ID.</p>
                    <p>Additional details and information would be displayed here.</p>
                `;
                
                modal.style.display = 'flex';
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('announcementModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('announcementModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Initialize announcements
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers to read more buttons
            document.querySelectorAll('.read-more-btn').forEach(btn => {
                btn.onclick = function() {
                    const id = this.getAttribute('onclick').match(/\d+/)[0];
                    viewAnnouncement(id);
                };
            });
        });
    </script>
</body>
</html>