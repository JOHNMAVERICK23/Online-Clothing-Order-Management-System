<?php
// Public store page - no session required
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clothing Store - Browse Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../CSS/store.css">
    <link rel="stylesheet" href="../CSS/style.css">
    <style>
        .cart-icon-container {
            position: relative;
            display: inline-block;
        }
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }
        .cart-icon-link {
            color: white;
            text-decoration: none;
            font-size: 20px;
            transition: color 0.3s;
        }
        .cart-icon-link:hover {
            color: #3498db;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="store.php">
                <i class="bi bi-shop"></i> Alas Ace
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="store.php">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="categoryDropdown" role="button" data-bs-toggle="dropdown">
                            Categories
                        </a>
                        <ul class="dropdown-menu" id="categoryMenu">
                            <li><a class="dropdown-item" href="#">Loading...</a></li>
                        </ul>
                    </li>
                </ul>
                <div class="d-flex align-items-center gap-3">
                    <div class="input-group me-3" style="width: 250px;">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search products...">
                        <button class="btn btn-outline-light" type="button" id="searchBtn">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    
                    <!-- SHOPPING CART ICON -->
                    <div class="cart-icon-container">
                        <a href="cart.php" class="cart-icon-link" title="Shopping Cart">
                            <i class="bi bi-cart3"></i>
                            <span class="cart-badge" id="cartCount">0</span>
                        </a>
                    </div>
                    
                    <a href="../login.html" class="btn btn-light btn-sm">
                        <i class="bi bi-person"></i> Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">Discover Your Style</h1>
                <p class="hero-subtitle">Shop the latest trends in clothing and accessories</p>
                <a href="#products" class="btn btn-primary btn-lg">Shop Now</a>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="container my-5" id="products">
        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card filter-card">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <select class="form-select" id="categoryFilter">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="sizeFilter">
                                    <option value="">All Sizes</option>
                                    <option value="XS">XS</option>
                                    <option value="S">S</option>
                                    <option value="M">M</option>
                                    <option value="L">L</option>
                                    <option value="XL">XL</option>
                                    <option value="XXL">XXL</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select" id="sortFilter">
                                    <option value="newest">Newest First</option>
                                    <option value="price_low">Price: Low to High</option>
                                    <option value="price_high">Price: High to Low</option>
                                    <option value="name">Name: A to Z</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card filter-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span id="productCount">Loading products...</span>
                            <div class="btn-group">
                                <button class="btn btn-outline-secondary active" id="gridViewBtn" title="Grid View">
                                    <i class="bi bi-grid-3x3-gap"></i>
                                </button>
                                <button class="btn btn-outline-secondary" id="listViewBtn" title="List View">
                                    <i class="bi bi-list"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div id="productsContainer" class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
            <!-- Products will be loaded here -->
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading products...</p>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-5 d-none">
            <i class="bi bi-emoji-frown display-1 text-muted"></i>
            <h3 class="mt-3">No products found</h3>
            <p class="text-muted">Try adjusting your search or filters</p>
            <button class="btn btn-primary" id="resetFiltersBtn">Reset Filters</button>
        </div>

        <!-- Pagination -->
        <nav aria-label="Product pagination" class="mt-5">
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Pagination will be loaded here -->
            </ul>
        </nav>
    </main>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Clothing Store</h5>
                    <p>Your one-stop shop for fashionable clothing and accessories.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="store.php" class="text-white-50 text-decoration-none">Home</a></li>
                        <li><a href="#products" class="text-white-50 text-decoration-none">Products</a></li>
                        <li><a href="cart.php" class="text-white-50 text-decoration-none">Cart</a></li>
                        <li><a href="../login.html" class="text-white-50 text-decoration-none">Staff Login</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Info</h5>
                    <p>
                        <i class="bi bi-geo-alt"></i> 123 Fashion Street, Manila<br>
                        <i class="bi bi-telephone"></i> +63 917 123 4567<br>
                        <i class="bi bi-envelope"></i> support@alascape.com
                    </p>
                </div>
            </div>
            <hr class="bg-light">
            <div class="text-center">
                <p class="mb-0">&copy; 2024 Alas Ace. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Product Quick View Modal -->
    <div class="modal fade" id="quickViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <img id="productModalImage" src="" class="img-fluid rounded" alt="">
                        </div>
                        <div class="col-md-6">
                            <h3 id="productModalName"></h3>
                            <div class="mb-3">
                                <span class="h4 text-primary" id="productModalPrice"></span>
                                <span class="badge bg-success ms-2" id="productModalStock"></span>
                            </div>
                            <p id="productModalDescription"></p>
                            <div class="mb-3">
                                <strong>Category:</strong> <span id="productModalCategory"></span>
                            </div>
                            <div class="mb-3">
                                <strong>Available Sizes:</strong> <span id="productModalSizes"></span>
                            </div>
                            <div class="mb-3">
                                <strong>Colors:</strong> <span id="productModalColors"></span>
                            </div>
                            <div class="mt-4">
                                <button class="btn btn-primary btn-lg" id="addToCartBtn">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                                <button class="btn btn-outline-secondary btn-lg ms-2" data-bs-dismiss="modal">
                                    Continue Shopping
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Toast Notification -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050">
        <div id="cartToast" class="toast" role="alert">
            <div class="toast-header">
                <i class="bi bi-cart-check text-success me-2"></i>
                <strong class="me-auto">Added to Cart</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                Product added to cart successfully! <a href="cart.php" style="color: #3498db; font-weight: 600;">View Cart</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../JS/store.js"></script>
    <script>
        // Update cart count on page load
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cartCount').textContent = totalItems;
        }

        // Update cart count whenever it changes
        window.addEventListener('storage', updateCartCount);
        document.addEventListener('DOMContentLoaded', updateCartCount);
    </script>
</body>
</html>