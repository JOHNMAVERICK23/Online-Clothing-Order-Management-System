// Common functions for the entire application

function logoutUser() {
    if (confirm('Are you sure you want to logout?')) {
        // Set a flag in localStorage that will be checked once on login page
        localStorage.setItem('show_logout_message', 'true');
        
        // Clear session storage
        sessionStorage.clear();
        
        // Redirect to logout.php
        window.location.href = 'PROCESS/logout.php';
    }
    return false;
}

// Check for logout message on login page
function checkLogoutMessage() {
    // Only run on login page
    if (window.location.pathname.includes('index.html') || 
        window.location.pathname.endsWith('/')) {
        
        const showLogout = localStorage.getItem('show_logout_message');
        
        if (showLogout === 'true') {
            // Show toast message
            if (typeof Toast !== 'undefined') {
                Toast.success('Logged out successfully');
            } else {
                // Fallback if Toast is not loaded yet
                setTimeout(() => {
                    if (typeof Toast !== 'undefined') {
                        Toast.success('Logged out successfully');
                    }
                }, 1000);
            }
            
            // Clear the flag so it doesn't show again
            localStorage.removeItem('show_logout_message');
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check for logout message
    checkLogoutMessage();
    
    // Check authentication for protected pages
    checkAuth();
    
    // Set current year in footer if exists
    const yearElement = document.getElementById('currentYear');
    if (yearElement) {
        yearElement.textContent = new Date().getFullYear();
    }
    
    // Set user info if available
    const userElement = document.getElementById('currentUser');
    if (userElement) {
        const userName = sessionStorage.getItem('user_name') || 'User';
        userElement.textContent = userName;
    }
});

// Check if user is logged in (simple check)
function checkAuth() {
    // Skip auth check for login page
    if (window.location.pathname.includes('index.html') || 
        window.location.pathname.endsWith('/')) {
        return;
    }
    
    // For demo purposes only - in real app, use proper session checking
    const protectedPages = ['dashboard.html', 'accounts.html', 'orders.html'];
    const currentPage = window.location.pathname.split('/').pop();
    
    if (protectedPages.includes(currentPage)) {
        // Check if we have a session indicator
        const hasSession = sessionStorage.getItem('user_email') || 
                          document.cookie.includes('PHPSESSID');
        
        if (!hasSession) {
            // Redirect to login
            window.location.href = '../login.html';
        }
    }
}