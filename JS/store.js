let allProducts = [];
let currentPage = 1;
const productsPerPage = 12;
let currentView = 'grid'; // 'grid' or 'list'
let currentFilters = {
    category: '',
    size: '',
    sort: 'newest',
    search: ''
};

document.addEventListener('DOMContentLoaded', function() {
    loadProducts();
    setupEventListeners();
    setupCart();
});

function setupEventListeners() {
    // Search
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            searchProducts();
        }
    });
    
    document.getElementById('searchBtn').addEventListener('click', searchProducts);
    
    // Filters
    document.getElementById('categoryFilter').addEventListener('change', function(e) {
        currentFilters.category = e.target.value;
        currentPage = 1;
        filterProducts();
    });
    
    document.getElementById('sizeFilter').addEventListener('change', function(e) {
        currentFilters.size = e.target.value;
        currentPage = 1;
        filterProducts();
    });
    
    document.getElementById('sortFilter').addEventListener('change', function(e) {
        currentFilters.sort = e.target.value;
        currentPage = 1;
        filterProducts();
    });
    
    // View toggle
    document.getElementById('gridViewBtn').addEventListener('click', function() {
        setView('grid');
    });
    
    document.getElementById('listViewBtn').addEventListener('click', function() {
        setView('list');
    });
    
    // Reset filters
    document.getElementById('resetFiltersBtn').addEventListener('click', resetFilters);
    
    // Quick view modal
    const quickViewModal = document.getElementById('quickViewModal');
    if (quickViewModal) {
        quickViewModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('productModalTitle').textContent = '';
            document.getElementById('productModalImage').src = '';
            document.getElementById('productModalName').textContent = '';
            document.getElementById('productModalPrice').textContent = '';
            document.getElementById('productModalStock').textContent = '';
            document.getElementById('productModalDescription').textContent = '';
            document.getElementById('productModalCategory').textContent = '';
            document.getElementById('productModalSizes').textContent = '';
            document.getElementById('productModalColors').textContent = '';
        });
    }
}

function loadProducts() {
    showLoading(true);
    
    fetch('PROCESS/getPublicProducts.php')
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                allProducts = data.products;
                populateCategories();
                filterProducts();
            } else {
                showEmptyState('Failed to load products: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error loading products:', error);
            showEmptyState('Failed to load products. Please try again later.');
        })
        .finally(() => {
            showLoading(false);
        });
}

function populateCategories() {
    const categoryFilter = document.getElementById('categoryFilter');
    const categoryMenu = document.getElementById('categoryMenu');
    
    if (!categoryFilter || !categoryMenu) return;
    
    // Get unique categories
    const categories = [...new Set(allProducts.map(p => p.category))].sort();
    
    // Update category filter dropdown
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categoryFilter.appendChild(option);
    });
    
    // Update category menu
    categoryMenu.innerHTML = '<li><a class="dropdown-item" href="#" data-category="">All Categories</a></li>';
    categories.forEach(category => {
        const li = document.createElement('li');
        const a = document.createElement('a');
        a.className = 'dropdown-item';
        a.href = '#';
        a.textContent = category;
        a.dataset.category = category;
        a.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('categoryFilter').value = category;
            currentFilters.category = category;
            currentPage = 1;
            filterProducts();
        });
        li.appendChild(a);
        categoryMenu.appendChild(li);
    });
}

function filterProducts() {
    let filtered = allProducts.filter(product => {
        // Filter by category
        if (currentFilters.category && product.category !== currentFilters.category) {
            return false;
        }
        
        // Filter by size
        if (currentFilters.size && product.size) {
            const sizes = product.size.split(',').map(s => s.trim());
            if (!sizes.includes(currentFilters.size)) {
                return false;
            }
        }
        
        // Filter by search
        if (currentFilters.search) {
            const searchTerm = currentFilters.search.toLowerCase();
            const searchFields = [
                product.product_name,
                product.description,
                product.category,
                product.color
            ].join(' ').toLowerCase();
            
            if (!searchFields.includes(searchTerm)) {
                return false;
            }
        }
        
        return true;
    });
    
    // Apply sorting
    filtered = sortProducts(filtered);
    
    // Update product count
    updateProductCount(filtered.length);
    
    // Paginate
    const totalPages = Math.ceil(filtered.length / productsPerPage);
    const startIndex = (currentPage - 1) * productsPerPage;
    const endIndex = startIndex + productsPerPage;
    const paginatedProducts = filtered.slice(startIndex, endIndex);
    
    // Display products
    displayProducts(paginatedProducts);
    
    // Update pagination
    updatePagination(totalPages, filtered.length);
    
    // Show empty state if no products
    if (filtered.length === 0) {
        showEmptyState('No products match your filters. Try adjusting your search criteria.');
    } else {
        document.getElementById('emptyState').classList.add('d-none');
    }
}

function sortProducts(products) {
    switch (currentFilters.sort) {
        case 'price_low':
            return [...products].sort((a, b) => a.price - b.price);
        case 'price_high':
            return [...products].sort((a, b) => b.price - a.price);
        case 'name':
            return [...products].sort((a, b) => a.product_name.localeCompare(b.product_name));
        case 'newest':
        default:
            return [...products].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    }
}

function displayProducts(products) {
    const container = document.getElementById('productsContainer');
    if (!container) return;
    
    if (products.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    const viewClass = currentView === 'list' ? 'list-view' : '';
    
    products.forEach(product => {
        const stockStatus = getStockStatus(product.quantity);
        const imageUrl = product.image_url || getPlaceholderImage(product.category);
        const sizes = product.size ? product.size.split(',').map(s => s.trim()) : [];
        const colors = product.color ? product.color.split(',').map(c => c.trim()) : [];
        
        html += `
            <div class="col${currentView === 'list' ? '-12' : ''}">
                <div class="card product-card ${viewClass}">
                    <img src="${imageUrl}" 
                         class="card-img-top" 
                         alt="${product.product_name}"
                         onerror="this.src='${getPlaceholderImage(product.category)}'">
                    <div class="card-body">
                        <h5 class="card-title">${product.product_name}</h5>
                        <p class="card-text text-muted small">${truncateText(product.description || 'No description', 100)}</p>
                        
                        <div class="product-meta">
                            <span class="category">${product.category}</span>
                            <span class="stock ${stockStatus.class}">${stockStatus.text}</span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="product-price">₱${parseFloat(product.price).toFixed(2)}</span>
                            <div class="product-actions">
                                <button class="btn btn-outline-primary btn-sm" onclick="quickView(${product.id})">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button class="btn btn-primary btn-sm" onclick="addToCart(${product.id})">
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                        
                        ${currentView === 'list' ? `
                            <div class="mt-3">
                                <div class="row">
                                    <div class="col-6">
                                        <small><strong>Sizes:</strong> ${sizes.join(', ') || 'N/A'}</small>
                                    </div>
                                    <div class="col-6">
                                        <small><strong>Colors:</strong> ${colors.join(', ') || 'N/A'}</small>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function getStockStatus(quantity) {
    if (quantity <= 0) {
        return { class: 'text-danger', text: 'Out of Stock' };
    } else if (quantity <= 10) {
        return { class: 'text-warning', text: 'Low Stock' };
    } else {
        return { class: 'text-success', text: 'In Stock' };
    }
}

function getPlaceholderImage(category) {
    const placeholders = {
        'T-Shirts': 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400&h=400&fit=crop',
        'Shirts': 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=400&h=400&fit=crop',
        'Pants': 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=400&h=400&fit=crop',
        'Jeans': 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=400&h=400&fit=crop',
        'Dresses': 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=400&h=400&fit=crop',
        'Skirts': 'https://images.unsplash.com/photo-1594633313593-ba5ccbacffb1?w=400&h=400&fit=crop',
        'Jackets': 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=400&h=400&fit=crop',
        'Shoes': 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=400&h=400&fit=crop',
        'Accessories': 'https://images.unsplash.com/photo-1556306535-0f09a537f0a3?w=400&h=400&fit=crop'
    };
    
    return placeholders[category] || 'https://via.placeholder.com/400x400/cccccc/ffffff?text=Product+Image';
}

function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength) + '...';
}

function updateProductCount(count) {
    const element = document.getElementById('productCount');
    if (element) {
        element.textContent = `${count} products found`;
    }
}

function updatePagination(totalPages, totalProducts) {
    const pagination = document.getElementById('pagination');
    if (!pagination) return;
    
    if (totalPages <= 1) {
        pagination.innerHTML = '';
        return;
    }
    
    let html = '';
    
    // Previous button
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>
        </li>
    `;
    
    // Page numbers
    const maxPagesToShow = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
    
    if (endPage - startPage + 1 < maxPagesToShow) {
        startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }
    
    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
            </li>
        `;
    }
    
    // Next button
    html += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>
        </li>
    `;
    
    pagination.innerHTML = html;
}

function changePage(page) {
    currentPage = page;
    filterProducts();
    window.scrollTo({ top: document.getElementById('products').offsetTop - 100, behavior: 'smooth' });
}

function setView(view) {
    currentView = view;
    
    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    
    if (view === 'grid') {
        gridBtn.classList.add('active');
        listBtn.classList.remove('active');
    } else {
        gridBtn.classList.remove('active');
        listBtn.classList.add('active');
    }
    
    filterProducts();
}

function searchProducts() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        currentFilters.search = searchInput.value.trim().toLowerCase();
        currentPage = 1;
        filterProducts();
    }
}

function resetFilters() {
    document.getElementById('categoryFilter').value = '';
    document.getElementById('sizeFilter').value = '';
    document.getElementById('sortFilter').value = 'newest';
    document.getElementById('searchInput').value = '';
    
    currentFilters = {
        category: '',
        size: '',
        sort: 'newest',
        search: ''
    };
    
    currentPage = 1;
    filterProducts();
}

function showLoading(show) {
    const loadingState = document.getElementById('loadingState');
    const productsContainer = document.getElementById('productsContainer');
    
    if (show) {
        if (loadingState) loadingState.classList.remove('d-none');
        if (productsContainer) productsContainer.innerHTML = '';
    } else {
        if (loadingState) loadingState.classList.add('d-none');
    }
}

function showEmptyState(message) {
    const emptyState = document.getElementById('emptyState');
    const productsContainer = document.getElementById('productsContainer');
    
    if (emptyState) {
        emptyState.classList.remove('d-none');
        const messageElement = emptyState.querySelector('p');
        if (messageElement && message) {
            messageElement.textContent = message;
        }
    }
    
    if (productsContainer) {
        productsContainer.innerHTML = '';
    }
}

function quickView(productId) {
    const product = allProducts.find(p => p.id == productId);
    if (!product) return;
    
    const modal = new bootstrap.Modal(document.getElementById('quickViewModal'));
    
    document.getElementById('productModalTitle').textContent = product.product_name;
    document.getElementById('productModalImage').src = product.image_url || getPlaceholderImage(product.category);
    document.getElementById('productModalName').textContent = product.product_name;
    document.getElementById('productModalPrice').textContent = `₱${parseFloat(product.price).toFixed(2)}`;
    
    const stockStatus = getStockStatus(product.quantity);
    document.getElementById('productModalStock').textContent = stockStatus.text;
    document.getElementById('productModalStock').className = `badge bg-${stockStatus.class === 'text-success' ? 'success' : stockStatus.class === 'text-warning' ? 'warning' : 'danger'}`;
    
    document.getElementById('productModalDescription').textContent = product.description || 'No description available.';
    document.getElementById('productModalCategory').textContent = product.category;
    document.getElementById('productModalSizes').textContent = product.size || 'N/A';
    document.getElementById('productModalColors').textContent = product.color || 'N/A';
    
    // Update add to cart button
    const addToCartBtn = document.getElementById('addToCartBtn');
    addToCartBtn.onclick = function() {
        addToCart(productId);
        modal.hide();
    };
    
    modal.show();
}

function setupCart() {
    // Initialize cart in localStorage if not exists
    if (!localStorage.getItem('cart')) {
        localStorage.setItem('cart', JSON.stringify([]));
    }
}

function addToCart(productId) {
    const product = allProducts.find(p => p.id == productId);
    if (!product) return;
    
    if (product.quantity <= 0) {
        alert('This product is out of stock!');
        return;
    }
    
    let cart = JSON.parse(localStorage.getItem('cart')) || [];
    
    // Check if product already in cart
    const existingItem = cart.find(item => item.id == productId);
    
    if (existingItem) {
        if (existingItem.quantity >= product.quantity) {
            alert('Cannot add more items. Maximum stock reached.');
            return;
        }
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: product.id,
            name: product.product_name,
            price: product.price,
            quantity: 1,
            image: product.image_url || getPlaceholderImage(product.category),
            maxQuantity: product.quantity
        });
    }
    
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Show toast notification
    const cartToast = new bootstrap.Toast(document.getElementById('cartToast'));
    cartToast.show();
    
    // Update cart count in navbar if exists
    updateCartCount();
}

function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    // You can add cart count display in navbar if needed
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        cartCountElement.textContent = totalItems;
    }
}

// Make functions available globally
window.changePage = changePage;
window.quickView = quickView;
window.addToCart = addToCart;