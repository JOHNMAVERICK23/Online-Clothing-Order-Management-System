document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
});

function loadDashboardData() {
    fetch('../PROCESS/getDashboardData.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalUsers').textContent = data.totalUsers;
                document.getElementById('adminCount').textContent = data.adminCount;
                document.getElementById('staffCount').textContent = data.staffCount;
                document.getElementById('activeCount').textContent = data.activeCount;
                
                loadRecentAccounts();
            }
        })
        .catch(error => console.error('Error:', error));
}

function loadRecentAccounts() {
    fetch('../PROCESS/getRecentAccounts.php')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('recentAccounts');
            tbody.innerHTML = '';

            if (data.success && data.accounts.length > 0) {
                data.accounts.forEach(account => {
                    const row = createAccountRow(account);
                    tbody.appendChild(row);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 30px;">No accounts found</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('recentAccounts').innerHTML = '<tr><td colspan="5" style="text-align: center; color: red;">Error loading accounts</td></tr>';
        });
}

function createAccountRow(account) {
    const row = document.createElement('tr');
    const fullName = account.first_name + ' ' + account.last_name;
    const statusBadge = `<span class="badge ${account.status === 'active' ? 'badge-active' : 'badge-inactive'}">${account.status.toUpperCase()}</span>`;
    const roleBadge = `<span class="badge ${account.role === 'admin' ? 'badge-admin' : 'badge-staff'}">${account.role.toUpperCase()}</span>`;
    
    row.innerHTML = `
        <td>${fullName}</td>
        <td>${account.email}</td>
        <td>${roleBadge}</td>
        <td>${statusBadge}</td>
        <td>${new Date(account.created_at).toLocaleDateString()}</td>
    `;
    
    return row;
}