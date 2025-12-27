class Toast {
    static show(message, type = 'info', duration = 5000) {
        // Create toast container if it doesn't exist
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

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            background: ${type === 'success' ? '#d4edda' : 
                        type === 'error' ? '#f8d7da' : 
                        type === 'warning' ? '#fff3cd' : '#d1ecf1'};
            color: ${type === 'success' ? '#155724' : 
                    type === 'error' ? '#721c24' : 
                    type === 'warning' ? '#856404' : '#0c5460'};
            border: 1px solid ${type === 'success' ? '#c3e6cb' : 
                            type === 'error' ? '#f5c6cb' : 
                            type === 'warning' ? '#ffeaa7' : '#bee5eb'};
            border-radius: 4px;
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            animation: slideIn 0.3s ease-out;
        `;

        // Add icon based on type
        const icon = type === 'success' ? '✓' : 
                    type === 'error' ? '✗' : 
                    type === 'warning' ? '⚠' : 'ℹ';
        
        toast.innerHTML = `
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 18px;">${icon}</span>
                <span style="flex-grow: 1;">${message}</span>
            </div>
            <button class="toast-close" style="
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: inherit;
                margin-left: 15px;
            ">&times;</button>
        `;

        toastContainer.appendChild(toast);

        // Add close functionality
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        });

        // Auto remove after duration
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'slideOut 0.3s ease-out forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }, duration);

        // Add CSS animations if not already added
        if (!document.getElementById('toast-animations')) {
            const style = document.createElement('style');
            style.id = 'toast-animations';
            style.textContent = `
                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }

    static success(message, duration = 5000) {
        this.show(message, 'success', duration);
    }

    static error(message, duration = 5000) {
        this.show(message, 'error', duration);
    }

    static warning(message, duration = 5000) {
        this.show(message, 'warning', duration);
    }

    static info(message, duration = 5000) {
        this.show(message, 'info', duration);
    }
}