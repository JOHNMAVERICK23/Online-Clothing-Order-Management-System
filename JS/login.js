document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const submitBtn = loginForm ? loginForm.querySelector('button[type="submit"]') : null;

    // Check if we're on login page
    if (!loginForm) return;

    // Check for session logout message
    checkLogoutMessage();

    // Load toast.js if not already loaded
    if (typeof Toast === 'undefined') {
        loadToastJS();
    }

    if (loginForm && submitBtn) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            // Basic validation
            if (!email || !password) {
                Toast.error('Please fill in all fields');
                return;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                Toast.error('Please enter a valid email address');
                return;
            }

            // Disable submit button and show loading
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

                const data = await response.json();

                if (data.success) {
                    Toast.success(data.message);

                    // Store user info in sessionStorage for dashboard
                    sessionStorage.setItem('user_name', email.split('@')[0]);
                    sessionStorage.setItem('user_email', email);
                    sessionStorage.setItem('last_login', new Date().toISOString());

                    // Show success message for 1.5 seconds before redirecting
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);

                } else {
                    Toast.error(data.message);
                    triggerShakeAnimation(loginForm);

                    // Kung naka-lockout, i-disable ang button at mag-countdown
                    if (data.locked && data.remaining > 0) {
                        startLockoutCountdown(submitBtn, originalText, data.remaining);
                    } else {
                        // Hindi pa naka-lock, i-enable lang ulit ang button
                        resetLoginButton(submitBtn, originalText);
                    }
                }

            } catch (error) {
                Toast.error('Network error. Please try again.');
                console.error('Login error:', error);
                resetLoginButton(submitBtn, originalText);
            }
        });
    }

    // Add shake animation style
    addShakeAnimationStyle();
});

// =============================================
// LOCKOUT COUNTDOWN
// =============================================
function startLockoutCountdown(button, originalText, seconds) {
    if (!button) return;

    button.disabled = true;
    button.style.opacity = '0.6';
    button.style.cursor = 'not-allowed';

    // Ipakita agad ang unang countdown
    button.textContent = `Wait ${seconds}s...`;

    const countdown = setInterval(() => {
        seconds--;

        if (seconds <= 0) {
            clearInterval(countdown);
            button.disabled = false;
            button.style.opacity = '1';
            button.style.cursor = 'pointer';
            button.textContent = originalText;
            Toast.info('You can try logging in again.');
        } else {
            button.textContent = `Wait ${seconds}s...`;
        }
    }, 1000);
}

// =============================================
// HELPER FUNCTIONS
// =============================================
function checkLogoutMessage() {
    const logoutMessage = localStorage.getItem('logout_message');
    if (logoutMessage) {
        Toast.success(logoutMessage);
        localStorage.removeItem('logout_message');
    }
}

function loadToastJS() {
    const paths = [
        'JS/toast.js',
        '../JS/toast.js',
        '/JS/toast.js'
    ];

    let loaded = false;
    paths.forEach(path => {
        if (!loaded) {
            const script = document.createElement('script');
            script.src = path;
            script.onload = () => {
                loaded = true;
                console.log('Toast.js loaded from:', path);
            };
            script.onerror = () => {
                console.log('Failed to load toast.js from:', path);
            };
            document.head.appendChild(script);
        }
    });
}

function resetLoginButton(button, originalText) {
    if (button) {
        button.disabled = false;
        button.textContent = originalText;
        button.style.opacity = '1';
        button.style.cursor = 'pointer';
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

// Simple Toast fallback if toast.js fails to load
if (typeof Toast === 'undefined') {
    window.Toast = {
        success: function(msg) { alert('Success: ' + msg); },
        error: function(msg) { alert('Error: ' + msg); },
        info: function(msg) { alert('Info: ' + msg); },
        warning: function(msg) { alert('Warning: ' + msg); }
    };
}