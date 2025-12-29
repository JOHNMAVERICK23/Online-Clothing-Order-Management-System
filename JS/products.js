let allProducts = [];
let selectedImageFile = null;
let currentImageUrl = '';
let currentFilters = {
    search: '',
    category: '',
    status: ''
};

document.addEventListener('DOMContentLoaded', function() {
    setupImagePreview();
    loadProducts();
    loadProductStats();
    setupEventListeners();
});

function setupImagePreview() {
    const imageInput = document.getElementById('productImage');
    const previewDiv = document.getElementById('imagePreview');
    
    imageInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        selectedImageFile = file;
        
        if (file) {
            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                previewDiv.innerHTML = `
                    <img src="${e.target.result}" 
                         class="product-image-lg" 
                         style="border: 2px solid #ddd; border-radius: 8px;">
                    <div class="small text-muted mt-1">Preview</div>
                `;
            };
            reader.readAsDataURL(file);
        } else {
            previewDiv.innerHTML = '';
        }
    });
}

function setupEventListeners() {
    // Modal buttons
    const btnAddProduct = document.getElementById('btnAddProduct');
    const closeModal = document.getElementById('closeModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const productForm = document.getElementById('productForm');
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');
    const btnExportCSV = document.getElementById('btnExportCSV');
    const btnPrint = document.getElementById('btnPrint');
    const productModal = document.getElementById('productModal');
    
    if (btnAddProduct) btnAddProduct.addEventListener('click', openAddModal);
    if (closeModal) closeModal.addEventListener('click', closeProductModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeProductModal);
    if (productForm) productForm.addEventListener('submit', submitProduct);
    
    // Search and filters
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            currentFilters.search = e.target.value.toLowerCase();
            filterProducts();
        });
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function(e) {
            currentFilters.category = e.target.value;
            filterProducts();
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function(e) {
            currentFilters.status = e.target.value;
            filterProducts();
        });
    }
    
    // Export buttons
    if (btnExportCSV) btnExportCSV.addEventListener('click', exportToCSV);
    if (btnPrint) btnPrint.addEventListener('click', printProducts);
    
    // Close modal when clicking outside
    if (productModal) {
        productModal.addEventListener('click', function(e) {
            if (e.target.id === 'productModal') {
                closeProductModal();
            }
        });
    }
}

function getProductImage(product) {
    if (product.image_url && product.image_url.trim() !== '') {
        return product.image_url;
    }
    
    // Return placeholder based on category
    const placeholders = {
        'T-Shirts': 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=150&h=150&fit=crop',
        'Shirts': 'https://images.unsplash.com/photo-1596755094514-f87e34085b2c?w=150&h=150&fit=crop',
        'Pants': 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=150&h=150&fit=crop',
        'Jeans': 'https://images.unsplash.com/photo-1542272604-787c3835535d?w=150&h=150&fit=crop',
        'Dresses': 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?w=150&h=150&fit=crop',
        'Skirts': 'https://images.unsplash.com/photo-1594633313593-ba5ccbacffb1?w=150&h=150&fit=crop',
        'Jackets': 'https://images.unsplash.com/photo-1551028719-00167b16eac5?w=150&h=150&fit=crop',
        'Shoes': 'https://images.unsplash.com/photo-1549298916-b41d501d3772?w=150&h=150&fit=crop',
        'Accessories': 'https://images.unsplash.com/photo-1556306535-0f09a537f0a3?w=150&h=150&fit=crop'
    };
    
    return placeholders[product.category] || 
           'https://via.placeholder.com/150/cccccc/ffffff?text=' + encodeURIComponent(product.category);
}

function loadProducts() {
    showLoading();
    
    fetch('../PROCESS/getProducts.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allProducts = data.products;
                filterProducts(); // Apply any existing filters
            } else {
                Toast.error('Failed to load products');
                updateProductCount(0);
            }
        })
        .catch(error => {
            Toast.error('Error loading products');
            console.error('Error:', error);
            updateProductCount(0);
        });
}

function loadProductStats() {
    fetch('../PROCESS/getProductStats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                animateCounter('totalProducts', data.totalProducts);
                animateCounter('activeProducts', data.activeProducts);
                animateCounter('lowStock', data.lowStock);
                
                // Format total value
                const totalValue = parseFloat(data.totalValue) || 0;
                document.getElementById('totalValue').textContent = 
                    '₱' + totalValue.toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
            }
        })
        .catch(error => {
            console.error('Error loading stats:', error);
        });
}

function filterProducts() {
    let filtered = allProducts.filter(product => {
        const matchesSearch = !currentFilters.search || 
            product.product_name.toLowerCase().includes(currentFilters.search) ||
            product.description.toLowerCase().includes(currentFilters.search);
        
        const matchesCategory = !currentFilters.category || 
            product.category === currentFilters.category;
        
        const matchesStatus = !currentFilters.status || 
            product.status === currentFilters.status;
        
        return matchesSearch && matchesCategory && matchesStatus;
    });
    
    displayProducts(filtered);
}

function displayProducts(products) {
    const tbody = document.getElementById('productsTable');
    
    if (products.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" style="text-align: center; padding: 40px;">
                    <i class="bi bi-box-seam" style="font-size: 48px; color: #ddd; display: block; margin-bottom: 15px;"></i>
                    <h4 style="color: #666;">No products found</h4>
                    <p class="text-muted">Try adjusting your search or filters</p>
                    <button class="btn-primary mt-2" id="btnAddFirstProduct">
                        <i class="bi bi-plus-circle"></i> Add Your First Product
                    </button>
                </td>
            </tr>
        `;
        
        // Add event listener to the button
        setTimeout(() => {
            const btn = document.getElementById('btnAddFirstProduct');
            if (btn) {
                btn.addEventListener('click', openAddModal);
            }
        }, 100);
        
        updateProductCount(0);
        return;
    }
    
    let html = '';
    products.forEach(product => {
        const stockBadge = getStockBadge(product.quantity);
        const statusBadge = product.status === 'active' ? 
            '<span class="badge badge-active">Active</span>' : 
            '<span class="badge badge-inactive">Inactive</span>';
        
        const imageUrl = getProductImage(product); // USE THE FUNCTION HERE
        const sizes = product.size ? product.size.split(',') : [];
        const colors = product.color ? product.color.split(',') : [];
        
        let sizeHtml = sizes.map(size => 
            `<span class="size-badge">${size.trim()}</span>`
        ).join(' ');
        
        let colorHtml = colors.map(color => 
            `<span class="color-indicator" style="background-color: ${color.trim()};" title="${color.trim()}"></span>`
        ).join('');
        
        html += `
            <tr>
                <td>
                    <img src="${imageUrl}" 
                         alt="${product.product_name}" 
                         class="product-image"
                         onerror="this.src='https://via.placeholder.com/150?text=No+Image'">
                </td>
                <td>
                    <strong>${product.product_name}</strong>
                    <div class="text-muted small">${product.description ? (product.description.substring(0, 50) + (product.description.length > 50 ? '...' : '')) : 'No description'}</div>
                </td>
                <td>${product.category}</td>
                <td>
                    <strong>₱${parseFloat(product.price).toFixed(2)}</strong>
                </td>
                <td>
                    ${stockBadge}
                    <div class="small text-muted">${product.quantity} units</div>
                </td>
                <td>
                    ${sizeHtml}
                    ${colorHtml}
                </td>
                <td>${statusBadge}</td>
                <td>${formatDate(product.created_at)}</td>
                <td>
                    <div class="table-actions">
                        <button class="btn-warning btn-sm" onclick="editProduct(${product.id})">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="btn-primary btn-sm" onclick="viewProduct(${product.id})">
                            <i class="bi bi-eye"></i> View
                        </button>
                        <button class="btn-danger btn-sm" onclick="deleteProduct(${product.id})">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    updateProductCount(products.length);
}

function getStockBadge(quantity) {
    if (quantity <= 0) {
        return '<span class="badge badge-outstock">Out of Stock</span>';
    } else if (quantity <= 10) {
        return '<span class="badge badge-lowstock">Low Stock</span>';
    } else {
        return '<span class="badge badge-instock">In Stock</span>';
    }
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

function updateProductCount(count) {
    const totalCount = allProducts.length;
    const showingText = currentFilters.search || currentFilters.category || currentFilters.status ?
        `Showing ${count} of ${totalCount} products` :
        `Total: ${count} products`;
    
    document.getElementById('productCount').textContent = showingText;
}

function showLoading() {
    const tbody = document.getElementById('productsTable');
    tbody.innerHTML = `
        <tr>
            <td colspan="9" style="text-align: center; padding: 30px;">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                Loading products...
            </td>
        </tr>
    `;
}

function animateCounter(elementId, targetValue) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const duration = 800;
    const step = 20;
    const totalSteps = duration / step;
    const increment = targetValue / totalSteps;
    let current = 0;
    let stepCount = 0;
    
    const timer = setInterval(() => {
        stepCount++;
        current += increment;
        if (stepCount >= totalSteps) {
            current = targetValue;
            clearInterval(timer);
        }
        element.textContent = Math.round(current);
    }, step);
}

// Modal Functions
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Product';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('status').value = 'active';
    document.getElementById('currentImage').value = '';
    document.getElementById('imagePreview').innerHTML = '';
    selectedImageFile = null;
    currentImageUrl = '';
    
    // Reset file input
    document.getElementById('productImage').value = '';
    
    document.getElementById('productModal').classList.add('active');
}


function editProduct(id) {
    const product = allProducts.find(p => p.id == id);
    if (!product) return;
    
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.product_name;
    document.getElementById('description').value = product.description || '';
    document.getElementById('category').value = product.category;
    document.getElementById('price').value = product.price;
    document.getElementById('quantity').value = product.quantity;
    document.getElementById('status').value = product.status;
    document.getElementById('size').value = product.size || '';
    document.getElementById('color').value = product.color || '';
    
    // Handle image preview for editing
    currentImageUrl = product.image_url || '';
    document.getElementById('currentImage').value = currentImageUrl;
    
    const previewDiv = document.getElementById('imagePreview');
    if (currentImageUrl) {
        previewDiv.innerHTML = `
            <img src="${currentImageUrl}" 
                 class="product-image-lg" 
                 style="border: 2px solid #ddd; border-radius: 8px;"
                 onerror="this.src='https://via.placeholder.com/150?text=No+Image'">
            <div class="small text-muted mt-1">Current Image</div>
        `;
    } else {
        previewDiv.innerHTML = '';
    }
    
    // Reset file input
    document.getElementById('productImage').value = '';
    selectedImageFile = null;
    
    document.getElementById('productModal').classList.add('active');
}

function viewProduct(id) {
    const product = allProducts.find(p => p.id == id);
    if (!product) return;
    
    // You can create a detailed view modal here
    alert(`Product Details:\n\nName: ${product.product_name}\nCategory: ${product.category}\nPrice: ₱${product.price}\nQuantity: ${product.quantity}\nStatus: ${product.status}`);
}

function closeProductModal() {
    document.getElementById('productModal').classList.remove('active');
}

async function submitProduct(e) {
    e.preventDefault();
    
    // Upload image first if selected
    let imageUrl = document.getElementById('currentImage').value;
    
    if (selectedImageFile) {
        try {
            const uploadedUrl = await uploadProductImage(selectedImageFile);
            if (uploadedUrl) {
                imageUrl = uploadedUrl;
            }
        } catch (error) {
            Toast.error('Failed to upload image: ' + error.message);
            return;
        }
    }
    
    // Continue with form submission...
    const productId = document.getElementById('productId').value;
    const productName = document.getElementById('productName').value.trim();
    const description = document.getElementById('description').value.trim();
    const category = document.getElementById('category').value;
    const price = document.getElementById('price').value;
    const quantity = document.getElementById('quantity').value;
    const status = document.getElementById('status').value;
    const size = document.getElementById('size').value;
    const color = document.getElementById('color').value.trim();
    
    // Validation
    if (!productName || !category || !price || !quantity) {
        Toast.error('Please fill in all required fields');
        return;
    }
    
    const formData = new FormData();
    formData.append('id', productId);
    formData.append('product_name', productName);
    formData.append('description', description);
    formData.append('category', category);
    formData.append('price', price);
    formData.append('quantity', quantity);
    formData.append('status', status);
    formData.append('size', size);
    formData.append('color', color);
    formData.append('image_url', imageUrl);
    
    const url = productId ? '../PROCESS/updateProduct.php' : '../PROCESS/saveProduct.php';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Toast.success(data.message);
            closeProductModal();
            loadProducts();
            loadProductStats();
        } else {
            Toast.error(data.message || 'Error saving product');
        }
    })
    .catch(error => {
        Toast.error('Network error. Please try again.');
        console.error('Error:', error);
    });
}

function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    
    const formData = new FormData();
    formData.append('id', id);
    
    fetch('../PROCESS/deleteProduct.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Toast.success(data.message);
            loadProducts();
            loadProductStats();
        } else {
            Toast.error(data.message || 'Error deleting product');
        }
    })
    .catch(error => {
        Toast.error('Network error. Please try again.');
        console.error('Error:', error);
    });
}

// Export Functions
function exportToCSV() {
    let csvContent = "data:text/csv;charset=utf-8,";
    
    // Headers
    const headers = ["ID", "Product Name", "Category", "Price", "Quantity", "Status", "Created At"];
    csvContent += headers.join(",") + "\n";
    
    // Data
    allProducts.forEach(product => {
        const row = [
            product.id,
            `"${product.product_name.replace(/"/g, '""')}"`,
            product.category,
            product.price,
            product.quantity,
            product.status,
            product.created_at
        ];
        csvContent += row.join(",") + "\n";
    });
    
    // Create download link
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "products_" + new Date().toISOString().split('T')[0] + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Toast.success('CSV exported successfully');
}

async function uploadProductImage(file) {
    const formData = new FormData();
    formData.append('image', file);
    
    const response = await fetch('../PROCESS/uploadImage.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    
    if (data.success) {
        return data.imageUrl;
    } else {
        throw new Error(data.message);
    }
}

function printProducts() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Product List - ${new Date().toLocaleDateString()}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #333; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .total { font-weight: bold; margin-top: 20px; }
                </style>
            </head>
            <body>
                <h1>Product List</h1>
                <p>Generated: ${new Date().toLocaleString()}</p>
                <p>Total Products: ${allProducts.length}</p>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${allProducts.map(product => `
                            <tr>
                                <td>${product.id}</td>
                                <td>${product.product_name}</td>
                                <td>${product.category}</td>
                                <td>₱${parseFloat(product.price).toFixed(2)}</td>
                                <td>${product.quantity}</td>
                                <td>${product.status}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                <div class="total">
                    Total Value: ₱${allProducts.reduce((sum, p) => sum + (p.price * p.quantity), 0).toFixed(2)}
                </div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 250);
}