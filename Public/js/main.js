// main.js - Global JavaScript for all pages

// ========== NAVBAR FUNCTIONALITY ==========
document.addEventListener('DOMContentLoaded', function() {
    // Initialize navbar functionality
    initNavbar();
    
    // Highlight active page in navbar
    highlightActivePage();
});

function initNavbar() {
    const nav = document.getElementById('customerNav');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    
    if (!nav || !mobileMenuBtn || !navMenu) return;
    
    // Navbar scroll behavior
    let lastScroll = 0;
    
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > lastScroll && currentScroll > 100) {
            nav.classList.add('nav-hidden');
        } else if (currentScroll < lastScroll) {
            nav.classList.remove('nav-hidden');
        }
        
        lastScroll = currentScroll;
    });

    // Mobile menu toggle
    mobileMenuBtn.addEventListener('click', () => {
        navMenu.classList.toggle('active');
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!navMenu.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
            navMenu.classList.remove('active');
        }
    });
}

function highlightActivePage() {
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-menu a');
    
    navLinks.forEach(link => {
        const linkHref = link.getAttribute('href');
        
        // Handle index.php and home page
        if ((currentPage === '' || currentPage === 'index.php') && 
            (linkHref === 'index.php' || linkHref === '')) {
            link.classList.add('active');
        } 
        // Handle other pages
        else if (currentPage === linkHref) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

// ========== CART & WISHLIST FUNCTIONS ==========
// These functions should be called by individual pages that need them
function updateCartCount(count) {
    const cartCountEl = document.getElementById('cartCount');
    if (cartCountEl) {
        cartCountEl.textContent = count;
    }
}

function updateWishlistCount(count) {
    const wishlistCountEl = document.getElementById('wishlistCount');
    
    if (count > 0) {
        if (wishlistCountEl) {
            wishlistCountEl.textContent = count;
        } else {
            // Create wishlist count badge if it doesn't exist
            const wishlistLink = document.querySelector('.nav-icon[href*="wishlist"]');
            if (wishlistLink) {
                const badge = document.createElement('span');
                badge.className = 'wishlist-count-badge';
                badge.id = 'wishlistCount';
                badge.textContent = count;
                wishlistLink.appendChild(badge);
            }
        }
    } else {
        // Remove badge if count is 0
        if (wishlistCountEl) {
            wishlistCountEl.remove();
        }
    }
}

// ========== UTILITY FUNCTIONS ==========
function showNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${type === 'success' ? '#4CAF50' : '#f44336'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 4px;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add notification CSS to head
(function() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
})();

// ========== AJAX HELPER ==========
async function ajaxRequest(url, data = null, method = 'POST') {
    try {
        const options = {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        if (data) {
            const formData = new FormData();
            for (const key in data) {
                formData.append(key, data[key]);
            }
            options.body = formData;
        }
        
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('AJAX request failed:', error);
        return { success: false, message: 'Network error occurred' };
    }
}

// ========== EXPORT FUNCTIONS FOR GLOBAL USE ==========
// Make functions available globally
window.navbar = {
    init: initNavbar,
    highlightActivePage: highlightActivePage
};

window.cart = {
    updateCount: updateCartCount,
    updateWishlistCount: updateWishlistCount
};

window.utils = {
    showNotification: showNotification,
    ajaxRequest: ajaxRequest
};