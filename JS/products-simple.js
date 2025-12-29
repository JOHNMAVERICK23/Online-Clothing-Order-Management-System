// Simple version - just use placeholders
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

// Then in displayProducts function, use:
const imageUrl = getProductImage(product);
