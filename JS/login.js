
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const submitBtn = loginForm ? loginForm.querySelector('button[type="submit"]') : null;

    if (!loginForm) return;

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;

        // Validation
        if (!email || !password) {
            showToast('Please fill in all fields', 'error');
            return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            showToast('Please enter a valid email address', 'error');
            return;
        }

        // Disable button
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Logging in...';
        submitBtn.style.opacity = '0.7';

        try {
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);

            const response = await fetch('PROCESS/login.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
                
                // Store in sessionStorage
                sessionStorage.setItem('user_email', email);
                sessionStorage.setItem('user_role', data.user_role);
                sessionStorage.setItem('user_name', data.user_name);
                sessionStorage.setItem('logged_in', 'true');
                
                // Redirect
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            } else {
                showToast(data.message || 'Login failed', 'error');
                resetLoginButton(submitBtn, originalText);
            }
        } catch (error) {
            console.error('Login error:', error);
            showToast('Network error. Please try again.', 'error');
            resetLoginButton(submitBtn, originalText);
        }
    });
});

function checkLogoutMessage() {
    const logoutMessage = sessionStorage.getItem('logout_message');
    if (logoutMessage) {
        showToast(logoutMessage, 'success');
        sessionStorage.removeItem('logout_message');
    }
}

function resetLoginButton(button, originalText) {
    if (button) {
        button.disabled = false;
        button.textContent = originalText;
        button.style.opacity = '1';
    }
}

function triggerShakeAnimation(form) {
    if (form) {
        form.style.animation = 'shake 0.5s';
        setTimeout(() => {
            form.style.animation = '';
        }, 500);
    }
}

function addShakeAnimationStyle() {
    if (!document.getElementById('shake-animation-style')) {
        const style = document.createElement('style');
        style.id = 'shake-animation-style';
        style.textContent = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                20%, 40%, 60%, 80% { transform: translateX(5px); }
            }
        `;
        document.head.appendChild(style);
    }
}

// Toast notification function
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        `;
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    toast.style.cssText = `
        background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
        color: ${type === 'success' ? '#155724' : '#721c24'};
        border: 1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'};
        border-radius: 4px;
        padding: 15px 20px;
        margin-bottom: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    `;
    toast.textContent = message;

    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

window.Toast = {
    success: function(msg) { showToast(msg, 'success'); },
    error: function(msg) { showToast(msg, 'error'); }
};