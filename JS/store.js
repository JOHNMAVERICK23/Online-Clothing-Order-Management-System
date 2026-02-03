let allProducts = [];
let currentPage = 1;
const productsPerPage = 12;
let currentView = 'grid';
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
    document.getElementById('searchInput').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            searchProducts();
        }
    });
    
    document.getElementById('searchBtn').addEventListener('click', searchProducts);
    
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
    
    document.getElementById('gridViewBtn').addEventListener('click', function() {
        setView('grid');
    });
    
    document.getElementById('listViewBtn').addEventListener('click', function() {
        setView('list');
    });
    
    document.getElementById('resetFiltersBtn').addEventListener('click', resetFilters);
    
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
    
    // FIXED: Correct path for root-level index.php
    fetch('../PROCESS/getPublicProducts.php')
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
    
    const categories = [...new Set(allProducts.map(p => p.category))].sort();
    
    categories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        categoryFilter.appendChild(option);
    });
    
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
        if (currentFilters.category && product.category !== currentFilters.category) {
            return false;
        }
        
        if (currentFilters.size && product.size) {
            const sizes = product.size.split(',').map(s => s.trim());
            if (!sizes.includes(currentFilters.size)) {
                return false;
            }
        }
        
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
    
    filtered = sortProducts(filtered);
    
    updateProductCount(filtered.length);
    
    const totalPages = Math.ceil(filtered.length / productsPerPage);
    const startIndex = (currentPage - 1) * productsPerPage;
    const endIndex = startIndex + productsPerPage;
    const paginatedProducts = filtered.slice(startIndex, endIndex);
    
    displayProducts(paginatedProducts);
    
    updatePagination(totalPages, filtered.length);
    
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
        const imageUrl = getProductImageUrl(product);
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
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-primary btn-sm btn-cart" onclick="addToCart(${product.id})">
                                    <i class="bi bi-cart-plus"></i> 
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

// CRITICAL FIX: Properly handle image URLs
function getProductImageUrl(product) {
    // If product has an image URL stored in database
    if (product.image_url && product.image_url.trim() !== '') {
        return product.image_url;
    }
    
    // Return placeholder if no image
    return getPlaceholderImage(product.category);
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
    const categoryText = category || 'Product';
    
    const localPlaceholder = 'data:image/svg+xml;base64,' + btoa(`
        <svg xmlns="http://www.w3.org/2000/svg" width="400" height="400">
            <rect width="400" height="400" fill="#f0f0f0"/>
            <rect width="380" height="380" x="10" y="10" fill="#ffffff" stroke="#cccccc" stroke-width="2"/>
            <text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#666666" font-family="Arial, sans-serif" font-size="16">
                ${categoryText}
            </text>
            <text x="50%" y="60%" text-anchor="middle" fill="#999999" font-family="Arial, sans-serif" font-size="12">
                No Image Available
            </text>
        </svg>
    `);
    
    return localPlaceholder;
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
    
    html += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>
        </li>
    `;
    
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
    document.getElementById('productModalImage').src = getProductImageUrl(product);
    document.getElementById('productModalName').textContent = product.product_name;
    document.getElementById('productModalPrice').textContent = `₱${parseFloat(product.price).toFixed(2)}`;
    
    const stockStatus = getStockStatus(product.quantity);
    document.getElementById('productModalStock').textContent = stockStatus.text;
    document.getElementById('productModalStock').className = `badge bg-${stockStatus.class === 'text-success' ? 'success' : stockStatus.class === 'text-warning' ? 'warning' : 'danger'}`;
    
    document.getElementById('productModalDescription').textContent = product.description || 'No description available.';
    document.getElementById('productModalCategory').textContent = product.category;
    document.getElementById('productModalSizes').textContent = product.size || 'N/A';
    document.getElementById('productModalColors').textContent = product.color || 'N/A';
    
    const addToCartBtn = document.getElementById('addToCartBtn');
    addToCartBtn.onclick = function() {
        addToCart(productId);
        modal.hide();
    };
    
    modal.show();
}

function setupCart() {
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
            image: getProductImageUrl(product),
            maxQuantity: product.quantity
        });
    }
    
    localStorage.setItem('cart', JSON.stringify(cart));
    
    const cartToast = new bootstrap.Toast(document.getElementById('cartToast'));
    cartToast.show();
    
    updateCartCount();
}

function updateCartCount() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
        cartCountElement.textContent = totalItems;
    }
}

let btn = document.getElementById("login-btn");
if (btn) {
    btn.onclick = function () {
        alert('wala panglan to diko pa naayus');
    };
}

window.changePage = changePage;
window.quickView = quickView;
window.addToCart = addToCart;