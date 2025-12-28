document.addEventListener('DOMContentLoaded', function() {
    // Set current user
    const loggedInUser = document.getElementById('loggedInUser');
    if (loggedInUser) {
        // Try to get from sessionStorage or use default
        const userName = sessionStorage.getItem('user_name') || 'Admin';
        loggedInUser.textContent = userName;
    }
    
    // Initialize date and time
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Set greeting based on time
    setGreeting();
    
    loadRecentAccounts();
    
    // Initialize system status
    updateSystemStatus();
});

function updateDateTime() {
    const now = new Date();
    
    // Update current time in sidebar
    const currentTime = document.getElementById('currentTime');
    if (currentTime) {
        currentTime.textContent = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }
    
    // Update date time in welcome card
    const currentDateTime = document.getElementById('currentDateTime');
    if (currentDateTime) {
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        currentDateTime.textContent = now.toLocaleDateString('en-US', options);
    }
}

function setGreeting() {
    const hour = new Date().getHours();
    const greetingText = document.getElementById('greetingText');
    const userName = sessionStorage.getItem('user_name') || 'Admin';
    
    let greeting;
    if (hour < 12) {
        greeting = `Good Morning, ${userName}!`;
    } else if (hour < 18) {
        greeting = `Good Afternoon, ${userName}!`;
    } else {
        greeting = `Good Evening, ${userName}!`;
    }
    
    if (greetingText) {
        greetingText.textContent = greeting;
    }
}


function loadRecentAccounts() {
    const tbody = document.getElementById('recentAccounts');
    if (!tbody) return;
    
    fetch('../PROCESS/getRecentAccounts.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.accounts.length > 0) {
                tbody.innerHTML = '';
                
                data.accounts.forEach(account => {
                    const row = createAccountRow(account);
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 30px;">
                            <i class="bi bi-person-x" style="font-size: 24px; color: #ccc; display: block; margin-bottom: 10px;"></i>
                            No accounts found
                        </td>
                    </tr>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; color: #e74c3c; padding: 30px;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 24px; display: block; margin-bottom: 10px;"></i>
                        Error loading accounts
                    </td>
                </tr>
            `;
        });
}

function createAccountRow(account) {
    const row = document.createElement('tr');
    const fullName = account.first_name + ' ' + account.last_name;
    const statusBadge = `<span class="badge ${account.status === 'active' ? 'badge-active' : 'badge-inactive'}">
        <i class="bi bi-${account.status === 'active' ? 'check-circle' : 'x-circle'}"></i>
        ${account.status.toUpperCase()}
    </span>`;
    const roleBadge = `<span class="badge ${account.role === 'admin' ? 'badge-admin' : 'badge-staff'}">
        <i class="bi bi-${account.role === 'admin' ? 'shield-check' : 'person-badge'}"></i>
        ${account.role.toUpperCase()}
    </span>`;
    const lastLogin = account.last_login_at ? 
        formatTimeAgo(account.last_login_at) : 
        '<span style="color: #999;">Never</span>';
    
    row.innerHTML = `
        <td>
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="
                    width: 36px;
                    height: 36px;
                    border-radius: 50%;
                    background: white;
                    color: black;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                ">${account.first_name[0]}${account.last_name[0]}</div>
                <div>
                    <div style="font-weight: 600;">${fullName}</div>
                    <div style="font-size: 12px; color: #666;">${account.email}</div>
                </div>
            </div>
        </td>
        <td>${account.email}</td>
        <td>${roleBadge}</td>
        <td>${statusBadge}</td>
        <td>${lastLogin}</td>
    `;
    
    return row;
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    if (diffMins < 1) {
        return 'Just now';
    } else if (diffMins < 60) {
        return `${diffMins}m ago`;
    } else if (diffHours < 24) {
        return `${diffHours}h ago`;
    } else if (diffDays < 7) {
        return `${diffDays}d ago`;
    } else {
        return date.toLocaleDateString();
    }
}

function updateSystemStatus() {
    // Simulate system status check
    const dbStatus = document.getElementById('dbStatus');
    if (dbStatus) {
        setTimeout(() => {
            dbStatus.className = 'badge badge-active';
            dbStatus.innerHTML = '<i class="bi bi-check-circle"></i> Connected';
        }, 1000);
    }
    
    // Update uptime
    let startTime = Date.now();
    const uptimeElement = document.getElementById('uptime');
    
    function updateUptime() {
        const elapsed = Date.now() - startTime;
        const hours = Math.floor(elapsed / 3600000);
        const minutes = Math.floor((elapsed % 3600000) / 60000);
        const seconds = Math.floor((elapsed % 60000) / 1000);
        
        if (uptimeElement) {
            uptimeElement.textContent = 
                `${hours.toString().padStart(2, '0')}:` +
                `${minutes.toString().padStart(2, '0')}:` +
                `${seconds.toString().padStart(2, '0')}`;
        }
    }
    
    setInterval(updateUptime, 1000);
    
    // Check last backup (simulated)
    const lastBackup = document.getElementById('lastBackup');
    if (lastBackup) {
        const lastBackupTime = localStorage.getItem('lastBackup');
        if (lastBackupTime) {
            lastBackup.textContent = formatTimeAgo(lastBackupTime);
        }
    }
}

function refreshDashboard() {
    Toast.info('Refreshing...', 800);

    setTimeout(() => {
        loadRecentAccounts();
        Toast.success('Updated successfully!', 1000);
    }, 400);
}


// Make functions available globally
window.refreshDashboard = refreshDashboard;